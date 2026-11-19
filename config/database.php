<?php
/*
 * Database connection for NSBM SportsVerse.
 *
 * Default ("auto"): use MySQL/MariaDB (XAMPP: root, empty password, db "sportsverse_db").
 * If MySQL is not reachable, it falls back to a local SQLite file (data/sportsverse.sqlite)
 * so the site always runs. Tables + demo data are created automatically on first load.
 *
 * Override anything in config/local.php, e.g.
 *   <?php return ['driver' => 'mysql', 'host' => '127.0.0.1', 'dbname' => 'sportsverse_db', 'username' => 'root', 'password' => ''];
 * or with environment variables: SV_DRIVER, SV_DB_HOST, SV_DB_NAME, SV_DB_USER, SV_DB_PASS.
 */

$cfg = [
    'driver'   => getenv('SV_DRIVER') ?: 'auto',      // auto | mysql | sqlite
    'host'     => getenv('SV_DB_HOST') ?: 'localhost',
    'dbname'   => getenv('SV_DB_NAME') ?: 'sportsverse_db',
    'username' => getenv('SV_DB_USER') !== false ? getenv('SV_DB_USER') : 'root',
    'password' => getenv('SV_DB_PASS') !== false ? getenv('SV_DB_PASS') : '',
    'sqlite'   => __DIR__ . '/../data/sportsverse.sqlite',
];
if (is_file(__DIR__ . '/local.php')) {
    $cfg = array_merge($cfg, (array) require __DIR__ . '/local.php');
}

$opts = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$pdo = null;
$DB_DRIVER = null;

if ($cfg['driver'] !== 'sqlite' && in_array('mysql', PDO::getAvailableDrivers(), true)) {
    try {
        try {
            $pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset=utf8mb4",
                $cfg['username'], $cfg['password'], $opts);
        } catch (PDOException $e) {
            if ((int) $e->getCode() !== 1049) { throw $e; }   // 1049 = unknown database
            $root = new PDO("mysql:host={$cfg['host']};charset=utf8mb4", $cfg['username'], $cfg['password'], $opts);
            $root->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset=utf8mb4",
                $cfg['username'], $cfg['password'], $opts);
        }
        $DB_DRIVER = 'mysql';
    } catch (PDOException $e) {
        if ($cfg['driver'] === 'mysql') {
            die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
        }
        $pdo = null;   // fall back to SQLite
    }
}

if ($pdo === null) {
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        die('No database available: start MySQL (XAMPP) or enable the pdo_sqlite PHP extension.');
    }
    if (!is_dir(dirname($cfg['sqlite']))) { mkdir(dirname($cfg['sqlite']), 0775, true); }
    $pdo = new PDO('sqlite:' . $cfg['sqlite'], null, null, $opts);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $DB_DRIVER = 'sqlite';
}

require_once __DIR__ . '/../lib/schema.php';
sv_ensure_schema($pdo, $DB_DRIVER);
