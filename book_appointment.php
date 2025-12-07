<?php
// book_appointment.php – Patient: book an appointment with a doctor
session_start();
require_once 'db.php';

// Only patients can access
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit;
}

$patientName = $_SESSION['fullname'] ?? 'Patient';
$userId      = $_SESSION['user_id'] ?? null;

if ($userId === null) {
    header('Location: login.php');
    exit;
}

// Get patient_id from patients table
$patientId = null;
$stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $patientId = (int)$row['id'];
}
$stmt->close();

if ($patientId === null) {
    die("Patient profile not found. Please make sure you signed up as a patient.");
}

// Load doctor info
$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$doctor   = null;

if ($doctorId > 0) {
    $stmt = $conn->prepare("
        SELECT 
            d.id AS doctor_id,
            u.fullname,
            d.specialization,
            d.chamber,
            d.consultation_fee,
            d.available_from,
            d.available_to
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        WHERE d.id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $doctorId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $doctor = $res->fetch_assoc();
    }
    $stmt->close();
}

if (!$doctor) {
    die("Doctor not found.");
}

$flashMessage = '';
$flashType    = 'info';

// Handle booking POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date  = trim($_POST['date'] ?? '');
    $time  = trim($_POST['time'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($date === '' || $time === '') {
        $flashMessage = 'Please select a date and time.';
        $flashType    = 'error';
    } else {
        $dateTime = $date . ' ' . $time . ':00';

        // Basic "in the past" check
        if (strtotime($dateTime) < time() - 60) {
            $flashMessage = 'You cannot book an appointment in the past.';
            $flashType    = 'error';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO appointments (doctor_id, patient_id, appointment_date, status, notes)
                VALUES (?, ?, ?, 'pending', ?)
            ");
            $stmt->bind_param(
                'iiss',
                $doctorId,
                $patientId,
                $dateTime,
                $notes
            );

            if ($stmt->execute()) {
                $flashMessage = 'Appointment request sent successfully. Reception will review and hold your slot.';
                $flashType    = 'success';
            } else {
                $flashMessage = 'Failed to create appointment. Please try again.';
                $flashType    = 'error';
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
    <title>Book Appointment</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-900 text-white">
<div class="max-w-xl mx-auto py-8 px-4">
    <header class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Book Appointment</h1>
            <p class="text-sm text-slate-300">
                Patient: <?php echo htmlspecialchars($patientName); ?>
            </p>
        </div>
        <a href="doctor_search.php"
           class="text-sm text-blue-300 hover:text-blue-200 underline">
            ⟵ Back to Search
        </a>
    </header>

    <!-- Doctor info card -->
    <div class="mb-5 bg-slate-800/80 border border-slate-700 rounded-xl p-4 flex gap-3">
        <div class="w-12 h-12 rounded-full bg-slate-700 overflow-hidden">
            <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?php echo urlencode($doctor['fullname']); ?>"
                 class="w-full h-full">
        </div>
        <div class="flex-1">
            <h2 class="text-base font-semibold">
                <?php echo htmlspecialchars($doctor['fullname']); ?>
            </h2>
            <p class="text-xs text-blue-300">
                <?php echo htmlspecialchars($doctor['specialization']); ?>
            </p>
            <?php if (!empty($doctor['chamber'])): ?>
            <p class="text-xs text-slate-400 mt-1">
                Chamber: <?php echo htmlspecialchars($doctor['chamber']); ?>
            </p>
            <?php endif; ?>
            <p class="text-xs text-slate-400 mt-1">
                Fee: <?php echo number_format((float)$doctor['consultation_fee'], 2); ?> Tk
            </p>
            <?php if ($doctor['available_from'] || $doctor['available_to']): ?>
                <p class="text-xs text-slate-400 mt-1">
                    Available:
                    <?php
                        $from = $doctor['available_from'] ? date('h:i A', strtotime($doctor['available_from'])) : 'N/A';
                        $to   = $doctor['available_to'] ? date('h:i A', strtotime($doctor['available_to'])) : 'N/A';
                        echo $from . ' - ' . $to;
                    ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($flashMessage !== ''): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm
            <?php
                echo $flashType === 'success'
                    ? 'bg-emerald-900/40 border border-emerald-500/60 text-emerald-200'
                    : ($flashType === 'error'
                        ? 'bg-red-900/40 border border-red-500/60 text-red-200'
                        : 'bg-slate-800 border border-slate-600 text-slate-200');
            ?>
        ">
            <?php echo htmlspecialchars($flashMessage); ?>
        </div>
    <?php endif; ?>

    <!-- Booking form -->
    <form method="post" class="bg-slate-800/80 border border-slate-700 rounded-xl p-4 space-y-4">
        <div class="flex gap-3">
            <div class="flex-1">
                <label class="block text-xs text-slate-400 mb-1">Date</label>
                <input type="date"
                       name="date"
                       required
                       class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-600 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex-1">
                <label class="block text-xs text-slate-400 mb-1">Time</label>
                <input type="time"
                       name="time"
                       required
                       class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-600 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>
        </div>

        <div>
            <label class="block text-xs text-slate-400 mb-1">
                Reason / Symptoms (optional but recommended)
            </label>
            <textarea name="notes"
                      rows="3"
                      class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-600 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                      placeholder="Describe your problem briefly (e.g. headache for 3 days, fever, etc.)"></textarea>
        </div>

        <p class="text-[11px] text-slate-400">
            After booking, your appointment will appear as <span class="text-amber-300">Pending</span>.
            Reception will review and hold/confirm your slot, and the doctor will see it in their upcoming list.
        </p>

        <button type="submit"
                class="w-full mt-2 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-sm font-semibold">
            Confirm Booking Request
        </button>
    </form>

    <div class="mt-4 text-xs text-slate-400">
        Tip: You can view or cancel your upcoming appointments from your main dashboard.
    </div>
</div>
</body>
</html>
