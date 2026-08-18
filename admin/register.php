<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . (is_staff_role() ? '/admin/dashboard.php' : '/student/dashboard.php'));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'staff';
    $schoolCode = trim($_POST['school_code'] ?? '');

    if ($fullName === '' || $email === '' || $phone === '' || $password === '') {
        $errors[] = 'Please fill in all fields.';
    } elseif (!in_array($role, ['staff', 'admin'], true)) {
        $errors[] = 'Invalid role selected.';
    } elseif (!hash_equals(ADMIN_SIGNUP_CODE, $schoolCode)) {
        $errors[] = 'Incorrect school code.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, email, phone, password_hash, role, status)
                 VALUES (?, ?, ?, ?, ?, 'active')"
            );
            $stmt->execute([
                $fullName,
                $email,
                $phone,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
            ]);
            flash('success', 'Account created. You can now log in.');
            header('Location: ' . BASE_URL . '/admin/login.php');
            exit;
        }
    }
}

$pageTitle = 'Staff/Admin Registration';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card p-4">
            <h3 class="mb-4">Records Office Staff/Admin Registration</h3>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required autofocus
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" required
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="staff" <?= (($_POST['role'] ?? '') === 'staff') ? 'selected' : '' ?>>Staff</option>
                        <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                    <label class="form-label">School Code</label>
                    <input type="text" name="school_code" class="form-control" required
                           placeholder="Provided by the Records Office">
                </div>
                <button type="submit" class="btn btn-success w-100">Create Account</button>
            </form>

            <div class="text-center mt-3">
                <a href="<?= BASE_URL ?>/admin/login.php">Already have an account? Log in</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>