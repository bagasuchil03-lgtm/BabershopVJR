<?php
// koneksi.php
// File ini digunakan untuk menghubungkan aplikasi PHP dengan database MySQL.

$host = 'localhost'; // Server database
$username = 'root';  // Username database (default XAMPP: root)
$password = '';      // Password database (default XAMPP: kosong)
$database = 'db_booking_barbershop'; // Nama database yang digunakan

// Membuat koneksi menggunakan mysqli (Procedural + Object Oriented concept)
$conn = new mysqli($host, $username, $password, $database);

// Mengecek apakah koneksi berhasil
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

// Mengatur charset utf8mb4 untuk mendukung berbagai karakter termasuk emoji
$conn->set_charset("utf8mb4");

    // Pastikan kolom nama_pelanggan ada di tabel booking
    $checkCol = $conn->query("SHOW COLUMNS FROM booking LIKE 'nama_pelanggan'");
    if ($checkCol && $checkCol->num_rows == 0) {
        $conn->query("ALTER TABLE booking ADD COLUMN nama_pelanggan VARCHAR(100) NOT NULL");
    }


// ==========================================
// KONFIGURASI WHATSAPP API (FONNTE)
// ==========================================
// Daftar di https://fonnte.com untuk mendapatkan API Key (gratis untuk testing)
define('WA_API_URL', 'https://api.fonnte.com/send');
define('WA_API_KEY', 'ISI_API_KEY_FONNTE_ANDA'); // Ganti dengan API Key Fonnte Anda
define('WA_ADMIN_NUMBER', '6281264626488'); // Nomor WA Admin untuk menerima notifikasi

// ==========================================
// KONFIGURASI UMUM
// ==========================================
define('SITE_NAME', 'Vijer Barbershop');
define('SITE_TAGLINE', 'Premium Barbershop Jepara');
define('SITE_PHONE', '+6281264626488');
define('SITE_EMAIL', 'info@vijerbarbershop.com');
define('SITE_ADDRESS', 'Jl. Kartini No. 123, Jepara, Jawa Tengah');
define('SITE_INSTAGRAM', '@vijer.barbershop');

// Fungsi pembantu untuk mencegah SQL Injection (Data Sanitization)
// Sangat disarankan tetap menggunakan Prepared Statements ($stmt) pada operasi krusial
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}
?>
