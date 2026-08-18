<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, send straight to the right dashboard
if (is_logged_in()) {
    header('Location: ' . BASE_URL . (is_staff_role() ? '/admin/dashboard.php' : '/student/dashboard.php'));
    exit;
}

$pageTitle = 'Home';
include __DIR__ . '/includes/header.php';
?>

<div class="row align-items-center" style="min-height: 60vh;">
    <div class="col-lg-7">
        <h1 class="fw-bold mb-3">Book Your Transcript Collection Appointment Online</h1>
        <p class="lead text-muted">
            No more unscheduled walk-in visits. Reserve a date and time to collect your
            official academic transcript from the CUK Transcripts and Records Office, and
            get an SMS &amp; email confirmation the moment your slot is booked.
        </p>
        <p class="text-muted">
            Note: transcripts are issued as official hard copies in person. OABS only
            digitizes the appointment scheduling step, not the document itself.
        </p>
        <a href="<?= BASE_URL ?>/student/register.php" class="btn btn-success btn-lg me-2">Get Started</a>
        <a href="<?= BASE_URL ?>/student/login.php" class="btn btn-outline-success btn-lg">Login</a>
    </div>
    <div class="col-lg-5">
        <div class="card p-4">
            <h5 class="mb-3">How it works</h5>
            <ol class="mb-0">
                <li class="mb-2">Register with your registration number / alumni details</li>
                <li class="mb-2">Choose an available date &amp; time slot</li>
                <li class="mb-2">Get an instant SMS &amp; email confirmation</li>
                <li>Visit the Records Office at your scheduled time to collect your transcript</li>
            </ol>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
