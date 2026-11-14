<?php
require_once __DIR__ . "/_guard.php";

$message = "";

if (isset($_POST['update_status'])) {
    $id = (int) $_POST['club_member_id'];
    $status = $_POST['status'];

    if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $stmt = $pdo->prepare(
            "UPDATE club_members
             SET status = ?
             WHERE club_member_id = ?"
        );
        $stmt->execute([$status, $id]);
        $message = "Membership status updated successfully!";
    }
}


if (isset($_POST['delete_membership'])) {
    $id = (int) $_POST['club_member_id'];

    $stmt = $pdo->prepare(
        "DELETE FROM club_members WHERE club_member_id = ?"
    );
    $stmt->execute([$id]);
    $message = "Membership deleted successfully!";
}

$stmt = $pdo->query(
    "SELECT
        cm.club_member_id,
        u.full_name,
        c.club_name,
        cm.joined_date,
        cm.status
     FROM club_members cm
     JOIN users u ON cm.user_id = u.user_id
     JOIN clubs c ON cm.club_id = c.club_id
     ORDER BY cm.club_member_id DESC"
);

$memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Memberships - SportsVerse</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 25px;
        }

        h1 {
            color: #1d3557;
        }

        .container {
            max-width: 1000px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #1d3557;
            color: white;
        }

        button, select {
            padding: 8px;
            margin: 3px;
        }

        .message {
            background: #d8f3dc;
            padding: 10px;
            margin: 15px 0;
        }
    </style>
</head>

<body>
<?php admin_nav($admin_page); ?>
<div class="container">

    <h1>SportsVerse - Club Memberships</h1>

    <?php if ($message !== ""): ?>
        <p class="message">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Member Name</th>
            <th>Club Name</th>
            <th>Joined Date</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        </thead>

        <tbody>
        <?php if (count($memberships) > 0): ?>

            <?php foreach ($memberships as $member): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars((string)$member['club_member_id']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($member['full_name']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($member['club_name']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($member['joined_date']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($member['status']) ?>
                    </td>

                    <td>
                        <form method="POST">
                            <input type="hidden"
                                   name="club_member_id"
                                   value="<?= (int)$member['club_member_id'] ?>">

                            <select name="status">
                                <option value="pending"
                                    <?= $member['status'] === 'pending' ? 'selected' : '' ?>>
                                    Pending
                                </option>

                                <option value="approved"
                                    <?= $member['status'] === 'approved' ? 'selected' : '' ?>>
                                    Approved
                                </option>

                                <option value="rejected"
                                    <?= $member['status'] === 'rejected' ? 'selected' : '' ?>>
                                    Rejected
                                </option>
                            </select>

                            <button type="submit" name="update_status">
                                Update
                            </button>
                        </form>

                        <form method="POST"
                              onsubmit="return confirm('Delete this membership?');">

                            <input type="hidden"
                                   name="club_member_id"
                                   value="<?= (int)$member['club_member_id'] ?>">

                            <button type="submit" name="delete_membership">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="6">No memberships found.</td>
            </tr>

        <?php endif; ?>
        </tbody>
    </table>

</div>
</body>
</html>