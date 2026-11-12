<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_ui.php';
$msg = '';
$statuses = ['Planned', 'Ongoing', 'Completed'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';
    try {
        if ($do === 'add') {
            $name = trim((string) $_POST['tournament_name']);
            $sid = (int) $_POST['sport_id'];
            $s = (string) $_POST['start_date']; $en = (string) $_POST['end_date'];
            if ($name === '' || $sid <= 0 || !strtotime($s) || !strtotime($en) || $en < $s) { $msg = 'Enter a name, a sport and valid dates.'; }
            else {
                $pdo->prepare('INSERT INTO tournaments (tournament_name, sport_id, start_date, end_date, status) VALUES (?,?,?,?,?)')->execute([$name, $sid, $s, $en, 'Planned']);
                $msg = 'Tournament created.';
            }
        } elseif ($do === 'status' && in_array($_POST['status'] ?? '', $statuses, true)) {
            $pdo->prepare('UPDATE tournaments SET status = ? WHERE tournament_id = ?')->execute([$_POST['status'], (int) $_POST['tournament_id']]);
            $msg = 'Status updated.';
        } elseif ($do === 'team') {
            $pdo->prepare('INSERT INTO tournament_teams (tournament_id, club_id) VALUES (?,?)')->execute([(int) $_POST['tournament_id'], (int) $_POST['club_id']]);
            $msg = 'Team registered.';
        } elseif ($do === 'delete') {
            $id = (int) $_POST['tournament_id'];
            foreach (['fixtures', 'tournament_teams', 'tournaments'] as $t) { $pdo->prepare("DELETE FROM $t WHERE tournament_id = ?")->execute([$id]); }
            $msg = 'Tournament deleted.';
        }
    } catch (PDOException $ex) { error_log($ex->getMessage()); $msg = 'Could not save (duplicate team?).'; }
}
$sports = $pdo->query('SELECT sport_id, sport_name FROM sports ORDER BY sport_name')->fetchAll();
$clubs = $pdo->query('SELECT club_id, club_name FROM clubs ORDER BY club_name')->fetchAll();
$rows = $pdo->query('SELECT t.*, s.sport_name, (SELECT COUNT(*) FROM tournament_teams x WHERE x.tournament_id = t.tournament_id) AS teams FROM tournaments t JOIN sports s ON s.sport_id = t.sport_id ORDER BY t.start_date DESC')->fetchAll();
ui_head('Tournaments');
echo '<h1>Tournaments</h1>';
if ($msg) echo '<div class="msg">' . e($msg) . '</div>';
$so = ''; foreach ($sports as $s) $so .= '<option value="' . (int) $s['sport_id'] . '">' . e($s['sport_name']) . '</option>';
$co = ''; foreach ($clubs as $c) $co .= '<option value="' . (int) $c['club_id'] . '">' . e($c['club_name']) . '</option>';
echo '<div class="card"><h2>New tournament</h2><form method="post"><input type="hidden" name="do" value="add">
  <label>Name</label><input name="tournament_name" required><label>Sport</label><select name="sport_id">' . $so . '</select>
  <label>Start date</label><input type="date" name="start_date" required><label>End date</label><input type="date" name="end_date" required>
  <p><button class="ok">Create</button></p></form></div>';
echo '<div class="card"><table><tr><th>Name</th><th>Sport</th><th>Dates</th><th>Teams</th><th>Status</th><th>Add team</th><th></th></tr>';
foreach ($rows as $r) {
    $id = (int) $r['tournament_id'];
    echo '<tr><td>' . e($r['tournament_name']) . '</td><td>' . e($r['sport_name']) . '</td><td>' . e($r['start_date']) . ' &rarr; ' . e($r['end_date']) . '</td><td>' . (int) $r['teams'] . '</td><td>
      <form class="inline" method="post"><input type="hidden" name="do" value="status"><input type="hidden" name="tournament_id" value="' . $id . '"><select name="status" onchange="this.form.submit()" style="width:auto">';
    foreach ($statuses as $s) echo '<option' . ($s === $r['status'] ? ' selected' : '') . '>' . $s . '</option>';
    echo '</select></form></td><td><form class="inline" method="post"><input type="hidden" name="do" value="team"><input type="hidden" name="tournament_id" value="' . $id . '"><select name="club_id" style="width:auto">' . $co . '</select> <button class="ok">Add</button></form></td>
      <td><form class="inline" method="post" onsubmit="return confirm(\'Delete tournament and its fixtures?\')"><input type="hidden" name="do" value="delete"><input type="hidden" name="tournament_id" value="' . $id . '"><button class="no">Delete</button></form></td></tr>';
}
echo '</table></div><p style="font-size:13px;color:#64748b">Attach matches to a tournament on the <a href="fixtures.php">Fixtures</a> page.</p>';
ui_foot();
