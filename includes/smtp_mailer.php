<?php
/**
 * Minimal dependency-free SMTP client (STARTTLS + AUTH LOGIN).
 * Returns ['success' => bool, 'info' => string] just like the old PHPMailer-based function did, so notification.php doesn't need to change.
 */
function smtp_send_mail($toEmail, $toName, $subject, $bodyHtml) {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $timeout = 15;

    $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        return ['success' => false, 'info' => "Connection failed: {$errstr} ({$errno})"];
    }
    stream_set_timeout($socket, $timeout);

    $read = function () use ($socket) {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            // Stop once we hit a line where the 4th char is a space (end of multi-line reply)
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $write = function ($command) use ($socket) {
        fwrite($socket, $command . "\r\n");
    };

    $expectCode = function ($response, $expected) {
        return isset($response[0]) && substr($response, 0, 3) === (string) $expected;
    };

    try {
        $response = $read();
        if (!$expectCode($response, 220)) {
            throw new Exception("Server greeting failed: {$response}");
        }

        $write('EHLO ' . (gethostname() ?: 'localhost'));
        $response = $read();
        if (!$expectCode($response, 250)) {
            throw new Exception("EHLO failed: {$response}");
        }

        $write('STARTTLS');
        $response = $read();
        if (!$expectCode($response, 220)) {
            throw new Exception("STARTTLS not accepted: {$response}");
        }

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception('TLS handshake failed');
        }

        // Re-EHLO after TLS as required by spec
        $write('EHLO ' . (gethostname() ?: 'localhost'));
        $response = $read();
        if (!$expectCode($response, 250)) {
            throw new Exception("EHLO after TLS failed: {$response}");
        }

        $write('AUTH LOGIN');
        $response = $read();
        if (!$expectCode($response, 334)) {
            throw new Exception("AUTH LOGIN not accepted: {$response}");
        }

        $write(base64_encode(SMTP_USERNAME));
        $response = $read();
        if (!$expectCode($response, 334)) {
            throw new Exception("Username rejected: {$response}");
        }

        $write(base64_encode(SMTP_PASSWORD));
        $response = $read();
        if (!$expectCode($response, 235)) {
            throw new Exception("Authentication failed: {$response}");
        }

        $write('MAIL FROM:<' . SMTP_FROM_EMAIL . '>');
        $response = $read();
        if (!$expectCode($response, 250)) {
            throw new Exception("MAIL FROM rejected: {$response}");
        }

        $write('RCPT TO:<' . $toEmail . '>');
        $response = $read();
        if (!$expectCode($response, 250) && !$expectCode($response, 251)) {
            throw new Exception("RCPT TO rejected: {$response}");
        }

        $write('DATA');
        $response = $read();
        if (!$expectCode($response, 354)) {
            throw new Exception("DATA not accepted: {$response}");
        }

        $boundaryDate = date('r');
        $headers = [
            'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
            'To: ' . $toName . ' <' . $toEmail . '>',
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Date: ' . $boundaryDate,
        ];

        // Dot-stuff any line starting with a lone '.' per SMTP spec
        $body = preg_replace('/^\./m', '..', $bodyHtml);

        $write(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.");
        $response = $read();
        if (!$expectCode($response, 250)) {
            throw new Exception("Message not accepted: {$response}");
        }

        $write('QUIT');
        fclose($socket);

        return ['success' => true, 'info' => 'sent'];
    } catch (Exception $e) {
        fclose($socket);
        return ['success' => false, 'info' => $e->getMessage()];
    }
}