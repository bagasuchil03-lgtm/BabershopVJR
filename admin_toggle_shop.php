<?php
session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/settings.php';

// Simple admin check
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Toggle shop status
$current = get_setting('shop_open');
$newStatus = ($current === '1') ? '0' : '1';
set_setting('shop_open', $newStatus);

// Redirect back with a status message
$msg = ($newStatus === '1') ? 'Toko dibuka' : 'Toko ditutup';
header('Location: admin.php?status=' . urlencode($msg));
exit();
?>
