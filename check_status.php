<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['contestant_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not registered']);
    exit;
}

// Get all contestants
$query = "SELECT username FROM contestants ORDER BY created_at";
$result = $conn->query($query);

$contestants = [];
while ($row = $result->fetch_assoc()) {
    $contestants[] = $row['username'];
}

// Check competition status
$status = 'waiting';
$competition_file = __DIR__ . '/competition_started.txt';
$competition_ended_file = __DIR__ . '/competition_ended.txt';

// Check if competition has ended
if (file_exists($competition_ended_file)) {
    $status = 'ended';
}

// Debug information
error_log('Checking competition file at: ' . $competition_file);
error_log('File exists: ' . (file_exists($competition_file) ? 'yes' : 'no'));

if (file_exists($competition_file)) {
    $file_content = file_get_contents($competition_file);
    error_log('File content: ' . $file_content);
    
    $start_time = intval($file_content);
    $current_time = time();
    $time_diff = $current_time - $start_time;
    
    // Debug information
    error_log("Competition check - Start time: $start_time, Current time: $current_time, Diff: $time_diff");
    
    if ($time_diff <= 3) {
        $status = 'starting';
        $countdown = 3 - $time_diff;
        error_log("Countdown active: $countdown seconds remaining");
    } else {
        $status = 'started';
        $_SESSION['competition_start_time'] = $start_time;
        error_log("Competition has fully started");
    }
} else {
    error_log('Competition file not found');
}

$response = [
    'status' => $status,
    'contestants' => $contestants,
    'debug' => [
        'current_time' => time(),
        'start_time' => $start_time ?? null,
        'time_diff' => $time_diff ?? null,
        'file_exists' => file_exists($competition_file)
    ]
];

if (isset($countdown)) {
    $response['countdown'] = $countdown;
}

echo json_encode($response);

$conn->close();
?>
