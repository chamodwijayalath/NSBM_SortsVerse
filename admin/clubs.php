<?php
require_once __DIR__ . "/_guard.php";

$message = "";


if (isset($_POST["add_club"])) {

    $club_name = trim($_POST["club_name"]);
    $sport_id = (int) $_POST["sport_id"];
    $training_schedule = trim($_POST["training_schedule"]);
    $max_members = (int) $_POST["max_members"];

    if ($club_name === "" || $sport_id <= 0 || $max_members <= 0) {
        $message = "Please enter valid club details.";
    } else {
        try {
            $sql = "INSERT INTO clubs
                    (club_name, sport_id, training_schedule, max_members, is_external)
                    VALUES (?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $club_name,
                $sport_id,
                $training_schedule,
                $max_members,
                isset($_POST["is_external"]) ? 1 : 0
            ]);

            header("Location: clubs.php?message=added");
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "Could not add the club.";
        }
    }
}

if (isset($_POST["update_club"])) {

    $club_id = (int) $_POST["club_id"];
    $club_name = trim($_POST["club_name"]);
    $sport_id = (int) $_POST["sport_id"];
    $training_schedule = trim($_POST["training_schedule"]);
    $max_members = (int) $_POST["max_members"];

    if (
        $club_id <= 0 ||
        $club_name === "" ||
        $sport_id <= 0 ||
        $max_members <= 0
    ) {
        $message = "Please enter valid club details.";
    } else {
        try {
            $sql = "UPDATE clubs
                    SET club_name = ?,
                        sport_id = ?,
                        training_schedule = ?,
                        max_members = ?
                    WHERE club_id = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $club_name,
                $sport_id,
                $training_schedule,
                $max_members,
                $club_id
            ]);

            header("Location: clubs.php?message=updated");
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "Could not update the club.";
        }
    }
}


if (isset($_POST["delete_club"])) {

    $club_id = (int) $_POST["club_id"];

    if ($club_id > 0) {
        try {
            $stmt = $pdo->prepare(
                "DELETE FROM clubs WHERE club_id = ?"
            );

            $stmt->execute([$club_id]);

            header("Location: clubs.php?message=deleted");
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "Could not delete the club. It may have members.";
        }
    }
}


if (isset($_GET["message"])) {
    $messages = [
        "added" => "Club added successfully!",
        "updated" => "Club updated successfully!",
        "deleted" => "Club deleted successfully!"
    ];

    $message = $messages[$_GET["message"]] ?? "";
}


$stmt = $pdo->query(
    "SELECT clubs.club_id,
            clubs.club_name,
            clubs.sport_id,
            clubs.training_schedule,
            clubs.max_members,
            sports.sport_name
     FROM clubs
     INNER JOIN sports
     ON clubs.sport_id = sports.sport_id
     ORDER BY clubs.club_id"
);

$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Get Sports for the dropdown */
$sports = $pdo->query(
    "SELECT sport_id, sport_name FROM sports ORDER BY sport_name"
)->fetchAll(PDO::FETCH_ASSOC);


$edit_club = null;

if (isset($_GET["edit"])) {
    $edit_id = (int) $_GET["edit"];

    $stmt = $pdo->prepare(
        "SELECT * FROM clubs WHERE club_id = ?"
    );
    $stmt->execute([$edit_id]);

    $edit_club = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SportsVerse - Manage Clubs</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f5f9;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        h1, h2 {
            color: #203864;
        }

        .card {
            background: white;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px #ddd;
        }

        input, select {
            width: 100%;
            padding: 10px;
            margin: 7px 0 15px;
            box-sizing: border-box;
        }

        button, .button {
            display: inline-block;
            padding: 9px 14px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            cursor: pointer;
            font-size: 14px;
        }

        .add {
            background: #198754;
        }

        .edit {
            background: #0d6efd;
        }

        .delete {
            background: #dc3545;
        }

        .cancel {
            background: #6c757d;
        }

        .message {
            padding: 12px;
            background: #d1e7dd;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .error {
            background: #f8d7da;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #203864;
            color: white;
        }

        tr:nth-child(even) {
            background: #f8f9fa;
        }

        .actions {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        .actions form {
            margin: 0;
        }

        @media (max-width: 700px) {
            body {
                padding: 10px;
            }

            .table-wrapper {
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>
<?php admin_nav($admin_page); ?>

<div class="container">

    <h1>SportsVerse - Club Management</h1>

    <?php if ($message !== ""): ?>
        <div class="message">
            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <!-- CREATE / UPDATE FORM -->
    <div class="card">

        <?php if ($edit_club): ?>

            <h2>Edit Club</h2>

            <form method="POST" action="clubs.php">

                <input
                    type="hidden"
                    name="club_id"
                    value="<?= (int) $edit_club["club_id"] ?>"
                >

                <label>Club Name</label>
                <input
                    type="text"
                    name="club_name"
                    required
                    value="<?= htmlspecialchars(
                        $edit_club["club_name"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >

                <label>Sport</label>
                <select name="sport_id" required>
                    <?php foreach ($sports as $sport): ?>
                        <option
                            value="<?= (int) $sport["sport_id"] ?>"
                            <?= $sport["sport_id"] == $edit_club["sport_id"]
                                ? "selected"
                                : "" ?>
                        >
                            <?= htmlspecialchars(
                                $sport["sport_name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Training Schedule</label>
                <input
                    type="text"
                    name="training_schedule"
                    value="<?= htmlspecialchars(
                        $edit_club["training_schedule"] ?? "",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >

                <label>Maximum Members</label>
                <input
                    type="number"
                    name="max_members"
                    min="1"
                    required
                    value="<?= (int) $edit_club["max_members"] ?>"
                >

                <button
                    type="submit"
                    name="update_club"
                    class="edit"
                >
                    Update Club
                </button>

                <a href="clubs.php" class="button cancel">
                    Cancel
                </a>

            </form>

        <?php else: ?>

            <h2>Add New Club</h2>

            <form method="POST" action="clubs.php">

                <label>Club Name</label>
                <input
                    type="text"
                    name="club_name"
                    placeholder="Enter club name"
                    required
                >

                <label>Sport</label>
                <select name="sport_id" required>
                    <option value="">Select Sport</option>

                    <?php foreach ($sports as $sport): ?>
                        <option value="<?= (int) $sport["sport_id"] ?>">
                            <?= htmlspecialchars(
                                $sport["sport_name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

                <label>Training Schedule</label>
                <input
                    type="text"
                    name="training_schedule"
                    placeholder="e.g. Monday 4 PM"
                >

                <label>Maximum Members</label>
                <input
                    type="number"
                    name="max_members"
                    min="1"
                    value="30"
                    required
                >

                <label style="display:block;margin:10px 0">
                    <input type="checkbox" name="is_external" value="1" style="width:auto">
                    Visiting team (another university - hidden from the public club list)
                </label>

                <button
                    type="submit"
                    name="add_club"
                    class="add"
                >
                    Add Club
                </button>

            </form>

        <?php endif; ?>

    </div>

    <!-- READ - CLUB LIST -->
    <div class="card">

        <h2>All Clubs</h2>

        <div class="table-wrapper">

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Club Name</th>
                        <th>Sport</th>
                        <th>Training Schedule</th>
                        <th>Max Members</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($clubs) > 0): ?>

                    <?php foreach ($clubs as $club): ?>

                        <tr>
                            <td>
                                <?= (int) $club["club_id"] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $club["club_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $club["sport_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $club["training_schedule"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= (int) $club["max_members"] ?>
                            </td>

                            <td>
                                <div class="actions">

                                    <a
                                        class="button edit"
                                        href="clubs.php?edit=<?= (int) $club["club_id"] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="clubs.php"
                                        onsubmit="return confirm('Are you sure you want to delete this club?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="club_id"
                                            value="<?= (int) $club["club_id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="delete_club"
                                            class="delete"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                </div>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6">No clubs found.</td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </div>

</div>

</body>
</html>