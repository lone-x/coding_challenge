<?php
require_once 'config.php';

// Insert level information
$levels = [
    [
        1,
        'Fill in the Blanks',
        'Complete the Python code snippets by filling in the correct function names and keywords',
        'fill_blanks'
    ],
    [
        2,
        'Find the Mistake',
        'Identify and fix the bugs in the code',
        'debug'
    ],
    [
        3,
        'HTML/CSS Challenge',
        'Create a webpage matching the given design',
        'frontend'
    ],
    [
        4,
        'Algorithm Challenge',
        'Solve a coding problem with optimal time complexity',
        'algorithm'
    ],
    [
        5,
        'Full-stack Challenge',
        'Build a complete feature with frontend and backend',
        'fullstack'
    ]
];

$stmt = $conn->prepare("INSERT INTO levels (level_number, title, description, challenge_type) VALUES (?, ?, ?, ?)");

foreach ($levels as $level) {
    $stmt->bind_param("isss", $level[0], $level[1], $level[2], $level[3]);
    $stmt->execute();
}

echo "Levels setup complete!\n";
$conn->close();
?>
