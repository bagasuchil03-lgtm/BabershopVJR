<?php
session_start();
if (isset($_SESSION['id_user'])) {
    require_once 'koneksi.php';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $log_stmt = $conn->prepare("INSERT INTO user_logs (id_user, action, ip_address) VALUES (?, 'Logout', ?)");
    if ($log_stmt) {
        $log_stmt->bind_param("is", $_SESSION['id_user'], $ip_address);
        $log_stmt->execute();
    }
}
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
header("Location: login.php");
exit;
?>
