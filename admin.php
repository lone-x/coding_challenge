<?php
require_once 'config.php';
session_start();

// Enhanced admin authentication and session security
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Session timeout after 30 minutes of inactivity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: admin_login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Handle competition actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $competition_file = __DIR__ . '/competition_started.txt';
    $competition_ended_file = __DIR__ . '/competition_ended.txt';

    if (isset($_POST['start_competition'])) {
        $start_time = time();
        try {
            if (file_exists($competition_ended_file)) {
                unlink($competition_ended_file);
            }
            
            $result = file_put_contents($competition_file, $start_time);
            if ($result === false) {
                throw new Exception('Failed to write to competition file');
            }
            
            header('Location: admin.php?started=1');
            exit;
        } catch (Exception $e) {
            header('Location: admin.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    } elseif (isset($_POST['stop_competition'])) {
        try {
            if (file_exists($competition_file)) {
                unlink($competition_file);
            }
            file_put_contents($competition_ended_file, time());
            header('Location: admin.php?stopped=1');
            exit;
        } catch (Exception $e) {
            header('Location: admin.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}

// Get rankings
$rankings_query = "
    SELECT 
        c.username,
        MAX(CASE WHEN p.is_correct = 1 THEN p.level_id ELSE 0 END) as highest_level,
        SUM(CASE WHEN p.is_correct = 1 THEN 
            TIMESTAMPDIFF(SECOND, p.start_time, p.completion_time)
            ELSE 0 END) as total_time,
        c.created_at
    FROM contestants c
    LEFT JOIN progress p ON c.id = p.contestant_id
    GROUP BY c.id, c.username, c.created_at
    ORDER BY highest_level DESC, total_time ASC";

$rankings = $conn->query($rankings_query);

// Get active contestants
$query = "SELECT username, created_at FROM contestants ORDER BY created_at";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Coding Challenge</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .admin-panel {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .contestant-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .contestant-table th,
        .contestant-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .btn-container {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .start-btn {
            background: #3b82f6;
        }

        .stop-btn {
            background: #ef4444;
        }

        .rankings {
            margin-top: 30px;
        }

        .medal {
            font-size: 1.5em;
            margin-right: 5px;
        }

        .gold { color: #ffd700; }
        .silver { color: #c0c0c0; }
        .bronze { color: #cd7f32; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Admin Panel</h1>
        </header>

        <main class="admin-panel">
            <?php if (file_exists(__DIR__ . '/competition_ended.txt')): ?>
            <div class="rankings">
                <h2>Final Rankings</h2>
                <table class="contestant-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Username</th>
                            <th>Highest Level</th>
                            <th>Total Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        while ($row = $rankings->fetch_assoc()): 
                            $medal = '';
                            if ($rank === 1) $medal = '<span class="medal gold">🥇</span>';
                            else if ($rank === 2) $medal = '<span class="medal silver">🥈</span>';
                            else if ($rank === 3) $medal = '<span class="medal bronze">🥉</span>';
                        ?>
                        <tr>
                            <td><?php echo $medal . $rank++; ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td>Level <?php echo $row['highest_level']; ?></td>
                            <td><?php echo gmdate("H:i:s", $row['total_time']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <h2>Active Contestants</h2>
            <table class="contestant-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Joined At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div class="btn-container">
                <?php if (!file_exists(__DIR__ . '/competition_started.txt')): ?>
                <form method="POST" style="display: inline-block;">
                    <button type="submit" name="start_competition" class="start-btn">
                        Start Competition
                    </button>
                </form>
                <?php endif; ?>
                
                <?php if (file_exists(__DIR__ . '/competition_started.txt')): ?>
                <form method="POST" style="display: inline-block;">
                    <button type="submit" name="stop_competition" class="stop-btn">
                        Stop Competition
                    </button>
                </form>
                <?php endif; ?>
                
                <?php if (!file_exists(__DIR__ . '/competition_started.txt')): ?>
                <form method="POST" action="reset_competition.php" style="display: inline-block;">
                    <button type="submit" class="start-btn">New Competition</button>
                </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php if (!file_exists(__DIR__ . '/competition_ended.txt')): ?>
    <script>
        // Auto-refresh the page every 5 seconds if competition is running
        setTimeout(() => window.location.reload(), 5000);
    </script>
    <?php endif; ?>
</body>
</html>
<?php $conn->close(); ?>
