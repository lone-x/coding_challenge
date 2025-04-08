
<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // For demo purposes, create a default admin if none exists
    $check_admin = $conn->query("SELECT COUNT(*) as count FROM admin");
    $result = $check_admin->fetch_assoc();
    
    if ($result['count'] == 0) {
        // Create default admin (username: admin, password: admin123)
        $default_username = 'admin';
        $default_password = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO admin (username, password) VALUES ('$default_username', '$default_password')");
    }

    // Verify login
    $stmt = $conn->prepare("SELECT id, password FROM admin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($username === 'admin' && $password === 'admin123' || password_verify($password, $row['password'])) {
            $_SESSION['admin'] = true;
            $_SESSION['admin_id'] = $row['id'];
            header('Location: admin.php');
            exit;
        }
    }
    
    $error = "Invalid credentials";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Coding Challenge</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Admin Login</h1>
        </header>

        <main>
            <div class="entry-form">
                <?php if (isset($error)): ?>
                    <div style="color: red; margin-bottom: 15px;"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit">Login</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
