<?php
/**
 * Site-wide configuration. Fill in your real API/SMTP credentials
 * once you have them - the system works in "log only" mode (no
 * real SMS/email sent, just recorded in the notifications table)
 * until you do, which is fine for development and demos.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('SITE_NAME', 'CUK Online Appointment Booking System (OABS)');
define('BASE_URL', '/oabs'); // change if your project folder name/path differs

// ---- Africa's Talking (SMS) ----
// Sign up at https://account.africastalking.com (use the free "sandbox"
// app for testing - it lets you simulate SMS sending without paying).
define('AT_USERNAME', 'sandbox');           // 'sandbox' for testing, your real username in production
define('AT_API_KEY', 'YOUR_AFRICASTALKING_API_KEY_HERE');
define('AT_SENDER_ID', '');                 // optional, leave blank for sandbox

// ---- PHPMailer / SMTP (Email) ----
// Example uses Gmail SMTP. You'll need an "App Password" if using Gmail
// (regular Gmail password won't work with SMTP). See README for steps.
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'youremail@gmail.com');
define('SMTP_PASSWORD', 'your_app_password_here');
define('SMTP_FROM_EMAIL', 'youremail@gmail.com');
define('SMTP_FROM_NAME', 'CUK Records Office');

// If true, no real SMS/email is sent - everything is just logged into
// the notifications table with status 'pending'. Flip to false once
// AT_API_KEY and SMTP credentials above are filled in correctly.
define('NOTIFY_DRY_RUN', true);
