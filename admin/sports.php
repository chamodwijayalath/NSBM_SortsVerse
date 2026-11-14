<?php
require_once __DIR__ . "/_guard.php";

$message = "";


if (isset($_POST["add_sport"])) {
    $sport_name = trim($_POST["sport_name"]);
    $description = trim($_POST["description"]);

    if ($sport_name === "") {
        $message = "Sport name is required.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO sports (sport_name, description)
                 VALUES (?, ?)"
            );

            $stmt->execute([$sport_name, $description]);

            header("Location: sports.php?message=added");
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "Could not add sport.";
        }
    }
}

if (isset($_POST["update_sport"])) {
    $sport_id = (int) $_POST["sport_id"];
    $sport_name = trim($_POST["sport_name"]);
    $description = trim($_POST["description"]);

    if ($sport_id <= 0 || $sport_name === "") {
        $message = "Please enter valid details.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "UPDATE sports
                 SET sport_name = ?, description = ?
                 WHERE sport_id = ?"
            );

            $stmt->execute([
                $sport_name,
                $description,
                $sport_id
            ]);

            header("Location: sports.php?message=updated");
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "Could not update sport.";
        }
    }
}


if (isset($_POST["delete_sport"])) {
    $sport_id = (int) $_POST["sport_id"];

    if ($sport_id > 0) {
        try {
            $stmt = $pdo->prepare(
                "DELETE FROM sports WHERE sport_id = ?"
            );

            $stmt->execute([$sport_id]);

            header("Location: sports.php?message=deleted");
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "Cannot delete this sport because clubs may use it.";
        }
    }
}


if (isset($_GET["message"])) {
    $messages = [
        "added" => "Sport added successfully!",
        "updated" => "Sport updated successfully!",
        "deleted" => "Sport deleted successfully!"
    ];

    $message = $messages[$_GET["message"]] ?? "";
}


$stmt = $pdo->query(
    "SELECT * FROM sports ORDER BY sport_id"
);

$sports = $stmt->fetchAll(PDO::FETCH_ASSOC);


$edit_sport = null;

if (isset($_GET["edit"])) {
    $edit_id = (int) $_GET["edit"];

    $stmt = $pdo->prepare(
        "SELECT * FROM sports WHERE sport_id = ?"
    );

    $stmt->execute([$edit_id]);

    $edit_sport = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>SportsVerse - Sports Management</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f4f8;
            padding: 25px;
        }

        .container {
            max-width: 900px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 8px;
        }

        h1, h2 {
            color: #203864;
        }

        input, textarea {
            width: 100%;
            padding: 10px;
            margin: 8px 0 15px;
            box-sizing: border-box;
        }

        button, .button {
            padding: 9px 13px;
            border: none;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            cursor: pointer;
            display: inline-block;
        }

        .add {
            background: green;
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
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #203864;
            color: white;
        }

        form.inline {
            display: inline;
        }

        @media (max-width: 650px) {
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

    <h1>SportsVerse - Sports Management</h1>

    <?php if ($message !== ""): ?>
        <div class="message">
            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <!-- CREATE / UPDATE FORM -->
    <div class="card">

        <?php if ($edit_sport): ?>

            <h2>Edit Sport</h2>

            <form method="POST" action="sports.php">

                <input type="hidden"
                       name="sport_id"
                       value="<?= (int) $edit_sport["sport_id"] ?>">

                <label>Sport Name</label>

                <input type="text"
                       name="sport_name"
                       required
                       value="<?= htmlspecialchars(
                           $edit_sport["sport_name"],
                           ENT_QUOTES,
                           "UTF-8"
                       ) ?>">

                <label>Description</label>

                <textarea name="description"><?= htmlspecialchars(
                    $edit_sport["description"] ?? "",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?></textarea>

                <button type="submit"
                        name="update_sport"
                        class="edit">
                    Update Sport
                </button>

                <a href="sports.php" class="button cancel">
                    Cancel
                </a>

            </form>

        <?php else: ?>

            <h2>Add New Sport</h2>

            <form method="POST" action="sports.php">

                <label>Sport Name</label>

                <input type="text"
                       name="sport_name"
                       required
                       placeholder="Enter sport name">

                <label>Description</label>

                <textarea name="description"
                          placeholder="Enter description"></textarea>

                <button type="submit"
                        name="add_sport"
                        class="add">
                    Add Sport
                </button>

            </form>

        <?php endif; ?>

    </div>

    <!-- READ - SPORTS LIST -->
    <div class="card">

        <h2>All Sports</h2>

        <div class="table-wrapper">

            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Sport Name</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>

                <?php if (count($sports) > 0): ?>

                    <?php foreach ($sports as $sport): ?>

                        <tr>
                            <td>
                                <?= (int) $sport["sport_id"] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $sport["sport_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $sport["description"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <a class="button edit"
                                   href="sports.php?edit=<?= (int) $sport["sport_id"] ?>">
                                    Edit
                                </a>

                                <form class="inline"
                                      method="POST"
                                      action="sports.php"
                                      onsubmit="return confirm('Delete this sport?');">

                                    <input type="hidden"
                                           name="sport_id"
                                           value="<?= (int) $sport["sport_id"] ?>">

                                    <button type="submit"
                                            name="delete_sport"
                                            class="delete">
                                        Delete
                                    </button>

                                </form>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="4">No sports found.</td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>
    </div>

</div>
</body>
</html>