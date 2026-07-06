<?php
session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/settings.php';

if (!isset($_GET['kode'])) {
    header('Location: index.php');
    exit();
}

$kode_booking = sanitize_input($_GET['kode']);

// Fetch booking details
$query = "
    SELECT b.*, l.nama_layanan, l.durasi_menit, br.nama_barber
    FROM booking b
    JOIN layanan l ON b.id_layanan = l.id_layanan
    JOIN barber br ON b.id_barber = br.id_barber
    WHERE b.kode_booking = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $kode_booking);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "Data booking tidak ditemukan.";
    exit();
}

$booking = $result->fetch_assoc();

// Fetch bank account setting
$bankAccount = get_setting('bank_account');
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Booking – Vijer Barbershop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --bg-color:#111111; --text-main:#f8f9fa; --gold-primary:#C9A96E; --border-color:rgba(201,169,110,0.3); }
        [data-theme="light"] { --bg-color:#f0f2f5; --text-main:#212529; --gold-primary:#b5952f; --border-color:rgba(0,0,0,0.15); }
        body { background-color:var(--bg-color); color:var(--text-main); font-family:'Montserrat',sans-serif; padding:40px 0; }
        .receipt-card { background:var(--card-bg, #1a1a1a); border:1px solid var(--border-color); border-radius:15px; padding:30px; max-width:800px; margin:auto; }
        .text-gold { color:var(--gold-primary)!important; }
        .detail-row { display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid rgba(255,255,255,0.05); }
        .detail-row:last-child { border:none; }
        .btn-gold { background:var(--gold-primary); color:#000; font-weight:600; border:none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="receipt-card">
            <h2 class="text-center mb-4 text-gold">Nota Booking</h2>
            <div class="detail-row"><span>Kode Booking</span><span class="fw-bold"><?php echo htmlspecialchars($kode_booking); ?></span></div>
            <div class="detail-row"><span>Nama</span><span><?php echo htmlspecialchars($booking['nama_pelanggan']); ?></span></div>
            <div class="detail-row"><span>Layanan</span><span><?php echo htmlspecialchars($booking['nama_layanan']); ?></span></div>
            <div class="detail-row"><span>Barber</span><span><?php echo htmlspecialchars($booking['nama_barber']); ?></span></div>
            <div class="detail-row"><span>Tanggal</span><span><?php echo date('d F Y', strtotime($booking['tanggal_booking'])); ?></span></div>
            <div class="detail-row"><span>Jam</span><span><?php echo date('H:i', strtotime($booking['jam_booking'])); ?> WIB</span></div>
            <div class="detail-row"><span>Total Harga</span><span class="text-gold fw-bold">Rp <?php echo number_format($booking['total_harga'],0,',','.'); ?></span></div>
            <?php if (!empty($bankAccount)) : ?>
            <div class="detail-row"><span>Rekening Bank</span><span class="text-gold fw-bold"><?php echo htmlspecialchars($bankAccount); ?></span></div>
            <?php endif; ?>
            <div class="text-center mt-4">
                <button class="btn btn-gold" onclick="window.print()">Print / Simpan</button>
                <a href="index.php" class="btn btn-outline-secondary ms-2">Kembali ke Beranda</a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
