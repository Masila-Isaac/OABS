<?php
// Expects $pageTitle to optionally be set before including this file.
$pageTitle = $pageTitle ?? 'OABS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | CUK OABS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/index.php">CUK OABS</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto">
                <?php if (is_logged_in() && !is_staff_role()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/student/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/student/book_appointment.php">Book Appointment</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/student/my_appointments.php">My Appointments</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/student/logout.php">Logout (<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>)</a></li>
                <?php elseif (is_logged_in() && is_staff_role()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/manage_slots.php">Manage Slots</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/manage_bookings.php">Bookings</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/logout.php">Logout (<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>)</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/student/login.php">Student/Alumni Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/student/register.php">Register</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/login.php">Staff Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <?php
    foreach (['success', 'error', 'info'] as $flashType) {
        $msg = flash($flashType);
        if ($msg) {
            $bsClass = $flashType === 'error' ? 'danger' : $flashType;
            echo '<div class="alert alert-' . $bsClass . ' alert-dismissible fade show" role="alert">'
                . htmlspecialchars($msg)
                . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    }
    ?>
</div>

<main class="container mb-5">
