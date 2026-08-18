<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'student';
    $registration_number = trim($_POST['registration_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || $email === '' || $phone === '' || $password === '') {
        $errors[] = 'Please fill in all required fields.';
    }
    if (!in_array($role, ['student', 'alumni'], true)) {
        $errors[] = 'Invalid role selected.';
    }
    if ($role === 'student' && $registration_number === '') {
        $errors[] = 'Registration number is required for students.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'An account with this email already exists. Please login instead.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO users (full_name, registration_number, email, phone, password_hash, role)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $full_name,
            $role === 'student' ? $registration_number : null,
            $email,
            $phone,
            $hash,
            $role,
        ]);
        $newUserId = $pdo->lastInsertId();
        log_action($pdo, $newUserId, 'register', "New {$role} account registered");

        flash('success', 'Registration successful! Please log in.');
        header('Location: ' . BASE_URL . '/student/login.php');
        exit;
    }
}

$pageTitle = 'Register';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card p-4">
            <h3 class="mb-4">Student / Alumni Registration</h3>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">I am a:</label>
                    <select name="role" id="role" class="form-select" onchange="toggleRegNumber()">
                        <option value="student" <?= ($_POST['role'] ?? '') === 'student' ? 'selected' : '' ?>>Current Student</option>
                        <option value="alumni" <?= ($_POST['role'] ?? '') === 'alumni' ? 'selected' : '' ?>>Alumni</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                </div>
                <div class="mb-3" id="regNumberField">
                    <label class="form-label">Registration Number</label>
                    <input type="text" name="registration_number" class="form-control" placeholder="e.g. T020/304076/2024"
                           value="<?= htmlspecialchars($_POST['registration_number'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="07XXXXXXXX" required
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-success w-100">Register</button>
            </form>
            <p class="text-center mt-3 mb-0">
                Already have an account? <a href="<?= BASE_URL ?>/student/login.php">Login here</a>
            </p>
        </div>
    </div>
</div>

<script>
function toggleRegNumber() {
    const role = document.getElementById('role').value;
    document.getElementById('regNumberField').style.display = (role === 'student') ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleRegNumber);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
