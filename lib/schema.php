<?php
/*
 * Creates all tables (if missing), applies small migrations and seeds demo data.
 * Works on MySQL/MariaDB and SQLite. Safe to run on every request (cheap check first).
 */

function sv_ensure_schema(PDO $pdo, string $driver): void
{
    $my = $driver === 'mysql';
    $pk = $my ? 'INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $engine = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci' : '';

    // Fast path: already installed
    try {
        $pdo->query('SELECT 1 FROM bookings LIMIT 1');
        $pdo->query('SELECT is_external FROM clubs LIMIT 1');
        $installed = true;
    } catch (PDOException $e) {
        $installed = false;
    }
    if ($installed) {
        return;
    }

    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            user_id $pk,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            student_id VARCHAR(50) DEFAULT NULL UNIQUE,
            role VARCHAR(20) DEFAULT 'student',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )$engine",
        "CREATE TABLE IF NOT EXISTS sports (
            sport_id $pk,
            sport_name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT DEFAULT NULL
        )$engine",
        "CREATE TABLE IF NOT EXISTS clubs (
            club_id $pk,
            club_name VARCHAR(100) NOT NULL,
            sport_id INT(11) NOT NULL,
            description TEXT DEFAULT NULL,
            training_schedule VARCHAR(255) DEFAULT NULL,
            max_members INT(11) DEFAULT 50,
            is_external TINYINT(1) NOT NULL DEFAULT 0,
            FOREIGN KEY (sport_id) REFERENCES sports (sport_id)
        )$engine",
        "CREATE TABLE IF NOT EXISTS club_members (
            club_member_id $pk,
            user_id INT(11) NOT NULL,
            club_id INT(11) NOT NULL,
            joined_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'pending',
            UNIQUE (user_id, club_id),
            FOREIGN KEY (user_id) REFERENCES users (user_id),
            FOREIGN KEY (club_id) REFERENCES clubs (club_id)
        )$engine",
        "CREATE TABLE IF NOT EXISTS matches (
            match_id $pk,
            home_club_id INT(11) NOT NULL,
            away_club_id INT(11) NOT NULL,
            match_date DATETIME NOT NULL,
            venue VARCHAR(150) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'Scheduled',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (home_club_id) REFERENCES clubs (club_id),
            FOREIGN KEY (away_club_id) REFERENCES clubs (club_id)
        )$engine",
        "CREATE TABLE IF NOT EXISTS results (
            result_id $pk,
            match_id INT(11) NOT NULL UNIQUE,
            home_score INT(11) NOT NULL DEFAULT 0,
            away_score INT(11) NOT NULL DEFAULT 0,
            winner_club_id INT(11) DEFAULT NULL,
            result_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (match_id) REFERENCES matches (match_id),
            FOREIGN KEY (winner_club_id) REFERENCES clubs (club_id)
        )$engine",
        "CREATE TABLE IF NOT EXISTS tournaments (
            tournament_id $pk,
            tournament_name VARCHAR(150) NOT NULL,
            sport_id INT(11) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'Planned',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sport_id) REFERENCES sports (sport_id)
        )$engine",
        "CREATE TABLE IF NOT EXISTS tournament_teams (
            tournament_team_id $pk,
            tournament_id INT(11) NOT NULL,
            club_id INT(11) NOT NULL,
            registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (tournament_id, club_id),
            FOREIGN KEY (tournament_id) REFERENCES tournaments (tournament_id),
            FOREIGN KEY (club_id) REFERENCES clubs (club_id)
        )$engine",
        "CREATE TABLE IF NOT EXISTS fixtures (
            fixture_id $pk,
            tournament_id INT(11) NOT NULL,
            match_id INT(11) NOT NULL,
            round_name VARCHAR(50) DEFAULT NULL,
            fixture_date DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (tournament_id, match_id),
            FOREIGN KEY (tournament_id) REFERENCES tournaments (tournament_id),
            FOREIGN KEY (match_id) REFERENCES matches (match_id)
        )$engine",
        "CREATE TABLE IF NOT EXISTS facilities (
            facility_id $pk,
            facility_name VARCHAR(100) NOT NULL UNIQUE,
            location VARCHAR(100) DEFAULT NULL,
            open_hour INT(11) NOT NULL DEFAULT 6,
            close_hour INT(11) NOT NULL DEFAULT 22,
            status VARCHAR(20) NOT NULL DEFAULT 'Open',
            bookable TINYINT(1) NOT NULL DEFAULT 1
        )$engine",
        "CREATE TABLE IF NOT EXISTS bookings (
            booking_id $pk,
            user_id INT(11) NOT NULL,
            facility_id INT(11) NOT NULL,
            booking_date DATE NOT NULL,
            slot_hour INT(11) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users (user_id),
            FOREIGN KEY (facility_id) REFERENCES facilities (facility_id)
        )$engine",
    ];
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    // Migration: tables imported from the original phpMyAdmin dump may lack is_external
    $hasExt = false;
    if ($my) {
        $hasExt = (bool) $pdo->query("SHOW COLUMNS FROM clubs LIKE 'is_external'")->fetch();
    } else {
        foreach ($pdo->query('PRAGMA table_info(clubs)') as $c) {
            if ($c['name'] === 'is_external') { $hasExt = true; }
        }
    }
    if (!$hasExt) {
        $pdo->exec('ALTER TABLE clubs ADD COLUMN is_external TINYINT(1) NOT NULL DEFAULT 0');
    }

    sv_seed($pdo);
}

function sv_seed(PDO $pdo): void
{
    $count = fn(string $t, string $where = '1=1') => (int) $pdo->query("SELECT COUNT(*) FROM $t WHERE $where")->fetchColumn();
    $ins = function (string $table, array $row) use ($pdo): int {
        $cols = implode(',', array_keys($row));
        $ph = implode(',', array_fill(0, count($row), '?'));
        $pdo->prepare("INSERT INTO $table ($cols) VALUES ($ph)")->execute(array_values($row));
        return (int) $pdo->lastInsertId();
    };
    $d = fn(string $rel) => date('Y-m-d H:i:s', strtotime($rel));

    // ---- users (demo passwords: admin123 / student123) ---------------------------------
    $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stuHash = password_hash('student123', PASSWORD_DEFAULT);
    if ($count('users') === 0) {
        $ins('users', ['full_name' => 'Admin User', 'email' => 'admin@example.com', 'password_hash' => $adminHash, 'student_id' => 'ADMIN001', 'role' => 'admin']);
        $ins('users', ['full_name' => 'Kamal Perera', 'email' => 'kamal@example.com', 'password_hash' => $stuHash, 'student_id' => 'STU001', 'role' => 'student']);
        $ins('users', ['full_name' => 'Nimal Silva', 'email' => 'nimal@example.com', 'password_hash' => $stuHash, 'student_id' => 'STU002', 'role' => 'student']);
    } else {
        // The original SQL dump used placeholder hashes ("test_hash_123"); give those accounts real demo passwords
        $fix = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ? AND password_hash NOT LIKE '$%'");
        $fix->execute([$adminHash, 'admin@example.com']);
        $fix->execute([$stuHash, 'kamal@example.com']);
        $fix->execute([$stuHash, 'nimal@example.com']);
    }

    // ---- sports + NSBM clubs -----------------------------------------------------------
    if ($count('sports') === 0) {
        foreach (['Football', 'Basketball', 'Cricket', 'Badminton', 'Rugby'] as $s) {
            $ins('sports', ['sport_name' => $s, 'description' => "$s Sports Club"]);
        }
    }
    $sportId = fn(string $n) => (int) $pdo->query("SELECT sport_id FROM sports WHERE sport_name = " . $pdo->quote($n))->fetchColumn();
    if ($count('clubs') === 0) {
        $clubs = [
            ['Football Club', 'Football', 'NSBM Football Club', 'Mon & Thu, 4:00 PM', 50],
            ['Basketball Club', 'Basketball', 'NSBM Basketball Club', 'Tue & Fri, 3:00 PM', 40],
            ['Cricket Club', 'Cricket', 'NSBM Cricket Club', 'Wed, 4:00 PM', 50],
            ['Badminton Club', 'Badminton', 'NSBM Badminton Club', 'Thu, 3:00 PM', 30],
            ['Rugby Club', 'Rugby', 'NSBM Rugby Club', 'Mon & Wed, 5:30 PM', 40],
        ];
        foreach ($clubs as [$n, $s, $desc, $sch, $max]) {
            $ins('clubs', ['club_name' => $n, 'sport_id' => $sportId($s), 'description' => $desc, 'training_schedule' => $sch, 'max_members' => $max]);
        }
    }
    $clubId = fn(string $n) => (int) $pdo->query("SELECT club_id FROM clubs WHERE club_name = " . $pdo->quote($n))->fetchColumn();

    // ---- facilities --------------------------------------------------------------------
    if ($count('facilities') === 0) {
        foreach ([
            ['Badminton court 1', 'Indoor hall', 6, 22, 'Open'],
            ['Badminton court 2', 'Indoor hall', 6, 22, 'Open'],
            ['Basketball court', 'Outdoor', 6, 21, 'Open'],
            ['Futsal ground', 'Outdoor', 16, 22, 'Open'],
            ['Gym', 'Sports complex', 6, 21, 'Open'],
            ['Swimming pool', 'Aquatic centre', 6, 20, 'Maintenance'],
        ] as [$n, $loc, $o, $c, $st]) {
            $ins('facilities', ['facility_name' => $n, 'location' => $loc, 'open_hour' => $o, 'close_hour' => $c, 'status' => $st]);
        }
    }

    // ---- demo competition data (only once) ---------------------------------------------
    if ($count('clubs', 'is_external = 1') > 0) {
        return;
    }
    $ext = [
        ['SLIIT FC', 'Football'], ['UoM FC', 'Football'], ['UoC FC', 'Football'], ['UCSC FC', 'Football'],
        ['KDU FC', 'Football'], ['APIIT FC', 'Football'], ['Jaffna Uni FC', 'Football'],
        ['UoC Titans', 'Basketball'], ['KDU Hoops', 'Basketball'],
        ['SLIIT Shuttlers', 'Badminton'], ['SLIIT Rugby', 'Rugby'], ['UoM Rugby', 'Rugby'], ['UoC Cricket', 'Cricket'],
    ];
    foreach ($ext as [$n, $s]) {
        $ins('clubs', ['club_name' => $n, 'sport_id' => $sportId($s), 'description' => 'Visiting university team', 'training_schedule' => '', 'max_members' => 0, 'is_external' => 1]);
    }

    $match = function (string $home, string $away, string $when, string $venue, string $status, ?array $score = null) use ($ins, $clubId, $d, $pdo): int {
        $h = $clubId($home); $a = $clubId($away);
        $id = $ins('matches', ['home_club_id' => $h, 'away_club_id' => $a, 'match_date' => $d($when), 'venue' => $venue, 'status' => $status]);
        if ($score) {
            $win = $score[0] > $score[1] ? $h : ($score[1] > $score[0] ? $a : null);
            if ($status === 'Live') { $win = null; }
            $ins('results', ['match_id' => $id, 'home_score' => $score[0], 'away_score' => $score[1], 'winner_club_id' => $win]);
        }
        return $id;
    };

    // Completed
    $qf1 = $match('Football Club', 'SLIIT FC', '-6 days 16:00', 'Main Ground', 'Completed', [3, 1]);
    $qf2 = $match('UoM FC', 'UoC FC', '-6 days 18:00', 'Main Ground', 'Completed', [2, 0]);
    $qf4 = $match('APIIT FC', 'Jaffna Uni FC', '-5 days 16:00', 'Main Ground', 'Completed', [1, 2]);
    $match('Basketball Club', 'KDU Hoops', '-4 days 15:00', 'Indoor Arena', 'Completed', [58, 61]);
    $match('Badminton Club', 'SLIIT Shuttlers', '-7 days 15:00', 'Indoor Hall', 'Completed', [3, 0]);
    $match('Rugby Club', 'SLIIT Rugby', '-8 days 17:00', 'Main Ground', 'Completed', [21, 14]);
    // Live now
    $qf3 = $match('UCSC FC', 'KDU FC', '-1 hour', 'Main Ground', 'Live', [2, 1]);
    // Upcoming
    $sf1 = $match('Football Club', 'UoM FC', '+9 days 16:00', 'Main Ground', 'Scheduled');
    $match('Basketball Club', 'UoC Titans', '+2 days 09:00', 'Indoor Arena', 'Scheduled');
    $match('Rugby Club', 'UoM Rugby', '+3 days 14:00', 'Main Ground', 'Scheduled');
    $match('Badminton Club', 'SLIIT Shuttlers', '+4 days 17:00', 'Indoor Hall', 'Scheduled');
    $match('Cricket Club', 'UoC Cricket', '+6 days 10:00', 'Cricket Oval', 'Scheduled');

    $tid = $ins('tournaments', [
        'tournament_name' => 'Inter-University Cup', 'sport_id' => $sportId('Football'),
        'start_date' => date('Y-m-d', strtotime('-10 days')), 'end_date' => date('Y-m-d', strtotime('+20 days')), 'status' => 'Ongoing',
    ]);
    foreach (['Football Club', 'SLIIT FC', 'UoM FC', 'UoC FC', 'UCSC FC', 'KDU FC', 'APIIT FC', 'Jaffna Uni FC'] as $n) {
        $ins('tournament_teams', ['tournament_id' => $tid, 'club_id' => $clubId($n)]);
    }
    $fx = [[$qf1, 'Quarterfinal', '-6 days 16:00'], [$qf2, 'Quarterfinal', '-6 days 18:00'], [$qf3, 'Quarterfinal', '-1 hour'],
           [$qf4, 'Quarterfinal', '-5 days 16:00'], [$sf1, 'Semifinal', '+9 days 16:00']];
    foreach ($fx as [$m, $r, $w]) {
        $ins('fixtures', ['tournament_id' => $tid, 'match_id' => $m, 'round_name' => $r, 'fixture_date' => $d($w)]);
    }

    // ---- demo students + memberships so clubs / leaderboard look alive ------------------
    $stuHash = password_hash('student123', PASSWORD_DEFAULT);
    $uids = [];
    foreach (['Kasun Perera', 'Nethmi Fernando', 'Sahan Jayawardena', 'Chamod Silva', 'Ishara Weerasinghe', 'Dilini Rathnayake'] as $i => $n) {
        $email = strtolower(explode(' ', $n)[0]) . '@example.com';
        $uids[] = $ins('users', ['full_name' => $n, 'email' => $email, 'password_hash' => $stuHash, 'student_id' => 'STU' . str_pad((string) (10 + $i), 3, '0', STR_PAD_LEFT), 'role' => 'student']);
    }
    $allUsers = array_map('intval', $pdo->query("SELECT user_id FROM users WHERE role = 'student'")->fetchAll(PDO::FETCH_COLUMN));
    $nsbm = $pdo->query('SELECT club_id FROM clubs WHERE is_external = 0 ORDER BY club_id')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($allUsers as $i => $u) {
        foreach ([$i % 5, ($i + 2) % 5] as $k => $ci) {
            if (!isset($nsbm[$ci])) { continue; }
            try {
                $ins('club_members', ['user_id' => $u, 'club_id' => $nsbm[$ci], 'status' => ($k === 1 && $i % 3 === 0) ? 'pending' : 'approved']);
            } catch (PDOException $e) { /* duplicate - ignore */ }
        }
    }
}
