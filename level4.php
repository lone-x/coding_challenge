<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['contestant_id'])) {
    header('Location: index.php');
    exit;
}

$contestant_id = $_SESSION['contestant_id'];
$level_id = 4;

// Check if previous level is completed
$prev_level = 3;
$stmt = $conn->prepare("SELECT is_correct FROM progress WHERE contestant_id = ? AND level_id = ?");
$stmt->bind_param("ii", $contestant_id, $prev_level);
$stmt->execute();
$prev_result = $stmt->get_result();
$prev_completed = $prev_result->num_rows > 0 && $prev_result->fetch_assoc()['is_correct'] == 1;

if (!$prev_completed) {
    $_SESSION['error_message'] = "⚠️ Please complete Level 3 first!";
    header('Location: level3.php');
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

// Handle form submission with results from Pyodide
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pyodide_result']) && isset($_POST['answer'])) {
    $answer = trim($_POST['answer']);
    $result = json_decode($_POST['pyodide_result'], true);
    
    if ($result && isset($result['all_passed']) && $result['all_passed']) {
        // Update the database to mark this level as complete
        $stmt = $conn->prepare("UPDATE progress SET is_correct = 1, completion_time = NOW() WHERE contestant_id = ? AND level_id = ?");
        $stmt->bind_param("ii", $contestant_id, $level_id);
        $stmt->execute();
        
        $_SESSION['success_message'] = "🎉 Level 4 Completed! You've mastered the palindromic triangle!";
        $_SESSION['level4_completed'] = true;
        $_SESSION['output'] = $result['output'] ?? '';
        header('Location: level5.php');
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
    <title>Level 4 - Palindromic Triangle</title>
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
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        pre {
            background: #cfcfcf;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        
        .output {
            background: #2d2d2d;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
            white-space: pre;
            font-family: monospace;
        }
        
        @media (max-width: 768px) {
            .code-editor {
                flex-direction: column;
            }
            
            .code-input {
                height: 150px;
            }
        }
            padding: 10px;
        }
        
        .grid-item {
            background: #3b82f6;
            color: white;
            padding: 20px;
            border-radius: 4px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
        }
        
        .header { grid-area: header; background: #2563eb; }
        .sidebar { grid-area: sidebar; background: #7c3aed; }
        .main { grid-area: main; background: #9333ea; }
        .footer { grid-area: footer; background: #6366f1; }
        
        .target-layout {
            opacity: 0.1;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-areas:
                "header header header"
                "sidebar main main"
                "footer footer footer";
            gap: 10px;
            padding: 10px;
        }
        
        .target-layout > div {
            background: #000;
            border-radius: 4px;
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

        .layout-example {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-areas:
                "header header header"
                "sidebar main main"
                "footer footer footer";
            gap: 5px;
            margin-top: 20px;
            opacity: 0.7;
            font-size: 12px;
        }

        .layout-example > div {
            padding: 5px;
            background: #4b5563;
            color: white;
            text-align: center;
            border-radius: 2px;
        }
    </style>
</head>
<body onload="brython()">
    <div class="container">
        <header>
            <h1>Level 4: Palindromic Triangle</h1>
            <p>Create a function that prints a palindromic triangle pattern.</p>
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
                <p>Write a function that takes a positive integer N and prints a palindromic triangle of size N.</p>
                <p>Your function will be tested with multiple values of N. Here are some examples:</p>
                <p>When N = 3:</p>
                <pre>1
121
12321</pre>
                <p>When N = 5:</p>
                <pre>1
121
12321
1234321
123454321</pre>
                <p><strong>Input Format:</strong> A single integer N (1 ≤ N ≤ 9)</p>
                <p><strong>Output Format:</strong> Print the palindromic triangle pattern with N lines. Each line should be a palindrome made of consecutive digits starting from 1.</p>
                <p><strong>Note:</strong> Your function will be tested with different values of N to ensure it works correctly for all valid inputs.</p>
            </div>

            <?php
            // Clear any previous output
            unset($_SESSION['output']);
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pyodide_result'])) {
                $result = json_decode($_POST['pyodide_result'], true);
                if ($result && isset($result['all_passed']) && $result['all_passed']) {
                    // Update progress in database
                    $contestant_id = $_SESSION['contestant_id'];
                    $level = 4;
                    $sql = "UPDATE progress SET is_correct = 1, completion_time = NOW() WHERE contestant_id = ? AND level_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $contestant_id, $level_id);
                    $stmt->execute();
                    
                    $_SESSION['success_message'] = "🎉 Level 4 Completed! You've mastered the palindromic triangle!";
                    $_SESSION['level4_completed'] = true;
                    $_SESSION['output'] = $result['output'] ?? '';
                    
                    // Redirect to next level
                    header("Location: level5.php");
                    exit;
                }
            }
            ?>
            
            <div class="code-editor">
                <div class="code-panel">
                    <h3>Your Solution:</h3>
                    <form method="POST" id="codeForm">
                        <textarea id="answer" class="code-input" name="answer" placeholder="Write your Python code here..." required>def print_palindromic_triangle(n):
    # Write your code here
    pass

# Function will be tested with multiple values of n</textarea>
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

        def debug_str(s):
            # Convert string to hex representation for debugging
            return ' '.join(hex(ord(c)) for c in s)

        def normalize_output(output):
            # Remove empty lines and normalize whitespace
            lines = [line.strip() for line in output.split('\n') if line.strip()]
            return '\n'.join(lines)

        def compare_outputs(user_output, expected_output):
            # First, show raw outputs for debugging
            debug_info = f"Raw user output (hex): {debug_str(user_output)}\n"
            debug_info += f"Raw expected output (hex): {debug_str(expected_output)}\n\n"
            
            # Normalize both outputs
            user_lines = user_output.strip().split('\n')
            expected_lines = expected_output.strip().split('\n')
            
            # Remove any empty lines
            user_lines = [line.strip() for line in user_lines if line.strip()]
            expected_lines = [line.strip() for line in expected_lines if line.strip()]
            
            debug_info += f"After normalization:\n"
            debug_info += f"User lines: {user_lines}\n"
            debug_info += f"Expected lines: {expected_lines}\n\n"
            
            # Check if we have the same number of lines
            if len(user_lines) != len(expected_lines):
                return False, debug_info + f"Expected {len(expected_lines)} lines, but got {len(user_lines)} lines"
            
            # Compare each line
            for i, (user, expected) in enumerate(zip(user_lines, expected_lines)):
                if user != expected:
                    debug_info += f"Line {i+1} mismatch:\n"
                    debug_info += f"Expected (hex): {debug_str(expected)}\n"
                    debug_info += f"Got (hex): {debug_str(user)}\n"
                    return False, debug_info
            
            return True, debug_info

        def run_tests():
            # Get the user's code
            user_code = document['answer'].value
            
            # Create StringIO object to capture stdout
            output = StringIO()
            sys.stdout = output
            
            # Dictionary to store test results
            results = {'output': '', 'all_passed': False}
            
            try:
                # Test cases with their expected outputs
                test_cases = [
                    (1, "1"),
                    (3, "1\n121\n12321"),
                    (5, "1\n121\n12321\n1234321\n123454321"),
                    (2, "1\n121"),
                    (4, "1\n121\n12321\n1234321")
                ]
                
                # Create a namespace for the user's code
                namespace = {}
                
                # First execute the user's code to define the function
                exec(user_code, namespace)
                
                # Run all test cases
                all_passed = True
                test_results = []
                
                for n, expected in test_cases:
                    # Create new StringIO for this test
                    test_output = StringIO()
                    sys.stdout = test_output
                    
                    # Run the test
                    try:
                        # Call the function with the test case
                        namespace['print_palindromic_triangle'](n)
                        
                        # Get the output
                        test_output_str = test_output.getvalue()
                        # Compare with expected
                        passed, debug = compare_outputs(test_output_str, expected)
                        
                        if not passed:
                            all_passed = False
                            test_results.append(f"\nTest case n={n} failed:\n{debug}")
                        else:
                            test_results.append(f"\nTest case n={n} passed!")
                            
                    except KeyError:
                        all_passed = False
                        test_results.append(f"\nTest case n={n} failed: Function 'print_palindromic_triangle' is not defined in your code")
                    except Exception as e:
                        all_passed = False
                        test_results.append(f"\nTest case n={n} raised an error: {str(e)}")
                    
                    # Reset stdout for next test
                    sys.stdout = output
                
                # Store results
                results['all_passed'] = all_passed
                if all_passed:
                    results['output'] = "All test cases passed! Here's the output for n=5:\n" + test_cases[2][1]
                else:
                    results['output'] = "Some test cases failed:\n" + "\n".join(test_results)
                
            except Exception as e:
                results['output'] = str(e)
                results['all_passed'] = False
            
            # Reset stdout
            sys.stdout = sys.__stdout__
            
            # Update the output div and hidden input
            document['output'].text = results['output']
            document['pyodideResult'].value = window.JSON.stringify(results)
            
            # Show/hide submit button based on result
            if results['all_passed']:
                document['submitFinalBtn'].style.display = 'block'
                document['output'].text += '\n\n✅ Perfect! Click "Submit Solution" to proceed to the next level.'
            else:
                document['submitFinalBtn'].style.display = 'none'

        def run_code(event):
            event.preventDefault()
            run_tests()
            
        # Bind the run_tests function to the submit button
        document['submitBtn'].bind('click', run_code)
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
