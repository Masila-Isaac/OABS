<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notification.php';
require_role(['student', 'alumni']);

$userId = $_SESSION['user_id'];
$errors = [];

// Prevent booking a second active appointment at the same time
$activeCheck = $pdo->prepare("SELECT COUNT(*) as c FROM appointments WHERE user_id = ? AND status IN ('pending','confirmed')");
$activeCheck->execute([$userId]);
$hasActive = $activeCheck->fetch()['c'] > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slotId = (int) ($_POST['slot_id'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');

    if ($hasActive) {
        $errors[] = 'You already have an active appointment. Cancel it before booking another.';
    } elseif ($slotId <= 0) {
        $errors[] = 'Please select a valid slot.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Lock the slot row to prevent two students grabbing the last seat at once
            $stmt = $pdo->prepare("SELECT * FROM appointment_slots WHERE slot_id = ? FOR UPDATE");
            $stmt->execute([$slotId]);
            $slot = $stmt->fetch();

            if (!$slot || $slot['status'] !== 'available' || $slot['booked_count'] >= $slot['capacity']) {
                $pdo->rollBack();
                $errors[] = 'Sorry, that slot is no longer available. Please choose another.';
            } else {
                $reference = generate_booking_reference();
                $insert = $pdo->prepare(
                    "INSERT INTO appointments (booking_reference, user_id, slot_id, purpose, status) VALUES (?, ?, ?, ?, 'pending')"
                );
                $insert->execute([$reference, $userId, $slotId, $purpose]);
                $appointmentId = $pdo->lastInsertId();

                $newBookedCount = $slot['booked_count'] + 1;
                $newStatus = ($newBookedCount >= $slot['capacity']) ? 'full' : 'available';
                $update = $pdo->prepare("UPDATE appointment_slots SET booked_count = ?, status = ? WHERE slot_id = ?");
                $update->execute([$newBookedCount, $newStatus, $slotId]);

                log_action($pdo, $userId, 'book_appointment', "Booked appointment {$reference}");
                $pdo->commit();

                notify_appointment($pdo, $appointmentId, 'booked');

                flash('success', "Appointment booked! Reference: {$reference}. You'll receive SMS/email confirmation.");
                header('Location: ' . BASE_URL . '/student/my_appointments.php');
                exit;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong while booking. Please try again.';
        }
    }
}

$slotsStmt = $pdo->query("
    SELECT * FROM appointment_slots
    WHERE status = 'available' AND booked_count < capacity AND slot_date >= CURDATE()
    ORDER BY slot_date ASC, slot_time ASC
");
$slots = $slotsStmt->fetchAll();

$pageTitle = 'Book Appointment';
include __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4">Book a Transcript Collection Appointment</h3>

<?php if ($hasActive): ?>
    <div class="alert alert-warning">
        You already have an active appointment. View it under
        <a href="<?= BASE_URL ?>/student/my_appointments.php">My Appointments</a>.
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (empty($slots)): ?>
    <div class="alert alert-info">No appointment slots are currently available. Please check back later.</div>
<?php elseif (!$hasActive): ?>
    <form method="POST" action="">
        <div class="row g-3 mb-4">
            <?php foreach ($slots as $slot): ?>
                <div class="col-md-4">
                    <div class="card p-3 h-100">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="slot_id"
                                   id="slot<?= $slot['slot_id'] ?>" value="<?= $slot['slot_id'] ?>" required>
                            <label class="form-check-label" for="slot<?= $slot['slot_id'] ?>">
                                <strong><?= date('jS F Y (D)', strtotime($slot['slot_date'])) ?></strong><br>
                                <?= date('h:i A', strtotime($slot['slot_time'])) ?><br>
                                <small class="text-muted"><?= ($slot['capacity'] - $slot['booked_count']) ?> seat(s) left</small>
                            </label>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Reason for collection (optional)</label>
            <input type="text" name="purpose" class="form-control" placeholder="e.g. Job application, postgraduate admission">
        </div>
        <button type="submit" class="btn btn-success">Confirm Booking</button>
    </form>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
