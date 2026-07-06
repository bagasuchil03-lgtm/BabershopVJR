<?php
require_once 'koneksi.php';

$hash = '$2y$10$xNaNK3tj7TBwZ705oWPtZOukUcHcb2oyPCYH5ThQ5X1JK6IR2d2hC';
$username = 'admin_vijer';

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $hash, $username);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo "<h2 style='color:green; font-family:sans-serif;'>✅ Password admin berhasil diset!</h2>";
    echo "<p style='font-family:sans-serif;'>Silakan login dengan:<br>
          <b>Username:</b> admin_vijer<br>
          <b>Password:</b> password</p>";
    echo "<a href='login.php' style='color:blue;'>→ Ke Halaman Login</a>";
} else {
    echo "<h2 style='color:red;'>Gagal: " . $conn->error . " (rows: " . $stmt->affected_rows . ")</h2>";
}
?>
