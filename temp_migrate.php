<?php
require 'koneksi.php';

$sql = "CREATE TABLE IF NOT EXISTS about_slider_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "Success creating table\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
