<?php
require_once __DIR__ . "/_guard.php";

$message = "";


if (isset($_POST['add_user'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $student_id = trim($_POST['student_id']);
    $role = $_POST['role'];
    $password = $_POST['password'];

    if (
        $full_name === "" ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        $student_id === "" ||
        $password === "" ||
        !in_array($role, ['student', 'admin'], true)
    ) {
        $message = "Please enter valid details in all fields.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users
                (full_name, email, password_hash, student_id, role)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $full_name,
                $email,
                $password_hash,
                $student_id,
                $role
            ]);

            header("Location: users.php?message=added");
            exit;
        } catch (PDOException $e) {
            $message = "Could not add user. Check whether the email or student ID already exists.";
        }
    }
}


if (isset($_POST['update_user'])) {
    $user_id = (int) $_POST['user_id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $student_id = trim($_POST['student_id']);
    $role = $_POST['role'];

    if (
        $user_id <= 0 ||
        $full_name === "" ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        $student_id === "" ||
        !in_array($role, ['student', 'admin'], true)
    ) {
        $message = "Please enter valid details.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "UPDATE users
                 SET full_name = ?, email = ?, student_id = ?, role = ?
                 WHERE user_id = ?"
            );

            $stmt->execute([
                $full_name,
                $email,
                $student_id,
                $role,
                $user_id
            ]);

            header("Location: users.php?message=updated");
            exit;
        } catch (PDOException $e) {
            $message = "Could not update user. Check whether the email or student ID is already used.";
        }
    }
}


if (isset($_POST['delete_user'])) {
    $user_id = (int) $_POST['user_id'];

    if ($user_id === (int) $_SESSION['user_id']) {
        $message = "You cannot delete your own account.";
    } else try {
        $stmt = $pdo->prepare(
            "DELETE FROM users WHERE user_id = ?"
        );
        $stmt->execute([$user_id]);

        header("Location: users.php?message=deleted");
        exit;
    } catch (PDOException $e) {
        $message = "Cannot delete this user because membership records may reference this account.";
    }
}


if (isset($_GET['message'])) {
    $messages = [
        'added' => 'User added successfully!',
        'updated' => 'User updated successfully!',
        'deleted' => 'User deleted successfully!'
    ];

    $message = $messages[$_GET['message']] ?? "";
}


$edit_user = null;

if (isset($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];

    $stmt = $pdo->prepare(
        "SELECT user_id, full_name, email, student_id, role
         FROM users
         WHERE user_id = ?"
    );
    $stmt->execute([$edit_id]);

    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}


$stmt = $pdo->query(
    "SELECT user_id, full_name, email, student_id, role, created_at
     FROM users
     ORDER BY user_id DESC"
);

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Users - SportsVerse</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f5f9;
            padding: 20px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        h1, h2 {
            color: #1d3557;
        }

        input, select, button {
            padding: 10px;
            margin: 5px 0;
            box-sizing: border-box;
        }

        input, select {
            width: 100%;
        }

        button {
            cursor: pointer;
            border: none;
            border-radius: 5px;
            background: #1d3557;
            color: white;
        }

        .delete-button {
            background: #c1121f;
        }

        .message {
            background: #d8f3dc;
            padding: 12px;
            border-radius: 5px;
        }

        .form-box {
            max-width: 500px;
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #1d3557;
            color: white;
        }

        .actions {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .actions form {
            margin: 0;
        }

        @media (max-width: 750px) {
            .table-wrapper {
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>
<?php admin_nav($admin_page); ?>
<div class="container">

    <h1>SportsVerse - Users Management</h1>

    <?php if ($message !== ""): ?>
        <p class="message">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <div class="form-box">
        <h2>
            <?= $edit_user ? "Edit User" : "Add New User" ?>
        </h2>

        <form method="POST">

            <?php if ($edit_user): ?>
                <input type="hidden"
                       name="user_id"
                       value="<?= (int)$edit_user['user_id'] ?>">
            <?php endif; ?>

            <label>Full Name</label>
            <input
                type="text"
                name="full_name"
                required
                value="<?= htmlspecialchars($edit_user['full_name'] ?? '') ?>"
            >

            <label>Email</label>
            <input
                type="email"
                name="email"
                required
                value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>"
            >

            <label>Student ID</label>
            <input
                type="text"
                name="student_id"
                required
                value="<?= htmlspecialchars($edit_user['student_id'] ?? '') ?>"
            >

            <label>Role</label>
            <select name="role" required>
                <option value="student"
                    <?= ($edit_user['role'] ?? 'student') === 'student' ? 'selected' : '' ?>>
                    Student
                </option>

                <option value="admin"
                    <?= ($edit_user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>
                    Admin
                </option>
            </select>

            <?php if (!$edit_user): ?>
                <label>Password</label>
                <input
                    type="password"
                    name="password"
                    required
                    minlength="8"
                    autocomplete="new-password"
                >

                <button type="submit" name="add_user">
                    Add User
                </button>
            <?php else: ?>
                <button type="submit" name="update_user">
                    Update User
                </button>

                <a href="users.php">Cancel</a>
            <?php endif; ?>

        </form>
    </div>

    <h2>All Users</h2>

    <div class="table-wrapper">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Student ID</th>
                <th>Role</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
            </thead>

            <tbody>
            <?php if (count($users) > 0): ?>

                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <?= (int)$user['user_id'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($user['full_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($user['email']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($user['student_id']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($user['role']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($user['created_at'] ?? '') ?>
                        </td>

                        <td>
                            <div class="actions">
                                <a href="users.php?edit=<?= (int)$user['user_id'] ?>">
                                    <button type="button">Edit</button>
                                </a>

                                <form method="POST"
                                      onsubmit="return confirm('Are you sure you want to delete this user?');">

                                    <input type="hidden"
                                           name="user_id"
                                           value="<?= (int)$user['user_id'] ?>">

                                    <button
                                        type="submit"
                                        name="delete_user"
                                        class="delete-button">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

            <?php else: ?>
                <tr>
                    <td colspan="7">No users found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>