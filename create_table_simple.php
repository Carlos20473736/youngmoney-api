<?php
header('Content-Type: text/plain');
require_once __DIR__ . '/database.php';

$conn = getDbConnection();

$sql = "CREATE TABLE IF NOT EXISTS spin_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    prize_value INT NOT NULL,
    prize_index INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at)
)";

if ($conn->query($sql)) {
    echo "SUCCESS: Table created!\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}

$conn->close();
?>
