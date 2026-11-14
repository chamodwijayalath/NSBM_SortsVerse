<?php
require_once __DIR__ . "/_guard.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tournament_id = filter_input(
        INPUT_POST,
        "tournament_id",
        FILTER_VALIDATE_INT
    );

    $match_id = filter_input(
        INPUT_POST,
        "match_id",
        FILTER_VALIDATE_INT
    );

    $round_name = trim($_POST["round_name"] ?? "");
    $fixture_date = $_POST["fixture_date"] ?? "";

    if (
        !$tournament_id ||
        !$match_id ||
        $round_name === "" ||
        $fixture_date === ""
    ) {
        $message = "Please fill in all fields correctly.";
    } else {
        try {
            $sql = "INSERT INTO fixtures
                    (tournament_id, match_id, round_name, fixture_date)
                    VALUES
                    (:tournament_id, :match_id, :round_name, :fixture_date)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ":tournament_id" => $tournament_id,
                ":match_id" => $match_id,
                ":round_name" => $round_name,
                ":fixture_date" => $fixture_date
            ]);

            $message = "Fixture added successfully.";

        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                $message = "Invalid tournament or match ID, or duplicate fixture.";
            } else {
                error_log($e->getMessage());
                $message = "Unable to add fixture.";
            }
        }
    }
}

$tournaments = $pdo->query(
    "SELECT tournament_id, tournament_name FROM tournaments"
)->fetchAll();

$matches = $pdo->query(
    "SELECT match_id, match_date, venue FROM matches"
)->fetchAll();

$sql = "SELECT f.fixture_id,
               t.tournament_name,
               f.match_id,
               f.round_name,
               f.fixture_date
        FROM fixtures f
        JOIN tournaments t
          ON f.tournament_id = t.tournament_id
        ORDER BY f.fixture_date";

$fixtures = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fixtures</title>
</head>
<body>
<?php admin_nav($admin_page); ?>

<h1>Tournament Fixtures</h1>

<p><?= htmlspecialchars($message) ?></p>

<form method="POST">

    <label>Tournament:</label>

    <select name="tournament_id" required>
        <option value="">Select Tournament</option>

        <?php foreach ($tournaments as $t): ?>
            <option value="<?= $t['tournament_id'] ?>">
                <?= htmlspecialchars($t['tournament_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <br><br>

    <label>Match:</label>

    <select name="match_id" required>
        <option value="">Select Match</option>

        <?php foreach ($matches as $m): ?>
            <option value="<?= $m['match_id'] ?>">
                <?= htmlspecialchars(
                    $m['match_date'] . " - " . $m['venue']
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <br><br>

    <label>Round:</label>
    <input type="text" name="round_name" required>

    <br><br>

    <label>Fixture Date:</label>
    <input type="datetime-local" name="fixture_date" required>

    <br><br>

    <button type="submit">Add Fixture</button>

</form>

<h2>Fixture List</h2>

<table border="1" cellpadding="8">
    <tr>
        <th>Fixture ID</th>
        <th>Tournament</th>
        <th>Match ID</th>
        <th>Round</th>
        <th>Date</th>
    </tr>

    <?php foreach ($fixtures as $f): ?>
        <tr>
            <td><?= $f['fixture_id'] ?></td>
            <td><?= htmlspecialchars($f['tournament_name']) ?></td>
            <td><?= $f['match_id'] ?></td>
            <td><?= htmlspecialchars($f['round_name'] ?? "") ?></td>
            <td><?= htmlspecialchars($f['fixture_date']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>