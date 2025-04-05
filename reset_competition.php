<?php
require_once 'config.php';

// Delete competition started file
$competition_file = __DIR__ . '/competition_started.txt';
if (file_exists($competition_file)) {
    unlink($competition_file);
}

// Temporarily disable foreign key checks
$conn->query('SET FOREIGN_KEY_CHECKS = 0');

try {
    // Reset database tables and reset all times
    $conn->query('TRUNCATE TABLE progress');
    $conn->query('TRUNCATE TABLE contestants');
    
    // Reset session times for all users
    if (file_exists(session_save_path())) {
        foreach (glob(session_save_path() . "/sess_*") as $file) {
            unlink($file);
        }
    }
    
    // Re-enable foreign key checks
    $conn->query('SET FOREIGN_KEY_CHECKS = 1');
    
    // Clear all sessions
    session_start();
    session_destroy();
    
    echo "<script>alert('Competition reset successfully!'); window.location.href = 'admin.php';</script>";
} catch (Exception $e) {
    // Re-enable foreign key checks even if there's an error
    $conn->query('SET FOREIGN_KEY_CHECKS = 1');
    die("Error resetting competition: " . $e->getMessage());
}
?>
