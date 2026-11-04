<?php
/* Admin-only guard. Include at the top of every admin page (replaces config/database.php). */
require_once __DIR__ . '/../lib/bootstrap.php';
if (!sv_is_admin()) {
    http_response_code(403);
    ?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin only</title>
    <style>body{font-family:Arial,sans-serif;background:#0B132B;color:#e5e7eb;display:grid;place-items:center;min-height:100vh;margin:0}
    .b{background:#1C2541;border:1px solid #2E3B63;border-radius:16px;padding:32px;max-width:380px;text-align:center}
    a{display:inline-block;margin-top:16px;background:#0F8A5F;color:#fff;padding:10px 18px;border-radius:10px;text-decoration:none}</style></head>
    <body><div class="b"><h2>Admin area</h2><p>Log in with an admin account on the main site first.<br><small>Demo: admin@example.com / admin123</small></p><a href="../index.php">Go to SportsVerse</a></div></body></html>
    <?php
    exit;
}
// --- CSRF: every POST must carry the session token; the token is injected into every <form method="post"> automatically
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(403);
        exit('Invalid or missing security token. Go back, refresh the page and try again.');
    }
}
ob_start(function ($html) {
    $tok = '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) . '">';
    return preg_replace('/(<form\b[^>]*\bmethod\s*=\s*["\']?post["\']?[^>]*>)/i', '$1' . $tok, $html);
});
$admin_page = basename($_SERVER['SCRIPT_NAME'], '.php');
function admin_nav(string $current): void
{
    $items = ['index' => 'Dashboard', 'bookings' => 'Bookings', 'facilities' => 'Facilities', 'members' => 'Club members', 'clubs' => 'Clubs', 'sports' => 'Sports',
              'matches' => 'Matches', 'results' => 'Results', 'tournaments' => 'Tournaments', 'fixtures' => 'Fixtures', 'users' => 'Users'];
    echo '<nav style="background:#0B132B;padding:10px 20px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-family:Arial,sans-serif;position:sticky;top:0;z-index:50">';
    echo '<a href="../index.php" style="color:#3DD6A0;text-decoration:none;font-weight:bold;margin-right:12px">&larr; SportsVerse</a>';
    foreach ($items as $f => $label) {
        $on = $f === $current;
        echo '<a href="' . $f . '.php" style="color:' . ($on ? '#fff' : '#9ca3af') . ';background:' . ($on ? '#1C2541' : 'transparent')
           . ';padding:6px 10px;border-radius:8px;text-decoration:none;font-size:14px">' . $label . '</a>';
    }
    echo '</nav>';
}
