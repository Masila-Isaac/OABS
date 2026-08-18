<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notification.php';
require_role(['staff', 'admin']);

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$appointmentId]);
    $appt = $stmt->fetch();

    if ($appt) {
        if ($action === 'confirm' && $appt['status'] === 'pending') {
            $pdo->prepare("UPDATE appointments SET status = 'confirmed' WHERE appointment_id = ?")->execute([$appointmentId]);
            log_action($pdo, $userId, 'confirm_appointment', "Confirmed {$appt['booking_reference']}");
            notify_appointment($pdo, $appointmentId, 'confirmed');
            flash('success', 'Appointment confirmed and student notified.');
        } elseif ($action === 'collected' && $appt['status'] === 'confirmed') {
            $pdo->prepare("UPDATE appointments SET status = 'collected' WHERE appointment_id = ?")->execute([$appointmentId]);
            log_action($pdo, $userId, 'mark_collected', "Marked {$appt['booking_reference']} as collected");
            notify_appointment($pdo, $appointmentId, 'collected');
            flash('success', 'Marked as collected.');
        } elseif ($action === 'cancel' && in_array($appt['status'], ['pending', 'confirmed'], true)) {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?")->execute([$appointmentId]);
            $pdo->prepare("UPDATE appointment_slots SET booked_count = booked_count - 1, status = 'available' WHERE slot_id = ?")
                ->execute([$appt['slot_id']]);
            log_action($pdo, $userId, 'cancel_appointment', "Staff cancelled {$appt['booking_reference']}");
            $pdo->commit();
            notify_appointment($pdo, $appointmentId, 'cancelled');
            flash('success', 'Appointment cancelled.');
        } elseif ($action === 'missed' && $appt['status'] === 'confirmed') {
            $pdo->prepare("UPDATE appointments SET status = 'missed' WHERE appointment_id = ?")->execute([$appointmentId]);
            log_action($pdo, $userId, 'mark_missed', "Marked {$appt['booking_reference']} as missed");
            flash('success', 'Marked as missed.');
        }
    }
    header('Location: ' . BASE_URL . '/admin/manage_bookings.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

$statusFilter = $_GET['status'] ?? 'all';
$sql = "
    SELECT a.*, s.slot_date, s.slot_time, u.full_name, u.phone, u.email, u.registration_number
    FROM appointments a
    JOIN appointment_slots s ON a.slot_id = s.slot_id
    JOIN users u ON a.user_id = u.user_id
";
$params = [];
if ($statusFilter !== 'all') {
    $sql .= " WHERE a.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY s.slot_date DESC, s.slot_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$pageTitle = 'Manage Bookings';
include __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4">All Bookings</h3>

<div class="mb-3">
    <?php foreach (['all', 'pending', 'confirmed', 'collected', 'cancelled', 'missed'] as $s): ?>
        <a href="?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-success' : 'btn-outline-success' ?> me-1">
            <?= ucfirst($s) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($bookings)): ?>
    <div class="alert alert-info">No bookings found for this filter.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle">
            <thead class="table-light">
                <tr>
                    <th>Reference</th><th>Name</th><th>Reg. No.</th><th>Date</th><th>Time</th>
                    <th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['booking_reference']) ?></td>
                        <td>
                            <?= htmlspecialchars($b['full_name']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($b['phone']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($b['registration_number'] ?: 'Alumni') ?></td>
                        <td><?= date('d M Y', strtotime($b['slot_date'])) ?></td>
                        <td><?= date('h:i A', strtotime($b['slot_time'])) ?></td>
                        <td><span class="badge badge-status-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                        <td class="d-flex gap-1 flex-wrap">
                            <?php if ($b['status'] === 'pending'): ?>
                                <form method="POST"><input type="hidden" name="appointment_id" value="<?= $b['appointment_id'] ?>">
                                    <input type="hidden" name="action" value="confirm">
                                    <button class="btn btn-sm btn-success">Confirm</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($b['status'] === 'confirmed'): ?>
                                <form method="POST"><input type="hidden" name="appointment_id" value="<?= $b['appointment_id'] ?>">
                                    <input type="hidden" name="action" value="collected">
                                    <button class="btn btn-sm btn-primary">Mark Collected</button>
                                </form>
                                <form method="POST"><input type="hidden" name="appointment_id" value="<?= $b['appointment_id'] ?>">
                                    <input type="hidden" name="action" value="missed">
                                    <button class="btn btn-sm btn-secondary">Mark Missed</button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($b['status'], ['pending', 'confirmed'], true)): ?>
                                <form method="POST" onsubmit="return confirm('Cancel this booking?');">
                                    <input type="hidden" name="appointment_id" value="<?= $b['appointment_id'] ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                </form>
                            <?php endif; ?>
                            <?php if (!in_array($b['status'], ['pending', 'confirmed'], true)): ?>&mdash;<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
