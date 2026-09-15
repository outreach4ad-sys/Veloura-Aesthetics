<?php
/**
 * Veloura Tec — Application bootstrap.
 *
 * Every public page and admin page must require this file FIRST:
 *     require __DIR__ . '/app/bootstrap.php';
 *
 * It loads configuration, prepares the database connection (lazily),
 * starts a hardened session, and registers the shared helper layer.
 */

declare(strict_types=1);

// Guard constant: every file under app/ refuses to run without it, so a
// direct HTTP request to app/helpers.php produces 403 rather than output.
define('VELOURA', true);

define('VELOURA_APP',  __DIR__);
define('VELOURA_ROOT', dirname(__DIR__));

// ---------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------
$configFile = VELOURA_APP . '/config.php';

if (!is_file($configFile)) {
    http_response_code(500);
    exit(
        'Configuration missing. Copy app/config.sample.php to app/config.php '
        . 'and fill in your database credentials.'
    );
}

/** @var array<string,mixed> $config */
$config = require $configFile;

// ---------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------
$isDev = ($config['env'] ?? 'production') === 'development';

ini_set('display_errors', $isDev ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$logFile = $config['error_log'] ?? (VELOURA_ROOT . '/storage/error.log');
$logDir  = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('error_log', $logFile);

// Uncaught exceptions must never leak a stack trace in production.
set_exception_handler(static function (Throwable $e) use ($isDev): void {
    error_log('[Veloura] Uncaught ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());

    http_response_code(500);
    if ($isDev) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo 'An unexpected error occurred. Please try again later.';
    }
    exit;
});

// ---------------------------------------------------------------------
// Core libraries (order matters: helpers before anything that uses them)
// ---------------------------------------------------------------------
require VELOURA_APP . '/helpers.php';
require VELOURA_APP . '/db.php';
require VELOURA_APP . '/security.php';
require VELOURA_APP . '/settings.php';
require VELOURA_APP . '/seo.php';
require VELOURA_APP . '/upload.php';
require VELOURA_APP . '/mailer.php';

// Data access layer: every repository file is loaded automatically, so a
// new repositories/*.php file needs no bootstrap edit.
foreach (glob(VELOURA_APP . '/repositories/*.php') ?: [] as $repository) {
    require $repository;
}

// Make config globally reachable through config('db.host') style lookups.
config_init($config);

// ---------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------
session_boot();

// ---------------------------------------------------------------------
// Language (structured so ar.php etc. can be dropped in later)
// ---------------------------------------------------------------------
lang_load(config('default_locale', 'en'));
