<?php
declare(strict_types=1);

/**
 * SMTP mailer with a graceful no-op mode. Registration never fails because
 * email is unavailable — every send is logged instead.
 */
function mail_configured(): bool
{
    return (bool) config('mail.enabled', false)
        && (string) config('mail.host', '') !== ''
        && (string) config('mail.username', '') !== '';
}

function send_mail(string $to, string $subject, string $html, string $text = ''): bool
{
    if (!valid_email($to)) {
        return false;
    }
    if (!mail_configured()) {
        log_mail($to, $subject, 'skipped (mail not configured)');
        return false;
    }

    $fromEmail = (string) config('mail.from_email', 'no-reply@sbosun.org');
    $fromName = (string) config('mail.from_name', 'SB Osun');
    $subject = trim(str_replace(["\r", "\n"], ' ', $subject));
    $boundary = 'sb-' . bin2hex(random_bytes(8));

    $headers = [
        'From: ' . mb_encode_mimeheader($fromName) . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    $text = $text !== '' ? $text : trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html)));
    $body = "--{$boundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $text . "\r\n\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $html . "\r\n\r\n"
        . "--{$boundary}--";

    try {
        smtp_send($to, $subject, $body, $headers);
        log_mail($to, $subject, 'sent');
        return true;
    } catch (Throwable $e) {
        log_mail($to, $subject, 'failed: ' . $e->getMessage());
        log_error('Mail send failed: ' . $e->getMessage(), ['to' => $to, 'subject' => $subject]);
        return false;
    }
}

function smtp_send(string $to, string $subject, string $body, array $headers): void
{
    $host = (string) config('mail.host');
    $port = (int) config('mail.port', 465);
    $encryption = strtolower((string) config('mail.encryption', 'ssl'));
    $username = (string) config('mail.username');
    $password = (string) config('mail.password');
    $from = (string) config('mail.from_email');

    $transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 20);
    if (!$socket) {
        throw new RuntimeException("SMTP connection failed: {$errstr} ({$errno})");
    }
    stream_set_timeout($socket, 20);

    $read = function () use ($socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $expect = function (string $code) use ($read): void {
        $response = $read();
        if (!str_starts_with($response, $code)) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }
    };
    $write = function (string $command) use ($socket): void {
        fwrite($socket, $command . "\r\n");
    };

    $expect('220');
    $write('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $read();

    if ($encryption === 'tls') {
        $write('STARTTLS');
        $expect('220');
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Could not start TLS.');
        }
        $write('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $read();
    }

    if ($username !== '') {
        $write('AUTH LOGIN');
        $expect('334');
        $write(base64_encode($username));
        $expect('334');
        $write(base64_encode($password));
        $expect('235');
    }

    $write('MAIL FROM:<' . $from . '>');
    $expect('250');
    $write('RCPT TO:<' . $to . '>');
    $expect('250');
    $write('DATA');
    $expect('354');

    $data = 'To: <' . $to . ">\r\n"
        . 'Subject: ' . mb_encode_mimeheader($subject) . "\r\n"
        . 'Date: ' . date('r') . "\r\n"
        . implode("\r\n", $headers) . "\r\n\r\n"
        . preg_replace('/^\./m', '..', $body) . "\r\n.";
    $write($data);
    $expect('250');
    $write('QUIT');
    fclose($socket);
}

function log_mail(string $to, string $subject, string $result): void
{
    $line = '[' . now() . '] ' . $result . ' | ' . $to . ' | ' . $subject;
    @file_put_contents(__DIR__ . '/../storage/logs/mail.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function email_layout(string $title, string $bodyHtml, string $preheader = ''): string
{
    $name = e((string) setting('name', 'SB HIC 2026'));
    $year = e((string) setting('year', '2026'));
    return '<!doctype html><html><body style="margin:0;padding:0;background:#F7F3E8;font-family:Arial,Helvetica,sans-serif;color:#14170F">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . e($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F3E8;padding:24px 12px">'
        . '<tr><td align="center"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:18px;overflow:hidden">'
        . '<tr><td style="background:#0F3D2E;padding:26px 28px;color:#F7F3E8">'
        . '<div style="display:inline-block;background:#E8B923;color:#0A2A20;width:38px;height:38px;line-height:38px;text-align:center;border-radius:50%;font-weight:900">SB</div>'
        . '<span style="font-weight:800;font-size:18px;margin-left:10px">' . $name . '</span>'
        . '<div style="font-size:12px;color:#C9D6C4;margin-top:8px">Standard Bearers Islamic Organization · Osun State</div>'
        . '</td></tr>'
        . '<tr><td style="padding:30px 28px">'
        . '<h1 style="margin:0 0 14px;font-size:24px;color:#0F3D2E">' . e($title) . '</h1>'
        . $bodyHtml
        . '</td></tr>'
        . '<tr><td style="padding:18px 28px;background:#0A2A20;color:#C9D6C4;font-size:12px">'
        . $name . ' · ' . camp_dates() . ' · ' . e((string) setting('venue_name')) . '<br>'
        . 'Need help? <a style="color:#E8B923" href="mailto:' . e((string) setting('support_email')) . '">' . e((string) setting('support_email')) . '</a>'
        . '</td></tr></table></td></tr></table></body></html>';
}

function send_registration_confirmation(array $participant): void
{
    if (!mail_configured()) {
        return;
    }
    $fullName = trim($participant['other_name'] . ' ' . $participant['surname']);
    $house = $participant['house_id']
        ? (db_one('SELECT name FROM houses WHERE id = ?', [$participant['house_id']])['name'] ?? 'Pending')
        : 'Pending';
    $class = $participant['class_id']
        ? (db_one('SELECT name FROM classes WHERE id = ?', [$participant['class_id']])['name'] ?? '—')
        : '—';

    $body = '<p style="font-size:15px;line-height:1.6">Assalamu Alaykum <strong>' . e($fullName) . '</strong>,</p>'
        . '<p style="font-size:15px;line-height:1.6">Your payment has been confirmed and your place at camp is secured.</p>'
        . '<table cellpadding="8" cellspacing="0" style="width:100%;font-size:14px;background:#F7F3E8;border-radius:12px;margin:18px 0">'
        . '<tr><td>Registration ID</td><td style="text-align:right;font-weight:700">' . e($participant['registration_id']) . '</td></tr>'
        . '<tr><td>Class</td><td style="text-align:right;font-weight:700">' . e($class) . '</td></tr>'
        . '<tr><td>House</td><td style="text-align:right;font-weight:700">' . e($house) . '</td></tr>'
        . '<tr><td>Dates</td><td style="text-align:right;font-weight:700">' . e(camp_dates()) . '</td></tr>'
        . '<tr><td>Venue</td><td style="text-align:right;font-weight:700">' . e((string) setting('venue_name')) . ', ' . e((string) setting('venue_address')) . '</td></tr>'
        . '</table>'
        . '<p style="font-size:14px;line-height:1.6">Keep your registration ID safe — you will need it at check-in. '
        . 'You can view your registration and ID card anytime:</p>'
        . '<p><a href="' . e(absolute_url('portal/' . $participant['token'])) . '" style="background:#E8B923;color:#0A2A20;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:700;display:inline-block">View my registration</a></p>';

    send_mail(
        (string) $participant['email'],
        'Registration confirmed — ' . setting('name', 'SB HIC 2026'),
        email_layout('You are registered for camp', $body, 'Your payment is confirmed. Welcome to SB HIC 2026.'),
        "Assalamu Alaykum {$fullName},\n\nYour payment is confirmed. Registration ID: {$participant['registration_id']}.\n"
    );
}

function send_newsletter_welcome(string $email, string $token): void
{
    if (!mail_configured()) {
        return;
    }
    $unsub = absolute_url('newsletter/unsubscribe/' . $token);
    $body = '<p style="font-size:15px;line-height:1.6">Thank you for subscribing to camp updates from Standard Bearers Islamic Organization, Osun State.</p>'
        . '<p style="font-size:15px;line-height:1.6">We will send registration reminders, camp information and important updates for <strong>' . e((string) setting('name')) . '</strong>.</p>'
        . '<p style="font-size:12px;color:#4B5347">You can unsubscribe at any time: <a href="' . e($unsub) . '">' . e($unsub) . '</a></p>';
    send_mail($email, 'Welcome to SB Osun camp updates', email_layout('You are on the list', $body, 'Camp updates from SB Osun.'), 'Thank you for subscribing. Unsubscribe: ' . $unsub);
}

function send_broadcast(array $subscriber, string $subject, string $title, string $message): bool
{
    $unsub = absolute_url('newsletter/unsubscribe/' . $subscriber['token']);
    $body = nl2p($message)
        . '<p style="margin-top:24px;font-size:12px;color:#4B5347">You are receiving this because you subscribed to SB Osun updates. '
        . '<a href="' . e($unsub) . '">Unsubscribe</a>.</p>';
    return send_mail(
        (string) $subscriber['email'],
        $subject,
        email_layout($title, $body, mb_substr(strip_tags($message), 0, 120)),
        strip_tags($message) . "\n\nUnsubscribe: " . $unsub
    );
}
