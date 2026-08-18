<?php
/**
 * ONE-TIME SETUP SCRIPT.
 * Run this once in your browser (e.g. http://localhost/oabs/database/seed_admin.php)
 * after importing oabs_schema.sql. It creates the first admin account
 * using PHP's password_hash() so the hash is generated correctly on
 * YOUR machine. Delete this file once you've logged in successfully.
 */

require_once __DIR__ . '/../config/database.php';

$full_name = 'Records Office Admin';
$email     = 'admin@cuk.ac.ke';
$phone     = '0700000000';
$password  = 'Admin@123'; // change this after first login via the dashboard

$check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
$check->execute([$email]);

if ($check->fetch()) {
    echo "Admin account already exists for {$email}. Nothing to do.";
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    "INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'admin')"
);
$stmt->execute([$full_name, $email, $phone, $hash]);

echo "Admin account created successfully.<br>";
echo "Email: {$email}<br>";
echo "Password: {$password}<br>";
echo "<strong>Delete this file (seed_admin.php) now, then log in and change your password.</strong>";
