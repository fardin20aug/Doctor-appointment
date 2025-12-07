<?php
session_start();
require_once 'db.php';

// Only patients can access this page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit;
}

$patientUserId = $_SESSION['user_id'] ?? null;
$patientName   = $_SESSION['fullname'] ?? 'Patient';
$flashMessage  = '';
$flashType     = 'success';

// Get patient_id from patients table (needed for appointments table)
$patientId = null;
if ($patientUserId !== null) {
    if ($stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ? LIMIT 1")) {
        $stmt->bind_param('i', $patientUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $patientId = (int)$row['id'];
        }
        $stmt->close();
    }
}

if ($patientId === null) {
    $flashMessage = 'Your patient profile was not found. Please make sure you signed up as patient correctly.';
    $flashType    = 'error';
}

// ================== HANDLE BOOK APPOINTMENT ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment']) && $patientId !== null) {
    $doctorId = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
    $date     = trim($_POST['appointment_date'] ?? '');
    $time     = trim($_POST['appointment_time'] ?? '');
    $notes    = trim($_POST['notes'] ?? '');

    if ($doctorId > 0 && $date !== '' && $time !== '') {
        // Validate doctor exists
        $validDoctor = false;
        if ($stmt = $conn->prepare("SELECT id FROM doctors WHERE id = ? LIMIT 1")) {
            $stmt->bind_param('i', $doctorId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->fetch_assoc()) {
                $validDoctor = true;
            }
            $stmt->close();
        }

        if ($validDoctor) {
            $dateTime = $date . ' ' . $time . ':00';
            if ($stmt = $conn->prepare("
                INSERT INTO appointments (doctor_id, patient_id, appointment_date, status, notes)
                VALUES (?, ?, ?, 'pending', ?)
            ")) {
                $stmt->bind_param('iiss', $doctorId, $patientId, $dateTime, $notes);
                if ($stmt->execute()) {
                    $flashMessage = 'Appointment request created successfully. Reception will confirm your slot.';
                    $flashType    = 'success';
                } else {
                    $flashMessage = 'Failed to create appointment. Please try again.';
                    $flashType    = 'error';
                }
                $stmt->close();
            } else {
                $flashMessage = 'Could not prepare appointment insert.';
                $flashType    = 'error';
            }
        } else {
            $flashMessage = 'Selected doctor not found.';
            $flashType    = 'error';
        }
    } else {
        $flashMessage = 'Please select doctor, date and time.';
        $flashType    = 'error';
    }
}

// ================== DOCTOR SEARCH ==================
$search = trim($_GET['q'] ?? '');
$doctors = [];

$sql = "
    SELECT 
        d.id               AS doctor_id,
        u.fullname         AS fullname,
        d.specialization   AS specialization,
        d.chamber          AS chamber,
        d.consultation_fee AS consultation_fee,
        d.experience_years AS experience_years
    FROM doctors d
    JOIN users u ON d.user_id = u.id
";

$params = [];
$types  = '';

if ($search !== '') {
    $sql .= " WHERE (u.fullname LIKE ? OR d.specialization LIKE ? OR d.chamber LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
    $types  = 'sss';
}

$sql .= " ORDER BY u.fullname ASC";

if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $doctors[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Find Doctors &amp; Book | MediDesk</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-900 text-white">
<div class="max-w-6xl mx-auto py-8 px-4">
    <!-- HEADER -->
    <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-8">
        <div>
            <h1 class="text-2xl font-bold">
                Find Doctors &amp; Book
            </h1>
            <p class="text-sm text-slate-300 mt-1">
                Logged in as <?php echo htmlspecialchars($patientName); ?> (patient)
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="content.php" class="px-3 py-2 rounded-lg bg-slate-800 text-sm hover:bg-slate-700">
                Back to Dashboard
            </a>
            <a href="logout.php"
               class="text-sm text-red-300 hover:text-red-200"
               onclick="return confirm('Log out?');">
                Logout
            </a>
        </div>
    </header>

    <!-- FLASH MESSAGE -->
    <?php if ($flashMessage !== ''): ?>
        <div class="mb-5 text-sm px-4 py-3 rounded-lg border 
            <?php echo ($flashType === 'success')
                ? 'bg-emerald-900/40 border-emerald-500/50 text-emerald-100'
                : 'bg-red-900/40 border-red-500/50 text-red-100'; ?>">
            <?php echo htmlspecialchars($flashMessage); ?>
        </div>
    <?php endif; ?>

    <!-- SEARCH BAR -->
    <form method="get" action="doctor_search.php" class="mb-6">
        <div class="flex flex-col md:flex-row gap-3 md:items-center">
            <div class="flex-1 relative">
                <input
                    type="text"
                    name="q"
                    placeholder="Search by doctor name, specialization, chamber..."
                    value="<?php echo htmlspecialchars($search); ?>"
                    class="w-full px-4 py-2.5 rounded-lg bg-slate-800 border border-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>
            <div class="flex gap-2">
                <button
                    type="submit"
                    class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-sm font-semibold shadow">
                    Search
                </button>
                <a href="doctor_search.php"
                   class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-sm">
                    Clear
                </a>
            </div>
        </div>
    </form>

    <!-- DOCTORS LIST -->
    <?php if (empty($doctors)): ?>
        <p class="text-sm text-slate-300">
            No doctors found. Try a different search term or contact reception.
        </p>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <?php foreach ($doctors as $doc): ?>
                <div class="bg-slate-800/80 border border-slate-600 rounded-xl p-5 flex flex-col justify-between">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-full bg-slate-700 overflow-hidden">
                            <img
                                src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?php echo urlencode($doc['fullname']); ?>"
                                class="w-full h-full"
                                alt="Doctor avatar">
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold">
                                <?php echo htmlspecialchars($doc['fullname']); ?>
                            </h2>
                            <p class="text-sm text-blue-300 mt-0.5">
                                <?php echo htmlspecialchars($doc['specialization'] ?: 'General Physician'); ?>
                            </p>
                            <?php if (!empty($doc['chamber'])): ?>
                                <p class="text-xs text-slate-300 mt-1">
                                    Chamber: <?php echo htmlspecialchars($doc['chamber']); ?>
                                </p>
                            <?php endif; ?>
                            <p class="text-xs text-slate-400 mt-1">
                                Experience:
                                <?php echo (int)($doc['experience_years'] ?? 0); ?> year(s)
                            </p>
                            <?php if (!empty($doc['consultation_fee'])): ?>
                                <p class="text-xs text-emerald-300 mt-1">
                                    Fee: <?php echo htmlspecialchars($doc['consultation_fee']); ?> Tk
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- BOOK APPOINTMENT FORM -->
                    <form method="post" action="doctor_search.php" class="mt-4 space-y-2">
                        <input type="hidden" name="book_appointment" value="1">
                        <input type="hidden" name="doctor_id" value="<?php echo (int)$doc['doctor_id']; ?>">

                        <div class="flex gap-3">
                            <div class="flex-1">
                                <label class="block text-[11px] text-slate-300 mb-1">Date</label>
                                <input type="date" name="appointment_date" required
                                       class="w-full px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-600 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div class="flex-1">
                                <label class="block text-[11px] text-slate-300 mb-1">Time</label>
                                <input type="time" name="appointment_time" required
                                       class="w-full px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-600 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] text-slate-300 mb-1">Reason / Notes (optional)</label>
                            <textarea name="notes" rows="2"
                                      class="w-full px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-600 text-xs resize-none focus:outline-none focus:ring-1 focus:ring-blue-500"
                                      placeholder="Example: headache, follow-up, test report review..."></textarea>
                        </div>

                        <button
                            type="submit"
                            class="w-full mt-1 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-sm font-semibold">
                            Book Appointment (send to Reception)
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
