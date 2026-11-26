<?php
function customerMail(string $email, string $purpose, string $token): bool {
    $base = rtrim(getenv('APP_URL') ?: 'http://127.0.0.1:8000', '/');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('~\Ahttps?://[^\s?#]+\z~i', $base)) {
        error_log('Customer email delivery failed: invalid mail configuration or address.');
        return false;
    }
    $url = $base . ($purpose === 'verify' ? '/verify-email.php?token=' : '/reset-password.php?token=') . rawurlencode($token);
    $subject = $purpose === 'verify' ? 'Verify your SalesPro email' : 'Reset your SalesPro password';
    $body = "Hello,\r\n\r\nOpen this link to " . ($purpose === 'verify' ? 'verify your email' : 'reset your password') . ":\r\n" . $url . "\r\n\r\nThis link expires in one hour. If you did not request it, ignore this email.\r\n";
    $host = getenv('MAILPIT_HOST') ?: '127.0.0.1';
    $port = (int)(getenv('MAILPIT_PORT') ?: 1025);
    $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 3);
    if (!$socket) {
        error_log('Customer email delivery failed: SMTP connection unavailable.');
        return false;
    }
    stream_set_timeout($socket, 3);
    $read = static function ($socket, array $expected): bool {
        do { $line = fgets($socket, 4096); if ($line === false) return false; } while (isset($line[3]) && $line[3] === '-');
        return in_array((int)substr($line, 0, 3), $expected, true);
    };
    $send = static function ($socket, string $command) { return fwrite($socket, $command . "\r\n") !== false; };
    $ok = $read($socket, [220]) && $send($socket, 'EHLO localhost') && $read($socket, [250]) && $send($socket, 'MAIL FROM:<no-reply@salespro.local>') && $read($socket, [250]) && $send($socket, 'RCPT TO:<' . $email . '>') && $read($socket, [250, 251]) && $send($socket, 'DATA') && $read($socket, [354]);
    if ($ok) {
        $message = "From: SalesPro <no-reply@salespro.local>\r\nTo: <$email>\r\nSubject: $subject\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . $body;
        $message = preg_replace('/^\./m', '..', $message);
        $ok = fwrite($socket, $message . "\r\n.\r\n") !== false && $read($socket, [250]);
    }
    if ($ok) $send($socket, 'QUIT');
    fclose($socket);
    if (!$ok) error_log('Customer email delivery failed: SMTP transaction failed.');
    return $ok;
}
