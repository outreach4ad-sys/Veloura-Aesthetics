<?php
/**
 * Veloura Tec — Session hardening, CSRF protection and authentication.
 */

declare(strict_types=1);

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

// =====================================================================
// Session
// =====================================================================

/**
 * Start a session with hardened cookie parameters.
 */
function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (
        (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
    );

    session_name((string) config('session_name', 'veloura_session'));

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_start();
}

// =====================================================================
// CSRF
// =====================================================================

/**
 * The current session CSRF token, generated on first use.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Hidden input to drop into every state-changing form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validate a submitted token in constant time.
 */
function csrf_verify(?string $token = null): bool
{
    $token ??= (string) ($_POST['csrf_token'] ?? '');
    $expected = $_SESSION['csrf_token'] ?? '';

    return $expected !== '' && $token !== '' && hash_equals($expected, $token);
}

/**
 * Abort the request when the CSRF token is missing or wrong.
 */
function csrf_guard(): void
{
    if (!csrf_verify()) {
        http_response_code(419);
        exit('Your session expired. Please go back, reload the page and try again.');
    }
}

// =====================================================================
// Authentication
// =====================================================================

/**
 * The signed-in admin row, or null.
 *
 * @return array<string,mixed>|null
 */
function auth_user(): ?array
{
    static $user = null;

    if ($user !== null) {
        return $user;
    }

    $id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    // Idle timeout.
    $lifetime = (int) config('session_lifetime', 7200);
    $lastSeen = (int) ($_SESSION['admin_last_seen'] ?? 0);

    if ($lastSeen > 0 && (time() - $lastSeen) > $lifetime) {
        auth_logout();

        return null;
    }
    $_SESSION['admin_last_seen'] = time();

    $row = db_one(
        'SELECT id, name, email, role, is_active FROM admins WHERE id = :id AND is_active = 1',
        ['id' => $id]
    );

    if ($row === null) {
        auth_logout();

        return null;
    }

    return $user = $row;
}

function auth_check(): bool
{
    return auth_user() !== null;
}

/**
 * Redirect to the login page unless signed in. Call at the top of every
 * admin page, before any output.
 */
function auth_guard(): void
{
    if (!auth_check()) {
        $_SESSION['admin_intended'] = current_path();
        redirect('admin/login.php');
    }
}

/**
 * Attempt a login. Returns an error message on failure, null on success.
 *
 * Failures are counted per account and a lockout window applies. The same
 * generic message is returned for unknown email and wrong password so the
 * form cannot be used to enumerate accounts.
 */
function auth_attempt(string $email, string $password): ?string
{
    $generic = t('auth.invalid_credentials');

    $admin = db_one(
        'SELECT id, name, password_hash, is_active, failed_attempts, locked_until
           FROM admins WHERE email = :email LIMIT 1',
        ['email' => $email]
    );

    if ($admin === null || (int) $admin['is_active'] !== 1) {
        // Equalise timing a little against a non-existent account.
        password_verify($password, '$2y$12$usesomesillystringfoeswfghifsdfvbnmqwertyuiopasdfghjkl');

        return $generic;
    }

    if (!empty($admin['locked_until']) && strtotime((string) $admin['locked_until']) > time()) {
        return t('auth.locked');
    }

    if (!password_verify($password, (string) $admin['password_hash'])) {
        $attempts = (int) $admin['failed_attempts'] + 1;
        $max      = (int) config('login_max_attempts', 5);

        $lockedUntil = $attempts >= $max
            ? date('Y-m-d H:i:s', time() + (int) config('login_lockout', 900))
            : null;

        db_execute(
            'UPDATE admins SET failed_attempts = :attempts, locked_until = :locked WHERE id = :id',
            ['attempts' => $attempts, 'locked' => $lockedUntil, 'id' => $admin['id']]
        );

        return $lockedUntil !== null ? t('auth.locked') : $generic;
    }

    // Success — new session id defeats fixation.
    session_regenerate_id(true);

    $_SESSION['admin_id']        = (int) $admin['id'];
    $_SESSION['admin_last_seen'] = time();
    unset($_SESSION['csrf_token']);

    db_execute(
        'UPDATE admins
            SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW()
          WHERE id = :id',
        ['id' => $admin['id']]
    );

    return null;
}

/**
 * Destroy the admin session completely.
 */
function auth_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Lax',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Hash a plain password for storage. Used by the seeder and by any future
 * password-change screen. Plain passwords are never stored anywhere.
 */
function auth_hash(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

// =====================================================================
// Response headers
// =====================================================================

/**
 * A per-request Content-Security-Policy nonce.
 *
 * Generated once and reused, so the CSP header and the inline JSON-LD
 * blocks (the only inline scripts on the site) carry the same value. This
 * lets script-src stay 'self' + nonce with no 'unsafe-inline'.
 */
function csp_nonce(): string
{
    static $nonce = null;

    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }

    return $nonce;
}

/**
 * Baseline security headers, sent from header.php before any markup.
 * (.htaccess sets the static ones too; this covers hosts where mod_headers
 * is off, and carries the CSP, which needs a per-request nonce .htaccess
 * cannot produce.)
 */
function security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    // Content-Security-Policy.
    //  - default 'self': everything loads from this origin unless widened
    //  - img-src allows data: (inline SVG placeholder, JS previews) and
    //    https: (a future CDN or the configured OG image on another host)
    //  - script-src is 'self' plus this request's nonce, so the inline
    //    JSON-LD runs while injected inline scripts do not
    //  - style-src 'self' — all CSS is external, no inline styles
    //  - frame-src limited to the YouTube embed used on product pages
    //  - object-src 'none', base-uri 'self', form-action 'self' close off
    //    plugin, <base> and form-hijack vectors
    $nonce = csp_nonce();
    $csp = implode('; ', [
        "default-src 'self'",
        "img-src 'self' data: https:",
        "script-src 'self' 'nonce-{$nonce}'",
        "style-src 'self'",
        "font-src 'self'",
        "connect-src 'self'",
        "frame-src https://www.youtube-nocookie.com https://www.youtube.com",
        "media-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ]);

    header('Content-Security-Policy: ' . $csp);
}
