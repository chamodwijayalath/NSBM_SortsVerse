<?php
require_once __DIR__ . "/_guard.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $match_id = filter_input(
        INPUT_POST,
        "match_id",
        FILTER_VALIDATE_INT
    );

    $home_score = filter_input(
        INPUT_POST,
        "home_score",
        FILTER_VALIDATE_INT
    );

    $away_score = filter_input(
        INPUT_POST,
        "away_score",
        FILTER_VALIDATE_INT
    );

    if (
        !$match_id ||
        $home_score === false ||
        $home_score === null ||
        $away_score === false ||
        $away_score === null ||
        $home_score < 0 ||
        $away_score < 0
    ) {
        $message = "Please enter valid match and score values.";
    } else {
        try {
            $sql = "SELECT home_club_id, away_club_id
                    FROM matches
                    WHERE match_id = :match_id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([":match_id" => $match_id]);

            $match = $stmt->fetch();

            if (!$match) {
                $message = "Match not found.";
            } else {
                $winner_club_id = null;

                if ($home_score > $away_score) {
                    $winner_club_id = $match["home_club_id"];
                } elseif ($away_score > $home_score) {
                    $winner_club_id = $match["away_club_id"];
                }

                // portable "upsert" (works on MySQL and SQLite)
                $pdo->prepare("DELETE FROM results WHERE match_id = :match_id")
                    ->execute([":match_id" => $match_id]);

                $stmt = $pdo->prepare(
                    "INSERT INTO results (match_id, home_score, away_score, winner_club_id)
                     VALUES (:match_id, :home_score, :away_score, :winner)"
                );

                $stmt->execute([
                    ":match_id" => $match_id,
                    ":home_score" => $home_score,
                    ":away_score" => $away_score,
                    ":winner" => $winner_club_id
                ]);

                $pdo->prepare(
                    "UPDATE matches
                     SET status = 'Completed'
                     WHERE match_id = :match_id"
                )->execute([":match_id" => $match_id]);

                $message = "Match result saved successfully.";
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "Unable to save result.";
        }
    }
}

$matches = $pdo->query(
    "SELECT match_id, match_date, venue
     FROM matches
     ORDER BY match_date DESC"
)->fetchAll();

$sql = "SELECT r.result_id,
               r.match_id,
               r.home_score,
               r.away_score,
               r.winner_club_id,
               home.club_name AS home_club,
               away.club_name AS away_club,
               winner.club_name AS winner
        FROM results r
        JOIN matches m
          ON r.match_id = m.match_id
        JOIN clubs home
          ON m.home_club_id = home.club_id
        JOIN clubs away
          ON m.away_club_id = away.club_id
        LEFT JOIN clubs winner
          ON r.winner_club_id = winner.club_id
        ORDER BY r.result_date DESC";

$results = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Match Results</title>
</head>
<body>
<?php admin_nav($admin_page); ?>

<h1>Match Results</h1>

<p><?= htmlspecialchars($message) ?></p>

<form method="POST">

    <label>Match:</label>

    <select name="match_id" required>
        <option value="">Select Match</option>

        <?php foreach ($matches as $m): ?>
            <option value="<?= $m['match_id'] ?>">
                <?= htmlspecialchars(
                    $m['match_id'] . " - " .
                    $m['match_date'] . " - " . $m['venue']
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <br><br>

    <label>Home Score:</label>
    <input type="number" name="home_score" min="0" required>

    <br><br>

    <label>Away Score:</label>
    <input type="number" name="away_score" min="0" required>

    <br><br>

    <button type="submit">Save Result</button>

</form>

<h2>Results List</h2>

<table border="1" cellpadding="8">
    <tr>
        <th>Match ID</th>
        <th>Home Club</th>
        <th>Score</th>
        <th>Away Club</th>
        <th>Winner</th>
    </tr>

    <?php foreach ($results as $r): ?>
        <tr>
            <td><?= $r['match_id'] ?></td>

            <td><?= htmlspecialchars($r['home_club']) ?></td>

            <td>
                <?= $r['home_score'] ?> -
                <?= $r['away_score'] ?>
            </td>

            <td><?= htmlspecialchars($r['away_club']) ?></td>

            <td>
                <?= htmlspecialchars(
                    $r['winner'] ?? "Draw"
                ) ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>