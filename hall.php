<?php
session_start();
require_once 'db.php';

// Only doctor or patient can access
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true ||
    !in_array($_SESSION['role'], ['doctor','patient'], true)) {
    header('Location: login.php');
    exit;
}

$name     = $_SESSION['fullname'] ?? 'User';
$role     = $_SESSION['role'];
$userId   = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? '';

// message for actions
$flashMessage = '';
$flashType    = 'info'; // success | error | info

// profile ids (from doctors/patients tables)
$doctorProfileId  = null;
$patientProfileId = null;

// Resolve profile IDs once so we can reuse
if ($userId !== null) {
    if ($role === 'doctor') {
        $stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ? LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $doctorProfileId = (int)$row['id'];
        }
        $stmt->close();
    } elseif ($role === 'patient') {
        $stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ? LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $patientProfileId = (int)$row['id'];
        }
        $stmt->close();
    }
}

// ---------- LOAD DOCTORS LIST (for patient "Find Doctor" + booking) ----------
$availableDoctors = [];
$sqlDocs = "
    SELECT 
        d.id,
        u.fullname,
        d.specialization,
        d.chamber,
        d.consultation_fee,
        d.available_from,
        d.available_to
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE u.status = 'active'
    ORDER BY d.specialization, u.fullname
";
if ($res = $conn->query($sqlDocs)) {
    while ($row = $res->fetch_assoc()) {
        $availableDoctors[] = [
            'id'        => (int)$row['id'],
            'name'      => $row['fullname'],
            'spec'      => $row['specialization'] ?: 'General Physician',
            'chamber'   => $row['chamber'] ?: 'Main Chamber',
            'fee'       => $row['consultation_fee'],
            'from'      => $row['available_from'],
            'to'        => $row['available_to'],
        ];
    }
    $res->free();
}

// ---------- HANDLE POST ACTIONS (status change, note, patient booking) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId !== null) {

    $action = $_POST['action'] ?? '';

    // PATIENT: request new appointment (goes to appointments table as pending)
    if ($action === 'patient_book' && $role === 'patient') {
        $doctorId   = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
        $date       = trim($_POST['appointment_date'] ?? '');
        $time       = trim($_POST['appointment_time'] ?? '');
        $note       = trim($_POST['note'] ?? '');

        if (!$patientProfileId) {
            $flashMessage = 'Patient profile not found. Please contact support.';
            $flashType    = 'error';
        } elseif ($doctorId <= 0 || $date === '' || $time === '') {
            $flashMessage = 'Please select doctor, date and time.';
            $flashType    = 'error';
        } else {
            // verify doctor exists
            $stmtCheck = $conn->prepare("SELECT id FROM doctors WHERE id = ? LIMIT 1");
            $stmtCheck->bind_param('i', $doctorId);
            $stmtCheck->execute();
            $res = $stmtCheck->get_result();
            if (!$res || $res->num_rows === 0) {
                $flashMessage = 'Selected doctor not found.';
                $flashType    = 'error';
            } else {
                // build datetime
                // if time already has seconds, don't double-append
                if (strlen($time) === 5) {
                    $dateTime = $date . ' ' . $time . ':00';
                } else {
                    $dateTime = $date . ' ' . $time;
                }

                $stmtIns = $conn->prepare("
                    INSERT INTO appointments (doctor_id, patient_id, appointment_date, status, notes)
                    VALUES (?, ?, ?, 'pending', ?)
                ");
                $stmtIns->bind_param('iiss', $doctorId, $patientProfileId, $dateTime, $note);
                if ($stmtIns->execute()) {
                    $flashMessage = 'Appointment request sent. Reception will confirm it.';
                    $flashType    = 'success';
                } else {
                    $flashMessage = 'Failed to request appointment. Please try again.';
                    $flashType    = 'error';
                }
                $stmtIns->close();
            }
            $stmtCheck->close();
        }
    }

    // Change appointment status
    if ($action === 'change_status') {
        $appointmentId   = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
        $newStatus       = $_POST['new_status'] ?? '';
        $allowedStatuses = ['pending','confirmed','completed','cancelled'];

        if ($appointmentId > 0 && in_array($newStatus, $allowedStatuses, true)) {
            if ($role === 'doctor') {
                // doctor can change status of their own appointments
                $stmt = $conn->prepare("
                    UPDATE appointments a
                    JOIN doctors d ON a.doctor_id = d.id
                    SET a.status = ?
                    WHERE a.id = ?
                      AND d.user_id = ?
                ");
                $stmt->bind_param('sii', $newStatus, $appointmentId, $userId);
            } elseif ($role === 'patient') {
                // patient can only cancel their own appointments
                if ($newStatus !== 'cancelled') {
                    $flashMessage = 'Patients can only cancel appointments.';
                    $flashType    = 'error';
                    $stmt = null;
                } else {
                    $stmt = $conn->prepare("
                        UPDATE appointments a
                        JOIN patients p ON a.patient_id = p.id
                        SET a.status = 'cancelled'
                        WHERE a.id = ?
                          AND p.user_id = ?
                    ");
                    $stmt->bind_param('ii', $appointmentId, $userId);
                }
            } else {
                $stmt = null;
            }

            if (isset($stmt) && $stmt) {
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $flashMessage = 'Appointment status updated.';
                    $flashType    = 'success';
                } else {
                    $flashMessage = 'Failed to update status (maybe not your appointment?).';
                    $flashType    = 'error';
                }
                $stmt->close();
            }
        } else {
            $flashMessage = 'Invalid appointment or status.';
            $flashType    = 'error';
        }
    }

    // Add note / mini-prescription
    if ($action === 'add_note') {
        $appointmentId = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
        $note          = trim($_POST['note'] ?? '');

        if ($appointmentId > 0 && $note !== '') {
            if ($role === 'doctor') {
                $stmt = $conn->prepare("
                    UPDATE appointments a
                    JOIN doctors d ON a.doctor_id = d.id
                    SET a.notes = CONCAT(
                        IFNULL(a.notes, ''),
                        CASE WHEN a.notes IS NULL OR a.notes = '' THEN '' ELSE '\n' END,
                        ?
                    )
                    WHERE a.id = ?
                      AND d.user_id = ?
                ");
                $stmt->bind_param('sii', $note, $appointmentId, $userId);
            } elseif ($role === 'patient') {
                // allow patient to add a small note too (symptoms, etc.)
                $stmt = $conn->prepare("
                    UPDATE appointments a
                    JOIN patients p ON a.patient_id = p.id
                    SET a.notes = CONCAT(
                        IFNULL(a.notes, ''),
                        CASE WHEN a.notes IS NULL OR a.notes = '' THEN '' ELSE '\n' END,
                        ?
                    )
                    WHERE a.id = ?
                      AND p.user_id = ?
                ");
                $stmt->bind_param('sii', $note, $appointmentId, $userId);
            } else {
                $stmt = null;
            }

            if (isset($stmt) && $stmt) {
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $flashMessage = 'Note / prescription saved.';
                    $flashType    = 'success';
                } else {
                    $flashMessage = 'Failed to save note.';
                    $flashType    = 'error';
                }
                $stmt->close();
            }
        } else {
            $flashMessage = 'Please write something before saving.';
            $flashType    = 'error';
        }
    }
}

// ---------- LOAD UPCOMING APPOINTMENTS FOR THIS USER ----------
$appointments   = [];
$upcomingCount  = 0;
$nextAppointment = null;

if ($userId !== null) {
    if ($role === 'doctor' && $doctorProfileId !== null) {
        $stmt = $conn->prepare("
            SELECT a.id,
                   a.appointment_date,
                   a.status,
                   a.notes,
                   u.fullname AS other_name
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN users u    ON p.user_id = u.id
            WHERE a.doctor_id = ?
              AND a.appointment_date >= NOW()
            ORDER BY a.appointment_date ASC
            LIMIT 50
        ");
        $stmt->bind_param('i', $doctorProfileId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($r = $result->fetch_assoc()) {
                $appointments[] = $r;
            }
        }
        $stmt->close();
    } elseif ($role === 'patient' && $patientProfileId !== null) {
        $stmt = $conn->prepare("
            SELECT a.id,
                   a.appointment_date,
                   a.status,
                   a.notes,
                   u.fullname AS other_name
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            JOIN users  u  ON d.user_id = u.id
            WHERE a.patient_id = ?
              AND a.appointment_date >= NOW()
            ORDER BY a.appointment_date ASC
            LIMIT 50
        ");
        $stmt->bind_param('i', $patientProfileId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($r = $result->fetch_assoc()) {
                $appointments[] = $r;
            }
        }
        $stmt->close();
    }
}

$upcomingCount = count($appointments);
if ($upcomingCount > 0) {
    $nextAppointment = $appointments[0];
}

// today count
$todayCount = 0;
foreach ($appointments as $a) {
    if (date('Y-m-d', strtotime($a['appointment_date'])) === date('Y-m-d')) {
        $todayCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo ucfirst($role); ?> Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-50">
<!-- Background glow -->
<div class="fixed inset-0 -z-10 bg-gradient-to-br from-sky-900/60 via-slate-950 to-slate-950"></div>
<div class="fixed -top-32 -right-32 w-72 h-72 rounded-full bg-sky-500/20 blur-3xl"></div>
<div class="fixed -bottom-40 -left-24 w-80 h-80 rounded-full bg-indigo-500/20 blur-3xl"></div>

<div class="max-w-6xl mx-auto py-8 px-4">
    <!-- HEADER -->
    <header class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 border-b border-white/10 pb-4 gap-3">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-sky-500/20 border border-sky-400/40">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                </span>
                <?php echo ($role === 'doctor') ? 'Doctor' : 'Patient'; ?> Portal
            </h1>
            <p class="text-sm text-slate-300 mt-1">
                Welcome, <span class="font-semibold"><?php echo htmlspecialchars($name); ?></span>
                <span class="text-slate-500">(<?php echo htmlspecialchars($role); ?>)</span>
            </p>

            <?php if ($nextAppointment): ?>
                <p class="mt-2 text-xs md:text-sm text-emerald-300 flex flex-wrap gap-1 items-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-400/40 text-[11px] uppercase tracking-wide">
                        Next Appointment
                    </span>
                    <span>
                        <?php
                            echo htmlspecialchars(
                                date('d M Y, h:i A', strtotime($nextAppointment['appointment_date']))
                            );
                        ?>
                        with <?php echo htmlspecialchars($nextAppointment['other_name']); ?>
                    </span>
                </p>
            <?php else: ?>
                <p class="mt-2 text-xs md:text-sm text-slate-400">
                    No upcoming appointments yet.
                </p>
            <?php endif; ?>
        </div>

        <div class="flex flex-col items-start md:items-end gap-2">
            <span class="px-3 py-1 rounded-full text-xs bg-slate-900/70 border border-slate-700/80">
                Logged in as:
                <span class="font-mono text-sky-300"><?php echo htmlspecialchars($username); ?></span>
            </span>
            <a href="logout.php"
               class="inline-flex items-center gap-1 text-sm text-red-300 hover:text-red-200"
               onclick="return confirm('Log out?');">
                <span class="w-2 h-2 rounded-full bg-red-400"></span>
                Logout
            </a>
        </div>
    </header>

    <!-- FLASH MESSAGE -->
    <?php if ($flashMessage !== ''): ?>
        <div class="mb-5 px-4 py-3 rounded-xl text-sm shadow-lg
            <?php
                echo $flashType === 'success'
                    ? 'bg-emerald-950/70 border border-emerald-500/60 text-emerald-100'
                    : ($flashType === 'error'
                        ? 'bg-red-950/70 border border-red-500/60 text-red-100'
                        : 'bg-slate-900/70 border border-slate-500/60 text-slate-100');
            ?>
        ">
            <?php echo htmlspecialchars($flashMessage); ?>
        </div>
    <?php endif; ?>

    <!-- TOP METRICS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-slate-900/70 border border-white/10 rounded-2xl p-4 shadow-md">
            <p class="text-xs text-slate-400 uppercase font-semibold tracking-wide">Upcoming</p>
            <h3 class="text-2xl font-bold mt-1"><?php echo (int)$upcomingCount; ?></h3>
            <p class="text-[11px] text-slate-400 mt-1">
                <?php echo ($role === 'doctor') ? 'Appointments in your schedule' : 'Your booked visits'; ?>
            </p>
        </div>

        <div class="bg-slate-900/70 border border-white/10 rounded-2xl p-4 shadow-md">
            <p class="text-xs text-slate-400 uppercase font-semibold tracking-wide">Today</p>
            <h3 class="text-lg font-semibold mt-1">
                <?php echo (int)$todayCount . ' appointment' . ($todayCount === 1 ? '' : 's'); ?>
            </h3>
            <p class="text-[11px] text-slate-400 mt-1">
                Based on <?php echo date('d M Y'); ?>.
            </p>
        </div>

        <div class="bg-slate-900/70 border border-white/10 rounded-2xl p-4 shadow-md">
            <p class="text-xs text-slate-400 uppercase font-semibold tracking-wide">
                <?php echo ($role === 'doctor') ? 'Clinical Tools' : 'Patient Tools'; ?>
            </p>
            <ul class="mt-2 text-[11px] space-y-1 text-slate-300">
                <?php if ($role === 'doctor'): ?>
                    <li>• Write quick note / prescription per visit</li>
                    <li>• Confirm / complete / cancel appointments</li>
                    <li>• (Coming soon) Full visit history</li>
                <?php else: ?>
                    <li>• Cancel upcoming visit if needed</li>
                    <li>• Add symptoms / questions for doctor</li>
                    <li>• (New) Book doctor directly from dashboard</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="bg-gradient-to-br from-sky-600/80 to-indigo-500/80 rounded-2xl p-4 shadow-xl border border-sky-400/40">
            <p class="text-xs text-sky-100 uppercase font-semibold tracking-wide">Quick Navigation</p>
            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                <button class="px-3 py-1 rounded-full bg-slate-900/40 border border-white/20">
                    Appointments
                </button>
                <?php if ($role === 'patient'): ?>
                    <button class="px-3 py-1 rounded-full bg-slate-900/40 border border-white/20"
                            onclick="document.getElementById('booking-section').scrollIntoView({behavior:'smooth'})">
                        Find Doctor & Book
                    </button>
                <?php else: ?>
                    <button class="px-3 py-1 rounded-full bg-slate-900/40 border border-white/20">
                        Prescriptions
                    </button>
                <?php endif; ?>
                <button class="px-3 py-1 rounded-full bg-slate-900/40 border border-white/20">
                    Profile
                </button>
            </div>
        </div>
    </div>

    <!-- MAIN LAYOUT: APPOINTMENTS + SIDE PANEL -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-10">
        <!-- APPOINTMENTS TABLE -->
        <div class="xl:col-span-2 bg-slate-900/70 border border-white/10 rounded-2xl p-6 shadow-lg">
            <h2 class="text-lg font-semibold mb-1 flex items-center justify-between">
                <span>
                    <?php echo ($role === 'doctor')
                        ? 'Your Upcoming Appointments'
                        : 'Your Upcoming Visits'; ?>
                </span>
                <span class="text-xs text-slate-400">
                    Showing <?php echo (int)$upcomingCount; ?> record<?php echo ($upcomingCount === 1 ? '' : 's'); ?>
                </span>
            </h2>
            <p class="text-xs text-slate-400 mb-4">
                Manage appointment status and add quick notes / prescriptions directly from this table.
            </p>

            <?php if (!empty($appointments)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm border border-slate-700 rounded-xl overflow-hidden">
                        <thead class="bg-slate-950/90 text-slate-200">
                            <tr>
                                <th class="px-3 py-2 text-left border-b border-slate-700">Date &amp; Time</th>
                                <th class="px-3 py-2 text-left border-b border-slate-700">
                                    <?php echo ($role === 'doctor') ? 'Patient' : 'Doctor'; ?>
                                </th>
                                <th class="px-3 py-2 text-left border-b border-slate-700">Status</th>
                                <th class="px-3 py-2 text-left border-b border-slate-700">Notes / Prescription</th>
                                <th class="px-3 py-2 text-left border-b border-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <?php foreach ($appointments as $appt): ?>
                                <?php
                                    $status = $appt['status'];
                                    $badgeClass = 'bg-slate-600/40 text-slate-100';
                                    if ($status === 'pending')   $badgeClass = 'bg-amber-500/20 text-amber-300';
                                    if ($status === 'confirmed') $badgeClass = 'bg-blue-500/20 text-blue-300';
                                    if ($status === 'completed') $badgeClass = 'bg-emerald-500/20 text-emerald-300';
                                    if ($status === 'cancelled') $badgeClass = 'bg-red-500/20 text-red-300';
                                ?>
                                <tr>
                                    <td class="px-3 py-2 align-top">
                                        <?php echo htmlspecialchars(
                                            date('d M Y, h:i A', strtotime($appt['appointment_date']))
                                        ); ?>
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <?php echo htmlspecialchars($appt['other_name']); ?>
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs <?php echo $badgeClass; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-200 align-top whitespace-pre-line max-w-xs">
                                        <?php
                                            $notes = trim($appt['notes'] ?? '');
                                            echo $notes !== '' ? htmlspecialchars($notes) : '—';
                                        ?>
                                        <!-- quick note form -->
                                        <form method="post" class="mt-2 space-y-1">
                                            <input type="hidden" name="action" value="add_note">
                                            <input type="hidden" name="appointment_id"
                                                   value="<?php echo (int)$appt['id']; ?>">
                                            <textarea name="note"
                                                      rows="2"
                                                      class="w-full px-2 py-1 rounded-lg bg-slate-950/70 border border-slate-600 text-[11px] focus:outline-none focus:ring-1 focus:ring-sky-500"
                                                      placeholder="<?php echo ($role === 'doctor')
                                                          ? 'Add quick prescription or visit summary...'
                                                          : 'Add note (symptoms / questions for doctor)...'; ?>"></textarea>
                                            <button type="submit"
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] bg-sky-600 hover:bg-sky-500">
                                                Save Note
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-3 py-2 align-top text-xs">
                                        <?php if ($role === 'doctor'): ?>
                                            <!-- Doctor: Confirm / Complete / Cancel -->
                                            <div class="space-y-1">
                                                <?php if ($status === 'pending'): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="change_status">
                                                        <input type="hidden" name="appointment_id"
                                                               value="<?php echo (int)$appt['id']; ?>">
                                                        <input type="hidden" name="new_status" value="confirmed">
                                                        <button type="submit"
                                                                class="w-full px-2 py-1 rounded bg-emerald-600/80 hover:bg-emerald-500 text-[11px]">
                                                            Confirm
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if (in_array($status, ['pending','confirmed'], true)): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="change_status">
                                                        <input type="hidden" name="appointment_id"
                                                               value="<?php echo (int)$appt['id']; ?>">
                                                        <input type="hidden" name="new_status" value="completed">
                                                        <button type="submit"
                                                                class="w-full px-2 py-1 rounded bg-blue-600/80 hover:bg-blue-500 text-[11px]">
                                                            Mark Completed
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($status !== 'cancelled' && $status !== 'completed'): ?>
                                                    <form method="post"
                                                          onsubmit="return confirm('Cancel this appointment?');">
                                                        <input type="hidden" name="action" value="change_status">
                                                        <input type="hidden" name="appointment_id"
                                                               value="<?php echo (int)$appt['id']; ?>">
                                                        <input type="hidden" name="new_status" value="cancelled">
                                                        <button type="submit"
                                                                class="w-full px-2 py-1 rounded bg-red-600/80 hover:bg-red-500 text-[11px]">
                                                            Cancel
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <!-- Patient: Cancel only -->
                                            <div class="space-y-1">
                                                <?php if ($status !== 'cancelled' && $status !== 'completed'): ?>
                                                    <form method="post"
                                                          onsubmit="return confirm('Cancel this appointment?');">
                                                        <input type="hidden" name="action" value="change_status">
                                                        <input type="hidden" name="appointment_id"
                                                               value="<?php echo (int)$appt['id']; ?>">
                                                        <input type="hidden" name="new_status" value="cancelled">
                                                        <button type="submit"
                                                                class="w-full px-2 py-1 rounded bg-red-600/80 hover:bg-red-500 text-[11px]">
                                                            Cancel Visit
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-slate-400 text-[11px]">
                                                        No further actions.
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-400">
                    No upcoming appointments in the system for your account yet.
                </p>
            <?php endif; ?>
        </div>

        <!-- SIDE PANEL: ROLE-SPECIFIC OPTIONS -->
        <aside class="space-y-4">
            <?php if ($role === 'patient'): ?>
                <!-- PATIENT: QUICK BOOKING -->
                <div id="booking-section" class="bg-slate-900/70 border border-sky-500/50 rounded-2xl p-5 shadow-lg">
                    <h3 class="text-sm font-semibold mb-1">Book a New Appointment</h3>
                    <p class="text-[11px] text-slate-300 mb-3">
                        Choose a doctor, pick date &amp; time and your request will go to reception.
                    </p>

                    <form method="post" class="space-y-3">
                        <input type="hidden" name="action" value="patient_book">

                        <div class="space-y-1">
                            <label class="text-xs text-slate-200">Select Doctor</label>
                            <select name="doctor_id" id="booking-doctor" required
                                    class="w-full px-3 py-2 rounded-lg bg-slate-950/70 border border-slate-600 text-xs focus:outline-none focus:ring-1 focus:ring-sky-500">
                                <option value="">-- Choose a doctor --</option>
                                <?php foreach ($availableDoctors as $doc): ?>
                                    <option value="<?php echo (int)$doc['id']; ?>">
                                        <?php echo htmlspecialchars($doc['name'] . ' — ' . $doc['spec']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p id="booking-doctor-label" class="text-[11px] text-sky-300 mt-1 hidden"></p>
                        </div>

                        <div class="flex gap-2">
                            <div class="flex-1 space-y-1">
                                <label class="text-xs text-slate-200">Date</label>
                                <input type="date" name="appointment_date" required
                                       class="w-full px-3 py-2 rounded-lg bg-slate-950/70 border border-slate-600 text-xs focus:outline-none focus:ring-1 focus:ring-sky-500">
                            </div>
                            <div class="flex-1 space-y-1">
                                <label class="text-xs text-slate-200">Time</label>
                                <input type="time" name="appointment_time" required
                                       class="w-full px-3 py-2 rounded-lg bg-slate-950/70 border border-slate-600 text-xs focus:outline-none focus:ring-1 focus:ring-sky-500">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs text-slate-200">Short Note (optional)</label>
                            <textarea name="note" rows="2"
                                      class="w-full px-3 py-2 rounded-lg bg-slate-950/70 border border-slate-600 text-xs focus:outline-none focus:ring-1 focus:ring-sky-500"
                                      placeholder="Write your main problem / question for doctor..."></textarea>
                        </div>

                        <button type="submit"
                                class="w-full py-2.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-sm font-semibold shadow-md">
                            Request Appointment
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- DOCTOR: CLINIC INFO BOX (placeholder UI) -->
                <div class="bg-slate-900/70 border border-white/10 rounded-2xl p-5 shadow-lg">
                    <h3 class="text-sm font-semibold mb-1">Clinic Overview</h3>
                    <p class="text-[11px] text-slate-300 mb-2">
                        Use this space for your chamber hours, fees and quick notes. (Backend can be added later.)
                    </p>
                    <ul class="text-[11px] text-slate-300 space-y-1">
                        <li>• (Coming soon) Edit chamber &amp; working hours</li>
                        <li>• (Coming soon) Manage consultation fee</li>
                        <li>• (Coming soon) Upload digital signature</li>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- COMMON: INFO BOX -->
            <div class="bg-slate-900/70 border border-white/10 rounded-2xl p-5 shadow-md">
                <h3 class="text-sm font-semibold mb-2">
                    <?php echo ($role === 'doctor') ? 'Quick Prescription Tips' : 'Before Your Visit'; ?>
                </h3>
                <ul class="text-[11px] text-slate-300 space-y-1">
                    <?php if ($role === 'doctor'): ?>
                        <li>• Use notes to record key complaints &amp; findings.</li>
                        <li>• Write drug, dose &amp; frequency in short form.</li>
                        <li>• Mark visit as <span class="text-emerald-300">Completed</span> after consultation.</li>
                    <?php else: ?>
                        <li>• Add your main complaints in the note section.</li>
                        <li>• Cancel appointment early if you can’t come.</li>
                        <li>• (Coming soon) Download your visit summary.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </aside>
    </div>

    <!-- PATIENT: FIND DOCTOR & LIST SECTION -->
    <?php if ($role === 'patient'): ?>
        <section class="bg-slate-900/70 border border-white/10 rounded-2xl p-6 shadow-xl mb-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-3 mb-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Doctor Directory</p>
                    <h2 class="text-xl font-semibold text-white mt-1">Find Doctors &amp; Book</h2>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Search by doctor name or specialization, then click “Book with this doctor” to pre-fill the form above.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="text"
                           id="doctor-search"
                           placeholder="Search name / specialty..."
                           class="px-3 py-2 rounded-lg bg-slate-950/70 border border-slate-600 text-xs focus:outline-none focus:ring-1 focus:ring-sky-500 w-56">
                </div>
            </div>

            <?php if (!empty($availableDoctors)): ?>
                <div id="doctor-list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($availableDoctors as $doc): ?>
                        <div class="doctor-card bg-slate-950/70 border border-slate-700 rounded-2xl p-4 text-sm shadow-md"
                             data-name="<?php echo htmlspecialchars(strtolower($doc['name'])); ?>"
                             data-spec="<?php echo htmlspecialchars(strtolower($doc['spec'])); ?>">
                            <p class="text-xs text-sky-300 font-semibold uppercase tracking-wide mb-1">
                                <?php echo htmlspecialchars($doc['spec']); ?>
                            </p>
                            <h3 class="font-semibold text-white">
                                <?php echo htmlspecialchars($doc['name']); ?>
                            </h3>
                            <p class="text-[11px] text-slate-300 mt-1">
                                Chamber: <?php echo htmlspecialchars($doc['chamber']); ?>
                            </p>
                            <p class="text-[11px] text-slate-300">
                                Fee:
                                <span class="text-sky-300 font-semibold">
                                <?php echo $doc['fee'] !== null ? number_format((float)$doc['fee'], 2) . ' Tk' : 'N/A'; ?>
                                </span>
                            </p>
                            <?php if ($doc['from'] && $doc['to']): ?>
                                <p class="text-[11px] text-slate-400">
                                    Time: <?php echo htmlspecialchars(substr($doc['from'], 0, 5)); ?> - <?php echo htmlspecialchars(substr($doc['to'], 0, 5)); ?>
                                </p>
                            <?php endif; ?>
                            <div class="mt-3 flex justify-between items-center">
                                <button type="button"
                                        class="px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-xs font-semibold"
                                        onclick="selectDoctorForBooking(<?php echo (int)$doc['id']; ?>)">
                                    Book with this doctor
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-400">
                    No doctors found. Please ask admin / reception to add doctor profiles.
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?php if ($role === 'patient'): ?>
<script>
// Simple client-side filter for doctor cards
const searchInput = document.getElementById('doctor-search');
const cards = document.querySelectorAll('.doctor-card');

if (searchInput && cards.length) {
    searchInput.addEventListener('input', () => {
        const q = searchInput.value.trim().toLowerCase();
        cards.forEach(card => {
            const name = card.dataset.name || '';
            const spec = card.dataset.spec || '';
            if (!q || name.includes(q) || spec.includes(q)) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    });
}

// When user clicks "Book with this doctor", pre-fill the booking form
function selectDoctorForBooking(docId) {
    const select = document.getElementById('booking-doctor');
    const label  = document.getElementById('booking-doctor-label');
    if (!select) return;
    select.value = String(docId);
    if (label) {
        const optionText = select.options[select.selectedIndex]?.text || '';
        label.textContent = optionText ? 'Selected doctor: ' + optionText : '';
        label.classList.remove('hidden');
    }
    const bookingSection = document.getElementById('booking-section');
    if (bookingSection) {
        bookingSection.scrollIntoView({ behavior: 'smooth' });
    }
}
</script>
<?php endif; ?>
</body>
</html>
