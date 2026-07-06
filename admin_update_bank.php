<?php
session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/settings.php';

// Simple admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bankAccount = $_POST['bank_account'] ?? '';
    if (empty($bankAccount)) {
        $msg = 'Bank account cannot be empty.';
        header('Location: admin.php?error=' . urlencode($msg));
        exit();
    }
    set_setting('bank_account', $bankAccount);
    $msg = 'Bank account updated.';
    header('Location: admin.php?success=' . urlencode($msg));
    exit();
}
?>
