<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_ui.php';
$msg = '';
$statuses = ['Open', 'In use', 'Maintenance'];
function fac_input(): array
{
    return [trim((string) ($_POST['facility_name'] ?? '')), trim((string) ($_POST['location'] ?? '')),
            (int) ($_POST['open_hour'] ?? 0), (int) ($_POST['close_hour'] ?? 0), (string) ($_POST['status'] ?? '')];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';
    try {
        if ($do === 'add' || $do === 'update') {
            [$name, $loc, $open, $close, $status] = fac_input();
            if ($name === '' || mb_strlen($name) > 100 || $open < 0 || $open > 23 || $close < 1 || $close > 24 || $close <= $open || !in_array($status, $statuses, true)) {
                $msg = 'Enter a name, valid opening/closing hours (closing after opening) and a status.';
            } elseif ($do === 'add') {
                $pdo->prepare('INSERT INTO facilities (facility_name, location, open_hour, close_hour, status) VALUES (?,?,?,?,?)')->execute([$name, $loc, $open, $close, $status]);
                $msg = 'Facility added.';
            } else {
                $pdo->prepare('UPDATE facilities SET facility_name=?, location=?, open_hour=?, close_hour=?, status=? WHERE facility_id=?')
                    ->execute([$name, $loc, $open, $close, $status, (int) $_POST['facility_id']]);
                $msg = 'Facility updated.';
            }
        } elseif ($do === 'delete') {
            $id = (int) $_POST['facility_id'];
            $pdo->prepare('DELETE FROM bookings WHERE facility_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM facilities WHERE facility_id = ?')->execute([$id]);
            $msg = 'Facility deleted (with its bookings).';
        }
    } catch (PDOException $ex) { error_log($ex->getMessage()); $msg = 'Could not save (is the name already used?).'; }
}
$rows = $pdo->query('SELECT * FROM facilities ORDER BY facility_id')->fetchAll();
$hours = function (string $field, int $sel) {
    $o = '';
    for ($h = ($field === 'close_hour' ? 1 : 0); $h <= ($field === 'close_hour' ? 24 : 23); $h++) {
        $label = (($h % 24) + 11) % 12 + 1 . ':00 ' . (($h % 24) < 12 ? 'AM' : 'PM');
        $o .= '<option value="' . $h . '"' . ($h === $sel ? ' selected' : '') . '>' . $label . '</option>';
    }
    return '<select name="' . $field . '" style="width:auto">' . $o . '</select>';
};
$statusSel = function (string $cur) use ($statuses) {
    $o = '';
    foreach ($statuses as $s) { $o .= '<option' . ($s === $cur ? ' selected' : '') . '>' . $s . '</option>'; }
    return '<select name="status" style="width:auto">' . $o . '</select>';
};
ui_head('Facilities');
echo '<h1>Campus facilities</h1>';
if ($msg) echo '<div class="msg">' . e($msg) . '</div>';
echo '<div class="card"><h2>Add a facility</h2><form method="post"><input type="hidden" name="do" value="add">
  <label>Name</label><input name="facility_name" required placeholder="Tennis court">
  <label>Location</label><input name="location" placeholder="Outdoor">
  <label>Opens</label>' . $hours('open_hour', 6) . '<label>Closes</label>' . $hours('close_hour', 22) . '
  <label>Status</label>' . $statusSel('Open') . '<p><button class="ok">Add facility</button></p></form></div>';
echo '<div class="card"><h2>Edit facilities</h2><p style="font-size:13px;color:#64748b">Changes show on the public Facilities page (it refreshes automatically within a minute). Students cannot book a facility set to Maintenance.</p>
  <table><tr><th>Name</th><th>Location</th><th>Opens</th><th>Closes</th><th>Status</th><th></th></tr>';
foreach ($rows as $r) {
    $id = (int) $r['facility_id'];
    echo '<tr><form method="post"><input type="hidden" name="facility_id" value="' . $id . '">
      <td><input name="facility_name" value="' . e($r['facility_name']) . '" required></td>
      <td><input name="location" value="' . e($r['location']) . '"></td>
      <td>' . $hours('open_hour', (int) $r['open_hour']) . '</td><td>' . $hours('close_hour', (int) $r['close_hour']) . '</td>
      <td>' . $statusSel($r['status']) . '</td>
      <td style="white-space:nowrap"><button class="ok" name="do" value="update">Save</button>
      <button class="no" name="do" value="delete" formnovalidate onclick="return confirm(\'Delete this facility and all its bookings?\')">Delete</button></td></form></tr>';
}
echo '</table></div>';
ui_foot();
