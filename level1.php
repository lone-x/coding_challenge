<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['contestant_id'])) {
    header('Location: index.php');
    exit;
}

$contestant_id = $_SESSION['contestant_id'];
$level_id = 1;

// Check if progress entry exists, if not create it
$stmt = $conn->prepare("SELECT start_time FROM progress WHERE contestant_id = ? AND level_id = ?");
$stmt->bind_param("ii", $contestant_id, $level_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO progress (contestant_id, level_id, start_time) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $contestant_id, $level_id);
    $stmt->execute();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = $_POST['answers'] ?? [];
    $correct_answers = [
        '+',
        '-',
        '-',
        '<',
        '1',
        '%'
    ];
    
    $score = 0;
    foreach ($answers as $index => $answer) {
        if (trim(strtolower($answer)) === $correct_answers[$index]) {
            $score++;
        }
    }
    
    $completion_time = time();
    $time_taken = $completion_time - $_SESSION['level1_start'];
    
    // Update progress in database
    $stmt = $conn->prepare("UPDATE progress SET completion_time = NOW(), is_correct = ? WHERE contestant_id = ? AND level_id = ?");
    $is_correct = ($score === count($correct_answers));
    $stmt->bind_param("iii", $is_correct, $contestant_id, $level_id);
    $stmt->execute();
    $stmt->close();
    
    if ($is_correct) {
        $_SESSION['level1_complete'] = true;
        $_SESSION['success_message'] = "🎉 Level 1 Completed! Moving to Level 2...";
        header('Location: level2.php');
        exit;
    } else {
        $_SESSION['error_message'] = "❌ Not all answers are correct. Check your code and try again!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Level 1 - Code Blocks</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
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

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .challenge-container {
            display: flex;
            padding: 20px;
            margin: 20px auto;
            max-width: 1200px;
            gap: 20px;
            flex-direction: column;
        }

        .answers-area {
            margin-bottom: 20px;
            padding: 15px;
            background: #1e1e1e;
            border-radius: 8px;
        }

        #options-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }

        .answer-option {
            padding: 8px 15px;
            background: #2d2d2d;
            border: 1px solid #3d3d3d;
            border-radius: 4px;
            color: #d4d4d4;
            cursor: move;
            font-family: monospace;
            font-size: 16px;
            user-select: none;
            touch-action: none;
            transition: background-color 0.2s;
        }

        .answer-option:hover {
            background: #3d3d3d;
        }

        .answer-option:active {
            background: #4d4d4d;
        }

        .dropzone {
            display: inline-block;
            width: 40px;
            height: 24px;
            background: #2d2d2d;
            border: 2px dashed #4d4d4d;
            border-radius: 4px;
            margin: 0 5px;
            vertical-align: middle;
        }

        .dropzone.active {
            border-color: #4CAF50;
            background: #2d2d2d;
        }

        .dropzone.filled {
            border-style: solid;
            border-color: #3d3d3d;
            background: #3d3d3d;
            color: #d4d4d4;
            text-align: center;
            line-height: 24px;
        }

        @media (min-width: 768px) {
            .challenge-container {
                flex-direction: row;
                padding: 30px;
            }
        }

        .code-area, .answers-area {
            flex: 1;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        @media (min-width: 768px) {
            .code-area, .answers-area {
                padding: 20px;
            }
        }

        .code-block {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 5px;
            margin: 15px 0;
            font-family: 'Consolas', monospace;
        }

        .dropzone {
            display: inline-block;
            width: 100px;
            height: 24px;
            border: 2px dashed #3b82f6;
            border-radius: 4px;
            margin: 0 5px;
            vertical-align: middle;
        }

        .dropzone.filled {
            border-style: solid;
            background: #2d2d2d;
        }

        .answer-option {
            display: inline-block;
            padding: 8px 16px;
            margin: 5px;
            background: #3b82f6;
            color: white;
            border-radius: 4px;
            cursor: move;
            user-select: none;
            touch-action: none;
            -webkit-tap-highlight-color: transparent;
        }

        #options-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            padding: 10px;
        }

        @media (max-width: 768px) {
            .answer-option {
                padding: 12px 20px;
                font-size: 1.1em;
            }

            .code-block {
                font-size: 0.9em;
                padding: 15px;
                overflow-x: auto;
            }

            .dropzone {
                min-width: 60px;
                min-height: 30px;
            }

            .timer {
                position: sticky;
                top: 0;
                width: 100%;
                text-align: center;
                z-index: 100;
            }

            button[type="submit"] {
                width: 100%;
                padding: 15px;
                font-size: 1.1em;
                margin-top: 20px;
            }
        }

        .answer-option.used {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #3b82f6;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="timer" id="timer">00:00</div>
        
        <header>
            <h1>Level 1: Python Fundamentals</h1>
            <p>Drag and drop the correct operators and values to complete the Python programs</p>
        </header>

        <main class="challenge-container">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="message error">
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="answers-area">
                <h3>Available Operators & Values</h3>
                <div id="options-container">
                    <div class="answer-option" draggable="true" data-answer="+">+</div>
                    <div class="answer-option" draggable="true" data-answer="-">-</div>
                    <div class="answer-option" draggable="true" data-answer="*">*</div>
                    <div class="answer-option" draggable="true" data-answer="/">/</div>
                    <div class="answer-option" draggable="true" data-answer="%">%</div>
                    <div class="answer-option" draggable="true" data-answer="-">-</div>
                    <div class="answer-option" draggable="true" data-answer=">">&gt;</div>
                    <div class="answer-option" draggable="true" data-answer="<="><=</div>
                    <div class="answer-option" draggable="true" data-answer=">=">&gt;=</div>
                    <div class="answer-option" draggable="true" data-answer="1">1</div>
                    <div class="answer-option" draggable="true" data-answer="<">&lt;</div>
                    <div class="answer-option" draggable="true" data-answer="2">2</div>
                </div>
            </div>

            <div class="code-area">
                <form method="POST" id="challengeForm">
                    <div class="code-block">
                        <p># Challenge 1: Swap two numbers without temporary variable</p>
                        <p>a = 5</p>
                        <p>b = 10</p>
                        <p>&nbsp;</p>
                        <p>a = a <span class="dropzone" data-index="0"></span> b</p>
                        <p>b = a <span class="dropzone" data-index="1"></span> b</p>
                        <p>a = a <span class="dropzone" data-index="2"></span> b</p>
                        <p>&nbsp;</p>
                        <p># Expected: a = 10, b = 5</p>
                    </div>

                    <div class="code-block">
                        <p># Challenge 2: Check if a number is prime</p>
                        <p>def is_prime(n):</p>
                        <p>&nbsp;&nbsp;&nbsp;&nbsp;if n <span class="dropzone" data-index="3"></span> 2:</p>
                        <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;return False</p>
                        <p>&nbsp;</p>
                        <p>&nbsp;&nbsp;&nbsp;&nbsp;for i in range(2, int(n ** 0.5) + <span class="dropzone" data-index="4"></span>):</p>
                        <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;if n <span class="dropzone" data-index="5"></span> i == 0:</p>
                        <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;return False</p>
                        <p>&nbsp;</p>
                        <p>&nbsp;&nbsp;&nbsp;&nbsp;return True</p>
                    </div>

                    <input type="hidden" name="answers[]" id="answer0">
                    <input type="hidden" name="answers[]" id="answer1">
                    <input type="hidden" name="answers[]" id="answer2">
                    <input type="hidden" name="answers[]" id="answer3">
                    <input type="hidden" name="answers[]" id="answer4">
                    <input type="hidden" name="answers[]" id="answer5">

                    <button type="submit" id="submitBtn" disabled>Submit Answers</button>
                </form>
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

    <script>
        // Timer functionality
        const startTime = <?php echo $_SESSION['level1_start']; ?> * 1000;
        const timerElement = document.getElementById('timer');

        function updateTimer() {
            const currentTime = new Date().getTime();
            const timeDiff = currentTime - startTime;
            
            const minutes = Math.floor(timeDiff / 60000);
            const seconds = Math.floor((timeDiff % 60000) / 1000);
            
            timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }

        setInterval(updateTimer, 1000);
        updateTimer();

        // Drag and Drop functionality
        const answerOptions = document.querySelectorAll('.answer-option');
        const dropzones = document.querySelectorAll('.dropzone');
        const submitBtn = document.getElementById('submitBtn');

        answerOptions.forEach(option => {
            option.addEventListener('dragstart', (e) => {
                if (!option.classList.contains('used')) {
                    e.dataTransfer.setData('text/plain', option.dataset.answer);
                } else {
                    e.preventDefault();
                }
            });
        });

        dropzones.forEach(dropzone => {
            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
            });

            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                const answer = e.dataTransfer.getData('text/plain');
                const option = document.querySelector(`.answer-option[data-answer="${answer}"]`);
                
                // Clear previous answer if any
                if (dropzone.dataset.currentAnswer) {
                    const prevOption = document.querySelector(`.answer-option[data-answer="${dropzone.dataset.currentAnswer}"]`);
                    prevOption.classList.remove('used');
                }

                // Set new answer
                dropzone.textContent = answer;
                dropzone.dataset.currentAnswer = answer;
                dropzone.classList.add('filled');
                option.classList.add('used');

                // Update hidden input
                document.getElementById(`answer${dropzone.dataset.index}`).value = answer;

                // Check if all dropzones are filled
                const allFilled = Array.from(dropzones).every(zone => zone.classList.contains('filled'));
                submitBtn.disabled = !allFilled;
            });

            // Allow removing answers by clicking
            dropzone.addEventListener('click', () => {
                if (dropzone.dataset.currentAnswer) {
                    const option = document.querySelector(`.answer-option[data-answer="${dropzone.dataset.currentAnswer}"]`);
                    option.classList.remove('used');
                    dropzone.textContent = '';
                    dropzone.classList.remove('filled');
                    dropzone.dataset.currentAnswer = '';
                    document.getElementById(`answer${dropzone.dataset.index}`).value = '';
                    submitBtn.disabled = true;
                }
            });
        });
    </script>
</body>
</html>
