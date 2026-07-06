<?php
session_start();
require_once 'koneksi.php';
require_once 'wa-notifikasi.php'; // Include the WA helper
// Pastikan kolom nama_pelanggan ada (jika belum ada, tambahkan)
$checkCol = $conn->query("SHOW COLUMNS FROM booking LIKE 'nama_pelanggan'");
if ($checkCol && $checkCol->num_rows == 0) {
    $conn->query("ALTER TABLE booking ADD COLUMN nama_pelanggan VARCHAR(100) NOT NULL DEFAULT ''");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form (guest)
    $nama_pelanggan = sanitize_input($_POST['nama_pelanggan']);
    $no_hp = sanitize_input($_POST['no_hp']);
    
    // Normalisasi no hp (ubah 08 jadi 628)
    if (substr($no_hp, 0, 1) == '0') {
        $no_hp = '62' . substr($no_hp, 1);
    }
    
    $id_layanan = $_POST['id_layanan'];
    $id_gaya = !empty($_POST['id_gaya']) ? $_POST['id_gaya'] : NULL;
    $id_barber = $_POST['id_barber'];
    $tanggal_booking = $_POST['tanggal_booking'];
    $jam_booking = $_POST['jam_booking']; // Format H:i misal 14:00
    $catatan = sanitize_input($_POST['catatan']);
    $metode_pembayaran = sanitize_input($_POST['metode_pembayaran'] ?? '');

    // 1. Validasi: Pastikan jam ini belum dibooking (double check di backend)
    $jam_cek = $jam_booking . ':00'; // Sesuaikan dengan tipe data TIME di DB (H:i:s)
    $cek_stmt = $conn->prepare("SELECT id_booking FROM booking WHERE tanggal_booking = ? AND jam_booking = ? AND id_barber = ? AND status != 'batal'");
    $cek_stmt->bind_param("ssi", $tanggal_booking, $jam_cek, $id_barber);
    $cek_stmt->execute();
    if ($cek_stmt->get_result()->num_rows > 0) {
        $_SESSION['error_msg'] = "Mohon maaf, jadwal tersebut baru saja dibooking oleh pelanggan lain. Silakan pilih jadwal lain.";
        header("Location: booking.php");
        exit;
    }

    // 2. Ambil Harga dan Nama Layanan
    $layanan_stmt = $conn->prepare("SELECT nama_layanan, harga FROM layanan WHERE id_layanan = ?");
    $layanan_stmt->bind_param("i", $id_layanan);
    $layanan_stmt->execute();
    $layanan_result = $layanan_stmt->get_result()->fetch_assoc();
    $total_harga = $layanan_result['harga'];
    $nama_layanan = $layanan_result['nama_layanan'];

    // Ambil Nama Barber
    $barber_stmt = $conn->prepare("SELECT nama_barber FROM barber WHERE id_barber = ?");
    $barber_stmt->bind_param("i", $id_barber);
    $barber_stmt->execute();
    $nama_barber = $barber_stmt->get_result()->fetch_assoc()['nama_barber'];

    // 3. Generate Kode Booking Unik
    $random = strtoupper(substr(md5(time() . rand()), 0, 4));
    $kode_booking = "VJR-" . date('ymd') . "-" . $random;

    // 4. Insert Data Booking
    if ($id_gaya === NULL) {
        $query = "INSERT INTO booking (kode_booking, nama_pelanggan, no_hp, id_barber, id_layanan, tanggal_booking, jam_booking, status, total_harga, catatan, metode_pembayaran) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)";
        $insert_stmt = $conn->prepare($query);
        $insert_stmt->bind_param("sssiissdss", $kode_booking, $nama_pelanggan, $no_hp, $id_barber, $id_layanan, $tanggal_booking, $jam_cek, $total_harga, $catatan, $metode_pembayaran);
    } else {
        $query = "INSERT INTO booking (kode_booking, nama_pelanggan, no_hp, id_barber, id_layanan, id_gaya, tanggal_booking, jam_booking, status, total_harga, catatan, metode_pembayaran) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)";
        $insert_stmt = $conn->prepare($query);
        $insert_stmt->bind_param("sssiiissdss", $kode_booking, $nama_pelanggan, $no_hp, $id_barber, $id_layanan, $id_gaya, $tanggal_booking, $jam_cek, $total_harga, $catatan, $metode_pembayaran);
    }

    if ($insert_stmt->execute()) {
        
        // 5. Kirim Notifikasi WhatsApp
        // Jika helper WA dan konstanta diset, jalankan
        if (defined('WA_API_KEY') && WA_API_KEY != 'ISI_API_KEY_FONNTE_ANDA') {
            $pesan = "Halo *{$nama_pelanggan}*,\n\n";
            $pesan .= "Terima kasih telah melakukan reservasi di *Vijer Barbershop*. Berikut adalah detail booking Anda:\n\n";
            $pesan .= "Kode Booking: *{$kode_booking}*\n";
            $pesan .= "Layanan: {$nama_layanan}\n";
            $pesan .= "Barber: {$nama_barber}\n";
            $pesan .= "Tanggal: " . date('d M Y', strtotime($tanggal_booking)) . "\n";
            $pesan .= "Jam: {$jam_booking} WIB\n\n";
            $pesan .= "Tunjukkan kode ini saat tiba di lokasi. Sampai jumpa!";
            
            // Panggil dari wa-notifikasi.php (kita modifikasi fungsi jika perlu, atau gunakan fungsi umum)
            kirimNotifikasiWA($no_hp, $pesan);
        }

        // Redirect ke halaman sukses
        header("Location: booking-sukses.php?kode=" . $kode_booking);
        exit;
    } else {
        $_SESSION['error_msg'] = "Gagal membuat booking: " . $conn->error;
        header("Location: booking.php");
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
?>
