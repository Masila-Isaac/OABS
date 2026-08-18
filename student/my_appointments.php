<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notification.php';
require_role(['student', 'alumni']);

$userId = $_SESSION['user_id'];

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment_id'])) {
    $appointmentId = (int) $_POST['cancel_appointment_id'];

    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ? AND user_id = ?");
    $stmt->execute([$appointmentId, $userId]);
    $appt = $stmt->fetch();

    if ($appt && in_array($appt['status'], ['pending', 'confirmed'], true)) {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?")->execute([$appointmentId]);
        // Free up the slot
        $pdo->prepare("UPDATE appointment_slots SET booked_count = booked_count - 1, status = 'available' WHERE slot_id = ?")
            ->execute([$appt['slot_id']]);
        log_action($pdo, $userId, 'cancel_appointment', "Cancelled appointment {$appt['booking_reference']}");
        $pdo->commit();

        notify_appointment($pdo, $appointmentId, 'cancelled');
        flash('success', 'Appointment cancelled.');
    } else {
        flash('error', 'That appointment cannot be cancelled.');
    }
    header('Location: ' . BASE_URL . '/student/my_appointments.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT a.*, s.slot_date, s.slot_time
    FROM appointments a
    JOIN appointment_slots s ON a.slot_id = s.slot_id
    WHERE a.user_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$userId]);
$appointments = $stmt->fetchAll();

$pageTitle = 'My Appointments';
include __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4">My Appointments</h3>

<?php if (empty($appointments)): ?>
    <div class="alert alert-info">You have no appointments yet. <a href="<?= BASE_URL ?>/student/book_appointment.php">Book one now</a>.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle bg-white">
            <thead class="table-light">
                <tr>
                    <th>Reference</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['booking_reference']) ?></td>
                        <td><?= date('d M Y', strtotime($a['slot_date'])) ?></td>
                        <td><?= date('h:i A', strtotime($a['slot_time'])) ?></td>
                        <td><?= htmlspecialchars($a['purpose'] ?: '-') ?></td>
                        <td><span class="badge badge-status-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                        <td>
                            <?php if (in_array($a['status'], ['pending', 'confirmed'], true)): ?>
                                <form method="POST" action="" onsubmit="return confirm('Cancel this appointment?');">
                                    <input type="hidden" name="cancel_appointment_id" value="<?= $a['appointment_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                </form>
                            <?php else: ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
