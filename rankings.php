<?php
session_start();
require_once 'config.php';

// Get rankings
$rankings_query = "
    SELECT 
        c.username,
        MAX(CASE WHEN p.is_correct = 1 THEN p.level_id ELSE 0 END) as highest_level,
        SUM(CASE WHEN p.is_correct = 1 THEN 
            TIMESTAMPDIFF(SECOND, p.start_time, p.completion_time)
            ELSE 0 END) as total_time
    FROM contestants c
    LEFT JOIN progress p ON c.id = p.contestant_id
    GROUP BY c.id, c.username
    ORDER BY highest_level DESC, total_time ASC";

$rankings = $conn->query($rankings_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competition Rankings</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .rankings {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        
        .rankings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .rankings-table th,
        .rankings-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .medal {
            font-size: 1.5em;
            margin-right: 5px;
        }
        
        .gold { color: #ffd700; }
        .silver { color: #c0c0c0; }
        .bronze { color: #cd7f32; }
        
        .highlight {
            background-color: #f0f9ff;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Competition Rankings</h1>
        </header>

        <main class="rankings">
            <table class="rankings-table">
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
                        
                        $highlight = isset($_SESSION['username']) && $_SESSION['username'] === $row['username'] ? ' class="highlight"' : '';
                    ?>
                    <tr<?php echo $highlight; ?>>
                        <td><?php echo $medal . $rank++; ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td>Level <?php echo $row['highest_level']; ?></td>
                        <td><?php echo gmdate("H:i:s", $row['total_time']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <?php if (file_exists(__DIR__ . '/competition_ended.txt')): ?>
            <div style="text-align: center; margin-top: 20px;">
                <a href="index.html" class="submit-btn" style="text-decoration: none;">Back to Home</a>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
<?php $conn->close(); ?>
