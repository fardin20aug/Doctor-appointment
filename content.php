<?php
session_start();
require_once 'db.php';

if (
    !isset($_SESSION['loggedin']) ||
    $_SESSION['loggedin'] !== true
) {
    header('Location: login.php');
    exit;
}

$role     = $_SESSION['role'] ?? '';
$fullname = $_SESSION['fullname'] ?? 'User';

if ($role === 'doctor' || $role === 'patient') {
    header('Location: hall.php');
    exit;
}
if ($role === 'reception') {
    header('Location: jo.php');
    exit;
}

// Simple counts
$totalUsers = 0;
$totalAppointments = 0;
$roleCounts = [
    'doctor'    => 0,
    'patient'   => 0,
    'reception' => 0,
    'admin'     => 0,
];

$res = $conn->query("SELECT role, COUNT(*) as c FROM users GROUP BY role");
if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        $totalUsers += (int)$r['c'];
        $roleKey = $r['role'];
        if (isset($roleCounts[$roleKey])) {
            $roleCounts[$roleKey] = (int)$r['c'];
        }
    }
}

$res2 = $conn->query("SELECT COUNT(*) AS c FROM appointments");
if ($res2 && $res2->num_rows > 0) {
    $row2 = $res2->fetch_assoc();
    $totalAppointments = (int)$row2['c'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Doctor Appointment System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #020617;
            color: #e5e7eb;
        }
        .card {
            background: rgba(15,23,42,0.95);
            border-radius: 16px;
            border: 1px solid rgba(148,163,184,0.35);
            box-shadow: 0 20px 45px rgba(0,0,0,0.6);
        }
    </style>
</head>
<body class="min-h-screen">
    <nav class="w-full border-b border-slate-800 bg-slate-950/80 backdrop-blur sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center text-white font-bold">
                    DA
                </div>
                <div>
                    <p class="text-sm font-semibold">
                        Doctor Appointment System <span class="text-xs text-emerald-400">| Admin</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <p class="text-xs text-slate-400 hidden sm:block">
                    <?php echo htmlspecialchars($fullname); ?> (<?php echo htmlspecialchars($role); ?>)
                </p>
                <a href="logout.php" class="text-xs text-red-400 hover:text-red-300 underline">Logout</a>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-8 space-y-6">
        <header>
            <p class="text-xs uppercase tracking-widest text-slate-400 font-semibold mb-1">Overview</p>
            <h1 class="text-3xl font-bold text-slate-50">Admin Dashboard</h1>
            <p class="text-sm text-slate-400 mt-1">
                High-level summary of users and appointments in the system.
            </p>
        </header>

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="card p-4">
                <p class="text-xs text-slate-400 uppercase font-semibold">Total Users</p>
                <p class="text-2xl font-bold mt-2 text-slate-50"><?php echo $totalUsers; ?></p>
            </div>
            <div class="card p-4">
                <p class="text-xs text-slate-400 uppercase font-semibold">Doctors</p>
                <p class="text-2xl font-bold mt-2 text-slate-50"><?php echo $roleCounts['doctor']; ?></p>
            </div>
            <div class="card p-4">
                <p class="text-xs text-slate-400 uppercase font-semibold">Patients</p>
                <p class="text-2xl font-bold mt-2 text-slate-50"><?php echo $roleCounts['patient']; ?></p>
            </div>
            <div class="card p-4">
                <p class="text-xs text-slate-400 uppercase font-semibold">Receptionists</p>
                <p class="text-2xl font-bold mt-2 text-slate-50"><?php echo $roleCounts['reception']; ?></p>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="card p-5">
                <h2 class="text-lg font-semibold text-slate-50 mb-3">Appointments Summary</h2>
                <p class="text-3xl font-bold text-slate-50 mb-3"><?php echo $totalAppointments; ?></p>
                <p class="text-sm text-slate-400">
                    Total appointments currently stored in the system.
                </p>
            </div>
            <div class="card p-5">
                <h2 class="text-lg font-semibold text-slate-50 mb-3">Quick Links</h2>
                <ul class="text-sm text-slate-300 space-y-2">
                    <li><a href="jo.php" class="text-sky-400 hover:text-sky-300 underline">Reception Dashboard</a></li>
                    <li><a href="hall.php" class="text-sky-400 hover:text-sky-300 underline">Doctor/Patient Dashboard</a></li>
                    <li><a href="pay.php" class="text-sky-400 hover:text-sky-300 underline">Payment Page</a></li>
                </ul>
            </div>
        </section>
    </main>
</body>
</html>
