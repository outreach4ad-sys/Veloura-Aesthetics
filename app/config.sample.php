<?php
/**
 * Veloura Tec — Configuration template.
 *
 * COPY THIS FILE TO app/config.php AND FILL IN REAL VALUES.
 * config.php is git-ignored and must never be committed.
 */

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

return [

    // -----------------------------------------------------------------
    // Environment: 'production' hides errors, 'development' shows them.
    // -----------------------------------------------------------------
    'env' => 'production',

    // -----------------------------------------------------------------
    // Absolute public base URL, no trailing slash.
    // Local example: 'http://localhost:8000'
    // -----------------------------------------------------------------
    'base_url' => 'https://veloura-tec.com',

    // -----------------------------------------------------------------
    // Database (Hostinger: hPanel > Databases > MySQL Databases)
    // -----------------------------------------------------------------
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'DATABASE_NAME',
        'user'    => 'DATABASE_USER',
        'pass'    => 'DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],

    // -----------------------------------------------------------------
    // Session / security
    // -----------------------------------------------------------------
    'session_name'     => 'veloura_session',
    'session_lifetime' => 7200,   // seconds of inactivity before admin logout
    'login_max_attempts' => 5,
    'login_lockout'      => 900,  // seconds locked after too many failures

    // -----------------------------------------------------------------
    // Uploads
    // -----------------------------------------------------------------
    'upload_dir'        => 'uploads',
    'max_image_bytes'   => 4 * 1024 * 1024,    // 4 MB
    'max_video_bytes'   => 32 * 1024 * 1024,   // 32 MB
    'allowed_image_ext' => ['jpg', 'jpeg', 'png', 'webp'],
    'allowed_image_mime'=> ['image/jpeg', 'image/png', 'image/webp'],
    'allowed_video_ext' => ['mp4', 'webm'],
    'allowed_video_mime'=> ['video/mp4', 'video/webm'],

    // -----------------------------------------------------------------
    // Localisation (structured for future multilingual support)
    // -----------------------------------------------------------------
    'default_locale' => 'en',
    'direction'      => 'ltr',

    // -----------------------------------------------------------------
    // Error log path. Keep outside the web root where possible.
    // -----------------------------------------------------------------
    'error_log' => __DIR__ . '/../storage/error.log',
];
