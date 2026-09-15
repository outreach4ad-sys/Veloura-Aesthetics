<?php
/**
 * Veloura Tec — Database access (PDO / MySQL).
 *
 * The connection is opened lazily on first use so that static pages and
 * error screens do not pay for a database handshake they never need.
 *
 * ALL queries in this project go through these helpers with bound
 * parameters. Never interpolate user input into SQL.
 */

declare(strict_types=1);

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * The shared PDO instance.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host    = (string) config('db.host', 'localhost');
    $port    = (int) config('db.port', 3306);
    $name    = (string) config('db.name', '');
    $charset = (string) config('db.charset', 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

    try {
        $pdo = new PDO(
            $dsn,
            (string) config('db.user', ''),
            (string) config('db.pass', ''),
            [
                // Real prepared statements, not client-side emulation.
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]
        );
    } catch (PDOException $e) {
        // The message can contain credentials — log it, never print it.
        error_log('[Veloura] Database connection failed: ' . $e->getMessage());

        http_response_code(503);
        exit(is_dev()
            ? 'Database connection failed: ' . e($e->getMessage())
            : 'The site is temporarily unavailable. Please try again shortly.');
    }

    return $pdo;
}

/**
 * Run a prepared statement and return the statement handle.
 *
 * @param array<string|int,mixed> $params
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

/**
 * Fetch a single row, or null.
 *
 * @param array<string|int,mixed> $params
 * @return array<string,mixed>|null
 */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();

    return $row === false ? null : $row;
}

/**
 * Fetch all rows.
 *
 * @param array<string|int,mixed> $params
 * @return array<int,array<string,mixed>>
 */
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/**
 * Fetch a single scalar value from the first column of the first row.
 *
 * @param array<string|int,mixed> $params
 */
function db_value(string $sql, array $params = [], mixed $default = null): mixed
{
    $value = db_query($sql, $params)->fetchColumn();

    return $value === false ? $default : $value;
}

/**
 * Execute a write and return the number of affected rows.
 *
 * @param array<string|int,mixed> $params
 */
function db_execute(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

/**
 * Insert a row from an associative array and return the new id.
 * Column names come from code, never from user input.
 *
 * @param array<string,mixed> $data
 */
function db_insert(string $table, array $data): int
{
    $columns = array_keys($data);
    $sql = sprintf(
        'INSERT INTO `%s` (%s) VALUES (%s)',
        $table,
        '`' . implode('`, `', $columns) . '`',
        ':' . implode(', :', $columns)
    );

    db_query($sql, $data);

    return (int) db()->lastInsertId();
}

/**
 * Update a row by primary key.
 *
 * @param array<string,mixed> $data
 */
function db_update(string $table, array $data, int $id, string $key = 'id'): int
{
    $assignments = [];
    foreach (array_keys($data) as $column) {
        $assignments[] = sprintf('`%s` = :%s', $column, $column);
    }

    $sql = sprintf(
        'UPDATE `%s` SET %s WHERE `%s` = :__id LIMIT 1',
        $table,
        implode(', ', $assignments),
        $key
    );

    return db_execute($sql, $data + ['__id' => $id]);
}

/**
 * Delete a row by primary key.
 */
function db_delete(string $table, int $id, string $key = 'id'): int
{
    return db_execute(
        sprintf('DELETE FROM `%s` WHERE `%s` = :id LIMIT 1', $table, $key),
        ['id' => $id]
    );
}

/**
 * Run a callback inside a transaction, rolling back on any exception.
 */
function db_transaction(callable $callback): mixed
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $result = $callback($pdo);
        $pdo->commit();

        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Produce a slug that is unique within a table, appending -2, -3, ... as
 * needed. $ignoreId lets an existing record keep its own slug on edit.
 */
function db_unique_slug(string $table, string $slug, ?int $ignoreId = null): string
{
    $table = preg_replace('/[^a-z_]/', '', $table) ?? '';
    $base  = $slug;
    $suffix = 1;

    while (true) {
        $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `slug` = :slug', $table);
        $params = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND `id` <> :id';
            $params['id'] = $ignoreId;
        }

        if ((int) db_value($sql, $params, 0) === 0) {
            return $slug;
        }

        $suffix++;
        $slug = $base . '-' . $suffix;
    }
}
