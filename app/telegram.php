<?php
declare(strict_types=1);

/**
 * Telegram admin notifications.
 *
 * Secrets live in .env (TELEGRAM_BOT_TOKEN). Chat IDs may be a comma-separated
 * list so several admins or a group/channel can receive the same alert.
 * Notification failures must never break a registration or payment.
 */
function telegram_configured(): bool
{
    return (bool) config('telegram.enabled', false)
        && trim((string) config('telegram.bot_token', '')) !== ''
        && (array) config('telegram.chat_ids', []) !== [];
}

function telegram_notify_admins(string $text): bool
{
    if (!telegram_configured()) {
        return false;
    }
    $ok = true;
    foreach ((array) config('telegram.chat_ids', []) as $chatId) {
        $chatId = trim((string) $chatId);
        if ($chatId === '') {
            continue;
        }
        $ok = telegram_send_message($chatId, $text) && $ok;
    }
    return $ok;
}

function telegram_send_message(string $chatId, string $text): bool
{
    $token = trim((string) config('telegram.bot_token', ''));
    if ($token === '') {
        return false;
    }
    if (function_exists('mb_substr') && mb_strlen($text) > 3900) {
        $text = mb_substr($text, 0, 3890) . '…';
    }

    $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    $payload = json_encode([
        'chat_id'                  => $chatId,
        'text'                     => $text,
        'disable_web_page_preview' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => max(5, (int) config('telegram.timeout', 10)),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        log_error('Telegram send failed: network', ['chat_id' => $chatId]);
        return false;
    }
    $json = json_decode((string) $raw, true);
    if ($status < 200 || $status >= 300 || !is_array($json) || empty($json['ok'])) {
        log_error('Telegram send failed', ['chat_id' => $chatId, 'status' => $status]);
        return false;
    }
    return true;
}

function telegram_payment_label(array $payment): string
{
    $type = (string) ($payment['payment_type'] ?? 'FULL');
    if ($type === 'INSTALLMENT') {
        return 'Instalment ' . (int) ($payment['installment_number'] ?? 0) . '/' . installment_count();
    }
    if ($type === 'BULK') {
        return 'Bulk payment';
    }
    return 'Full payment';
}

function telegram_notify_payment_started(array $payment, string $mode = ''): void
{
    try {
        if (!telegram_configured()) {
            return;
        }
        $lines = ['🟡 New payment started'];
        $amount = (int) ($payment['amount'] ?? 0);
        $lines[] = 'Type: ' . telegram_payment_label($payment);
        $lines[] = 'Amount: ' . money($amount);
        $lines[] = 'Reference: ' . (string) ($payment['reference'] ?? '');
        if ($mode !== '') {
            $lines[] = 'Mode: ' . $mode;
        }

        if (!empty($payment['bulk_order_id'])) {
            $order = db_one('SELECT * FROM bulk_orders WHERE id = ?', [$payment['bulk_order_id']]);
            if ($order) {
                $lines[] = 'Buyer: ' . (string) $order['buyer_email'];
                $lines[] = 'Seats: ' . (int) $order['seats'];
            }
        } elseif ((int) ($payment['participant_id'] ?? 0) > 0) {
            $participant = db_one('SELECT * FROM participants WHERE id = ?', [$payment['participant_id']]);
            if ($participant) {
                $lines[] = 'Delegate: ' . delegate_name($participant);
                $lines[] = 'Registration: ' . (string) $participant['registration_id'];
                $lines[] = 'Category: ' . (string) $participant['category'];
            }
        }

        telegram_notify_admins(implode("\n", $lines));
    } catch (Throwable $e) {
        log_error('Telegram started-notification failed: ' . $e->getMessage());
    }
}

function telegram_notify_payment_verified(array $payment, int $amount, string $source = 'gateway'): void
{
    try {
        if (!telegram_configured()) {
            return;
        }
        $lines = ['🟢 Payment verified'];
        $lines[] = 'Type: ' . telegram_payment_label($payment);
        $lines[] = 'Amount: ' . money($amount);
        $lines[] = 'Reference: ' . (string) ($payment['reference'] ?? '');
        if (!empty($payment['provider_reference'])) {
            $lines[] = 'Gateway ref: ' . (string) $payment['provider_reference'];
        }
        $lines[] = 'Source: ' . $source;

        if (!empty($payment['bulk_order_id'])) {
            $order = db_one('SELECT * FROM bulk_orders WHERE id = ?', [$payment['bulk_order_id']]);
            if ($order) {
                $lines[] = 'Buyer: ' . (string) $order['buyer_email'];
                $lines[] = 'Seats: ' . (int) $order['seats'];
                $lines[] = 'Order status: ' . (string) $order['status'];
            }
        } elseif ((int) ($payment['participant_id'] ?? 0) > 0) {
            $participant = db_one('SELECT * FROM participants WHERE id = ?', [$payment['participant_id']]);
            if ($participant) {
                $lines[] = 'Delegate: ' . delegate_name($participant);
                $lines[] = 'Registration: ' . (string) $participant['registration_id'];
                $lines[] = 'Category: ' . (string) $participant['category'];
                $lines[] = 'Payment status: ' . (string) $participant['payment_status'];
                if ((string) $participant['payment_status'] === 'FULLY_PAID') {
                    $lines[] = '✅ Fully paid';
                }
            }
        }

        telegram_notify_admins(implode("\n", $lines));
    } catch (Throwable $e) {
        log_error('Telegram verified-notification failed: ' . $e->getMessage());
    }
}
