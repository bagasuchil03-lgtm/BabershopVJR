<?php
// settings.php – helper functions for key/value settings
// Ensure settings table exists
if ($conn->query("CREATE TABLE IF NOT EXISTS settings (`key` VARCHAR(50) PRIMARY KEY, `value` VARCHAR(255) NOT NULL) ENGINE=InnoDB;") === FALSE) {
    // ignore error if already exists
}

function get_setting($key) {
    global $conn;
    $stmt = $conn->prepare('SELECT `value` FROM settings WHERE `key` = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->bind_result($value);
    if ($stmt->fetch()) {
        $stmt->close();
        return $value;
    }
    $stmt->close();
    return null;
}

function set_setting($key, $value) {
    global $conn;
    // Upsert: insert or update
    $stmt = $conn->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $stmt->close();
}
?>
