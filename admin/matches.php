<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_ui.php';
$msg = '';
$statuses = ['Scheduled', 'Live', 'Completed', 'Cancelled'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';
    try {
        if ($do === 'add') {
            $h = (int) $_POST['home_club_id']; $a = (int) $_POST['away_club_id'];
            $when = str_replace('T', ' ', (string) $_POST['match_date']);
            $venue = trim((string) $_POST['venue']);
            if ($h <= 0 || $a <= 0 || $h === $a || $venue === '' || !strtotime($when)) { $msg = 'Pick two different clubs, a date and a venue.'; }
            else {
                $pdo->prepare('INSERT INTO matches (home_club_id, away_club_id, match_date, venue, status) VALUES (?,?,?,?,?)')
                    ->execute([$h, $a, date('Y-m-d H:i:s', strtotime($when)), $venue, 'Scheduled']);
                $msg = 'Match scheduled.';
            }
        } elseif ($do === 'status' && in_array($_POST['status'] ?? '', $statuses, true)) {
            $pdo->prepare('UPDATE matches SET status = ? WHERE match_id = ?')->execute([$_POST['status'], (int) $_POST['match_id']]);
            $msg = 'Status updated.';
        } elseif ($do === 'delete') {
            $id = (int) $_POST['match_id'];
            $pdo->prepare('DELETE FROM results WHERE match_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM fixtures WHERE match_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM matches WHERE match_id = ?')->execute([$id]);
            $msg = 'Match deleted.';
        }
    } catch (PDOException $ex) { error_log($ex->getMessage()); $msg = 'Could not save that change.'; }
}
$clubs = $pdo->query('SELECT club_id, club_name, is_external FROM clubs ORDER BY is_external, club_name')->fetchAll();
$rows = $pdo->query(
    "SELECT m.match_id, m.match_date, m.venue, m.status, h.club_name AS hn, a.club_name AS an, r.home_score, r.away_score
     FROM matches m JOIN clubs h ON h.club_id = m.home_club_id JOIN clubs a ON a.club_id = m.away_club_id
     LEFT JOIN results r ON r.match_id = m.match_id ORDER BY m.match_date DESC"
)->fetchAll();
ui_head('Matches');
echo '<h1>Matches</h1>';
if ($msg) echo '<div class="msg">' . e($msg) . '</div>';
$opts = '';
foreach ($clubs as $c) { $opts .= '<option value="' . (int) $c['club_id'] . '">' . e($c['club_name']) . ($c['is_external'] ? ' (visiting)' : '') . '</option>'; }
echo '<div class="card"><h2>Schedule a match</h2><form method="post"><input type="hidden" name="do" value="add">
  <label>Home club</label><select name="home_club_id" required>' . $opts . '</select>
  <label>Away club</label><select name="away_club_id" required>' . $opts . '</select>
  <label>Date &amp; time</label><input type="datetime-local" name="match_date" required>
  <label>Venue</label><input name="venue" required placeholder="Main Ground"><p><button class="ok">Add match</button></p></form></div>';
echo '<div class="card"><table><tr><th>Date</th><th>Match</th><th>Venue</th><th>Score</th><th>Status</th><th></th></tr>';
foreach ($rows as $r) {
    echo '<tr><td>' . e($r['match_date']) . '</td><td>' . e($r['hn']) . ' vs ' . e($r['an']) . '</td><td>' . e($r['venue']) . '</td><td>'
       . ($r['home_score'] === null ? '-' : (int) $r['home_score'] . ' - ' . (int) $r['away_score']) . '</td><td>
       <form class="inline" method="post"><input type="hidden" name="do" value="status"><input type="hidden" name="match_id" value="' . (int) $r['match_id'] . '">
       <select name="status" onchange="this.form.submit()" style="width:auto">';
    foreach ($statuses as $s) echo '<option' . ($s === $r['status'] ? ' selected' : '') . '>' . $s . '</option>';
    echo '</select></form></td><td><form class="inline" method="post" onsubmit="return confirm(\'Delete this match and its result?\')"><input type="hidden" name="do" value="delete"><input type="hidden" name="match_id" value="' . (int) $r['match_id'] . '"><button class="no">Delete</button></form></td></tr>';
}
echo '</table></div><p style="font-size:13px;color:#64748b">Enter scores on the <a href="results.php">Results</a> page.</p>';
ui_foot();
