<?php
require 'koneksi.php';

$res = $conn->query("SHOW TABLES LIKE 'about_slider_photos'");
if ($res && $res->num_rows > 0) {
    echo "Table exists.\n";
    $q = $conn->query("SELECT COUNT(*) as c FROM about_slider_photos");
    $row = $q->fetch_assoc();
    echo "Count: " . $row['c'] . "\n";
} else {
    echo "Table does NOT exist.\n";
}
