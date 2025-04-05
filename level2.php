<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['contestant_id'])) {
    header('Location: index.php');
    exit;
}

$contestant_id = $_SESSION['contestant_id'];
$level_id = 2;

// Check if previous level is completed
$prev_level = 1;
$stmt = $conn->prepare("SELECT is_correct FROM progress WHERE contestant_id = ? AND level_id = ?");
$stmt->bind_param("ii", $contestant_id, $prev_level);
$stmt->execute();
$prev_result = $stmt->get_result();
$prev_completed = $prev_result->num_rows > 0 && $prev_result->fetch_assoc()['is_correct'] == 1;

if (!$prev_completed) {
    $_SESSION['error_message'] = "⚠️ Please complete Level 1 first!";
    header('Location: level1.php');
    exit;
}

// Check if the contestant has already started this level
$stmt = $conn->prepare("SELECT start_time FROM progress WHERE contestant_id = ? AND level_id = ?");
$stmt->bind_param("ii", $contestant_id, $level_id);
$stmt->execute();
$result = $stmt->get_result();

// If not started, create a new progress entry
if ($result->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO progress (contestant_id, level_id, start_time) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $contestant_id, $level_id);
    $stmt->execute();
}
$stmt->close();

// Handle form submission with results from Pyodide
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pyodide_result']) && isset($_POST['answer'])) {
    $answer = trim($_POST['answer']);
    $result = json_decode($_POST['pyodide_result'], true);
    
    if ($result && isset($result['all_passed']) && $result['all_passed']) {
        // Update the database to mark this level as complete
        $stmt = $conn->prepare("UPDATE progress SET is_correct = 1, completion_time = NOW() WHERE contestant_id = ? AND level_id = ?");
        $stmt->bind_param("ii", $contestant_id, $level_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success_message'] = "🎉 Level 2 Completed! You found all the bugs!";
        $_SESSION['level2_completed'] = true;
        $_SESSION['output'] = $result['output'] ?? '';
        header('Location: level3.php');
        exit;
    } else {
        $_SESSION['error_message'] = "❌ That's not quite right. Check your solution and try again.";
        $_SESSION['output'] = $result['output'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Level 2 - Debug the Loop</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/brython/3.11.3/brython.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/brython/3.11.3/brython_stdlib.js"></script>
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
            font-family: monospace;
        }
        
        .code-input {
            width: 100%;
            height: 200px;
            font-family: monospace;
            font-size: 14px;
            padding: 10px;
            background: #1e1e1e;
            color: #d4d4d4;
            border: 1px solid #444;
            border-radius: 4px;
            resize: vertical;
        }
        
        .challenge-description {
            background: #2a2a2a;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .challenge-description h3 {
            color: #fff;
            margin-top: 0;
        }
        
        .challenge-description p, .challenge-description li {
            color: #d4d4d4;
        }
        
        .output-panel {
            flex: 1;
            background: #1e1e1e;
            padding: 20px;
            border-radius: 8px;
            color: #d4d4d4;
        }
        
        .output-panel pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            margin: 0;
            padding: 10px;
            background: #2a2a2a;
            border-radius: 4px;
        }
        
        .next-level-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s ease-in-out ;
        }
        
        .next-level-btn:hover {
            background: #45a049;
        }
        
        .code-input {
            background: #2d2d2d;
            color: #d4d4d4;
            border: 1px solid #3d3d3d;
            border-radius: 4px;
            padding: 10px;
            font-family: monospace;
            resize: none;
        }
        
        .bug-highlight {
            color: #f87171;
            text-decoration: underline wavy #f87171;
        }
        
        .hint {
            margin-top: 10px;
            color: #9ca3af;
            font-style: italic;
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

        .test-case {
            background: #374151;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .comment {
            color: #6b7280;
        }
        
        #loading {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
        
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border-left-color: #09f;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .test-results {
            margin-top: 15px;
            background: #1a1a1a;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        
        .test-result-item {
            margin-bottom: 8px;
            padding: 8px;
            border-radius: 4px;
        }
        
        .test-pass {
            background: rgba(22, 163, 74, 0.2);
            border-left: 3px solid #16a34a;
        }
        
        .test-fail {
            background: rgba(220, 38, 38, 0.2);
            border-left: 3px solid #dc2626;
        }
    </style>
</head>
<body onload="brython()">
    <div class="container">
        <header>
            <h1>Level 2: Debug the Loop</h1>
            <p>Find and fix the bugs in the Python code</p>
        </header>

        <main>
            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error">
                <?php 
                echo htmlspecialchars($_SESSION['error_message']);
                unset($_SESSION['error_message']);
                ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success">
                <?php 
                echo htmlspecialchars($_SESSION['success_message']);
                unset($_SESSION['success_message']);
                ?>
            </div>
            <?php endif; ?>

            <div class="challenge-description">
                <h3>Debug the Loop</h3>
                <p>The following code is supposed to print even numbers from 1 to 10, but it's not working correctly. Debug and fix the code.</p>
            </div>
            
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

            <div id="loading" style="display: block; text-align: center; margin: 20px;">
                <div class="spinner"></div>
                <p>Loading Python environment...</p>
            </div>

            <div class="code-editor" style="display: none;" id="editor-container">
                <div class="code-panel">
                    <h3>Fix the Bugs:</h3>
                    <form id="codeForm" method="POST">
                        <textarea name="answer" id="code-input" class="code-input" required>def print_even_numbers():
    for i in range(1, 10):
        if i % 2 = 0:
            print i

print_even_numbers()</textarea>
                        <input type="hidden" name="pyodide_result" id="pyodide_result">
                        <button type="submit" id="submit-btn" disabled>Submit Solution</button>
                    </form>
                </div>
                <div class="output-panel">
                    <h3>Output:</h3>
                    <pre id="output"><?php echo isset($_SESSION['output']) ? htmlspecialchars($_SESSION['output']) : ''; ?></pre>
                    <?php if (isset($_SESSION['level2_completed']) && $_SESSION['level2_completed']): ?>
                    <a href="level3.php" class="next-level-btn">Next Level →</a>
                    <?php endif; ?>
                </div>
            </div>


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

    <script type="text/python">
        from browser import document, window, html
        import sys
        from io import StringIO

        def run_code(ev):
            ev.preventDefault()
            form = document['codeForm']
            user_code = document['code-input'].value
            output_div = document['output']
            result_input = document['pyodide_result']

            # Capture stdout
            old_stdout = sys.stdout
            sys.stdout = captured_output = StringIO()

            try:
                # Execute the code
                exec(user_code)
                output = captured_output.getvalue()

                # Test the output
                expected_numbers = [2, 4, 6, 8, 10]
                actual_numbers = [int(n.strip()) for n in output.strip().split('\n') if n.strip()]

                all_passed = (len(actual_numbers) == len(expected_numbers) and
                            all(n in expected_numbers for n in actual_numbers))

                # Update output display
                output_div.textContent = output

                # Set result
                result = {
                    'all_passed': all_passed,
                    'output': output
                }
                result_input.value = window.JSON.stringify(result)

                # Submit form after a short delay to show output
                if all_passed:
                    window.setTimeout(lambda: form.submit(), 500)
                else:
                    window.setTimeout(lambda: form.submit(), 1500)

            except Exception as e:
                error_msg = str(e)
                output_div.textContent = error_msg
                result = {
                    'all_passed': False,
                    'output': error_msg
                }
                result_input.value = window.JSON.stringify(result)
                window.setTimeout(lambda: form.submit(), 1500)
            finally:
                sys.stdout = old_stdout

        # Bind submit event
        document['codeForm'].bind('submit', run_code)

        # Hide loading and show editor
        document['loading'].style.display = 'none'
        document['editor-container'].style.display = 'block'
        document['submit-btn'].disabled = False
    </script>
</body>
</html>
<?php $conn->close(); ?>
