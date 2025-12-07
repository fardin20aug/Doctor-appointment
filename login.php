<?php
session_start();
require_once 'db.php';

$login_error   = '';
$login_success = '';

// If user already logged in, send them to their dashboard
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if ($_SESSION['role'] === 'reception') {
        header('Location: jo.php');
    } else { // doctor or patient
        header('Location: hall.php');
    }
    exit;
}

// Show message if redirected after signup
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $login_success = 'Registration successful. Please log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    $validRoles = ['doctor', 'patient', 'reception'];

    if ($username === '' || $password === '' || $role === '') {
        $login_error = 'Please fill in all fields.';
    } elseif (!in_array($role, $validRoles, true)) {
        $login_error = 'Invalid role selected.';
    } else {
        $stmt = $conn->prepare(
            'SELECT id, fullname, email, username, password_hash, role, status 
             FROM users 
             WHERE username = ? AND role = ? 
             LIMIT 1'
        );

        if (!$stmt) {
            $login_error = 'Internal error (could not prepare query).';
        } else {
            $stmt->bind_param('ss', $username, $role);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user   = $result->fetch_assoc();
                $stored = trim($user['password_hash']);

                if ($user['status'] !== 'active') {
                    $login_error = 'Your account is inactive. Please contact admin.';
                } elseif (!password_verify($password, $stored)) {
                    $login_error = 'Incorrect password.';
                } else {
                    // ✅ Success
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id']  = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['fullname'] = $user['fullname'];
                    $_SESSION['role']     = $user['role'];

                    if ($user['role'] === 'reception') {
                        header('Location: jo.php');
                    } else { // doctor or patient
                        header('Location: hall.php');
                    }
                    exit;
                }
            } else {
                $login_error = 'No user found with that username and role.';
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Appointment System - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            scroll-behavior: smooth;
        }
        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
           	align-items: center;
            padding: 0;
            color: #fff;
            margin: 0;
            overflow-x: hidden;
            background-color: #000;
            font-size: 1.125rem;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        }
        @keyframes drift-zoom {
            0% { background-size: 100% 100%; background-position: 0% 50%; }
            100% { background-size: 135% 135%; background-position: 100% 50%; }
        }
        .fixed-background {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            z-index: -1;
            animation: drift-zoom 8s linear infinite alternate;
            background-image:
                linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)),
                url('images/pp.jpg');
            background-size: 110% 110%;
            background-position: 0% 50%;
            background-repeat: no-repeat;
            will-change: background-size, background-position;
        }
        .glass-card {
            background: rgba(84, 84, 146, 0.8);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.3);
            box-shadow: 0 8px 32px rgba(0,0,0,0.37);
            padding: 40px 60px;
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        .glass-card h1 { font-size: 1.8rem; margin-bottom: 5px; }
        .glass-card p { margin-bottom: 30px; color: rgba(255,255,255,0.8); }
        .form-group { margin-bottom: 25px; text-align: left; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: rgba(255,255,255,0.15);
            color: #111827;
            box-sizing: border-box;
            transition: background 0.3s ease;
        }
        .form-group input:focus,
        .form-group select:focus {
            background: rgba(255,255,255,0.25);
            outline: none;
            box-shadow: 0 0 0 2px rgba(255,255,255,0.5);
        }
        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="white" d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 40px;
        }
        .login-button {
            width: 100%;
            border: none;
            padding: 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1.1rem;
            background: #00bcd4;
            color: #1a1a1a;
            margin-top: 10px;
            margin-bottom: 10px;
            transition: background 0.3s, transform 0.2s;
        }
        .login-button:hover { background: #00e5ff; transform: translateY(-2px); }
        .signup-button {
            width: 100%;
            border: 1px solid rgba(255,255,255,0.5);
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            background: rgba(255,255,255,0.2);
            color: #fff;
            text-decoration: none;
            display: block;
            margin-top: 5px;
        }
        .signup-button:hover {
            background: rgba(255,255,255,0.35);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
<div class="fixed-background"></div>
<main>
    <div class="glass-card">
        <h1>Doctor Appointment System Login</h1>
        <p>Sign in with your credentials to access the dashboard.</p>

        <?php if (!empty($login_success)): ?>
            <p style="color:#b3ffb3;background:rgba(0,0,0,0.4);padding:10px 14px;border-radius:8px;margin-bottom:15px;">
                <?php echo htmlspecialchars($login_success); ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($login_error)): ?>
            <p style="color:#ffb3b3;background:rgba(0,0,0,0.4);padding:10px 14px;border-radius:8px;margin-bottom:15px;">
                <?php echo htmlspecialchars($login_error); ?>
            </p>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="username">Username / ID</label>
                <input type="text" id="username" name="username"
                       placeholder="Enter ID or Username" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="Enter Password" required>
            </div>

            <div class="form-group">
                <label for="role">Select Role</label>
                <select id="role" name="role" required>
                    <option value="" disabled selected>Choose your role</option>
                    <option value="doctor">Doctor</option>
                    <option value="patient">Patient</option>
                    <option value="reception">Reception</option>
                </select>
            </div>

            <button type="submit" class="login-button">Login</button>

            <a href="signup.php" class="signup-button">Register / Sign Up</a>
        </form>
    </div>
</main>
</body>
</html>
