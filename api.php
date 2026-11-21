<?php

require_once __DIR__ . '/lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(string $msg, int $code = 400): void
{
    out(['ok' => false, 'error' => $msg], $code);
}
function need_user(): array
{
    $u = sv_user();
    if (!$u) { fail('Please log in first.', 401); }
    return $u;
}

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$in = [];
if ($isPost) {
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') { fail('Bad request.', 403); }
    $in = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
    $action = $in['action'] ?? '';
} else {
    $action = $_GET['action'] ?? 'bootstrap';
}

function slot_range(array $f): array
{
    return range((int) $f['open_hour'], (int) $f['close_hour'] - 1);
}

try {
    switch ($action) {

        case 'register': {
            $name = trim($in['full_name'] ?? '');
            $email = strtolower(trim($in['email'] ?? ''));
            $sid = trim($in['student_id'] ?? '');
            $pw = (string) ($in['password'] ?? '');
            if ($name === '' || mb_strlen($name) > 100) { fail('Enter your full name.'); }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { fail('Enter a valid email address.'); }
            if ($sid === '' || mb_strlen($sid) > 50) { fail('Enter your student ID.'); }
            if (strlen($pw) < 6) { fail('Password must be at least 6 characters.'); }
            $st = $pdo->prepare('SELECT 1 FROM users WHERE email = ? OR student_id = ?');
            $st->execute([$email, $sid]);
            if ($st->fetch()) { fail('That email or student ID is already registered.'); }
            $pdo->prepare("INSERT INTO users (full_name, email, password_hash, student_id, role) VALUES (?,?,?,?, 'student')")
                ->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT), $sid]);
            sv_login((int) $pdo->lastInsertId());
            out(['ok' => true]);
        }

        case 'login': {
            $email = strtolower(trim($in['email'] ?? ''));
            $pw = (string) ($in['password'] ?? '');
           
            $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (id ' . ($DB_DRIVER === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ', k VARCHAR(255) NOT NULL, at_time INT NOT NULL)');
            $key = hash('sha256', $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
            $pdo->prepare('DELETE FROM login_attempts WHERE at_time < ?')->execute([time() - 600]);
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE k = ?');
            $cnt->execute([$key]);
            if ((int) $cnt->fetchColumn() >= 5) { fail('Too many failed attempts. Try again in 10 minutes.', 429); }
            $st = $pdo->prepare('SELECT user_id, password_hash FROM users WHERE email = ?');
            $st->execute([$email]);
            $row = $st->fetch();
            if (!$row || !password_verify($pw, $row['password_hash'])) {
                $pdo->prepare('INSERT INTO login_attempts (k, at_time) VALUES (?, ?)')->execute([$key, time()]);
                fail('Wrong email or password.', 401);
            }
            $pdo->prepare('DELETE FROM login_attempts WHERE k = ?')->execute([$key]);
            sv_login((int) $row['user_id']);
            out(['ok' => true]);
        }

        case 'logout':
            sv_logout();
            out(['ok' => true]);

        /* ------------------------------------------------------------ clubs */
        case 'join_club': {
            $u = need_user();
            $cid = (int) ($in['club_id'] ?? 0);
            $st = $pdo->prepare('SELECT club_id, max_members FROM clubs WHERE club_id = ? AND is_external = 0');
            $st->execute([$cid]);
            $club = $st->fetch();
            if (!$club) { fail('Club not found.', 404); }
            $st = $pdo->prepare('SELECT 1 FROM club_members WHERE user_id = ? AND club_id = ?');
            $st->execute([$u['user_id'], $cid]);
            if ($st->fetch()) { fail('You already requested to join this club.'); }
            $st = $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE club_id = ? AND status = 'approved'");
            $st->execute([$cid]);
            if ((int) $club['max_members'] > 0 && (int) $st->fetchColumn() >= (int) $club['max_members']) { fail('This club is full.'); }
            $pdo->prepare("INSERT INTO club_members (user_id, club_id, status) VALUES (?,?, 'pending')")->execute([$u['user_id'], $cid]);
            out(['ok' => true]);
        }

        case 'leave_club': {
            $u = need_user();
            $pdo->prepare('DELETE FROM club_members WHERE user_id = ? AND club_id = ?')->execute([$u['user_id'], (int) ($in['club_id'] ?? 0)]);
            out(['ok' => true]);
        }

        /* --------------------------------------------------------- bookings */
        case 'slots': {
            $fid = (int) ($_GET['facility_id'] ?? 0);
            $date = $_GET['date'] ?? '';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { fail('Bad date.'); }
            $st = $pdo->prepare('SELECT * FROM facilities WHERE facility_id = ?');
            $st->execute([$fid]);
            $f = $st->fetch();
            if (!$f) { fail('Facility not found.', 404); }
            $st = $pdo->prepare("SELECT slot_hour FROM bookings WHERE facility_id = ? AND booking_date = ? AND status IN ('pending','approved')");
            $st->execute([$fid, $date]);
            $taken = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
            $slots = [];
            foreach (slot_range($f) as $h) {
                $slots[] = ['hour' => $h, 'booked' => in_array($h, $taken, true)];
            }
            out(['ok' => true, 'status' => $f['status'], 'slots' => $slots]);
        }

        case 'book': {
            $u = need_user();
            $fid = (int) ($in['facility_id'] ?? 0);
            $date = (string) ($in['date'] ?? '');
            $hour = (int) ($in['hour'] ?? -1);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) { fail('Pick a valid date.'); }
            $today = date('Y-m-d');
            if ($date < $today || $date > date('Y-m-d', strtotime('+30 days'))) { fail('You can book up to 30 days ahead.'); }
            if ($date === $today && $hour <= (int) date('G')) { fail('That time has already started.'); }
            $st = $pdo->prepare('SELECT * FROM facilities WHERE facility_id = ?');
            $st->execute([$fid]);
            $f = $st->fetch();
            if (!$f) { fail('Facility not found.', 404); }
            if ($f['status'] === 'Maintenance') { fail('This facility is under maintenance.'); }
            if (!in_array($hour, slot_range($f), true)) { fail('That time is outside opening hours.'); }
            $st = $pdo->prepare("SELECT 1 FROM bookings WHERE facility_id = ? AND booking_date = ? AND slot_hour = ? AND status IN ('pending','approved')");
            $st->execute([$fid, $date, $hour]);
            if ($st->fetch()) { fail('Sorry, that slot was just taken.'); }
            $pdo->prepare("INSERT INTO bookings (user_id, facility_id, booking_date, slot_hour, status) VALUES (?,?,?,?, 'pending')")
                ->execute([$u['user_id'], $fid, $date, $hour]);
            out(['ok' => true]);
        }

        case 'cancel_booking': {
            $u = need_user();
            $pdo->prepare("DELETE FROM bookings WHERE booking_id = ? AND user_id = ? AND status = 'pending'")
                ->execute([(int) ($in['booking_id'] ?? 0), $u['user_id']]);
            out(['ok' => true]);
        }

        /* -------------------------------------------------------- bootstrap */
        case 'bootstrap': {
            $u = sv_user();

            // Clubs (NSBM clubs only) with approved member counts and the viewer's membership status
            $clubs = $pdo->query(
                "SELECT c.club_id, c.club_name, c.description, c.training_schedule, c.max_members, c.is_external,
                        s.sport_name,
                        (SELECT COUNT(*) FROM club_members m WHERE m.club_id = c.club_id AND m.status = 'approved') AS members
                 FROM clubs c JOIN sports s ON s.sport_id = c.sport_id
                 WHERE c.is_external = 0 ORDER BY c.club_id"
            )->fetchAll();
            $mine = [];
            if ($u) {
                $st = $pdo->prepare('SELECT club_id, status FROM club_members WHERE user_id = ?');
                $st->execute([$u['user_id']]);
                foreach ($st->fetchAll() as $r) { $mine[(int) $r['club_id']] = $r['status']; }
            }
            foreach ($clubs as &$c) {
                $c['club_id'] = (int) $c['club_id'];
                $c['name'] = sv_club_label($c);
                $c['members'] = (int) $c['members'];
                $c['max_members'] = (int) $c['max_members'];
                $c['my_status'] = $mine[$c['club_id']] ?? null;
                unset($c['club_name'], $c['is_external']);
            }
            unset($c);

            // Matches (+ result, sport)
            $rows = $pdo->query(
                "SELECT m.match_id, m.match_date, m.venue, m.status,
                        h.club_name AS h_name, h.is_external AS h_ext, a.club_name AS a_name, a.is_external AS a_ext,
                        h.club_id AS h_id, a.club_id AS a_id, s.sport_name,
                        r.home_score, r.away_score, r.winner_club_id
                 FROM matches m
                 JOIN clubs h ON h.club_id = m.home_club_id
                 JOIN clubs a ON a.club_id = m.away_club_id
                 JOIN sports s ON s.sport_id = h.sport_id
                 LEFT JOIN results r ON r.match_id = m.match_id
                 ORDER BY m.match_date"
            )->fetchAll();
            $matches = [];
            foreach ($rows as $r) {
                $matches[] = [
                    'id' => (int) $r['match_id'], 'date' => $r['match_date'], 'venue' => $r['venue'], 'status' => $r['status'],
                    'sport' => $r['sport_name'],
                    'home' => sv_club_label(['club_name' => $r['h_name'], 'is_external' => $r['h_ext']]),
                    'away' => sv_club_label(['club_name' => $r['a_name'], 'is_external' => $r['a_ext']]),
                    'home_id' => (int) $r['h_id'], 'away_id' => (int) $r['a_id'],
                    'home_score' => $r['home_score'] === null ? null : (int) $r['home_score'],
                    'away_score' => $r['away_score'] === null ? null : (int) $r['away_score'],
                    'winner_id' => $r['winner_club_id'] === null ? null : (int) $r['winner_club_id'],
                    'ours' => !$r['h_ext'] || !$r['a_ext'],
                ];
            }

            // Leaderboard from completed matches (win = 3, draw = 1)
            $table = [];
            foreach ($pdo->query('SELECT c.club_id, c.club_name, c.is_external, s.sport_name FROM clubs c JOIN sports s ON s.sport_id = c.sport_id')->fetchAll() as $c) {
                $table[(int) $c['club_id']] = ['club_id' => (int) $c['club_id'], 'name' => sv_club_label($c), 'sport' => $c['sport_name'],
                    'external' => (bool) $c['is_external'], 'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'points' => 0];
            }
            foreach ($matches as $m) {
                if ($m['status'] !== 'Completed' || $m['home_score'] === null) { continue; }
                foreach ([$m['home_id'], $m['away_id']] as $cid) {
                    $table[$cid]['played']++;
                    if ($m['winner_id'] === null) { $table[$cid]['draws']++; $table[$cid]['points'] += 1; }
                    elseif ($m['winner_id'] === $cid) { $table[$cid]['wins']++; $table[$cid]['points'] += 3; }
                    else { $table[$cid]['losses']++; }
                }
            }
            $leaderboard = array_values(array_filter($table, fn($t) => $t['played'] > 0));

            // Tournaments + fixtures + teams
            $tournaments = [];
            foreach ($pdo->query('SELECT t.*, s.sport_name FROM tournaments t JOIN sports s ON s.sport_id = t.sport_id ORDER BY t.start_date DESC')->fetchAll() as $t) {
                $tid = (int) $t['tournament_id'];
                $fx = $pdo->prepare('SELECT match_id, round_name, fixture_date FROM fixtures WHERE tournament_id = ? ORDER BY fixture_date');
                $fx->execute([$tid]);
                $teams = $pdo->prepare('SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = ?');
                $teams->execute([$tid]);
                $tournaments[] = [
                    'id' => $tid, 'name' => $t['tournament_name'], 'sport' => $t['sport_name'],
                    'start' => $t['start_date'], 'end' => $t['end_date'], 'status' => $t['status'],
                    'teams' => (int) $teams->fetchColumn(),
                    'fixtures' => array_map(fn($f) => ['match_id' => (int) $f['match_id'], 'round' => $f['round_name'] ?: 'Round', 'date' => $f['fixture_date']], $fx->fetchAll()),
                ];
            }

            // Facilities
            $facilities = array_map(function ($f) {
                $f['facility_id'] = (int) $f['facility_id'];
                $f['open_hour'] = (int) $f['open_hour'];
                $f['close_hour'] = (int) $f['close_hour'];
                return $f;
            }, $pdo->query('SELECT facility_id, facility_name, location, open_hour, close_hour, status FROM facilities ORDER BY facility_id')->fetchAll());

            // Viewer's bookings + passport
            $bookings = [];
            $passport = null;
            if ($u) {
                $st = $pdo->prepare(
                    "SELECT b.booking_id, b.booking_date, b.slot_hour, b.status, f.facility_name
                     FROM bookings b JOIN facilities f ON f.facility_id = b.facility_id
                     WHERE b.user_id = ? ORDER BY b.booking_date DESC, b.slot_hour DESC LIMIT 20"
                );
                $st->execute([$u['user_id']]);
                $bookings = $st->fetchAll();
                $approvedClubs = count(array_filter($mine, fn($s) => $s === 'approved'));
                $pending = count(array_filter($mine, fn($s) => $s === 'pending')) + count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
                $approvedBookings = count(array_filter($bookings, fn($b) => $b['status'] === 'approved'));
                $pts = $approvedClubs * 100 + $approvedBookings * 50;
                $level = 1 + intdiv($pts, 250);
                $passport = ['points' => $pts, 'level' => $level, 'next' => $level * 250, 'clubs' => $approvedClubs, 'bookings' => $approvedBookings, 'pending' => $pending];
            }

            out([
                'ok' => true,
                'user' => $u ? ['id' => (int) $u['user_id'], 'name' => $u['full_name'], 'email' => $u['email'], 'student_id' => $u['student_id'], 'role' => $u['role']] : null,
                'clubs' => $clubs, 'matches' => $matches, 'leaderboard' => $leaderboard, 'tournaments' => $tournaments,
                'facilities' => $facilities, 'bookings' => $bookings, 'passport' => $passport,
                'now' => date('Y-m-d H:i:s'),
            ]);
        }

        default:
            fail('Unknown action.', 404);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    fail('Server error. Please try again.', 500);
}
