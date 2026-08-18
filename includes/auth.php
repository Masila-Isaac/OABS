<?php
/**
 * Authentication / authorization helpers.
 * Include this AFTER config/config.php and config/database.php.
 */

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_user_role() {
    return $_SESSION['role'] ?? null;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function require_role($roles) {
    require_login();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array(current_user_role(), $roles, true)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function is_staff_role() {
    return in_array(current_user_role(), ['staff', 'admin'], true);
}

function log_action($pdo, $user_id, $action, $description = null) {
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, description) VALUES (?, ?, ?)"
    );
    $stmt->execute([$user_id, $action, $description]);
}

function generate_booking_reference() {
    return 'OABS-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));
}

function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return;
    }
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}
