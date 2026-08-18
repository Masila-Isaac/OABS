<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['staff', 'admin']);

$userId = $_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_slot'])) {
        $slot_date = $_POST['slot_date'] ?? '';
        $slot_time = $_POST['slot_time'] ?? '';
        $capacity = (int) ($_POST['capacity'] ?? 1);

        if ($slot_date === '' || $slot_time === '' || $capacity < 1) {
            $errors[] = 'Please fill in date, time, and a valid capacity.';
        } elseif (strtotime($slot_date) < strtotime(date('Y-m-d'))) {
            $errors[] = 'Slot date cannot be in the past.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO appointment_slots (slot_date, slot_time, capacity, created_by) VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([$slot_date, $slot_time, $capacity, $userId]);
                log_action($pdo, $userId, 'create_slot', "Created slot {$slot_date} {$slot_time}");
                flash('success', 'Slot created successfully.');
            } catch (PDOException $e) {
                $errors[] = 'A slot already exists for that exact date and time.';
            }
        }
    } elseif (isset($_POST['close_slot_id'])) {
        $slotId = (int) $_POST['close_slot_id'];
        $pdo->prepare("UPDATE appointment_slots SET status = 'closed' WHERE slot_id = ?")->execute([$slotId]);
        log_action($pdo, $userId, 'close_slot', "Closed slot ID {$slotId}");
        flash('success', 'Slot closed.');
    }

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
    }
    header('Location: ' . BASE_URL . '/admin/manage_slots.php');
    exit;
}

$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']);

$slots = $pdo->query("
    SELECT * FROM appointment_slots
    WHERE slot_date >= CURDATE()
    ORDER BY slot_date ASC, slot_time ASC
")->fetchAll();

$pageTitle = 'Manage Slots';
include __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4">Manage Appointment Slots</h3>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card p-4 mb-4">
    <h5 class="mb-3">Create New Slot</h5>
    <form method="POST" action="" class="row g-3">
        <input type="hidden" name="create_slot" value="1">
        <div class="col-md-4">
            <label class="form-label">Date</label>
            <input type="date" name="slot_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Time</label>
            <input type="time" name="slot_time" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Capacity (students)</label>
            <input type="number" name="capacity" class="form-control" value="5" min="1" required>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-success w-100">Add</button>
        </div>
    </form>
</div>

<h5>Upcoming Slots</h5>
<?php if (empty($slots)): ?>
    <div class="alert alert-info">No upcoming slots. Create one above.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle">
            <thead class="table-light">
                <tr><th>Date</th><th>Time</th><th>Capacity</th><th>Booked</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($slots as $slot): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($slot['slot_date'])) ?></td>
                        <td><?= date('h:i A', strtotime($slot['slot_time'])) ?></td>
                        <td><?= $slot['capacity'] ?></td>
                        <td><?= $slot['booked_count'] ?></td>
                        <td><span class="badge bg-secondary"><?= ucfirst($slot['status']) ?></span></td>
                        <td>
                            <?php if ($slot['status'] !== 'closed'): ?>
                                <form method="POST" action="" onsubmit="return confirm('Close this slot?');">
                                    <input type="hidden" name="close_slot_id" value="<?= $slot['slot_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Close</button>
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
