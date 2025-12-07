<?php
// jo.php – Reception Dashboard (LIVE DATA)
session_start();
require_once 'db.php';

// Only reception can access this page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'reception') {
    header('Location: login.php');
    exit;
}

$receptionName   = $_SESSION['fullname'] ?? 'Receptionist';
$today           = date('Y-m-d');
$action_message  = '';

// ================== UPDATE APPOINTMENT STATUS (NEW) ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appointmentId = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
    $newStatus     = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';

    $allowedStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];

    if ($appointmentId > 0 && in_array($newStatus, $allowedStatuses, true)) {
        if ($stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?")) {
            $stmt->bind_param('si', $newStatus, $appointmentId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $action_message = 'Appointment status updated successfully.';
            } else {
                $action_message = 'Failed to update appointment status.';
            }
            $stmt->close();
        } else {
            $action_message = 'Could not prepare status update statement.';
        }
    } else {
        $action_message = 'Invalid appointment or status.';
    }
}

// ============= CREATE APPOINTMENT FROM MODAL (ALREADY EXISTING) =============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_appointment'])) {
    $doctorUserId  = isset($_POST['doctor_user_id'])  ? (int)$_POST['doctor_user_id']  : 0;
    $patientUserId = isset($_POST['patient_user_id']) ? (int)$_POST['patient_user_id'] : 0;
    $date          = trim($_POST['appointment_date'] ?? '');
    $time          = trim($_POST['appointment_time'] ?? '');
    $notes         = trim($_POST['notes'] ?? '');

    if ($doctorUserId && $patientUserId && $date !== '' && $time !== '') {
        // Get doctor_id from doctors table
        $doctorId = null;
        if ($stmt = $conn->prepare('SELECT id FROM doctors WHERE user_id = ? LIMIT 1')) {
            $stmt->bind_param('i', $doctorUserId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $doctorId = (int)$row['id'];
            }
            $stmt->close();
        }

        // Get patient_id from patients table
        $patientId = null;
        if ($stmt = $conn->prepare('SELECT id FROM patients WHERE user_id = ? LIMIT 1')) {
            $stmt->bind_param('i', $patientUserId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $patientId = (int)$row['id'];
            }
            $stmt->close();
        }

        if ($doctorId && $patientId) {
            $dateTime = $date . ' ' . $time . ':00';

            if ($stmt = $conn->prepare('
                INSERT INTO appointments (doctor_id, patient_id, appointment_date, status, notes)
                VALUES (?, ?, ?, "pending", ?)
            ')) {
                $stmt->bind_param('iiss', $doctorId, $patientId, $dateTime, $notes);
                if ($stmt->execute()) {
                    $action_message = 'Appointment created successfully.';
                } else {
                    $action_message = 'Failed to save appointment.';
                }
                $stmt->close();
            } else {
                $action_message = 'Could not prepare appointment insert.';
            }
        } else {
            $action_message = 'Doctor or patient profile not found. Make sure they signed up correctly.';
        }
    } else {
        $action_message = 'Please fill all required appointment fields.';
    }
}

/**
 * Small helper to safely get COUNT(*)
 */
function getCount(mysqli $conn, string $sql): int
{
    $res = $conn->query($sql);
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['c'];
    }
    return 0;
}

/* ---------- DASHBOARD COUNTS (LIVE) ---------- */
$totalPatients = getCount($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'patient'");
$totalDoctors  = getCount($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'doctor'");
$todayAppointmentsCount = getCount(
    $conn,
    "SELECT COUNT(*) AS c FROM appointments WHERE DATE(appointment_date) = '" . $conn->real_escape_string($today) . "'"
);
$pendingPaymentsCount = getCount(
    $conn,
    "SELECT COUNT(*) AS c FROM payments WHERE status = 'pending'"
);

/* ---------- TODAY'S APPOINTMENTS (for “Today’s Schedule”) ---------- */
$appointmentsToday = [];

$stmt = $conn->prepare("
    SELECT 
        a.id AS id,
        DATE_FORMAT(a.appointment_date, '%h:%i %p') AS time_label,
        pu.fullname AS patient_name,
        du.fullname AS doctor_name,
        a.status,
        COALESCE(a.notes, '') AS notes
    FROM appointments a
    JOIN patients p      ON a.patient_id = p.id
    JOIN users   pu      ON p.user_id = pu.id
    JOIN doctors d       ON a.doctor_id = d.id
    JOIN users   du      ON d.user_id = du.id
    WHERE DATE(a.appointment_date) = ?
    ORDER BY a.appointment_date ASC
");
$stmt->bind_param('s', $today);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $labelStatus = $row['status'];
    switch ($row['status']) {
        case 'pending':   $labelStatus = 'Waiting';    break;
        case 'confirmed': $labelStatus = 'Checked In'; break;
        case 'completed': $labelStatus = 'Completed';  break;
        case 'cancelled': $labelStatus = 'Cancelled';  break;
    }

    $appointmentsToday[] = [
        'id'         => (int)$row['id'],
        'time'       => $row['time_label'],
        'patient'    => $row['patient_name'],
        'doctor'     => $row['doctor_name'],
        'type'       => $row['notes'] !== '' ? $row['notes'] : 'Consultation',
        'status'     => $labelStatus,          // pretty label
        'raw_status' => $row['status'],        // real db status for actions
    ];
}
$stmt->close();

/* ---------- ALL APPOINTMENTS (for “All Appointments” tab) ---------- */
$appointmentsAll = [];

$sqlAll = "
    SELECT 
        a.id AS id,
        DATE_FORMAT(a.appointment_date, '%Y-%m-%d %h:%i %p') AS time_label,
        pu.fullname AS patient_name,
        du.fullname AS doctor_name,
        a.status,
        COALESCE(a.notes, '') AS notes
    FROM appointments a
    JOIN patients p      ON a.patient_id = p.id
    JOIN users   pu      ON p.user_id = pu.id
    JOIN doctors d       ON a.doctor_id = d.id
    JOIN users   du      ON d.user_id = du.id
    ORDER BY a.appointment_date DESC
    LIMIT 300
";
if ($res = $conn->query($sqlAll)) {
    while ($row = $res->fetch_assoc()) {
        $labelStatus = $row['status'];
        switch ($row['status']) {
            case 'pending':   $labelStatus = 'Waiting';    break;
            case 'confirmed': $labelStatus = 'Checked In'; break;
            case 'completed': $labelStatus = 'Completed';  break;
            case 'cancelled': $labelStatus = 'Cancelled';  break;
        }

        $appointmentsAll[] = [
            'id'         => (int)$row['id'],
            'time'       => $row['time_label'],
            'patient'    => $row['patient_name'],
            'doctor'     => $row['doctor_name'],
            'type'       => $row['notes'] !== '' ? $row['notes'] : 'Consultation',
            'status'     => $labelStatus,          // pretty label
            'raw_status' => $row['status'],        // real db status for actions
        ];
    }
    $res->free();
}

/* ---------- PATIENT DIRECTORY (for Patients table) ---------- */
$patientRecords = [];

$sqlPatients = "
    SELECT 
        p.id            AS patient_id,
        u.fullname      AS fullname,
        u.email         AS email,
        p.phone         AS phone,
        p.gender        AS gender,
        p.blood_group   AS blood_group,
        p.date_of_birth AS dob
    FROM patients p
    JOIN users u ON p.user_id = u.id
    ORDER BY u.fullname ASC
    LIMIT 150
";

if ($res = $conn->query($sqlPatients)) {
    $todayDateTime = new DateTime();

    while ($row = $res->fetch_assoc()) {
        $age = null;
        if (!empty($row['dob']) && $row['dob'] !== '0000-00-00') {
            try {
                $dob = new DateTime($row['dob']);
                $age = $todayDateTime->diff($dob)->y;
            } catch (Exception $e) {
                $age = null;
            }
        }

        $genderShort = 'O';
        if ($row['gender'] === 'male')   $genderShort = 'M';
        if ($row['gender'] === 'female') $genderShort = 'F';

        $blood = $row['blood_group'] ?? 'N/A';

        $condition      = 'Healthy';
        $conditionClass = 'badge-healthy';

        $patientRecords[] = [
            'id'            => 'P-' . str_pad((string)$row['patient_id'], 4, '0', STR_PAD_LEFT),
            'name'          => $row['fullname'],
            'email'         => $row['email'],
            'phone'         => $row['phone'] ?: 'N/A',
            'age'           => $age ?? 'N/A',
            'gender'        => $genderShort,
            'blood'         => $blood,
            'condition'     => $condition,
            'conditionClass'=> $conditionClass,
            'lastVisit'     => 'N/A',
            'nextAppt'      => 'N/A',
        ];
    }
    $res->free();
}

/* ---------- BILLING RECORDS (for billing table) ---------- */
$billingRecords = [];

$sqlBills = "
    SELECT 
        pay.id            AS payment_id,
        pay.amount        AS amount,
        pay.status        AS status,
        pay.method        AS method,
        pay.created_at    AS created_at,
        pu.fullname       AS patient_name
    FROM payments pay
    JOIN appointments a  ON pay.appointment_id = a.id
    JOIN patients p      ON a.patient_id = p.id
    JOIN users pu        ON p.user_id = pu.id
    ORDER BY pay.created_at DESC
    LIMIT 100
";

if ($res = $conn->query($sqlBills)) {
    while ($row = $res->fetch_assoc()) {
        $statusLabel = ucfirst($row['status']); // paid, pending, refunded...
        $billDate    = $row['created_at'] ? date('M d, Y', strtotime($row['created_at'])) : 'N/A';

        $billingRecords[] = [
            'id'       => '#INV-' . str_pad((string)$row['payment_id'], 4, '0', STR_PAD_LEFT),
            'patient'  => $row['patient_name'],
            'insurance'=> strtoupper($row['method']) . ' payment',
            'date'     => $billDate,
            'amount'   => number_format((float)$row['amount'], 2) . 'Tk',
            'status'   => $statusLabel,
        ];
    }
    $res->free();
}

/* ---------- SIMPLE LISTS FOR DROPDOWNS (doctor & patient users) ---------- */
$doctorUsers  = [];
$patientUsers = [];

// all doctors
$res = $conn->query("SELECT id, fullname FROM users WHERE role = 'doctor' ORDER BY fullname");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $doctorUsers[] = [
            'id'       => (int)$row['id'],
            'fullname' => $row['fullname'],
        ];
    }
    $res->free();
}

// all patients
$res = $conn->query("SELECT id, fullname FROM users WHERE role = 'patient' ORDER BY fullname");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $patientUsers[] = [
            'id'       => (int)$row['id'],
            'fullname' => $row['fullname'],
        ];
    }
    $res->free();
}

/* ---------- DOCTORS LIST FOR "DOCTORS" TAB ---------- */
$doctorsList = [];

$sqlDoctors = "
    SELECT 
        d.id                AS doctor_id,
        u.fullname          AS fullname,
        d.specialization    AS specialization,
        d.chamber           AS chamber,
        d.experience_years  AS experience_years
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    ORDER BY u.fullname ASC
";

if ($res = $conn->query($sqlDoctors)) {
    while ($row = $res->fetch_assoc()) {
        $doctorsList[] = [
            'id'            => (int)$row['doctor_id'],
            'name'          => $row['fullname'],
            'specialization'=> $row['specialization'] ?: 'General Physician',
            'chamber'       => $row['chamber'] ?: 'Main Chamber',
            'experience'    => (int)$row['experience_years'],
        ];
    }
    $res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard | MediDesk</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            --glass-bg: rgba(33, 33, 229, 0.8);
            --glass-border: rgba(255, 255, 255, 0.125);
            --glass-highlight: rgba(255, 255, 255, 0.05);
            --accent-primary: #3b82f6;
            --text-main: #f3f4f6;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f172a;
            color: var(--text-main);
            overflow-x: hidden;
        }

        .fixed-bg-layer {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            z-index: -1;
            overflow: hidden;
        }

        .bg-image {
            width: 100%;
            height: 100%;
            background-image: url('pp.jpg');
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
            animation: drift-zoom 10s ease-in-out infinite alternate;
        }

        @keyframes drift-zoom {
            0% {
                background-size: 100% auto;
                background-position: 50% 50%;
            }
            50% {
                background-size: 120% auto;
                background-position: 50% 30%;
            }
            100% {
                background-size: 120% auto;
                background-position: 60% 50%;
            }
        }

        @media (max-width: 768px) {
            .bg-image {
                background-size: cover;
                background-position: center;
                animation: none;
            }
        }

        .bg-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background: transparent;
            backdrop-filter: blur(0px);
        }

        .glass-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            border-radius: 16px;
        }

        .glass-nav {
            background: rgba(17, 25, 40, 0.6);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
        }

        .glass-input {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--glass-border);
            color: white;
            transition: all 0.2s ease;
        }

        .glass-input:focus {
            outline: none;
            border-color: var(--accent-primary);
            background: rgba(0, 0, 0, 0.4);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }

        .badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-waiting { background: rgba(234, 179, 8, 0.2); color: #fde047; border: 1px solid rgba(234, 179, 8, 0.3); }
        .badge-checked-in { background: rgba(59, 130, 246, 0.2); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.3); }
        .badge-cancelled { background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); }

        .badge-paid { background: rgba(34, 197, 94, 0.2); color: #86efac; border: 1px solid rgba(34, 197, 94, 0.3); }
        .badge-overdue { background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-pending { background: rgba(249, 115, 22, 0.2); color: #fdba74; border: 1px solid rgba(249, 115, 22, 0.3); }

        .badge-critical { background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.2); }
        .badge-chronic { background: rgba(168, 85, 247, 0.15); color: #d8b4fe; border: 1px solid rgba(168, 85, 247, 0.2); }
        .badge-healthy { background: rgba(34, 197, 94, 0.15); color: #86efac; border: 1px solid rgba(34, 197, 94, 0.2); }
        .badge-blood { background: rgba(255, 255, 255, 0.1); color: #cbd5e1; border: 1px solid rgba(255, 255, 255, 0.2); font-family: monospace; }

        .hidden-view { display: none; }
        .active-nav { background: rgba(255, 255, 255, 0.1) !important; color: white !important; }
    </style>
</head>
<body class="antialiased min-h-screen relative">

<div class="fixed-bg-layer">
    <div class="bg-image"></div>
    <div class="bg-overlay"></div>
</div>

<nav class="fixed top-0 w-full z-50 glass-nav h-16 flex items-center justify-between px-4 lg:px-8">
    <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded bg-blue-500 flex items-center justify-center text-white font-bold shadow-lg">
            <i data-lucide="activity" class="w-5 h-5"></i>
        </div>
        <span class="font-semibold text-lg tracking-tight">
            MediDesk <span class="text-blue-400 text-sm font-normal">Reception</span>
        </span>
    </div>

    <div class="hidden md:flex items-center gap-1">
        <button type="button" onclick="switchTab('dashboard')" id="nav-dashboard"
                class="px-4 py-2 rounded-lg text-sm font-medium hover:bg-white/10 transition text-gray-300 active-nav">
            Dashboard
        </button>
        <button type="button" onclick="switchTab('appointments')" id="nav-appointments"
                class="px-4 py-2 rounded-lg text-sm font-medium hover:bg-white/10 transition text-gray-300">
            Appointments
        </button>
        <button type="button" onclick="switchTab('billing')" id="nav-billing"
                class="px-4 py-2 rounded-lg text-sm font-medium hover:bg-white/10 transition text-gray-300">
            Billing
        </button>
        <button type="button" onclick="switchTab('patients')" id="nav-patients"
                class="px-4 py-2 rounded-lg text-sm font-medium hover:bg-white/10 transition text-gray-300">
            Patients
        </button>
        <button type="button" onclick="switchTab('doctors')" id="nav-doctors"
                class="px-4 py-2 rounded-lg text-sm font-medium hover:bg-white/10 transition text-gray-300">
            Doctors
        </button>
    </div>

    <div class="flex items-center gap-4">
        <div class="flex items-center gap-3 pl-4 border-l border-white/10">
            <div class="text-right hidden sm:block">
                <p class="text-sm font-medium text-white">
                    <?php echo htmlspecialchars($receptionName); ?>
                </p>
                <a href="logout.php"
                   class="text-xs text-red-300 hover:text-red-200"
                   onclick="return confirm('Log out?');">
                    Logout
                </a>
            </div>
            <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-purple-500 to-blue-500 p-[2px]">
                <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?php echo urlencode($receptionName); ?>"
                     class="w-full h-full rounded-full bg-gray-900">
            </div>
        </div>
    </div>
</nav>

<main class="relative z-10 pt-24 pb-12 px-4 lg:px-8 max-w-7xl mx-auto">

    <?php if (!empty($action_message)): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm bg-emerald-900/40 border border-emerald-500/40 text-emerald-200">
            <?php echo htmlspecialchars($action_message); ?>
        </div>
    <?php endif; ?>

    <!-- DASHBOARD VIEW -->
    <div id="view-dashboard" class="view-section">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-8">
            <div>
                <p class="text-gray-400 text-sm mb-1 uppercase tracking-wider font-semibold">Overview</p>
                <h1 class="text-3xl font-bold text-white">Dashboard Overview</h1>
            </div>
            <button type="button" onclick="openModal()"
                    class="flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-lg shadow-lg">
                <i data-lucide="plus" class="w-4 h-4"></i> New Appointment
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="glass-panel p-5 flex justify-between">
                <div>
                    <p class="text-gray-200 text-xs font-medium uppercase">Appointments Today</p>
                    <h3 class="text-2xl font-bold text-white mt-1">
                        <?php echo (int)$todayAppointmentsCount; ?>
                    </h3>
                </div>
                <div class="p-3 rounded-lg bg-blue-500/10 text-blue-200">
                    <i data-lucide="calendar" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="glass-panel p-5 flex justify-between">
                <div>
                    <p class="text-gray-200 text-xs font-medium uppercase">Patients</p>
                    <h3 class="text-2xl font-bold text-white mt-1">
                        <?php echo (int)$totalPatients; ?>
                    </h3>
                </div>
                <div class="p-3 rounded-lg bg-yellow-500/10 text-yellow-200">
                    <i data-lucide="users" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="glass-panel p-5 flex justify-between">
                <div>
                    <p class="text-gray-200 text-xs font-medium uppercase">Doctors</p>
                    <h3 class="text-2xl font-bold text-white mt-1">
                        <?php echo (int)$totalDoctors; ?>
                    </h3>
                </div>
                <div class="p-3 rounded-lg bg-purple-500/10 text-purple-200">
                    <i data-lucide="stethoscope" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="glass-panel p-5 flex justify-between">
                <div>
                    <p class="text-gray-200 text-xs font-medium uppercase">Pending Bills</p>
                    <h3 class="text-2xl font-bold text-white mt-1">
                        <?php echo (int)$pendingPaymentsCount; ?>
                    </h3>
                </div>
                <div class="p-3 rounded-lg bg-red-500/10 text-red-200">
                    <i data-lucide="alert-circle" class="w-6 h-6"></i>
                </div>
            </div>
        </div>

        <div class="glass-panel rounded-xl p-6">
            <h3 class="text-lg font-semibold mb-4">Today's Schedule</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                    <tr class="text-xs text-gray-200 uppercase border-b border-white/10">
                        <th class="pb-3">Time</th>
                        <th class="pb-3">Patient</th>
                        <th class="pb-3">Doctor</th>
                        <th class="pb-3">Status</th>
                    </tr>
                    </thead>
                    <tbody id="dashboard-table-body" class="text-sm">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- APPOINTMENTS VIEW -->
    <div id="view-appointments" class="view-section hidden-view">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-white">All Appointments</h1>
            <div class="flex gap-2">
                <input type="date" class="glass-input px-3 py-2 rounded-lg text-sm">
                <button class="bg-blue-600 px-4 py-2 rounded-lg text-white">Filter</button>
            </div>
        </div>

        <div class="glass-panel rounded-xl overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-white/5 text-xs uppercase text-gray-200">
                <tr>
                    <th class="px-6 py-4">Time</th>
                    <th class="px-6 py-4">Patient Name</th>
                    <th class="px-6 py-4">Reason</th>
                    <th class="px-6 py-4">Doctor</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-right">Action</th>
                </tr>
                </thead>
                <tbody id="appointments-full-table-body" class="text-sm divide-y divide-white/10">
                </tbody>
            </table>
        </div>
    </div>

    <!-- BILLING VIEW -->
    <div id="view-billing" class="view-section hidden-view">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-8">
            <div>
                <p class="text-gray-400 text-sm mb-1 uppercase tracking-wider font-semibold">Financials</p>
                <h1 class="text-3xl font-bold text-white">Billing & Invoices</h1>
            </div>
            <div class="flex gap-3">
                <button class="glass-panel px-4 py-2 rounded-lg text-sm hover:bg-white/5 transition flex items-center gap-2">
                    <i data-lucide="download" class="w-4 h-4"></i> Export Report
                </button>
                <button class="bg-green-600 hover:bg-green-500 text-white px-5 py-2.5 rounded-lg shadow-lg flex items-center gap-2">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Create Invoice
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="glass-panel p-5 border-l-4 border-green-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-200 text-xs font-medium uppercase tracking-wide">Monthly Revenue</p>
                        <h3 class="text-2xl font-bold text-white mt-1">42,590Tk</h3>
                    </div>
                    <div class="p-2 bg-green-500/10 rounded-lg text-green-400">
                        <i data-lucide="dollar-sign" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>
            <div class="glass-panel p-5 border-l-4 border-red-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-200 text-xs font-medium uppercase tracking-wide">Outstanding</p>
                        <h3 class="text-2xl font-bold text-white mt-1">3,850Tk</h3>
                    </div>
                    <div class="p-2 bg-red-500/10 rounded-lg text-red-400">
                        <i data-lucide="alert-octagon" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>
            <div class="glass-panel p-5 border-l-4 border-blue-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-200 text-xs font-medium uppercase tracking-wide">Claims Processed</p>
                        <h3 class="text-2xl font-bold text-white mt-1">156Tk</h3>
                    </div>
                    <div class="p-2 bg-blue-500/10 rounded-lg text-blue-400">
                        <i data-lucide="file-text" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>
            <div class="glass-panel p-5 border-l-4 border-purple-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-200 text-xs font-medium uppercase tracking-wide">Avg. Daily Bill</p>
                        <h3 class="text-2xl font-bold text-white mt-1">215Tk</h3>
                    </div>
                    <div class="p-2 bg-purple-500/10 rounded-lg text-purple-400">
                        <i data-lucide="bar-chart-2" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 glass-panel rounded-xl overflow-hidden flex flex-col">
                <div class="p-5 border-b border-white/10 flex justify-between items-center">
                    <h3 class="font-bold text-lg">Recent Invoices</h3>
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-2.5 text-gray-500"></i>
                        <input type="text" placeholder="Search invoices..."
                               class="glass-input pl-9 pr-4 py-1.5 rounded-full text-sm w-48 focus:w-64 transition-all">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-white/5 text-xs uppercase text-gray-200">
                        <tr>
                            <th class="px-5 py-3">Invoice ID</th>
                            <th class="px-5 py-3">Patient</th>
                            <th class="px-5 py-3">Insurance</th>
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Amount</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                        </thead>
                        <tbody id="billing-table-body" class="text-sm divide-y divide-white/10">
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="glass-panel rounded-xl p-6">
                <h3 class="font-bold text-lg mb-4">Live Transactions</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 rounded-lg bg-white/5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center text-green-400">
                                <i data-lucide="credit-card" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-white">Copay Received</p>
                                <p class="text-xs text-gray-400">Alice Johnson</p>
                            </div>
                        </div>
                        <span class="text-green-400 font-bold text-sm">+$45.00</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-lg bg-white/5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-400">
                                <i data-lucide="shield-check" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-white">Ins. Claim Paid</p>
                                <p class="text-xs text-gray-400">BlueCross - #9921</p>
                            </div>
                        </div>
                        <span class="text-blue-400 font-bold text-sm">+$850.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PATIENTS VIEW -->
    <div id="view-patients" class="view-section hidden-view">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-8">
            <div>
                <p class="text-gray-400 text-sm mb-1 uppercase tracking-wider font-semibold">Directory</p>
                <h1 class="text-3xl font-bold text-white">Patient Records</h1>
            </div>
            <div class="flex gap-3">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-3 text-gray-400"></i>
                    <input type="text" placeholder="Search by name or ID..." class="glass-input pl-10 pr-4 py-2.5 rounded-lg w-64">
                </div>
                <button class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-lg shadow-lg flex items-center gap-2">
                    <i data-lucide="user-plus" class="w-4 h-4"></i> Add Patient
                </button>
            </div>
        </div>

        <div class="glass-panel rounded-xl overflow-hidden">
            <div class="p-4 border-b border-white/10 flex gap-4 items-center">
                <button class="text-white text-sm font-medium border-b-2 border-blue-500 pb-1">All Patients</button>
                <button class="text-gray-400 text-sm font-medium hover:text-white pb-1">In-Patient</button>
                <button class="text-gray-400 text-sm font-medium hover:text-white pb-1">Out-Patient</button>
                <div class="flex-1 text-right">
                    <select class="bg-black/20 border border-white/10 text-gray-300 text-xs rounded px-2 py-1 outline-none">
                        <option>Sort by Name</option>
                        <option>Sort by Last Visit</option>
                    </select>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-white/5 text-xs uppercase text-gray-200">
                    <tr>
                        <th class="px-6 py-4">Patient</th>
                        <th class="px-6 py-4">Contact</th>
                        <th class="px-6 py-4">Bio / Medical</th>
                        <th class="px-6 py-4">Visit History</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody id="patients-table-body" class="text-sm divide-y divide-white/10">
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-white/10 flex justify-between items-center text-xs text-gray-400">
                <span>Showing <?php echo count($patientRecords); ?> patients</span>
                <div class="flex gap-2">
                    <button class="px-3 py-1 rounded bg-white/5 hover:bg-white/10">Prev</button>
                    <button class="px-3 py-1 rounded bg-white/5 hover:bg-white/10">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- DOCTORS VIEW -->
    <div id="view-doctors" class="view-section hidden-view">
        <h1 class="text-3xl font-bold text-white mb-6">Medical Staff</h1>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php if (!empty($doctorsList)): ?>
                <?php foreach ($doctorsList as $doc): ?>
                    <div class="glass-panel p-6 rounded-xl text-center">
                        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?php echo urlencode($doc['name']); ?>"
                             class="w-20 h-20 rounded-full mx-auto mb-3 bg-gray-700">
                        <h3 class="font-bold text-lg">
                            <?php echo htmlspecialchars($doc['name']); ?>
                        </h3>
                        <p class="text-blue-300 text-sm">
                            <?php echo htmlspecialchars($doc['specialization']); ?>
                        </p>
                        <p class="text-gray-200 text-xs mt-1">
                            <?php echo htmlspecialchars($doc['chamber']); ?>
                        </p>
                        <div class="mt-4 flex justify-center gap-2">
                            <span class="badge badge-checked-in">Available</span>
                            <?php if ($doc['experience'] > 0): ?>
                                <span class="text-xs text-gray-200">
                                    <?php echo (int)$doc['experience']; ?> yrs exp.
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="col-span-3 text-gray-300 text-sm">
                    No doctors found yet. Please sign up a doctor account first.
                </p>
            <?php endif; ?>
        </div>
    </div>

</main>

<!-- NEW APPOINTMENT MODAL -->
<div id="appointment-modal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="glass-panel w-full max-w-lg rounded-2xl p-6 relative">
            <button type="button" onclick="closeModal()" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
            <h2 class="text-2xl font-bold mb-4">New Appointment</h2>

            <?php if (!empty($action_message)): ?>
                <p class="mb-3 text-sm text-emerald-200 bg-emerald-900/40 border border-emerald-500/40 px-3 py-2 rounded-lg">
                    <?php echo htmlspecialchars($action_message); ?>
                </p>
            <?php endif; ?>

            <form method="POST" action="jo.php" class="space-y-4">
                <input type="hidden" name="create_appointment" value="1">

                <div class="space-y-2">
                    <label class="block text-xs font-semibold tracking-wide text-slate-200">
                        Select Doctor
                    </label>
                    <select name="doctor_user_id" required
                            class="glass-input w-full px-4 py-2 rounded-lg bg-black/30 border border-white/20 text-sm">
                        <option value="" disabled selected>Select a doctor</option>
                        <?php foreach ($doctorUsers as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>">
                                <?php echo htmlspecialchars($d['fullname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-semibold tracking-wide text-slate-200">
                        Select Patient
                    </label>
                    <select name="patient_user_id" required
                            class="glass-input w-full px-4 py-2 rounded-lg bg-black/30 border border-white/20 text-sm">
                        <option value="" disabled selected>Select a patient</option>
                        <?php foreach ($patientUsers as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>">
                                <?php echo htmlspecialchars($p['fullname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex gap-4">
                    <div class="flex-1 space-y-1">
                        <label class="block text-xs font-semibold tracking-wide text-slate-200">
                            Date
                        </label>
                        <input type="date" name="appointment_date" required
                               class="glass-input w-full px-4 py-2 rounded-lg bg-black/30 border border-white/20 text-sm">
                    </div>
                    <div class="flex-1 space-y-1">
                        <label class="block text-xs font-semibold tracking-wide text-slate-200">
                            Time
                        </label>
                        <input type="time" name="appointment_time" required
                               class="glass-input w-full px-4 py-2 rounded-lg bg-black/30 border border-white/20 text-sm">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-semibold tracking-wide text-slate-200">
                        Notes (optional)
                    </label>
                    <textarea name="notes" rows="2"
                              class="glass-input w-full px-4 py-2 rounded-lg bg-black/30 border border-white/20 text-sm"
                              placeholder="Short note about the visit (reason, symptoms, etc.)"></textarea>
                </div>

                <button type="submit" class="w-full py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-medium">
                    Confirm Booking
                </button>
            </form>

        </div>
    </div>
</div>

<script>
    const appointmentsToday = <?php echo json_encode($appointmentsToday, JSON_UNESCAPED_UNICODE); ?>;
    const appointmentsAll   = <?php echo json_encode($appointmentsAll, JSON_UNESCAPED_UNICODE); ?>;
    const billingRecords    = <?php echo json_encode($billingRecords, JSON_UNESCAPED_UNICODE); ?>;
    const patientRecords    = <?php echo json_encode($patientRecords, JSON_UNESCAPED_UNICODE); ?>;

    document.addEventListener('DOMContentLoaded', () => {
        if (window.lucide) {
            lucide.createIcons();
        }
        renderDashboardTables();
        renderBillingTable();
        renderPatientTable();
    });

    function switchTab(tabName) {
        document.querySelectorAll('.view-section').forEach(el => el.classList.add('hidden-view'));
        const selectedView = document.getElementById('view-' + tabName);
        if (selectedView) selectedView.classList.remove('hidden-view');

        document.querySelectorAll('nav button').forEach(btn => {
            btn.classList.remove('active-nav', 'text-white');
            btn.classList.add('text-gray-300');
        });
        const activeBtn = document.getElementById('nav-' + tabName);
        if (activeBtn) {
            activeBtn.classList.add('active-nav');
            activeBtn.classList.remove('text-gray-300');
        }
    }

    function renderDashboardTables() {
        const dashboardBody = document.getElementById('dashboard-table-body');
        const fullBody      = document.getElementById('appointments-full-table-body');

        if (dashboardBody) dashboardBody.innerHTML = '';
        if (fullBody)      fullBody.innerHTML      = '';

        // Today's Schedule (read-only view)
        appointmentsToday.forEach((appt) => {
            let badgeClass = 'badge-waiting';
            if (appt.status === 'Checked In') badgeClass = 'badge-checked-in';
            if (appt.status === 'Cancelled')  badgeClass = 'badge-cancelled';
            if (appt.status === 'Completed')  badgeClass = 'badge-checked-in';

            if (dashboardBody) {
                dashboardBody.innerHTML += `
                    <tr class="border-b border-white/5">
                        <td class="py-3 text-gray-200">${appt.time}</td>
                        <td class="py-3 font-medium text-white">${appt.patient}</td>
                        <td class="py-3 text-gray-200">${appt.doctor}</td>
                        <td class="py-3"><span class="badge ${badgeClass}">${appt.status}</span></td>
                    </tr>`;
            }
        });

        // All Appointments tab (WITH status change forms)
        appointmentsAll.forEach((appt) => {
            let badgeClass = 'badge-waiting';
            if (appt.status === 'Checked In') badgeClass = 'badge-checked-in';
            if (appt.status === 'Cancelled')  badgeClass = 'badge-cancelled';
            if (appt.status === 'Completed')  badgeClass = 'badge-checked-in';

            // Build actions HTML based on raw_status
            let actionsHtml = '';

            // Confirm (pending -> confirmed)
            if (appt.raw_status === 'pending') {
                actionsHtml += `
                    <form method="post" style="display:inline-block; margin-right:4px;">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="appointment_id" value="${appt.id}">
                        <input type="hidden" name="new_status" value="confirmed">
                        <button type="submit"
                                class="px-2 py-1 rounded bg-emerald-600/80 hover:bg-emerald-500 text-[11px] text-white">
                            Confirm
                        </button>
                    </form>`;
            }

            // Complete (pending/confirmed -> completed)
            if (appt.raw_status === 'pending' || appt.raw_status === 'confirmed') {
                actionsHtml += `
                    <form method="post" style="display:inline-block; margin-right:4px;">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="appointment_id" value="${appt.id}">
                        <input type="hidden" name="new_status" value="completed">
                        <button type="submit"
                                class="px-2 py-1 rounded bg-blue-600/80 hover:bg-blue-500 text-[11px] text-white">
                            Complete
                        </button>
                    </form>`;
            }

            // Cancel (anything except already cancelled/completed)
            if (appt.raw_status !== 'cancelled' && appt.raw_status !== 'completed') {
                actionsHtml += `
                    <form method="post" style="display:inline-block;"
                          onsubmit="return confirm('Cancel this appointment?');">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="appointment_id" value="${appt.id}">
                        <input type="hidden" name="new_status" value="cancelled">
                        <button type="submit"
                                class="px-2 py-1 rounded bg-red-600/80 hover:bg-red-500 text-[11px] text-white">
                            Cancel
                        </button>
                    </form>`;
            }

            if (fullBody) {
                fullBody.innerHTML += `
                    <tr class="hover:bg-white/5 transition">
                        <td class="px-6 py-4 text-gray-200">${appt.time}</td>
                        <td class="px-6 py-4 font-bold text-white">${appt.patient}</td>
                        <td class="px-6 py-4 text-gray-300">${appt.type}</td>
                        <td class="px-6 py-4 text-gray-200">${appt.doctor}</td>
                        <td class="px-6 py-4"><span class="badge ${badgeClass}">${appt.status}</span></td>
                        <td class="px-6 py-4 text-right">
                            ${actionsHtml}
                        </td>
                    </tr>`;
            }
        });
    }

    function renderBillingTable() {
        const billingBody = document.getElementById('billing-table-body');
        if (!billingBody) return;
        billingBody.innerHTML = '';

        billingRecords.forEach(bill => {
            let badgeClass = 'badge-pending';
            if (bill.status === 'Paid')    badgeClass = 'badge-paid';
            if (bill.status === 'Overdue') badgeClass = 'badge-overdue';

            billingBody.innerHTML += `
                <tr class="hover:bg-white/5 transition group">
                    <td class="px-5 py-4 font-mono text-xs text-blue-300">${bill.id}</td>
                    <td class="px-5 py-4 font-medium text-white">${bill.patient}</td>
                    <td class="px-5 py-4 text-gray-300">${bill.insurance}</td>
                    <td class="px-5 py-4 text-gray-200 text-xs">${bill.date}</td>
                    <td class="px-5 py-4 font-bold text-white">${bill.amount}</td>
                    <td class="px-5 py-4"><span class="badge ${badgeClass}">${bill.status}</span></td>
                    <td class="px-5 py-4 text-right">
                        <button class="text-gray-500 hover:text-white opacity-0 group-hover:opacity-100 transition">
                            <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                        </button>
                    </td>
                </tr>`;
        });
    }

    function renderPatientTable() {
        const patientBody = document.getElementById('patients-table-body');
        if (!patientBody) return;
        patientBody.innerHTML = '';

        patientRecords.forEach(p => {
            patientBody.innerHTML += `
                <tr class="hover:bg-white/5 transition">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gray-700">
                                <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=${encodeURIComponent(p.name)}"
                                     class="w-full h-full rounded-full">
                            </div>
                            <div>
                                <p class="text-white font-medium text-sm">${p.name}</p>
                                <p class="text-gray-300 text-xs">${p.email}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2 text-gray-200 text-sm">
                            <i data-lucide="phone" class="w-3 h-3"></i> ${p.phone}
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex gap-2 mb-1">
                            <span class="badge badge-blood">${p.blood}</span>
                            <span class="text-gray-200 text-xs self-center">${p.age} yrs / ${p.gender}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-gray-200 text-sm">Last: ${p.lastVisit}</p>
                        <p class="text-blue-300 text-xs">Next: ${p.nextAppt}</p>
                    </td>
                    <td class="px-6 py-4">
                        <span class="badge ${p.conditionClass}">${p.condition}</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <button class="p-1.5 rounded-lg bg-blue-600/20 text-blue-300 hover:bg-blue-600 hover:text-white transition" title="Profile">
                                <i data-lucide="user" class="w-4 h-4"></i>
                            </button>
                            <button class="p-1.5 rounded-lg bg-white/5 text-gray-200 hover:bg-white/10 hover:text-white transition" title="Message">
                                <i data-lucide="message-square" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
        });
    }

    function openModal() {
        document.getElementById('appointment-modal').classList.remove('hidden');
    }
    function closeModal() {
        document.getElementById('appointment-modal').classList.add('hidden');
    }
</script>
</body>
</html>
