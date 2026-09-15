<?php
/**
 * Veloura Tec — Web installer.
 *
 * A one-page, browser-based setup so no SSH or .htaccess editing is needed:
 *   1. Enter database details  → writes app/config.php and tests the connection
 *   2. Import the schema       → creates the tables from database/schema.sql
 *   3. Create the administrator → your admin email + password
 *   4. Delete the installer     → one click removes this file
 *
 * SECURITY
 * --------
 * This file is self-disabling: once an administrator account exists it does
 * nothing except offer to delete itself. It is CSRF-protected and validates
 * every input server-side. Still, DELETE IT as soon as setup is done — the
 * final step does this for you.
 *
 * It lives at the site root (not under /app or /database), so it is reachable
 * right after upload without touching .htaccess.
 */

declare(strict_types=1);

session_start();

const ROOT        = __DIR__;
const CONFIG_FILE = ROOT . '/app/config.php';
const SAMPLE_FILE = ROOT . '/app/config.sample.php';
const SCHEMA_FILE = ROOT . '/database/schema.sql';

/* --------------------------------------------------------------- helpers */

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function csrf(): string
{
    if (empty($_SESSION['install_csrf'])) {
        $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['install_csrf'];
}

function csrf_ok(): bool
{
    return isset($_POST['csrf'], $_SESSION['install_csrf'])
        && hash_equals($_SESSION['install_csrf'], (string) $_POST['csrf']);
}

/** Escape a value for embedding inside a single-quoted PHP string. */
function php_str(string $v): string
{
    return str_replace(['\\', "'"], ['\\\\', "\\'"], $v);
}

/**
 * Load the config array without triggering the VELOURA guard.
 *
 * @return array<string,mixed>|null
 */
function load_config(): ?array
{
    if (!is_file(CONFIG_FILE)) {
        return null;
    }
    if (!defined('VELOURA')) {
        define('VELOURA', true);
    }
    $config = require CONFIG_FILE;

    return is_array($config) ? $config : null;
}

/**
 * Open a PDO connection from a config array. Returns [pdo, null] or [null, error].
 *
 * @param array<string,mixed> $db
 * @return array{0:?PDO,1:?string}
 */
function try_connect(array $db): array
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $db['host'] ?? 'localhost',
        (int) ($db['port'] ?? 3306),
        $db['name'] ?? ''
    );
    try {
        $pdo = new PDO($dsn, (string) ($db['user'] ?? ''), (string) ($db['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        return [$pdo, null];
    } catch (PDOException $e) {
        return [null, $e->getMessage()];
    }
}

function admins_table_exists(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM admins LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function admin_count(PDO $pdo): int
{
    try {
        return (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/* ----------------------------------------------------- determine the state */

$errors  = [];
$notices = [];
$config  = load_config();
$pdo     = null;
$dbOk    = false;
$schemaOk = false;
$hasAdmin = false;

if ($config !== null && !empty($config['db'])) {
    [$pdo, $connErr] = try_connect($config['db']);
    if ($pdo !== null) {
        $dbOk     = true;
        $schemaOk = admins_table_exists($pdo);
        $hasAdmin = $schemaOk && admin_count($pdo) > 0;
    }
}

/* ------------------------------------------------------------- form values */

$form = [
    'db_host' => $config['db']['host'] ?? 'localhost',
    'db_name' => $config['db']['name'] ?? '',
    'db_user' => $config['db']['user'] ?? '',
    'admin_name'  => '',
    'admin_email' => '',
];

/* --------------------------------------------------------------- actions */

$action = (string) ($_POST['action'] ?? '');

if ($action !== '' && !csrf_ok()) {
    $errors[] = 'Your session expired. Please reload the page and try again.';
    $action = '';
}

// ---- Delete the installer (available once setup is complete) ------------
if ($action === 'delete') {
    if (@unlink(__FILE__)) {
        // The file is gone; send the admin to the login page.
        header('Location: admin/login.php');
        exit;
    }
    $errors[] = 'Could not delete the file automatically. Please delete install.php from the server manually.';
}

// ---- Step 1: save database details -> write config.php ------------------
if ($action === 'save_db' && !$hasAdmin) {
    $form['db_host'] = trim((string) ($_POST['db_host'] ?? 'localhost'));
    $form['db_name'] = trim((string) ($_POST['db_name'] ?? ''));
    $form['db_user'] = trim((string) ($_POST['db_user'] ?? ''));
    $dbPass          = (string) ($_POST['db_pass'] ?? '');

    if ($form['db_name'] === '' || $form['db_user'] === '') {
        $errors[] = 'Please enter the database name and username.';
    } else {
        // Test the connection before writing anything.
        [$testPdo, $connErr] = try_connect([
            'host' => $form['db_host'], 'name' => $form['db_name'],
            'user' => $form['db_user'], 'pass' => $dbPass,
        ]);

        if ($testPdo === null) {
            $errors[] = 'Could not connect: ' . $connErr;
        } elseif (!is_file(SAMPLE_FILE)) {
            $errors[] = 'app/config.sample.php is missing — cannot generate the config file.';
        } else {
            // Build config.php from the template, filling in the real values.
            $tpl = (string) file_get_contents(SAMPLE_FILE);
            $tpl = str_replace(
                ["'DATABASE_NAME'", "'DATABASE_USER'", "'DATABASE_PASSWORD'", "'host'    => 'localhost'"],
                [
                    "'" . php_str($form['db_name']) . "'",
                    "'" . php_str($form['db_user']) . "'",
                    "'" . php_str($dbPass) . "'",
                    "'host'    => '" . php_str($form['db_host']) . "'",
                ],
                $tpl
            );
            // The site is domain-independent, so base_url only needs a sane
            // value; use this request's origin.
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $tpl = str_replace("'base_url' => 'https://veloura-tec.com'", "'base_url' => '" . php_str($origin) . "'", $tpl);

            if (@file_put_contents(CONFIG_FILE, $tpl) === false) {
                $errors[] = 'Could not write app/config.php. Check that the app/ folder is writable (permissions 755), or create the file manually.';
            } else {
                $notices[] = 'Database connected and configuration saved.';
                // Refresh state.
                $config = load_config();
                [$pdo] = try_connect($config['db']);
                $dbOk = $pdo !== null;
                $schemaOk = $dbOk && admins_table_exists($pdo);
                $hasAdmin = $schemaOk && admin_count($pdo) > 0;
            }
        }
    }
}

// ---- Step 2: import the schema -----------------------------------------
if ($action === 'import_schema' && $dbOk && !$schemaOk) {
    if (!is_file(SCHEMA_FILE)) {
        $errors[] = 'database/schema.sql is missing.';
    } else {
        try {
            $sql = (string) file_get_contents(SCHEMA_FILE);
            $pdo->exec($sql);
            $schemaOk = admins_table_exists($pdo);
            $hasAdmin = $schemaOk && admin_count($pdo) > 0;
            if ($schemaOk) {
                $notices[] = 'Database tables created successfully.';
            } else {
                $errors[] = 'The import ran but the tables were not found. Import database/schema.sql manually via phpMyAdmin.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Import failed: ' . $e->getMessage() . ' — you can import database/schema.sql via phpMyAdmin instead.';
        }
    }
}

// ---- Step 3: create the administrator ----------------------------------
if ($action === 'create_admin' && $dbOk && $schemaOk && !$hasAdmin) {
    $form['admin_name']  = trim((string) ($_POST['admin_name'] ?? ''));
    $form['admin_email'] = strtolower(trim((string) ($_POST['admin_email'] ?? '')));
    $pass    = (string) ($_POST['admin_pass'] ?? '');
    $confirm = (string) ($_POST['admin_pass_confirm'] ?? '');

    if ($form['admin_name'] === '') {
        $errors[] = 'Please enter your name.';
    }
    if (!filter_var($form['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address (this is your login username).';
    }
    if (strlen($pass) < 10) {
        $errors[] = 'The password must be at least 10 characters.';
    }
    if ($pass !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if ($errors === []) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO admins (name, email, password_hash, role, is_active)
                 VALUES (:name, :email, :hash, :role, 1)'
            );
            $stmt->execute([
                'name'  => $form['admin_name'],
                'email' => $form['admin_email'],
                'hash'  => password_hash($pass, PASSWORD_DEFAULT),
                'role'  => 'owner',
            ]);
            $hasAdmin = true;
            $notices[] = 'Administrator created.';
        } catch (PDOException $e) {
            $errors[] = 'Could not create the administrator: ' . $e->getMessage();
        }
    }
}

// Which step are we on?
$step = 1;
if ($dbOk) {
    $step = 2;
}
if ($dbOk && $schemaOk) {
    $step = 3;
}
if ($hasAdmin) {
    $step = 4;
}

$token = csrf();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Install — Veloura Tec</title>
<style>
  :root{--navy:#0B1F35;--copper:#B8734B;--copper-600:#9E5F3B;--ivory:#F7F5F2;
        --hair:#DCE1E7;--ink:#16202C;--dim:#5A6675;--ok:#1F7A55;--err:#B3261E;--warn:#7A5312}
  *{box-sizing:border-box}
  body{margin:0;background:var(--navy);color:var(--ink);
       font:16px/1.6 "Segoe UI",Tahoma,system-ui,-apple-system,sans-serif;
       min-height:100vh;padding:32px 16px}
  .card{max-width:560px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;
        box-shadow:0 20px 60px rgba(0,0,0,.35)}
  .head{background:var(--navy);color:#fff;padding:24px 28px}
  .head h1{margin:0;font-size:20px;letter-spacing:.02em}
  .head p{margin:6px 0 0;color:#9DB0C4;font-size:14px}
  .steps{display:flex;gap:6px;padding:16px 28px;border-bottom:1px solid var(--hair);flex-wrap:wrap}
  .pill{font-size:12px;padding:5px 12px;border-radius:999px;background:#F1F2F4;color:var(--dim)}
  .pill.active{background:var(--copper);color:#fff}
  .pill.done{background:#E6F2EC;color:var(--ok)}
  .body{padding:28px}
  h2{font-size:17px;margin:0 0 6px;color:var(--navy)}
  .sub{color:var(--dim);font-size:14px;margin:0 0 20px}
  label{display:block;font-size:14px;font-weight:600;color:var(--navy);margin:0 0 6px}
  .hint{font-weight:400;color:var(--dim);font-size:13px}
  input{width:100%;padding:11px;border:1px solid #C9CDD3;border-radius:6px;font:inherit;margin-bottom:16px}
  input:focus{outline:2px solid var(--copper);outline-offset:1px;border-color:var(--copper)}
  button{width:100%;padding:13px;border:0;border-radius:6px;background:var(--copper);color:#fff;
         font:inherit;font-weight:600;cursor:pointer;letter-spacing:.03em}
  button:hover{background:var(--copper-600)}
  .msg{padding:11px 14px;border-radius:6px;font-size:14px;margin-bottom:14px}
  .msg.err{background:#FBEAE9;color:var(--err);border:1px solid #E7C3C0}
  .msg.ok{background:#EAF5F0;color:var(--ok);border:1px solid #BEE3D2}
  .msg.warn{background:#FFFBF3;color:var(--warn);border:1px solid #E0B968}
  code{background:#F1F2F4;padding:1px 6px;border-radius:3px;font-size:13px}
  .done-box{text-align:center}
  .done-box .check{font-size:44px;color:var(--ok);line-height:1}
  a.btn{display:block;text-decoration:none;text-align:center;margin-top:10px;padding:13px;
        border-radius:6px;border:1px solid var(--hair);color:var(--navy);font-weight:600}
</style>
</head>
<body>
<div class="card">
  <div class="head">
    <h1>Veloura Tec — Setup</h1>
    <p>Create your admin login. No SSH or file editing needed.</p>
  </div>

  <div class="steps">
    <span class="pill <?= $step>1?'done':($step==1?'active':'') ?>">1 · Database</span>
    <span class="pill <?= $step>2?'done':($step==2?'active':'') ?>">2 · Tables</span>
    <span class="pill <?= $step>3?'done':($step==3?'active':'') ?>">3 · Admin</span>
    <span class="pill <?= $step==4?'active':'' ?>">4 · Finish</span>
  </div>

  <div class="body">
    <?php foreach ($errors as $m): ?><div class="msg err"><?= h($m) ?></div><?php endforeach; ?>
    <?php foreach ($notices as $m): ?><div class="msg ok"><?= h($m) ?></div><?php endforeach; ?>

    <?php if ($step === 4): ?>
      <!-- ======================================================= DONE -->
      <div class="done-box">
        <div class="check">&#10003;</div>
        <h2>Setup complete</h2>
        <p class="sub">Your administrator account is ready.</p>
        <div class="msg warn">
          <strong>Important:</strong> delete this installer now for security. The button
          below removes it and takes you to the login page.
        </div>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h($token) ?>">
          <input type="hidden" name="action" value="delete">
          <button type="submit">Delete installer &amp; go to login</button>
        </form>
        <a class="btn" href="admin/login.php">Skip to login (delete install.php manually)</a>
      </div>

    <?php elseif ($step === 1): ?>
      <!-- =================================================== STEP 1: DB -->
      <h2>Database details</h2>
      <p class="sub">
        Create a MySQL database and user in your hosting panel first, then enter them here.
      </p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($token) ?>">
        <input type="hidden" name="action" value="save_db">

        <label for="db_host">Database host <span class="hint">(usually <code>localhost</code>)</span></label>
        <input type="text" id="db_host" name="db_host" value="<?= h($form['db_host']) ?>" required>

        <label for="db_name">Database name</label>
        <input type="text" id="db_name" name="db_name" value="<?= h($form['db_name']) ?>" required>

        <label for="db_user">Database username</label>
        <input type="text" id="db_user" name="db_user" value="<?= h($form['db_user']) ?>" required>

        <label for="db_pass">Database password</label>
        <input type="password" id="db_pass" name="db_pass" autocomplete="off">

        <button type="submit">Test &amp; save</button>
      </form>

    <?php elseif ($step === 2): ?>
      <!-- =============================================== STEP 2: schema -->
      <h2>Create the tables</h2>
      <p class="sub">
        Connected successfully. Now create the database tables. If you already imported
        <code>schema.sql</code> in phpMyAdmin, this step will simply confirm them.
      </p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($token) ?>">
        <input type="hidden" name="action" value="import_schema">
        <button type="submit">Create tables</button>
      </form>

    <?php else: ?>
      <!-- ================================================ STEP 3: admin -->
      <h2>Create your admin login</h2>
      <p class="sub">Your email is the username you will sign in with.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($token) ?>">
        <input type="hidden" name="action" value="create_admin">

        <label for="admin_name">Your name</label>
        <input type="text" id="admin_name" name="admin_name" value="<?= h($form['admin_name']) ?>" required>

        <label for="admin_email">Email <span class="hint">(your username)</span></label>
        <input type="email" id="admin_email" name="admin_email" value="<?= h($form['admin_email']) ?>"
               autocomplete="username" required>

        <label for="admin_pass">Password <span class="hint">(min 10 characters)</span></label>
        <input type="password" id="admin_pass" name="admin_pass" minlength="10"
               autocomplete="new-password" required>

        <label for="admin_pass_confirm">Confirm password</label>
        <input type="password" id="admin_pass_confirm" name="admin_pass_confirm" minlength="10"
               autocomplete="new-password" required>

        <button type="submit">Create administrator</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
