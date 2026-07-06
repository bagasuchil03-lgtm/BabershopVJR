<?php
// proses_pembayaran.php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_booking_barbershop";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_pesanan']) && isset($_POST['aksi'])) {
    $id_pesanan = (int) $_POST['id_pesanan'];
    $aksi = $_POST['aksi'];
    
    // Tentukan status berdasarkan tombol yang diklik
    if ($aksi == 'konfirmasi') {
        $status_baru = 'Dikonfirmasi';
    } elseif ($aksi == 'tolak') {
        $status_baru = 'Ditolak';
    } else {
        // Aksi tidak valid
        header("Location: admin_pesanan.php?pesan=aksi_invalid");
        exit;
    }

    // Update status di database menggunakan Prepared Statement untuk keamanan dari SQL Injection
    $stmt = $conn->prepare("UPDATE pesanan SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status_baru, $id_pesanan);

    if ($stmt->execute()) {
        // Redirect kembali ke halaman admin dengan pesan sukses
        header("Location: admin_pesanan.php?pesan=sukses_update");
    } else {
        // Redirect dengan pesan error
        header("Location: admin_pesanan.php?pesan=gagal_update");
    }

    $stmt->close();
} else {
    // Jika diakses langsung tanpa method POST
    header("Location: admin_pesanan.php");
}

$conn->close();
?>
