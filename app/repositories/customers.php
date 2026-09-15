<?php
/**
 * Veloura Tec — Customer accounts (separate from admin accounts).
 *
 * Accounts confirm their email with a 6-digit code before they can sign in.
 * Passwords use password_hash(). The customer session is kept under its own
 * key so it never mixes with the admin session.
 */

declare(strict_types=1);

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

const CUSTOMER_CODE_TTL_MIN   = 15;   // verification code lifetime, minutes
const CUSTOMER_CODE_MAX_TRIES = 6;    // wrong-code attempts before a new code is needed
const CUSTOMER_LOGIN_MAX      = 5;    // failed logins before lockout
const CUSTOMER_LOCK_MINUTES   = 15;

/** Are customer accounts switched on in settings? */
function accounts_enabled(): bool
{
    return setting_bool('accounts_enabled', false);
}

/**
 * @return array<string,mixed>|null
 */
function customer_by_email(string $email): ?array
{
    return db_one('SELECT * FROM customers WHERE email = :e LIMIT 1', ['e' => strtolower(trim($email))]);
}

/**
 * @return array<string,mixed>|null
 */
function customer_by_id(int $id): ?array
{
    return db_one('SELECT * FROM customers WHERE id = :id LIMIT 1', ['id' => $id]);
}

/**
 * Validate a registration submission.
 *
 * @param array<string,mixed> $data
 * @return array<string,string>
 */
function customer_validate(array $data): array
{
    $errors = [];

    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        $errors['name'] = 'Please enter your name.';
    } elseif (mb_strlen($name) > 160) {
        $errors['name'] = 'Your name is too long.';
    }

    $email = strtolower(trim((string) ($data['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (mb_strlen($email) > 190) {
        $errors['email'] = 'The email address is too long.';
    }

    $pass = (string) ($data['password'] ?? '');
    if (strlen($pass) < 8) {
        $errors['password'] = 'The password must be at least 8 characters.';
    }

    return $errors;
}

/**
 * A fresh 6-digit code and its expiry.
 *
 * @return array{code:string,expires:string}
 */
function customer_new_code(): array
{
    return [
        'code'    => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
        'expires' => date('Y-m-d H:i:s', time() + CUSTOMER_CODE_TTL_MIN * 60),
    ];
}

/**
 * Create an unverified customer and return its id and verification code.
 *
 * @param array<string,mixed> $data
 * @return array{id:int,code:string}
 */
function customer_create(array $data): array
{
    $code = customer_new_code();

    $id = db_insert('customers', [
        'name'           => trim((string) $data['name']),
        'email'          => strtolower(trim((string) $data['email'])),
        'password_hash'  => password_hash((string) $data['password'], PASSWORD_DEFAULT),
        'phone'          => trim((string) ($data['phone'] ?? '')) ?: null,
        'country'        => trim((string) ($data['country'] ?? '')) ?: null,
        'company'        => trim((string) ($data['company'] ?? '')) ?: null,
        'is_verified'    => 0,
        'verify_code'    => $code['code'],
        'verify_expires' => $code['expires'],
        'verify_sent_at' => date('Y-m-d H:i:s'),
    ]);

    return ['id' => $id, 'code' => $code['code']];
}

/**
 * Issue a new verification code for an existing unverified account.
 * Returns the code, or null if the account is already verified / missing.
 */
function customer_reissue_code(int $id): ?string
{
    $customer = customer_by_id($id);
    if ($customer === null || (int) $customer['is_verified'] === 1) {
        return null;
    }

    $code = customer_new_code();
    db_execute(
        'UPDATE customers
            SET verify_code = :c, verify_expires = :e, verify_sent_at = NOW(), verify_attempts = 0
          WHERE id = :id',
        ['c' => $code['code'], 'e' => $code['expires'], 'id' => $id]
    );

    return $code['code'];
}

/**
 * Check a submitted code. Returns null on success, or an error message.
 */
function customer_verify_code(int $id, string $code): ?string
{
    $customer = customer_by_id($id);
    if ($customer === null) {
        return 'Account not found.';
    }
    if ((int) $customer['is_verified'] === 1) {
        return null;
    }

    if ((int) $customer['verify_attempts'] >= CUSTOMER_CODE_MAX_TRIES) {
        return 'Too many attempts. Please request a new code.';
    }
    if (empty($customer['verify_expires']) || strtotime((string) $customer['verify_expires']) < time()) {
        return 'The code has expired. Please request a new one.';
    }

    if (!hash_equals((string) $customer['verify_code'], trim($code))) {
        db_execute('UPDATE customers SET verify_attempts = verify_attempts + 1 WHERE id = :id', ['id' => $id]);

        return 'That code is not correct.';
    }

    db_execute(
        'UPDATE customers
            SET is_verified = 1, verify_code = NULL, verify_expires = NULL, verify_attempts = 0
          WHERE id = :id',
        ['id' => $id]
    );

    return null;
}

/**
 * Attempt a login. Returns the customer row on success, or an error string.
 *
 * @return array<string,mixed>|string
 */
function customer_attempt_login(string $email, string $password): array|string
{
    $customer = customer_by_email($email);

    if ($customer === null) {
        // Equalise timing against a missing account.
        password_verify($password, '$2y$12$usesomesillystringfoeswfghifsdfvbnmqwertyuiopasdfghjkl');

        return 'Incorrect email or password.';
    }

    if (!empty($customer['locked_until']) && strtotime((string) $customer['locked_until']) > time()) {
        return 'Too many attempts. Please try again later.';
    }

    if (!password_verify($password, (string) $customer['password_hash'])) {
        $attempts = (int) $customer['failed_logins'] + 1;
        $lock = $attempts >= CUSTOMER_LOGIN_MAX
            ? date('Y-m-d H:i:s', time() + CUSTOMER_LOCK_MINUTES * 60)
            : null;
        db_execute(
            'UPDATE customers SET failed_logins = :a, locked_until = :l WHERE id = :id',
            ['a' => $attempts, 'l' => $lock, 'id' => $customer['id']]
        );

        return 'Incorrect email or password.';
    }

    if ((int) $customer['is_verified'] !== 1) {
        return 'Please confirm your email first. Check your inbox for the code.';
    }

    db_execute(
        'UPDATE customers SET failed_logins = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id',
        ['id' => $customer['id']]
    );

    return $customer;
}

// =====================================================================
// Customer session (independent of the admin session)
// =====================================================================

function customer_login_session(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = $id;
    $_SESSION['customer_seen'] = time();
}

/**
 * @return array<string,mixed>|null
 */
function customer_user(): ?array
{
    static $user = null;
    if ($user !== null) {
        return $user;
    }

    $id = (int) ($_SESSION['customer_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $row = customer_by_id($id);
    if ($row === null || (int) $row['is_verified'] !== 1) {
        customer_logout();

        return null;
    }

    return $user = $row;
}

function customer_check(): bool
{
    return customer_user() !== null;
}

function customer_logout(): void
{
    unset($_SESSION['customer_id'], $_SESSION['customer_seen']);
}

/**
 * Inquiries submitted with this customer's email address.
 *
 * @return array<int,array<string,mixed>>
 */
function customer_inquiries(string $email): array
{
    return db_all(
        'SELECT id, reference, status, items_count, created_at
           FROM inquiries WHERE email = :e ORDER BY created_at DESC',
        ['e' => strtolower(trim($email))]
    );
}

/**
 * Send (or log) the verification code email for a customer.
 *
 * @return array{ok:bool,error:?string}
 */
function customer_send_code(array $customer, string $code): array
{
    $html = mail_render('verify-code', [
        'title'   => 'Confirm your email',
        'name'    => $customer['name'],
        'code'    => $code,
        'minutes' => CUSTOMER_CODE_TTL_MIN,
    ]);

    return mail_send((string) $customer['email'], 'Your confirmation code', $html, [
        'template' => 'verify-code',
    ]);
}
