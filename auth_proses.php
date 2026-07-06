<?php
// Sembunyikan error dari browser, catat di log server
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'koneksi.php';

// Helper function to write log
function write_login_log($message) {
    $log_file = __DIR__ . '/login_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'login') {
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];

        write_login_log("PROSES LOGIN - Percobaan login untuk username: '$username'");

        // Login bisa dengan username ATAU email
        $stmt = $conn->prepare(
            "SELECT id_user, password, nama_lengkap, role, status FROM users 
             WHERE username = ? OR email = ? LIMIT 1"
        );
        if (!$stmt) {
            write_login_log("QUERY ERROR - Gagal mempersiapkan statement: " . $conn->error);
            $_SESSION['error_msg'] = "Terjadi kesalahan sistem. Silakan coba lagi.";
            header("Location: login.php");
            exit;
        }
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        write_login_log("QUERY USER - Baris hasil query: " . $result->num_rows);

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            write_login_log("QUERY RESULT - Ditemukan user ID: " . $user['id_user'] . ", Nama: " . $user['nama_lengkap'] . ", Role: " . $user['role']);
            
            // Check if user status is active
            if (isset($user['status']) && $user['status'] === 'nonaktif') {
                write_login_log("LOGIN BLOCKED - Akun nonaktif.");
                $_SESSION['error_msg'] = "Akun Anda telah dinonaktifkan oleh Admin!";
                header("Location: login.php");
                exit;
            }

            if (password_verify($password, $user['password'])) {
                write_login_log("PASSWORD VERIFY - Sukses, password cocok.");
                
                $_SESSION['id_user']     = $user['id_user'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'] ?? $username;
                $_SESSION['role']         = $user['role'];
                
                // Write log to database user_logs
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $log_stmt = $conn->prepare("INSERT INTO user_logs (id_user, action, ip_address) VALUES (?, 'Login', ?)");
                if ($log_stmt) {
                    $log_stmt->bind_param("is", $user['id_user'], $ip_address);
                    $log_stmt->execute();
                }

                write_login_log("STATUS SESSION - " . json_encode($_SESSION));
                
                if($user['role'] == 'admin' || $user['role'] == 'kasir') {
                    write_login_log("REDIRECT - Diarahkan ke admin.php");
                    header("Location: admin.php");
                } else {
                    write_login_log("REDIRECT - Diarahkan ke index.php");
                    header("Location: index.php");
                }
                exit;
            } else {
                write_login_log("PASSWORD VERIFY ERROR - Password salah.");
                $_SESSION['error_msg'] = "Password salah!";
                header("Location: login.php");
                exit;
            }
        } else {
            write_login_log("QUERY ERROR - Username '$username' tidak ditemukan.");
            $_SESSION['error_msg'] = "Username tidak ditemukan!";
            header("Location: login.php");
            exit;
        }

    } elseif ($action == 'register') {
        $nama_lengkap = sanitize_input($_POST['nama_lengkap']);
        $no_hp = sanitize_input($_POST['no_hp']);
        $username = sanitize_input($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        
        // Cek username
        $check = $conn->prepare("SELECT id_user FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['error_msg'] = "Username sudah digunakan!";
            header("Location: register.php");
            exit;
        }

        $stmt = $conn->prepare(
            "INSERT INTO users (username, nama_lengkap, nama, password, no_hp, role) 
             VALUES (?, ?, ?, ?, ?, 'user')"
        );
        $stmt->bind_param("sssss", $username, $nama_lengkap, $nama_lengkap, $password, $no_hp);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Pendaftaran berhasil! Silakan login.";
            header("Location: login.php");
            exit;
        } else {
            $_SESSION['error_msg'] = "Terjadi kesalahan sistem.";
            header("Location: register.php");
            exit;
        }
    }
}
?>
