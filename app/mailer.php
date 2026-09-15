<?php
/**
 * Veloura Tec — Email sending, with no external dependencies.
 *
 * Three transports, chosen by the `mail_transport` setting:
 *   - 'smtp'   : raw SMTP over a socket (works with a Hostinger noreply@
 *                mailbox, or any SMTP server). No PHPMailer/Composer needed.
 *   - 'resend' : the Resend HTTP API over cURL (best deliverability).
 *   - 'log'    : the default until email is configured — writes the message
 *                to storage/mail.log and the email_log table instead of
 *                sending, so nothing breaks before setup.
 *
 * Every attempt is recorded in the email_log table. Callers use mail_send()
 * and get back ['ok' => bool, 'error' => ?string]; sending never throws, so
 * a mail failure can never break an inquiry or a page.
 */

declare(strict_types=1);

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * The configured transport: 'smtp', 'resend' or 'log'.
 */
function mail_transport(): string
{
    $t = strtolower((string) setting('mail_transport', 'log'));

    return in_array($t, ['smtp', 'resend', 'log'], true) ? $t : 'log';
}

/**
 * True when email is really configured to send (not the log fallback and
 * the required credentials are present). Used to gate account verification.
 */
function mail_ready(): bool
{
    return match (mail_transport()) {
        'smtp'   => setting('smtp_host', '') !== '' && setting('smtp_user', '') !== '',
        'resend' => setting('resend_api_key', '') !== '',
        default  => false,
    };
}

/**
 * The From address and name.
 *
 * @return array{0:string,1:string} [email, name]
 */
function mail_from(): array
{
    $email = (string) setting('mail_from_email', '');
    if ($email === '' || str_starts_with($email, '[PLACEHOLDER')) {
        // Fall back to the contact email, then a safe default on this host.
        $email = setting_public('contact_email');
        if ($email === '') {
            $host = (string) (parse_url((string) abs_url(), PHP_URL_HOST) ?: 'localhost');
            $email = 'noreply@' . $host;
        }
    }

    return [$email, (string) setting('mail_from_name', setting('site_name', 'Veloura Tec'))];
}

/**
 * Send an email. Never throws.
 *
 * @param array{template?:string,text?:string,reply_to?:string} $opts
 * @return array{ok:bool,error:?string}
 */
function mail_send(string $to, string $subject, string $html, array $opts = []): array
{
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient address.'];
    }

    $text     = $opts['text'] ?? mail_html_to_text($html);
    $from     = mail_from();
    $transport = mail_transport();

    try {
        $result = match ($transport) {
            'smtp'   => mail_transport_smtp($to, $subject, $html, $text, $from, $opts),
            'resend' => mail_transport_resend($to, $subject, $html, $text, $from, $opts),
            default  => mail_transport_log($to, $subject, $html, $from),
        };
    } catch (Throwable $e) {
        error_log('[Veloura] Mail error: ' . $e->getMessage());
        $result = ['ok' => false, 'error' => 'Mail transport error.'];
    }

    // Record the attempt.
    $status = $transport === 'log' ? 'logged' : ($result['ok'] ? 'sent' : 'failed');
    try {
        db_insert('email_log', [
            'to_email'  => mb_substr($to, 0, 190),
            'subject'   => mb_substr($subject, 0, 255),
            'template'  => isset($opts['template']) ? mb_substr((string) $opts['template'], 0, 60) : null,
            'transport' => $transport,
            'status'    => $status,
            'error'     => $result['error'] !== null ? mb_substr((string) $result['error'], 0, 255) : null,
        ]);
    } catch (Throwable $e) {
        // Logging must never break sending.
    }

    return $result;
}

// =====================================================================
// Transports
// =====================================================================

/**
 * Log transport: write the message to storage/mail.log. Returns ok so the
 * calling flow proceeds; the email is simply not delivered until a real
 * transport is configured.
 *
 * @param array{0:string,1:string} $from
 * @return array{ok:bool,error:?string}
 */
function mail_transport_log(string $to, string $subject, string $html, array $from): array
{
    $line = sprintf(
        "[%s] to=%s from=%s subject=%s\n%s\n%s\n",
        date('Y-m-d H:i:s'),
        $to,
        $from[0],
        $subject,
        str_repeat('-', 60),
        mail_html_to_text($html)
    );

    $file = VELOURA_ROOT . '/storage/mail.log';
    @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);

    return ['ok' => true, 'error' => null];
}

/**
 * Resend API transport (https://resend.com) over cURL.
 *
 * @param array{0:string,1:string} $from
 * @param array<string,mixed> $opts
 * @return array{ok:bool,error:?string}
 */
function mail_transport_resend(string $to, string $subject, string $html, string $text, array $from, array $opts): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is not available on this server.'];
    }

    $key = (string) setting('resend_api_key', '');
    if ($key === '') {
        return ['ok' => false, 'error' => 'Resend API key is not set.'];
    }

    $payload = [
        'from'    => sprintf('%s <%s>', $from[1], $from[0]),
        'to'      => [$to],
        'subject' => $subject,
        'html'    => $html,
        'text'    => $text,
    ];
    if (!empty($opts['reply_to'])) {
        $payload['reply_to'] = $opts['reply_to'];
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $response = curl_exec($ch);
    $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'Network error: ' . $curlErr];
    }
    if ($code >= 200 && $code < 300) {
        return ['ok' => true, 'error' => null];
    }

    $body = json_decode((string) $response, true);
    $msg  = is_array($body) && isset($body['message']) ? (string) $body['message'] : 'HTTP ' . $code;

    return ['ok' => false, 'error' => 'Resend: ' . $msg];
}

/**
 * SMTP transport over a raw socket. Supports implicit SSL (port 465),
 * STARTTLS (port 587) and unencrypted (testing only).
 *
 * @param array{0:string,1:string} $from
 * @param array<string,mixed> $opts
 * @return array{ok:bool,error:?string}
 */
function mail_transport_smtp(string $to, string $subject, string $html, string $text, array $from, array $opts): array
{
    $host = (string) setting('smtp_host', '');
    $port = setting_int('smtp_port', 465);
    $sec  = strtolower((string) setting('smtp_security', 'ssl'));
    $user = (string) setting('smtp_user', '');
    $pass = (string) setting('smtp_pass', '');

    if ($host === '') {
        return ['ok' => false, 'error' => 'SMTP host is not set.'];
    }

    $remote = ($sec === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $context = stream_context_create([
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true],
    ]);

    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        return ['ok' => false, 'error' => "Could not connect to {$host}:{$port} ({$errstr})"];
    }
    stream_set_timeout($fp, 20);

    // Helper: read a full (possibly multi-line) SMTP reply and check its code.
    $expect = static function ($fp, int $code) : array {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            // Lines like "250-..." continue; "250 ..." ends the reply.
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        $got = (int) substr($data, 0, 3);

        return [$got === $code, $data];
    };

    $write = static function ($fp, string $cmd): void {
        fwrite($fp, $cmd . "\r\n");
    };

    $fail = static function ($fp, string $msg): array {
        @fwrite($fp, "QUIT\r\n");
        @fclose($fp);

        return ['ok' => false, 'error' => $msg];
    };

    $host_ehlo = (string) (parse_url((string) abs_url(), PHP_URL_HOST) ?: 'localhost');

    [$ok] = $expect($fp, 220);
    if (!$ok) {
        return $fail($fp, 'SMTP server did not greet.');
    }

    $write($fp, 'EHLO ' . $host_ehlo);
    [$ok] = $expect($fp, 250);
    if (!$ok) {
        return $fail($fp, 'EHLO rejected.');
    }

    if ($sec === 'tls') {
        $write($fp, 'STARTTLS');
        [$ok] = $expect($fp, 220);
        if (!$ok) {
            return $fail($fp, 'STARTTLS rejected.');
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return $fail($fp, 'Could not start TLS.');
        }
        $write($fp, 'EHLO ' . $host_ehlo);
        [$ok] = $expect($fp, 250);
        if (!$ok) {
            return $fail($fp, 'EHLO after TLS rejected.');
        }
    }

    // AUTH LOGIN (username and password base64-encoded).
    if ($user !== '') {
        $write($fp, 'AUTH LOGIN');
        [$ok] = $expect($fp, 334);
        if (!$ok) {
            return $fail($fp, 'AUTH LOGIN not accepted.');
        }
        $write($fp, base64_encode($user));
        [$ok] = $expect($fp, 334);
        if (!$ok) {
            return $fail($fp, 'SMTP username rejected.');
        }
        $write($fp, base64_encode($pass));
        [$ok] = $expect($fp, 235);
        if (!$ok) {
            return $fail($fp, 'SMTP authentication failed. Check the username and password.');
        }
    }

    $write($fp, 'MAIL FROM:<' . $from[0] . '>');
    [$ok] = $expect($fp, 250);
    if (!$ok) {
        return $fail($fp, 'MAIL FROM rejected.');
    }

    $write($fp, 'RCPT TO:<' . $to . '>');
    [$ok, $data] = $expect($fp, 250);
    if (!$ok) {
        return $fail($fp, 'Recipient rejected: ' . trim($data));
    }

    $write($fp, 'DATA');
    [$ok] = $expect($fp, 354);
    if (!$ok) {
        return $fail($fp, 'DATA not accepted.');
    }

    // Build the MIME message.
    $message = mail_build_message($to, $subject, $html, $text, $from, $opts);
    // Dot-stuffing: a line that is just "." would end DATA early.
    $message = preg_replace('/^\./m', '..', $message) ?? $message;

    fwrite($fp, $message . "\r\n.\r\n");
    [$ok, $data] = $expect($fp, 250);
    if (!$ok) {
        return $fail($fp, 'Message not accepted: ' . trim($data));
    }

    $write($fp, 'QUIT');
    @fclose($fp);

    return ['ok' => true, 'error' => null];
}

// =====================================================================
// Message building
// =====================================================================

/**
 * Encode a header value that may contain non-ASCII (e.g. Arabic) per RFC
 * 2047 so subjects and names survive.
 */
function mail_encode_header(string $value): string
{
    if (preg_match('/[\x80-\xFF]/', $value)) {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    return $value;
}

/**
 * Assemble a multipart/alternative MIME message (plain text + HTML).
 *
 * @param array{0:string,1:string} $from
 * @param array<string,mixed> $opts
 */
function mail_build_message(string $to, string $subject, string $html, string $text, array $from, array $opts): string
{
    $boundary = 'vt_' . bin2hex(random_bytes(12));
    $fromName = mail_encode_header($from[1]);

    $headers = [];
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'From: ' . $fromName . ' <' . $from[0] . '>';
    $headers[] = 'To: <' . $to . '>';
    $headers[] = 'Subject: ' . mail_encode_header($subject);
    $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' .
        (parse_url((string) abs_url(), PHP_URL_HOST) ?: 'localhost') . '>';
    if (!empty($opts['reply_to']) && filter_var($opts['reply_to'], FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: <' . $opts['reply_to'] . '>';
    }
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $body = '';
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($text)) . "\r\n";
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($html)) . "\r\n";
    $body .= '--' . $boundary . "--\r\n";

    // Normalise line endings to CRLF for SMTP.
    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;

    return str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $message);
}

/**
 * A readable plain-text fallback from an HTML body.
 */
function mail_html_to_text(string $html): string
{
    $text = preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
    $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
    $text = preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n", $text) ?? $text;
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

    return trim($text);
}

/**
 * Render an email template from includes/emails/<name>.php inside the shared
 * layout. $vars are extracted into the template's scope.
 *
 * @param array<string,mixed> $vars
 */
function mail_render(string $template, array $vars = []): string
{
    $file = VELOURA_ROOT . '/includes/emails/' . basename($template) . '.php';
    if (!is_file($file)) {
        return '';
    }

    $render = static function (string $__file, array $__vars): string {
        extract($__vars, EXTR_SKIP);
        ob_start();
        include $__file;

        return (string) ob_get_clean();
    };

    $content = $render($file, $vars);

    // Wrap in the layout.
    $layout = VELOURA_ROOT . '/includes/emails/layout.php';
    if (is_file($layout)) {
        return $render($layout, ['content' => $content, 'title' => $vars['title'] ?? '']);
    }

    return $content;
}
