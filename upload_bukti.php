<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: riwayat.php");
    exit;
}

$id_user = $_SESSION['id_user'];
$id_booking = intval($_GET['id']);

// Pastikan booking milik user ini
$query = "SELECT * FROM booking WHERE id_booking = ? AND (id_user = ? OR no_hp = (SELECT no_hp FROM users WHERE id_user = ?))";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $id_booking, $id_user, $id_user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: riwayat.php");
    exit;
}

$booking = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $filename = $_FILES['bukti_pembayaran']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = "bukti_" . $id_booking . "_" . time() . "." . $ext;
            $upload_dir = 'uploads/bukti/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_tmp = $_FILES['bukti_pembayaran']['tmp_name'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            
            $file_data = file_get_contents($file_tmp);
            $base64 = 'data:' . $mime_type . ';base64,' . base64_encode($file_data);

            if (move_uploaded_file($file_tmp, $upload_dir . $new_filename) || true) {
                $upd_query = "UPDATE booking SET bukti_pembayaran = ?, status_pembayaran = 'Menunggu Verifikasi', tanggal_pembayaran = NOW() WHERE id_booking = ?";
                $upd_stmt = $conn->prepare($upd_query);
                $upd_stmt->bind_param("si", $new_filename, $id_booking);
                
                if ($upd_stmt->execute()) {
                    $ins_file = $conn->prepare("INSERT INTO bukti_pembayaran_files (id_booking, file_data) VALUES (?, ?)");
                    $ins_file->bind_param("is", $id_booking, $base64);
                    $ins_file->execute();
                    
                    $_SESSION['success_msg'] = "Bukti pembayaran berhasil diupload dan sedang menunggu verifikasi.";
                    header("Location: riwayat.php");
                    exit;
                } else {
                    $error = "Gagal memperbarui database.";
                }
            } else {
                $error = "Gagal mengupload file.";
            }
        } else {
            $error = "Format file tidak didukung. Harap upload JPG, JPEG, PNG, atau PDF.";
        }
    } else {
        $error = "Harap pilih file bukti pembayaran.";
    }
}

// Rekening Bank
require_once 'settings.php';
$rekening_bank = get_setting('bank_account');
$qris_merchant = get_setting('qris_merchant_name') ?? 'Vijer Barbershop';
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Bukti Pembayaran - Vijer Barbershop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --bg-color: #111111; 
            --text-main: #f8f9fa; 
            --text-muted: #adb5bd;
            --gold-primary: #D4AF37; 
            --card-bg: #1a1a1a; 
            --border-color: rgba(212, 175, 55, 0.2);
        }
        body { background-color: var(--bg-color); color: var(--text-main); font-family: 'Inter', sans-serif; padding-top: 50px; }
        .card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 15px; }
        .text-gold { color: var(--gold-primary) !important; }
        .btn-gold { background-color: var(--gold-primary); color: #000; font-weight: bold; border: none; }
        .btn-gold:hover { opacity: 0.9; }
        .form-control { background-color: #2a2a2a; border: 1px solid #444; color: #fff; }
        .form-control:focus { background-color: #333; color: #fff; border-color: var(--gold-primary); box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25); }
    </style>
</head>
<body>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="text-center mb-4">
                <a href="riwayat.php" class="text-decoration-none text-muted mb-3 d-inline-block"><i class="fas fa-arrow-left me-2"></i>Kembali ke Riwayat</a>
                <h2 class="fw-bold"><i class="fas fa-file-invoice-dollar text-gold me-2"></i>Upload <span class="text-gold">Bukti Pembayaran</span></h2>
            </div>

            <div class="card p-4 shadow-lg">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="mb-4">
                    <p class="mb-1 text-muted">Kode Booking</p>
                    <h4 class="text-gold fw-bold"><?= $booking['kode_booking'] ?></h4>
                </div>

                <div class="mb-4">
                    <p class="mb-1 text-muted">Total Tagihan</p>
                    <h3 class="fw-bold">Rp <?= number_format($booking['total_harga'], 0, ',', '.') ?></h3>
                </div>

                <div class="alert alert-info bg-dark border-secondary text-light">
                    <?php if ($booking['metode_pembayaran'] == 'Transfer'): ?>
                        <strong>Info Rekening Transfer:</strong><br>
                        <?= nl2br(htmlspecialchars($rekening_bank)) ?>
                    <?php else: ?>
                        <strong>Info Pembayaran QRIS:</strong><br>
                        Silakan bayar menggunakan QRIS ke merchant: <?= htmlspecialchars($qris_merchant) ?>
                    <?php endif; ?>
                </div>

                <form action="upload_bukti.php?id=<?= $id_booking ?>" method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="bukti_pembayaran" class="form-label text-muted">Pilih File Bukti Pembayaran (JPG/PNG/PDF)</label>
                        <input type="file" class="form-control" id="bukti_pembayaran" name="bukti_pembayaran" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>

                    <button type="submit" class="btn btn-gold w-100 py-2 rounded-pill">
                        <i class="fas fa-upload me-2"></i> Upload Sekarang
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
