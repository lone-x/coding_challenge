<?php
session_start();
require_once 'config.php';

// Clear any previous output
unset($_SESSION['output']);

if (!isset($_SESSION['contestant_id'])) {
    header('Location: index.php');
    exit;
}

$contestant_id = $_SESSION['contestant_id'];
$level_id = 5;

// Check if previous level is completed
$prev_level = 4;
$stmt = $conn->prepare("SELECT is_correct FROM progress WHERE contestant_id = ? AND level_id = ?");
$stmt->bind_param("ii", $contestant_id, $prev_level);
$stmt->execute();
$prev_result = $stmt->get_result();
$prev_completed = $prev_result->num_rows > 0 && $prev_result->fetch_assoc()['is_correct'] == 1;

if (!$prev_completed) {
    $_SESSION['error_message'] = "⚠️ Please complete Level 4 first!";
    header('Location: level4.php');
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

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pyodide_result'])) {
    $result = json_decode($_POST['pyodide_result'], true);
    if ($result && isset($result['all_passed']) && $result['all_passed']) {
        $stmt = $conn->prepare("UPDATE progress SET is_correct = 1, completion_time = NOW() WHERE contestant_id = ? AND level_id = ?");
        $stmt->bind_param("ii", $contestant_id, $level_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success_message'] = "🎉 Level 5 Completed! You've mastered text analysis!";
        $_SESSION['level5_completed'] = true;
        $_SESSION['output'] = $result['output'] ?? '';
        header('Location: rankings.php');
        exit;
    } else {
        $_SESSION['error_message'] = "❌ Not quite right. Check your word frequency analysis logic!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Level 5 - Word Frequency Analyzer</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/brython/3.11.3/brython.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/brython/3.11.3/brython_stdlib.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .challenge-container {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .editor-panel {
            flex: 1;
            background: #1e1e1e;
            padding: 20px;
            border-radius: 8px;
            color: #d4d4d4;
        }
        
        .preview-panel {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            min-height: 400px;
            position: relative;
        }
        
        .code-input {
            width: 100%;
            height: 200px;
            background: #2d2d2d;
            color: #d4d4d4;
            border: 1px solid #3d3d3d;
            border-radius: 4px;
            padding: 10px;
            font-family: monospace;
            resize: none;
        }
        
        .ball {
            width: 50px;
            height: 50px;
            background: #3b82f6;
            border-radius: 50%;
            position: absolute;
            bottom: 50px;
            left: calc(50% - 25px);
        }
        
        .target-ball {
            width: 50px;
            height: 50px;
            background: rgba(59, 130, 236, 0.2);
            border: 2px dashed #3b82f6;
            border-radius: 50%;
            position: absolute;
            bottom: 50px;
            left: calc(50% - 25px);
            animation: bounce 1s ease-in-out infinite;
        }
        
        @keyframes bounce {
            0% { transform: translateY(0); }
            50% { transform: translateY(-100px); }
            100% { transform: translateY(0); }
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

        .animation-controls {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .control-btn {
            background: #4b5563;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        .control-btn:hover {
            background: #374151;
        }

        .speed-control {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .speed-slider {
            flex: 1;
        }
    </style>
</head>
<body onload="brython()">
    <div class="container">
        <header>
            <h1>Level 5: Word Frequency Analyzer</h1>
            <p>Create a function that analyzes text and returns word frequencies in a specific format</p>
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

            <div class="challenge-description">
                <h3>Challenge:</h3>
                <p>Write a function <code>analyze_text(text)</code> that processes a string of text and returns a list of tuples containing word frequencies, following these rules:</p>
                <ul>
                    <li>Split the text into words (words are separated by spaces)</li>
                    <li>Count how many times each word appears</li>
                    <li>Return a list of (word, frequency) tuples, sorted by:</li>
                    <ul>
                        <li>Frequency (highest first)</li>
                        <li>Alphabetically for words with same frequency</li>
                    </ul>
                </ul>
                <p><strong>Parameters:</strong></p>
                <ul>
                    <li><code>text</code>: A string containing words separated by spaces</li>
                </ul>
                <p><strong>Return:</strong> List of tuples [(word, frequency), ...] sorted by frequency then alphabetically</p>
                <p><strong>Example:</strong></p>
                <pre>text = "cat dog cat bird"

Result: [
    ("cat", 2),
    ("bird", 1),
    ("dog", 1)
]

Explanation:
- Split text into words
- Count word frequencies
- Sort by frequency (highest first)
- For same frequency, sort alphabetically</pre>
            </div>
            
            <div class="code-editor">
                <div class="code-panel">
                    <h3>Your Solution:</h3>
                    <form method="POST" id="codeForm">
                        <textarea id="answer" class="code-input" name="answer" placeholder="Write your Python code here..." required>def analyze_text(text):
    # Your code here
    pass</textarea>
                        <input type="hidden" name="pyodide_result" id="pyodideResult">
                        <button type="button" id="submitBtn">Run Code</button>
                        <button type="submit" id="submitFinalBtn" style="display: none;">Submit Solution</button>
                    </form>
                </div>

                <div class="code-panel">
                    <h3>Output:</h3>
                    <div class="output" id="output"><?php echo isset($_SESSION['output']) ? htmlspecialchars($_SESSION['output']) : ''; ?></div>
                </div>
            </div>
        </main>
    </div>

    <script type="text/python">
        from browser import document, window, html
        import sys
        from io import StringIO

        def run_tests(ev):
            form = document['codeForm']
            user_code = document['answer'].value
            output_div = document['output']
            result_input = document['pyodideResult']

            # Create a namespace for the user's code
            namespace = {}
            
            # Capture stdout
            old_stdout = sys.stdout
            sys.stdout = captured_output = StringIO()
            
            try:
                # Execute the user's code to define the function
                exec(user_code, namespace)
                
                # Test cases
                test_cases = [
                    # Basic test case
                    ("cat dog cat bird",
                     [("cat", 2), ("bird", 1), ("dog", 1)]),
                    # Empty string
                    ("", []),
                    # Single word
                    ("hello", [("hello", 1)]),
                    # Multiple occurrences
                    ("apple apple banana banana banana cherry",
                     [("banana", 3), ("apple", 2), ("cherry", 1)]),
                    # Simple sentence
                    ("I am a happy coder I am",
                     [("I", 2), ("am", 2), ("a", 1), ("happy", 1), ("coder", 1)])
                ]
                
                all_passed = True
                test_results = []
                
                for i, (text, expected) in enumerate(test_cases, 1):
                    try:
                        result = namespace['analyze_text'](text)
                        result = sorted(result, key=lambda x: (-x[1], x[0]))
                        expected = sorted(expected, key=lambda x: (-x[1], x[0]))
                        
                        if result == expected:
                            test_results.append(f"Test case {i} passed!")
                        else:
                            all_passed = False
                            test_results.append(f"Test case {i} failed:")
                            test_results.append(f"Input text: {text}")
                            test_results.append(f"Expected: {expected}")
                            test_results.append(f"Got: {result}")
                            
                    except Exception as e:
                        all_passed = False
                        test_results.append(f"Test case {i} raised an error: {str(e)}")
                
                output = "\n".join(test_results)
                if all_passed:
                    output += "\n\nAll test cases passed! Click Submit Solution to complete the challenge!"
                output_div.text = output

                # Set result
                result = {
                    'all_passed': all_passed,
                    'output': output
                }
                result_input.value = window.JSON.stringify(result)

                # Submit form after a short delay to show output
                if all_passed:
                    window.setTimeout(lambda: form.submit(), 1000)
                
            except Exception as e:
                error_msg = str(e)
                output_div.text = error_msg
                result = {
                    'all_passed': False,
                    'output': error_msg
                }
                result_input.value = window.JSON.stringify(result)
            finally:
                sys.stdout = old_stdout

        # Bind submit event
        document['codeForm'].bind('submit', run_tests)

        # Bind the run_tests function to the submit button
        document['submitBtn'].bind('click', run_tests)
    </script>
    
    <script>
        // Handle form submission
        document.getElementById('codeForm').addEventListener('submit', function(e) {
            // Only allow submission if the submit button is visible (meaning code passed tests)
            if (document.getElementById('submitFinalBtn').style.display === 'none') {
                e.preventDefault();
                return false;
            }
        });

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
