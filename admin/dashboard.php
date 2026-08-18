<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['staff', 'admin']);

$today = date('Y-m-d');

$totalSlotsToday = $pdo->prepare("SELECT COUNT(*) c FROM appointment_slots WHERE slot_date = ?");
$totalSlotsToday->execute([$today]);
$slotsToday = $totalSlotsToday->fetch()['c'];

$pendingStmt = $pdo->query("SELECT COUNT(*) c FROM appointments WHERE status = 'pending'");
$pendingCount = $pendingStmt->fetch()['c'];

$confirmedTodayStmt = $pdo->prepare("
    SELECT COUNT(*) c FROM appointments a
    JOIN appointment_slots s ON a.slot_id = s.slot_id
    WHERE s.slot_date = ? AND a.status = 'confirmed'
");
$confirmedTodayStmt->execute([$today]);
$confirmedToday = $confirmedTodayStmt->fetch()['c'];

$collectedStmt = $pdo->query("SELECT COUNT(*) c FROM appointments WHERE status = 'collected'");
$collectedCount = $collectedStmt->fetch()['c'];

$todaysAppointmentsStmt = $pdo->prepare("
    SELECT a.*, s.slot_date, s.slot_time, u.full_name, u.phone, u.email, u.registration_number
    FROM appointments a
    JOIN appointment_slots s ON a.slot_id = s.slot_id
    JOIN users u ON a.user_id = u.user_id
    WHERE s.slot_date = ? AND a.status IN ('pending', 'confirmed')
    ORDER BY s.slot_time ASC
");
$todaysAppointmentsStmt->execute([$today]);
$todaysAppointments = $todaysAppointmentsStmt->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4">Records Office Dashboard</h3>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <h4><?= $slotsToday ?></h4>
            <small class="text-muted">Slots Today</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <h4><?= $pendingCount ?></h4>
            <small class="text-muted">Pending (All Time)</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <h4><?= $confirmedToday ?></h4>
            <small class="text-muted">Confirmed Today</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <h4><?= $collectedCount ?></h4>
            <small class="text-muted">Total Collected</small>
        </div>
    </div>
</div>

<a href="<?= BASE_URL ?>/admin/manage_slots.php" class="btn btn-success mb-4">Manage Slots</a>
<a href="<?= BASE_URL ?>/admin/manage_bookings.php" class="btn btn-outline-success mb-4">All Bookings</a>

<h5>Today's Schedule (<?= date('jS F Y') ?>)</h5>
<?php if (empty($todaysAppointments)): ?>
    <div class="alert alert-info">No appointments scheduled for today.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle">
            <thead class="table-light">
                <tr>
                    <th>Time</th><th>Student/Alumni</th><th>Reg. No.</th><th>Phone</th><th>Status</th><th>Reference</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todaysAppointments as $a): ?>
                    <tr>
                        <td><?= date('h:i A', strtotime($a['slot_time'])) ?></td>
                        <td><?= htmlspecialchars($a['full_name']) ?></td>
                        <td><?= htmlspecialchars($a['registration_number'] ?: 'Alumni') ?></td>
                        <td><?= htmlspecialchars($a['phone']) ?></td>
                        <td><span class="badge badge-status-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                        <td><?= htmlspecialchars($a['booking_reference']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
