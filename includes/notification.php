<?php
/**
 * Notification service.
 * - SMS sent via raw cURL to Africa's Talking REST API (no SDK/Composer
 *   dependency needed for this part).
 * - Email sent via PHPMailer, which DOES need Composer:
 *       composer require phpmailer/phpmailer
 *   If vendor/autoload.php is missing, or NOTIFY_DRY_RUN is true in
 *   config.php, nothing is actually sent - it's just logged into the
 *   notifications table with status 'pending', so the rest of the
 *   system (and your demo) still works.
 */

function send_sms($phone, $message) {
    if (NOTIFY_DRY_RUN) {
        return ['success' => false, 'info' => 'dry_run'];
    }

    // Africa's Talking expects phone numbers in international format, e.g. 2547XXXXXXXX
    $phone = preg_replace('/^0/', '254', $phone);

    $url = (AT_USERNAME === 'sandbox')
        ? 'https://api.sandbox.africastalking.com/version1/messaging'
        : 'https://api.africastalking.com/version1/messaging';

    $postFields = [
        'username' => AT_USERNAME,
        'to'       => '+' . $phone,
        'message'  => $message,
    ];
    if (!empty(AT_SENDER_ID)) {
        $postFields['from'] = AT_SENDER_ID;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postFields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'apiKey: ' . AT_API_KEY,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['success' => ($httpCode === 200 || $httpCode === 201), 'info' => $response];
}

function send_email_notification($toEmail, $toName, $subject, $bodyHtml) {
    if (NOTIFY_DRY_RUN) {
        return ['success' => false, 'info' => 'dry_run'];
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        return ['success' => false, 'info' => 'PHPMailer not installed (run: composer require phpmailer/phpmailer)'];
    }
    require_once $autoload;

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;

        $mail->send();
        return ['success' => true, 'info' => 'sent'];
    } catch (Exception $e) {
        return ['success' => false, 'info' => $mail->ErrorInfo];
    }
}

/**
 * Composes and sends both SMS + email for a given appointment event,
 * and logs every attempt into the notifications table regardless of
 * whether NOTIFY_DRY_RUN is on.
 *
 * $event one of: 'booked', 'confirmed', 'cancelled', 'reminder', 'collected'
 */
function notify_appointment($pdo, $appointment_id, $event) {
    $stmt = $pdo->prepare("
        SELECT a.appointment_id, a.booking_reference, a.status,
               s.slot_date, s.slot_time,
               u.full_name, u.email, u.phone
        FROM appointments a
        JOIN appointment_slots s ON a.slot_id = s.slot_id
        JOIN users u ON a.user_id = u.user_id
        WHERE a.appointment_id = ?
    ");
    $stmt->execute([$appointment_id]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }

    $dateStr = date('jS F Y', strtotime($row['slot_date']));
    $timeStr = date('h:i A', strtotime($row['slot_time']));

    $messages = [
        'booked' => "CUK OABS: Your appointment {$row['booking_reference']} for transcript collection on {$dateStr} at {$timeStr} has been received and is pending confirmation.",
        'confirmed' => "CUK OABS: Your appointment {$row['booking_reference']} on {$dateStr} at {$timeStr} is CONFIRMED. Please bring your ID to the Records Office.",
        'cancelled' => "CUK OABS: Your appointment {$row['booking_reference']} scheduled for {$dateStr} at {$timeStr} has been cancelled.",
        'reminder' => "CUK OABS Reminder: You have a transcript collection appointment ({$row['booking_reference']}) tomorrow, {$dateStr} at {$timeStr}.",
        'collected' => "CUK OABS: Transcript collection for appointment {$row['booking_reference']} has been marked as COMPLETE. Thank you.",
    ];
    $message = $messages[$event] ?? "CUK OABS: Update on your appointment {$row['booking_reference']}.";

    // SMS
    $smsResult = send_sms($row['phone'], $message);
    $stmt = $pdo->prepare(
        "INSERT INTO notifications (appointment_id, type, recipient, message, status, sent_at) VALUES (?, 'sms', ?, ?, ?, ?)"
    );
    $stmt->execute([
        $appointment_id,
        $row['phone'],
        $message,
        $smsResult['success'] ? 'sent' : 'pending',
        $smsResult['success'] ? date('Y-m-d H:i:s') : null,
    ]);

    // Email
    $subject = 'CUK Transcript Collection Appointment - ' . ucfirst($event);
    $bodyHtml = "<p>Dear {$row['full_name']},</p><p>{$message}</p><p>Regards,<br>CUK Transcripts and Records Office</p>";
    $emailResult = send_email_notification($row['email'], $row['full_name'], $subject, $bodyHtml);
    $stmt = $pdo->prepare(
        "INSERT INTO notifications (appointment_id, type, recipient, message, status, sent_at) VALUES (?, 'email', ?, ?, ?, ?)"
    );
    $stmt->execute([
        $appointment_id,
        $row['email'],
        $message,
        $emailResult['success'] ? 'sent' : 'pending',
        $emailResult['success'] ? date('Y-m-d H:i:s') : null,
    ]);
}
