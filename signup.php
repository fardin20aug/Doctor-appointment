<?php
session_start();
require_once 'db.php';

$signup_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname         = trim($_POST['fullname'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $new_username     = trim($_POST['new_username'] ?? '');
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $new_role         = $_POST['new_role'] ?? '';

    // Doctor extra fields
    $doc_specialization = trim($_POST['doc_specialization'] ?? '');
    $doc_chamber        = trim($_POST['doc_chamber'] ?? '');
    $doc_fee            = $_POST['doc_fee'] ?? '';
    $doc_experience     = $_POST['doc_experience'] ?? '';
    $doc_from_time      = $_POST['doc_from_time'] ?? '';
    $doc_to_time        = $_POST['doc_to_time'] ?? '';

    // Patient extra fields
    $pat_dob        = $_POST['pat_dob'] ?? '';
    $pat_gender     = $_POST['pat_gender'] ?? '';
    $pat_phone      = trim($_POST['pat_phone'] ?? '');
    $pat_address    = trim($_POST['pat_address'] ?? '');
    $pat_blood      = trim($_POST['pat_blood'] ?? '');

    $validRoles = ['doctor', 'patient', 'reception'];

    // 1) Basic validation
    if ($fullname === '' || $email === '' || $new_username === '' ||
        $new_password === '' || $confirm_password === '' || $new_role === '') {
        $signup_error = 'Please fill in all required fields.';
    } elseif ($new_password !== $confirm_password) {
        $signup_error = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $signup_error = 'Invalid email address.';
    } elseif (!in_array($new_role, $validRoles, true)) {
        $signup_error = 'Invalid role selected.';
    } else {
        // 2) Check for existing username/email
        $check = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        if (!$check) {
            $signup_error = 'Internal error. Please try again later.';
        } else {
            $check->bind_param('ss', $new_username, $email);
            $check->execute();
            $checkRes = $check->get_result();

            if ($checkRes && $checkRes->num_rows > 0) {
                $signup_error = 'Username or email already exists.';
            } else {
                // 3) Insert into users
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare(
                    'INSERT INTO users (fullname, email, username, password_hash, role, status)
                     VALUES (?, ?, ?, ?, ?, "active")'
                );

                if (!$stmt) {
                    $signup_error = 'Failed to prepare user insert.';
                } else {
                    $stmt->bind_param('sssss', $fullname, $email, $new_username, $password_hash, $new_role);

                    if ($stmt->execute()) {
                        $user_id = $conn->insert_id;
                        $stmt->close();

                        /* ---------- CREATE PROFILE BASED ON ROLE ---------- */

                        if ($new_role === 'doctor') {
                            // reasonable defaults if empty
                            if ($doc_specialization === '') $doc_specialization = 'General Physician';
                            if ($doc_chamber === '')        $doc_chamber = 'Main Chamber';
                            if ($doc_fee === '')            $doc_fee = 0;
                            if ($doc_experience === '')     $doc_experience = 0;
                            if ($doc_from_time === '')      $doc_from_time = '09:00:00';
                            if ($doc_to_time === '')        $doc_to_time = '17:00:00';

                            $doc_fee        = (float)$doc_fee;
                            $doc_experience = (int)$doc_experience;

                            $dStmt = $conn->prepare("
                                INSERT INTO doctors
                                    (user_id, specialization, chamber, experience_years, consultation_fee, available_from, available_to)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            if ($dStmt) {
                                $dStmt->bind_param(
                                    'issidss',
                                    $user_id,
                                    $doc_specialization,
                                    $doc_chamber,
                                    $doc_experience,
                                    $doc_fee,
                                    $doc_from_time,
                                    $doc_to_time
                                );
                                $dStmt->execute();
                                $dStmt->close();
                            }

                        } elseif ($new_role === 'patient') {

                            if ($pat_dob === '')   $pat_dob = null;
                            if ($pat_gender === '') $pat_gender = null;
                            if ($pat_phone === '')  $pat_phone = null;
                            if ($pat_address === '') $pat_address = null;
                            if ($pat_blood === '')   $pat_blood = null;

                            $pStmt = $conn->prepare("
                                INSERT INTO patients
                                    (user_id, date_of_birth, gender, phone, address, blood_group)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            if ($pStmt) {
                                $pStmt->bind_param(
                                    'isssss',
                                    $user_id,
                                    $pat_dob,
                                    $pat_gender,
                                    $pat_phone,
                                    $pat_address,
                                    $pat_blood
                                );
                                $pStmt->execute();
                                $pStmt->close();
                            }

                        }
                        // reception role doesn't need extra table

                        $check->close();
                        header('Location: login.php?registered=1');
                        exit;
                    } else {
                        $signup_error = 'Failed to create account. Please try again.';
                        $stmt->close();
                    }
                }
            }
            $check->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Appointment System - Sign Up</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
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
            font-size: 1.05rem;
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
            animation: drift-zoom 10s linear infinite alternate;
            background-image:
                linear-gradient(rgba(15, 23, 42, 0.7), rgba(15, 23, 42, 0.85)),
                url('images/pp.jpg');
            background-size: 115% 115%;
            background-position: 0% 50%;
            background-repeat: no-repeat;
        }
        main {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            box-sizing: border-box;
        }
        .signup-container {
            width: 100%;
            max-width: 520px;
            background: rgba(15, 23, 42, 0.92);
            border-radius: 20px;
            padding: 2.3rem 2rem 2.1rem;
            border: 1px solid rgba(148, 163, 184, 0.45);
            box-shadow:
                0 25px 60px rgba(0, 0, 0, 0.9),
                0 0 0 1px rgba(148, 163, 184, 0.25);
            backdrop-filter: blur(22px);
        }
        .signup-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .signup-title {
            font-size: 1.6rem;
            letter-spacing: 0.03em;
            font-weight: 700;
        }
        .signup-subtitle {
            font-size: 0.9rem;
            color: #9ca3af;
            margin-top: 0.35rem;
        }
        .section-label {
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9ca3af;
            margin-top: 1rem;
            margin-bottom: 0.4rem;
        }
        .form-row {
            display: flex;
            gap: 0.7rem;
        }
        .form-row > div {
            flex: 1;
        }
        .form-group {
            margin-bottom: 0.85rem;
        }
        label {
            display: block;
            margin-bottom: 0.3rem;
            font-size: 0.8rem;
            color: #e5e7eb;
            letter-spacing: 0.02em;
        }
        input, select, textarea {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border-radius: 0.9rem;
            border: 1px solid rgba(148, 163, 184, 0.6);
            background: rgba(15, 23, 42, 0.85);
            color: #e5e7eb;
            font-size: 0.9rem;
            outline: none;
            box-sizing: border-box;
            transition: all 0.18s ease;
        }
        input:focus, select:focus, textarea:focus {
            border-color: rgba(59, 130, 246, 0.9);
            box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.2);
            background: rgba(15, 23, 42, 0.98);
        }
        textarea {
            resize: vertical;
            min-height: 60px;
        }
        .signup-button {
            width: 100%;
            padding: 0.75rem;
            border: none;
            border-radius: 999px;
            background: linear-gradient(to right, #10b981, #22c55e);
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            margin-top: 0.3rem;
            margin-bottom: 0.6rem;
            letter-spacing: 0.03em;
            transition: all 0.15s ease-out;
        }
        .signup-button:hover {
            filter: brightness(1.08);
            transform: translateY(-1px);
            box-shadow: 0 18px 40px rgba(16, 185, 129, 0.6);
        }
        .login-link {
            width: 100%;
            padding: 0.65rem;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.75);
            background: transparent;
            color: #e5e7eb;
            font-size: 0.9rem;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            margin-top: 0.1rem;
            letter-spacing: 0.02em;
            transition: all 0.15s ease-out;
        }
        .login-link:hover {
            background: rgba(15, 23, 42, 0.85);
            border-color: rgba(209, 213, 219, 0.9);
        }
        .error-message {
            font-size: 0.8rem;
            margin-bottom: 0.75rem;
            padding: 0.45rem 0.6rem;
            border-radius: 0.75rem;
            background: rgba(248, 113, 113, 0.15);
            border: 1px solid rgba(248, 113, 113, 0.5);
            color: #fecaca;
        }
        .hint {
            font-size: 0.7rem;
            color: #9ca3af;
            margin-top: 0.1rem;
        }
    </style>
</head>
<body>
<div class="fixed-background"></div>
<main>
    <div class="signup-container">
        <div class="signup-header">
            <h1 class="signup-title">Create your account</h1>
            <p class="signup-subtitle">Register as doctor, patient, or reception staff.</p>
        </div>

        <?php if (!empty($signup_error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($signup_error); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <!-- BASIC USER INFO -->
            <div class="section-label">Basic Info</div>

            <div class="form-group">
                <label for="fullname">Full Name</label>
                <input type="text" id="fullname" name="fullname"
                       placeholder="Enter full name" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       placeholder="Enter email address" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="new_username">Username</label>
                    <input type="text" id="new_username" name="new_username"
                           placeholder="Choose a username" required>
                </div>
                <div class="form-group">
                    <label for="new_role">Role</label>
                    <select id="new_role" name="new_role" required>
                        <option value="" disabled selected>Choose role</option>
                        <option value="doctor">Doctor</option>
                        <option value="patient">Patient</option>
                        <option value="reception">Reception</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="new_password">Password</label>
                    <input type="password" id="new_password" name="new_password"
                           placeholder="Create password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Confirm password" required>
                </div>
            </div>

            <!-- DOCTOR DETAILS -->
            <div class="section-label">Doctor Details (if you select Doctor)</div>

            <div class="form-row">
                <div class="form-group">
                    <label for="doc_specialization">Specialization</label>
                    <input type="text" id="doc_specialization" name="doc_specialization"
                           placeholder="e.g. Cardiology, General Physician">
                </div>
                <div class="form-group">
                    <label for="doc_chamber">Chamber / Room</label>
                    <input type="text" id="doc_chamber" name="doc_chamber"
                           placeholder="e.g. Room 302, City Clinic">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="doc_fee">Consultation Fee</label>
                    <input type="number" step="0.01" id="doc_fee" name="doc_fee"
                           placeholder="e.g. 500">
                </div>
                <div class="form-group">
                    <label for="doc_experience">Experience (years)</label>
                    <input type="number" id="doc_experience" name="doc_experience"
                           placeholder="e.g. 3">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="doc_from_time">Available From</label>
                    <input type="time" id="doc_from_time" name="doc_from_time">
                </div>
                <div class="form-group">
                    <label for="doc_to_time">Available To</label>
                    <input type="time" id="doc_to_time" name="doc_to_time">
                </div>
            </div>
            <p class="hint">These doctor fields are used only if the role is Doctor.</p>

            <!-- PATIENT DETAILS -->
            <div class="section-label">Patient Details (if you select Patient)</div>

            <div class="form-row">
                <div class="form-group">
                    <label for="pat_dob">Date of Birth</label>
                    <input type="date" id="pat_dob" name="pat_dob">
                </div>
                <div class="form-group">
                    <label for="pat_gender">Gender</label>
                    <select id="pat_gender" name="pat_gender">
                        <option value="">Select</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="pat_phone">Phone</label>
                    <input type="text" id="pat_phone" name="pat_phone"
                           placeholder="Patient phone number">
                </div>
                <div class="form-group">
                    <label for="pat_blood">Blood Group</label>
                    <input type="text" id="pat_blood" name="pat_blood"
                           placeholder="e.g. A+, B-, O+">
                </div>
            </div>

            <div class="form-group">
                <label for="pat_address">Address</label>
                <textarea id="pat_address" name="pat_address"
                          placeholder="House, road, city..."></textarea>
            </div>
            <p class="hint">These patient fields are used only if the role is Patient.</p>

            <button type="submit" class="signup-button">Create Account</button>

            <a href="login.php" class="login-link">Already have an account? Sign in</a>
        </form>
    </div>
</main>
</body>
</html>
