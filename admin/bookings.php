<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_ui.php';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['booking_id'] ?? 0);
    $act = $_POST['do'] ?? '';
    if ($act === 'approve' || $act === 'reject') {
        $status = $act === 'approve' ? 'approved' : 'rejected';
        $pdo->prepare('UPDATE bookings SET status = ? WHERE booking_id = ?')->execute([$status, $id]);
        $msg = "Booking $status.";
    } elseif ($act === 'delete') {
        $pdo->prepare('DELETE FROM bookings WHERE booking_id = ?')->execute([$id]);
        $msg = 'Booking deleted.';
    }
}
$rows = $pdo->query(
    "SELECT b.booking_id, b.booking_date, b.slot_hour, b.status, b.created_at, u.full_name, u.student_id, f.facility_name
     FROM bookings b JOIN users u ON u.user_id = b.user_id JOIN facilities f ON f.facility_id = b.facility_id
     ORDER BY (b.status = 'pending') DESC, b.booking_date DESC, b.slot_hour"
)->fetchAll();
ui_head('Bookings');
echo '<h1>Facility bookings</h1>';
if ($msg) echo '<div class="msg">' . e($msg) . '</div>';
echo '<div class="card"><table><tr><th>Student</th><th>Facility</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr>';
foreach ($rows as $r) {
    $h = (int) $r['slot_hour'];
    $t = (($h + 11) % 12 + 1) . ':00 ' . ($h < 12 ? 'AM' : 'PM');
    echo '<tr><td>' . e($r['full_name']) . ' <small>(' . e($r['student_id']) . ')</small></td><td>' . e($r['facility_name']) . '</td><td>' . e($r['booking_date']) . '</td><td>' . $t
       . '</td><td><span class="tag ' . e($r['status']) . '">' . e($r['status']) . '</span></td><td>';
    foreach ([['approve', 'ok', 'Approve'], ['reject', 'no', 'Reject'], ['delete', 'mute', 'Delete']] as [$d, $c, $l]) {
        if (($d === 'approve' && $r['status'] === 'approved') || ($d === 'reject' && $r['status'] === 'rejected')) continue;
        echo '<form class="inline" method="post"><input type="hidden" name="booking_id" value="' . (int) $r['booking_id'] . '"><button class="' . $c . '" name="do" value="' . $d . '">' . $l . '</button></form> ';
    }
    echo '</td></tr>';
}
if (!$rows) echo '<tr><td colspan="6">No bookings yet.</td></tr>';
echo '</table></div>';
ui_foot();
