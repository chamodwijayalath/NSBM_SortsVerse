<?php
/* Shared bootstrap: database, session, auth helpers. */
require_once __DIR__ . '/../config/database.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function sv_user(): ?array
{
    global $pdo;
    static $cache = false;
    if ($cache !== false) { return $cache; }
    if (empty($_SESSION['user_id'])) { return $cache = null; }
    $st = $pdo->prepare('SELECT user_id, full_name, email, student_id, role FROM users WHERE user_id = ?');
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch() ?: null;
    if (!$u) { unset($_SESSION['user_id']); }
    return $cache = $u;
}

function sv_login(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function sv_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function sv_is_admin(): bool
{
    $u = sv_user();
    return $u && $u['role'] === 'admin';
}

/** Display name: our own clubs are shown as "NSBM Football Club", visiting teams as stored. */
function sv_club_label(array $c): string
{
    $n = $c['club_name'];
    return (!empty($c['is_external']) || stripos($n, 'NSBM') === 0) ? $n : 'NSBM ' . $n;
}
