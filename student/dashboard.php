<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'alumni']);

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT a.*, s.slot_date, s.slot_time
    FROM appointments a
    JOIN appointment_slots s ON a.slot_id = s.slot_id
    WHERE a.user_id = ? AND a.status IN ('pending', 'confirmed')
    ORDER BY s.slot_date ASC, s.slot_time ASC
    LIMIT 1
");
$stmt->execute([$userId]);
$nextAppointment = $stmt->fetch();

$countStmt = $pdo->prepare("SELECT status, COUNT(*) as total FROM appointments WHERE user_id = ? GROUP BY status");
$countStmt->execute([$userId]);
$counts = ['pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'collected' => 0, 'missed' => 0];
foreach ($countStmt->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['total'];
}

$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></h3>

<?php if ($nextAppointment): ?>
    <div class="alert alert-success">
        <strong>Upcoming appointment:</strong>
        <?= date('jS F Y', strtotime($nextAppointment['slot_date'])) ?>
        at <?= date('h:i A', strtotime($nextAppointment['slot_time'])) ?>
        &mdash; Ref: <?= htmlspecialchars($nextAppointment['booking_reference']) ?>
        (Status: <?= htmlspecialchars(ucfirst($nextAppointment['status'])) ?>)
    </div>
<?php else: ?>
    <div class="alert alert-info">
        You have no upcoming appointment. <a href="<?= BASE_URL ?>/student/book_appointment.php">Book one now</a>.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <h4><?= $counts['pending'] ?></h4>
            <small class="text-muted">Pending</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <h4><?= $counts['confirmed'] ?></h4>
            <small class="text-muted">Confirmed</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <h4><?= $counts['collected'] ?></h4>
            <small class="text-muted">Collected</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <h4><?= $counts['cancelled'] ?></h4>
            <small class="text-muted">Cancelled</small>
        </div>
    </div>
</div>

<a href="<?= BASE_URL ?>/student/book_appointment.php" class="btn btn-success">Book New Appointment</a>
<a href="<?= BASE_URL ?>/student/my_appointments.php" class="btn btn-outline-success">View All My Appointments</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
