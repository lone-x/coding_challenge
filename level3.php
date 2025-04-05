<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['contestant_id'])) {
    header('Location: index.php');
    exit;
}

$contestant_id = $_SESSION['contestant_id'];
$level_id = 3;

// Check if previous level is completed
$prev_level = 2;
$stmt = $conn->prepare("SELECT is_correct FROM progress WHERE contestant_id = ? AND level_id = ?");
$stmt->bind_param("ii", $contestant_id, $prev_level);
$stmt->execute();
$prev_result = $stmt->get_result();
$prev_completed = $prev_result->num_rows > 0 && $prev_result->fetch_assoc()['is_correct'] == 1;

if (!$prev_completed) {
    $_SESSION['error_message'] = "⚠️ Please complete Level 2 first!";
    header('Location: level2.php');
    exit;
}

$stmt = $conn->prepare("SELECT start_time FROM progress WHERE contestant_id = ? AND level_id = ?");
$stmt->bind_param("ii", $contestant_id, $level_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO progress (contestant_id, level_id, start_time) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $contestant_id, $level_id);
    $stmt->execute();
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer'])) {
    $answer = trim($_POST['answer']);
    $expected = '[1, 3, 5]';
    
    // Normalize answers by removing spaces
    $answer = preg_replace('/\s+/', '', $answer);
    $expected = preg_replace('/\s+/', '', $expected);
    
    if ($answer === $expected) {
        // Update the database to mark this level as complete
        $stmt = $conn->prepare("UPDATE progress SET is_correct = 1, completion_time = NOW() WHERE contestant_id = ? AND level_id = ?");
        $stmt->bind_param("ii", $contestant_id, $level_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success_message'] = "🎉 Level 3 Completed! You predicted the output correctly!";
        $_SESSION['level3_completed'] = true;
        header('Location: level4.php');
        exit;
    } else {
        $_SESSION['error_message'] = "❌ That's not quite right. Try tracing the code step by step to understand what's happening.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Level 3 - List Modification Challenge</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .code-editor {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .code-panel {
            flex: 1;
            background: #1e1e1e;
            padding: 20px;
            border-radius: 8px;
            color: #d4d4d4;
        }
        
        .output-panel {
            flex: 1;
            background: #1e1e1e;
            padding: 20px;
            border-radius: 8px;
            color: #d4d4d4;
        }
        
        .code-input {
            width: 100%;
            height: 80px;
            font-family: monospace;
            font-size: 14px;
            padding: 10px;
            background: #2d2d2d;
            color: #d4d4d4;
            border: 1px solid #3d3d3d;
            border-radius: 4px;
            resize: vertical;
        }

        .hint {
            margin: 20px 0;
            padding: 15px;
            background: #2d2d2d;
            border-left: 4px solid #4CAF50;
            border-radius: 4px;
        }

        .hint p {
            margin: 0 0 10px 0;
            color: #d4d4d4;
        }

        .hint ul {
            margin: 0;
            padding-left: 20px;
            color: #a0a0a0;
        }
        }
        
        .target-box {
            width: 100px;
            height: 100px;
            background: #3b82f6;
            border-radius: 4px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-weight: bold;
        }
        
        .message {
            padding: 10px 20px;
            border-radius: 4px;
            margin: 10px 0;
            animation: fadeIn 0.5s;
        }
        
        .error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        
        .hint {
            margin-top: 10px;
            color: #9ca3af;
            font-style: italic;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .target-overlay {
            position: absolute;
            width: 100px;
            height: 100px;
            background: rgba(59, 130, 246, 0.2);
            border: 2px dashed #3b82f6;
            border-radius: 4px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }

        .success-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: #1e1e1e;
            padding: 20px;
            border-radius: 8px;
            animation: fadeIn 0.5s;
        }

        .next-level-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #4CAF50;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(76, 175, 80, 0.3);
        }

        .next-level-btn:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(76, 175, 80, 0.4);
        }

        .next-level-btn::after {
            content: '→';
            font-size: 20px;
            margin-left: 8px;
            transition: transform 0.3s ease;
        }

        .next-level-btn:hover::after {
            transform: translateX(4px);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Level 3: List Modification Challenge</h1>
            <p>What will be the output of this code? Write the exact output, including any spaces or line breaks.</p>
        </header>

        <main>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="message error">
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="message success">
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="challenge-description">
                <h3>Code to Analyze:</h3>
                <pre><code>nums = [1, 2, 3, 4, 5]
for num in nums:
    if num % 2 == 0:
        nums.remove(num)
print(nums)</code></pre>
                <p>Write the exact output of this code, including any spaces or line breaks.</p>
                <div class="hint">
                    <p>💡 Hint: When modifying a list while iterating over it:</p>
                    <ul>
                        <li>The loop counter continues to move forward after each removal</li>
                        <li>Elements shift left when one is removed</li>
                        <li>This can cause some elements to be skipped</li>
                    </ul>
                </div>
            </div>

            <div class="code-editor">
                <div class="code-panel">
                    <h3>Your Answer:</h3>
                    <form method="POST">
                        <textarea name="answer" class="code-input" required placeholder="Enter the output you expect..."></textarea>
                        <button type="submit" class="submit-btn">Submit Answer</button>
                    </form>
                </div>
                <?php if (isset($_SESSION['level3_completed']) && $_SESSION['level3_completed']): ?>
                <div class="success-panel">
                    <h3 style="color: #4CAF50; margin-bottom: 20px;">🎉 Level Complete!</h3>
                    <p style="color: #d4d4d4; margin-bottom: 20px; text-align: center;">Great job! You've mastered list modification. Ready for the next challenge?</p>
                    <a href="level4.php" class="next-level-btn">Continue to Level 4</a>
                </div>
                <?php endif; ?>
            </div>

            </div>
        </main>
    </div>

    <script>
        // Check if competition has ended
        async function checkCompetitionStatus() {
            try {
                const response = await fetch('check_status.php');
                const data = await response.json();
                
                if (data.status === 'ended') {
                    window.location.href = 'rankings.php';
                }
            } catch (error) {
                console.error('Error checking competition status:', error);
            }
        }

        // Check status every 5 seconds
        setInterval(checkCompetitionStatus, 5000);
    </script>

</body>
</html>
<?php $conn->close(); ?>
