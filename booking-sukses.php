<?php
session_start();
require_once 'koneksi.php';
require_once 'settings.php';

if (!isset($_GET['kode'])) {
    header("Location: index.php");
    exit;
}

$kode_booking = sanitize_input($_GET['kode']);

// Handle upload proof of payment
$upload_error = '';
$upload_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bukti_pembayaran'])) {
    if ($_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['bukti_pembayaran']['tmp_name'];
        $file_name = $_FILES['bukti_pembayaran']['name'];
        $file_size = $_FILES['bukti_pembayaran']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);
        
        if (!in_array($file_ext, $allowed_exts) || strpos($mime_type, 'image/') !== 0) {
            $upload_error = "Format file tidak didukung. Harap upload gambar (JPG, PNG, GIF).";
        } elseif ($file_size > 2 * 1024 * 1024) {
            $upload_error = "Ukuran file maksimal 2MB.";
        } else {
            $new_file_name = 'bukti_' . $kode_booking . '_' . uniqid() . '.' . $file_ext;
            $dest_path = 'uploads/bukti/' . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $dest_path)) {
                $now = date('Y-m-d H:i:s');
                $update_stmt = $conn->prepare("UPDATE booking SET bukti_pembayaran = ?, status_pembayaran = 'Menunggu Verifikasi', tanggal_pembayaran = ? WHERE kode_booking = ?");
                $update_stmt->bind_param("sss", $new_file_name, $now, $kode_booking);
                if ($update_stmt->execute()) {
                    $upload_success = "Bukti pembayaran berhasil diunggah! Menunggu verifikasi admin.";
                } else {
                    $upload_error = "Gagal memperbarui data booking di database.";
                }
            } else {
                $upload_error = "Gagal menyimpan file di server.";
            }
        }
    } else {
        $upload_error = "Terjadi kesalahan saat mengunggah file.";
    }
}

// Fetch booking details
$query = "
    SELECT b.*, l.nama_layanan, l.durasi_menit, br.nama_barber 
    FROM booking b
    JOIN layanan l ON b.id_layanan = l.id_layanan
    JOIN barber br ON b.id_barber = br.id_barber
    WHERE b.kode_booking = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $kode_booking);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "Data booking tidak ditemukan.";
    exit;
}

$booking = $result->fetch_assoc();
$status_p = $booking['status_pembayaran'];
$cancel_msg = '';
// Tampilkan pesan jika pembatalan berhasil (dari redirect)
if (isset($_GET['canceled'])) {
    $cancel_msg = 'Booking telah dibatalkan.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    $cancel_stmt = $conn->prepare("UPDATE booking SET status_pembayaran='Dibatalkan' WHERE kode_booking = ?");
    $cancel_stmt->bind_param('s', $kode_booking);
    $cancel_stmt->execute();
    // Redirect to avoid form resubmission and refresh data
    header('Location: booking-sukses.php?kode=' . urlencode($kode_booking) . '&canceled=1');
    exit;
}

// QR URL for booking check-in
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($kode_booking);
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Sukses - Vijer Barbershop</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-color: #111111;
            --text-main: #f8f9fa;
            --text-muted: #adb5bd;
            --gold-primary: #C9A96E;
            --card-bg: #1a1a1a;
            --border-color: rgba(201, 169, 110, 0.3);
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 40px 0;
        }
        h1, h2, h3, h4, h5, h6 { font-family: 'Playfair Display', serif; font-weight: 700; }
        .text-gold { color: var(--gold-primary) !important; }
        
        .success-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
            position: relative;
            overflow: hidden;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: rgba(37, 211, 102, 0.1);
            color: #25D366;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 20px;
        }
        
        .booking-details {
            background: rgba(0,0,0,0.2);
            border: 1px dashed var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding-bottom: 8px;
        }
        .detail-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .detail-label { color: var(--text-muted); font-size: 0.9rem; }
        .detail-value { font-weight: 600; text-align: right; }
        
        .qr-section {
            background: #fff;
            padding: 15px;
            border-radius: 12px;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .payment-box {
            background: linear-gradient(135deg, rgba(201, 169, 110, 0.1), rgba(0,0,0,0.3));
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .btn-gold {
            background: linear-gradient(135deg, #C9A96E, #B8943F);
            color: #000;
            font-weight: 700;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            transition: all 0.3s;
        }
        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(201, 169, 110, 0.3);
        }
        .btn-outline-gold {
            background: transparent;
            color: var(--gold-primary);
            border: 2px solid var(--gold-primary);
            font-weight: 700;
            padding: 12px 30px;
            border-radius: 50px;
            transition: all 0.3s;
        }
        .btn-outline-gold:hover {
            background: var(--gold-primary);
            color: #000;
        }
    </style>
    <style>
    /* Styling khusus untuk cetak */
    @media print {
        /* Sembunyikan tombol dan elemen navigasi saat mencetak */
        .btn, .btn-outline-gold, .btn-gold, .text-center.mt-5, .payment-box button { display: none !important; }
        .payment-box, .booking-details, .qr-section { page-break-inside: avoid; }
    }
    /* Highlight informasi rekening bank yang dipilih */
    .bank-info {
        font-weight: 600;
        color: var(--gold-primary);
        margin-top: 1rem;
        font-size: 1.1rem;
    }
    </style>
</head>
<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
            <div class="text-center">
    <img src="assets/logo.svg" alt="Vijer Barbershop" class="mb-3" style="max-width:120px;">
    <div class="success-icon">
        <i class="fas fa-check-circle"></i>
    </div>
    <?php
// Mapping of payment methods to account details
$bankInfo = [
    'Transfer Bank BRI' => [
        'account' => '0023-01-098765-53-2',
        'owner' => 'Barbershop Anda'
    ],
    'Transfer Bank BCA' => [
        'account' => '822-1234-567',
        'owner' => 'Barbershop Anda'
    ],
    'Transfer Bank Mandiri' => [
        'account' => '131-00-1234567-9',
        'owner' => 'Barbershop Anda'
    ],
    // Add digital wallets if needed
];
$method = $booking['metode_pembayaran'] ?? 'Cash';
$accountNumber = '';
$accountOwner = '';
if (isset($bankInfo[$method])) {
    $accountNumber = $bankInfo[$method]['account'];
    $accountOwner = $bankInfo[$method]['owner'];
}
?>
<h2 class="fw-bold mb-2">Booking Berhasil!</h2>
<p class="text-muted">Detail reservasi telah dikirim ke WhatsApp Anda.</p>
<p class="text-gold fw-bold">Terima kasih telah melakukan booking di Vijer Barbershop!</p>
<p class="text-success fw-bold">Booking Anda telah diterima.</p>


<?php if (!empty($cancel_msg)) echo "<div class='alert alert-warning mt-2'>$cancel_msg</div>"; ?>
</div>            </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <h5 class="text-gold border-bottom border-secondary pb-2 mb-3">Detail Booking</h5>
                            <div class="booking-details">
                                <div class="detail-row">
                                    <span class="detail-label">Nama</span>
                                    <span class="detail-value"><?= htmlspecialchars($booking['nama_pelanggan']) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Kode Booking</span>
                                    <span class="detail-value text-gold fs-5"><?= $kode_booking ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Tanggal</span>
                                    <span class="detail-value"><?= date('d F Y', strtotime($booking['tanggal_booking'])) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Jam</span>
                                    <span class="detail-value"><?= date('H:i', strtotime($booking['jam_booking'])) ?> WIB</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Layanan</span>
                                    <span class="detail-value"><?= htmlspecialchars($booking['nama_layanan']) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Barber</span>
                                    <span class="detail-value"><?= htmlspecialchars($booking['nama_barber']) ?></span>
                                </div>
                                <div class="detail-row mt-3 pt-3 border-top border-secondary">
                                    <span class="detail-label">Total Harga</span>
                                    <span class="detail-value text-gold fs-5">Rp <?= number_format($booking['total_harga'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                        <div class="payment-box">
                            <h5 class="text-gold mb-3"><i class="fas fa-wallet me-2"></i>Detail Pembayaran</h5>
                            <?php if (!empty($accountNumber)): ?>
                                <div class="payment-detail mb-3 p-3 rounded" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-color);">
                                    <h6 class="fw-bold mb-2">Metode Pembayaran: <span class="text-gold"><?= htmlspecialchars($method); ?></span></h6>
                                    <p class="mb-1"><strong>Nomor Rekening:</strong> <span id="account-number" class="text-gold fw-bold"><?= htmlspecialchars($accountNumber); ?></span>
                                        <button type="button" class="btn btn-sm btn-outline-gold ms-2" onclick="copyToClipboard('account-number')">Salin</button>
                                    </p>
                                    <p class="mb-0"><strong>Atas Nama:</strong> <span class="text-gold fw-bold"><?= htmlspecialchars($accountOwner); ?></span></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($upload_success)): ?>
                                <div class="alert alert-success p-2 small"><i class="fas fa-check-circle me-1"></i><?= $upload_success ?></div>
                            <?php endif; ?>
                            <?php if (!empty($upload_error)): ?>
                                <div class="alert alert-danger p-2 small"><i class="fas fa-exclamation-circle me-1"></i><?= $upload_error ?></div>
                            <?php endif; ?>
                            <div class="row align-items-center">
                                <div class="col-md-7 text-start">
                                    <?php
                                    $metode = $booking['metode_pembayaran'] ?? 'Cash';
                                    $no_rek = '';
                                    $atas_nama = 'Vijer Barbershop';
                                    
                                    if (strpos($metode, 'BRI') !== false) {
                                        $no_rek = '0023-01-098765-53-2';
                                    } elseif (strpos($metode, 'BCA') !== false) {
                                        $no_rek = '822-1234-567';
                                    } elseif (strpos($metode, 'Mandiri') !== false) {
                                        $no_rek = '131-00-1234567-8';
                                    } elseif ($metode === 'ShopeePay') {
                                        $no_rek = '082177657214';
                                    } elseif ($metode === 'GoPay') {
                                        $no_rek = '081234567890';
                                    } elseif ($metode === 'OVO') {
                                        $no_rek = '081234567891';
                                    } elseif ($metode === 'DANA') {
                                        $no_rek = '081234567892';
                                    }
                                    
                                    $status_p = $booking['status_pembayaran'];
                                    $status_badge_class = 'bg-secondary';
                                    if ($status_p === 'Lunas') $status_badge_class = 'bg-success text-white';
                                    elseif ($status_p === 'Menunggu Verifikasi') $status_badge_class = 'bg-warning text-dark';
                                    elseif ($status_p === 'Ditolak') $status_badge_class = 'bg-danger text-white';
                                    elseif ($status_p === 'Dibatalkan') $status_badge_class = 'bg-secondary text-white';
                                    ?>
                                    
                                    <!-- Detail Pembayaran Box -->
                                    <div class="p-3 mb-4 rounded shadow-sm" style="background-color: var(--card-bg, #1a1a1a); border: 1px solid var(--border-color, rgba(212,175,55,0.3));">
                                        <h6 class="fw-bold mb-3"><i class="fas fa-wallet text-gold me-2"></i>Detail Pembayaran</h6>
                                        
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Metode / Bank:</small>
                                            <span class="fw-bold text-gold"><?= htmlspecialchars($metode) ?></span>
                                        </div>

                                        <?php if ($metode === 'QRIS'): ?>
                                            <div class="mb-3">
                                                <small class="text-muted d-block">Instruksi:</small>
                                                <span class="fw-semibold">Silakan scan kode QRIS di samping kanan.</span>
                                            </div>
                                        <?php elseif ($no_rek === ''): ?>
                                            <div class="mb-3">
                                                <small class="text-muted d-block">Instruksi:</small>
                                                <span class="fw-semibold">Pembayaran secara tunai (Cash Only) di kasir barbershop.</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="mb-2 position-relative">
                                                <small class="text-muted d-block">No. Rekening / VA:</small>
                                                <div class="d-flex align-items-center mt-1">
                                                    <span class="fw-bold fs-5 me-3" id="rekNumber"><?= $no_rek ?></span>
                                                    <button type="button" class="btn btn-sm btn-outline-gold py-0 px-2 rounded-pill" onclick="copyToClipboard('rekNumber')" title="Salin Nomor Rekening">
                                                        <i class="fas fa-copy"></i> Salin
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <small class="text-muted d-block">Atas Nama:</small>
                                                <span class="fw-semibold"><?= $atas_nama ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($status_p === 'Menunggu Pembayaran'): ?>
                                            <hr class="border-secondary my-3" />
                                            <form action="" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan booking ini?');">
                                                <button type="submit" name="cancel" class="btn btn-outline-danger btn-sm w-100">
                                                    <i class="fas fa-times-circle me-1"></i> Batalkan Booking
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <p class="small text-muted mb-3">Status Pembayaran: 
                                        <span class="badge <?= $status_badge_class ?>"><?= htmlspecialchars($status_p); ?></span>
                                    </p>
                                    <hr class="border-secondary my-2" />
                                    <?php if ($status_p === 'Lunas'): ?>
                                        <div class="alert alert-success p-3 small text-start">
                                            <i class="fas fa-check-circle me-1"></i> Pembayaran Anda telah dikonfirmasi LUNAS. Silakan datang sesuai dengan jadwal pemesanan Anda.
                                        </div>
                                    <?php else: ?>
                                        <p class="small text-muted mb-2 text-start">Silakan selesaikan pembayaran ke rekening di atas.</p>
                                        <div class="mt-2 fw-semibold">Jumlah Transfer: <span class="text-gold">Rp <?= number_format($booking['total_harga'], 0, ',', '.'); ?></span></div>
                                    <?php endif; ?>
                                    <?php if ($status_p === 'Menunggu Pembayaran' || $status_p === 'Ditolak'): ?>
                                        <form action="" method="POST" enctype="multipart/form-data" class="mt-3 text-start">
                                            <label class="form-label small fw-bold">Upload Bukti Pembayaran:</label>
                                            <div class="input-group input-group-sm">
                                                <input type="file" name="bukti_pembayaran" class="form-control" accept="image/*" required>
                                                <button type="submit" class="btn btn-gold">Unggah</button>
                                            </div>
                                            <small class="text-muted" style="font-size:0.75rem;">Maksimal file 2MB (JPG, PNG, GIF)</small>
                                        </form>
                                    <?php elseif ($status_p === 'Menunggu Verifikasi'): ?>
                                        <div class="alert alert-info p-3 small text-start">
                                            <i class="fas fa-spinner fa-spin me-1"></i> Bukti pembayaran telah diunggah. Kami sedang memverifikasi pembayaran Anda.
                                            <?php if (!empty($booking['bukti_pembayaran'])): ?>
                                                <div class="mt-2 text-center text-md-start">
                                                    <a href="uploads/bukti/<?= htmlspecialchars($booking['bukti_pembayaran']) ?>" target="_blank" class="small text-gold text-decoration-none">
                                                        <i class="fas fa-image me-1"></i> Lihat Bukti yang Diunggah
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-5 text-center mb-3 mb-md-0 d-flex flex-column justify-content-center align-items-center">
                                <?php if ($booking['metode_pembayaran'] === 'QRIS' && $status_p !== 'Lunas'): ?>
                                    <?php
                                    $qrisImgPath = get_setting('qris_image');
                                    $qrisMerchantName = get_setting('qris_merchant_name') ?? 'NAFIS LAILATUL BADRIYAH';
                                    $qrisMerchantBank = get_setting('qris_merchant_bank') ?? 'BRI (BritAma)';
                                    $qrisMerchantAcct = get_setting('qris_merchant_account') ?? '0022 **** **** 509';
                                    ?>
                                    <div style="background: white; padding: 16px; border-radius: 12px; display: inline-block; text-align: center;">
                                        <img src="https://upload.wikimedia.org/wikipedia/commons/a/a2/Logo_QRIS.svg" alt="QRIS" width="80" class="mb-2"><br>
                                        <?php if ($qrisImgPath && file_exists($qrisImgPath)): ?>
                                            <img src="<?= htmlspecialchars($qrisImgPath) ?>?t=<?= filemtime($qrisImgPath) ?>" alt="QRIS Payment" style="max-width:220px; border-radius:6px;">
                                        <?php else: ?>
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=QRIS_VIJER_BARBERSHOP" alt="QRIS Payment">
                                        <?php endif; ?>
                                        <div style="margin-top:10px; color:#333;">
                                            <div style="font-weight:800; font-size:14px;"><?= htmlspecialchars($qrisMerchantName) ?></div>
                                            <div style="font-size:12px; color:#666;"><?= htmlspecialchars($qrisMerchantBank) ?> &ndash; <?= htmlspecialchars($qrisMerchantAcct) ?></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="small text-muted mb-2">Butuh bantuan? Hubungi kami:</p>
                                    <a href="https://wa.me/<?= WA_ADMIN_NUMBER ?>" class="btn btn-outline-gold btn-sm rounded-pill px-3" target="_blank">
                                        <i class="fab fa-whatsapp me-1"></i> Hubungi WA Admin
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-5">
                        <a href="index.php" class="btn btn-outline-gold me-2">Kembali ke Beranda</a>
                        <a href="booking.php" class="btn btn-gold">Booking Lagi</a>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>

    <script>
        // Copy to clipboard function
        function copyToClipboard(elementId) {
            const el = document.getElementById(elementId);
            if (!el) return;
            const range = document.createRange();
            range.selectNodeContents(el);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
            try {
                document.execCommand('copy');
                // Optional: show feedback
                alert('Nomor rekening disalin ke clipboard');
            } catch (err) {
                console.error('Copy command failed', err);
            }
            sel.removeAllRanges();
        }

    </script>
</body>
</html>
