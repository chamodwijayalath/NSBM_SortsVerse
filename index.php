<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_ui.php';
$n = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();
$stats = [
    'Users' => $n('SELECT COUNT(*) FROM users'),
    'Clubs' => $n('SELECT COUNT(*) FROM clubs WHERE is_external = 0'),
    'Pending club requests' => $n("SELECT COUNT(*) FROM club_members WHERE status = 'pending'"),
    'Pending bookings' => $n("SELECT COUNT(*) FROM bookings WHERE status = 'pending'"),
    'Upcoming matches' => $n("SELECT COUNT(*) FROM matches WHERE status = 'Scheduled'"),
    'Tournaments' => $n('SELECT COUNT(*) FROM tournaments'),
];
ui_head('Dashboard');
echo '<h1>Admin dashboard</h1><div class="grid" style="margin-bottom:22px">';
foreach ($stats as $k => $v) { echo '<div class="stat"><b>' . $v . '</b>' . e($k) . '</div>'; }
echo '</div><div class="card"><h2>Quick links</h2><p>
  <a class="btn" href="bookings.php">Approve bookings</a>
  <a class="btn" href="facilities.php">Edit facilities</a>
  <a class="btn" href="members.php">Approve club members</a>
  <a class="btn" href="matches.php">Schedule a match</a>
  <a class="btn" href="results.php">Enter a result</a></p>
  <p style="font-size:13px;color:#64748b">Database driver: ' . e($DB_DRIVER) . '</p></div>';
ui_foot();
