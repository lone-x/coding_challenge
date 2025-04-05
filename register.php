<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';

if (empty($username)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username is required']);
    exit;
}

// Check if username already exists
$stmt = $conn->prepare("SELECT id FROM contestants WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Username already taken']);
    exit;
}

// Insert new contestant
$stmt = $conn->prepare("INSERT INTO contestants (username) VALUES (?)");
$stmt->bind_param("s", $username);

if ($stmt->execute()) {
    $contestant_id = $stmt->insert_id;
    $_SESSION['contestant_id'] = $contestant_id;
    $_SESSION['username'] = $username;
    echo json_encode([
        'success' => true, 
        'id' => $contestant_id,
        'username' => $username
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed']);
}

$stmt->close();
$conn->close();
?>
