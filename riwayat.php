<?php
// Sembunyikan error dari browser
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$id_user = $_SESSION['id_user'];

// Query riwayat: pakai COALESCE untuk handle barber.id vs barber.id_barber
$query = "SELECT b.*, 
           l.nama_layanan, 
           g.nama_gaya, 
           br.nama_barber 
          FROM booking b 
          LEFT JOIN layanan l  ON b.id_layanan = l.id_layanan 
          LEFT JOIN barber br  ON (b.id_barber = COALESCE(br.id_barber, br.id))
          LEFT JOIN gaya_rambut g ON b.id_gaya = g.id_gaya 
          WHERE b.no_hp = (SELECT no_hp FROM users WHERE id_user = ? LIMIT 1)
             OR b.id_user = ?
          ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("ii", $id_user, $id_user);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = null;
}

$active_bookings = [];
$history_bookings = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (in_array(strtolower($row['status'] ?? ''), ['selesai', 'batal', 'ditolak'])) {
            $history_bookings[] = $row;
        } else {
            $active_bookings[] = $row;
        }
    }
}

// Ambil no_hp user
$user_stmt = $conn->prepare("SELECT no_hp FROM users WHERE id_user = ?");
$user_stmt->bind_param("i", $id_user);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$no_hp_user = $user_data['no_hp'] ?? '';

// Update notifikasi jadi terbaca jika ada parameter mark_read
if (isset($_GET['mark_read']) && $_GET['mark_read'] == 1 && !empty($no_hp_user)) {
    $upd = $conn->prepare("UPDATE notifikasi SET status_baca = 1 WHERE no_hp = ?");
    $upd->bind_param("s", $no_hp_user);
    $upd->execute();
    header("Location: riwayat.php");
    exit;
}

// Ambil notifikasi
$notif_count = 0;
$notifikasi_list = [];
if (!empty($no_hp_user)) {
    $n_stmt = $conn->prepare("SELECT * FROM notifikasi WHERE no_hp = ? ORDER BY tanggal_dibuat DESC LIMIT 10");
    $n_stmt->bind_param("s", $no_hp_user);
    $n_stmt->execute();
    $n_res = $n_stmt->get_result();
    while ($n = $n_res->fetch_assoc()) {
        $notifikasi_list[] = $n;
        if ($n['status_baca'] == 0) $notif_count++;
    }
}

// Nomor WA Admin (ganti sesuai kebutuhan)
$no_wa_admin = "6281234567890";
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Booking - Vijer Barbershop</title>
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
            --nav-bg: rgba(17, 17, 17, 0.95);
        }
        [data-theme="light"] {
            --bg-color: #f0f2f5;
            --text-main: #212529;
            --text-muted: #6c757d;
            --gold-primary: #b5952f;
            --card-bg: #ffffff;
            --border-color: rgba(0, 0, 0, 0.15);
            --nav-bg: rgba(255, 255, 255, 0.95);
        }
        body { background-color: var(--bg-color); color: var(--text-main); font-family: 'Inter', sans-serif; padding-top: 80px; transition: background-color 0.3s, color 0.3s; }
        h1, h2, h3, h4, .brand-text { font-family: 'Outfit', sans-serif; color: var(--text-main); }
        p, span, li { color: var(--text-main); }
        .text-muted { color: var(--text-muted) !important; }
        
        .navbar { background-color: var(--nav-bg); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border-color); }
        .navbar-brand { color: var(--gold-primary) !important; font-weight: 800; letter-spacing: 1px; }
        .nav-link { color: var(--text-main) !important; transition: color 0.3s ease; }
        .nav-link:hover, .nav-link.active { color: var(--gold-primary) !important; }
        
        .history-card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 15px; margin-bottom: 20px; transition: background-color 0.3s, border-color 0.3s; }
        .history-card:hover { border-color: var(--gold-primary); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        
        .qr-box { background-color: #fff; padding: 10px; border-radius: 10px; display: inline-block; }
        
        /* Badge Colors */
        .badge-pending { background-color: #ffc107; color: #000; }
        .badge-disetujui, .badge-diterima { background-color: #0dcaf0; color: #000; }
        .badge-diproses { background-color: #0d6efd; color: #fff; }
        .badge-selesai { background-color: #198754; color: #fff; }
        .badge-batal, .badge-ditolak { background-color: #dc3545; color: #fff; }
        .badge-menunggu_pembayaran { background-color: #fd7e14; color: #fff; }
        .badge-menunggu_verifikasi { background-color: #17a2b8; color: #fff; }
        .badge-lunas { background-color: #20c997; color: #fff; }
        
        .text-gold { color: var(--gold-primary) !important; }
        .btn-wa { background-color: #25D366; color: white; border: none; }
        .btn-wa:hover { background-color: #128C7E; color: white; }
        .btn-gold { background-color: var(--gold-primary); color: #000; font-weight: bold; border: none; }
        .btn-gold:hover { opacity: 0.9; }
        .btn-outline-gold { background-color: transparent; color: var(--gold-primary); border: 2px solid var(--gold-primary); font-weight: 600; transition: all 0.3s ease; }
        .btn-outline-gold:hover { background-color: var(--gold-primary); color: #000; }
        .theme-toggle { cursor: pointer; font-size: 1.2rem; color: var(--text-main); background: none; border: none; padding: 5px 10px; transition: color 0.3s; }
        .theme-toggle:hover { color: var(--gold-primary); }

        .nav-tabs { border-bottom-color: var(--border-color); }
        .nav-tabs .nav-link { color: var(--text-main); border: none; border-bottom: 2px solid transparent; font-weight: 500; }
        .nav-tabs .nav-link:hover { border-color: transparent; color: var(--gold-primary); }
        .nav-tabs .nav-link.active { background: transparent; border-color: var(--gold-primary); color: var(--gold-primary); }

        /* Notification Dropdown */
        .notif-dropdown { width: 320px; max-height: 400px; overflow-y: auto; background-color: var(--card-bg); border: 1px solid var(--border-color); }
        .notif-item { border-bottom: 1px solid var(--border-color); padding: 10px 15px; transition: background 0.2s; }
        .notif-item:hover { background-color: rgba(201,169,110,0.05); }
        .notif-unread { background-color: rgba(201,169,110,0.1); font-weight: 600; }
        .notif-time { font-size: 0.75rem; color: var(--text-muted); }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand fs-3" href="index.php">
                <i class="fas fa-cut me-2"></i>VIJER
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-toggle="target" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon" style="filter: invert(1) grayscale(100%) brightness(200%);"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link" href="katalog.php">Layanan & Gaya</a></li>
                    <li class="nav-item"><a class="nav-link active" href="riwayat.php">Riwayat Booking</a></li>
                    
                    <li class="nav-item dropdown ms-lg-3">
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if($notif_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                <?= $notif_count ?>
                            </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end notif-dropdown shadow" aria-labelledby="notifDropdown">
                            <li><h6 class="dropdown-header text-gold">Notifikasi</h6></li>
                            <?php if(empty($notifikasi_list)): ?>
                                <li><span class="dropdown-item text-muted text-center py-3">Belum ada notifikasi</span></li>
                            <?php else: ?>
                                <?php foreach($notifikasi_list as $n): ?>
                                    <li>
                                        <div class="dropdown-item notif-item <?= $n['status_baca'] == 0 ? 'notif-unread' : '' ?>">
                                            <div class="text-wrap" style="font-size: 0.85rem;"><?= htmlspecialchars($n['pesan']) ?></div>
                                            <div class="notif-time mt-1"><?= date('d/m/Y H:i', strtotime($n['tanggal_dibuat'])) ?></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center text-gold small" href="riwayat.php?mark_read=1">Tandai Semua Sudah Dibaca</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-gold px-4 rounded-pill" href="logout.php">Logout</a>
                    </li>
                    <li class="nav-item ms-3">
                        <button class="theme-toggle" id="themeToggle" title="Ganti Tema">
                            <i class="fas fa-moon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h2 class="fw-bold mb-4"><i class="fas fa-history text-gold me-2"></i>Status <span class="text-gold">Reservasi</span></h2>

        <?php if(isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success p-3" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
            </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4" id="bookingTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-bookings" type="button" role="tab" aria-controls="active-bookings" aria-selected="true">
                    Booking Aktif <span class="badge bg-primary ms-1"><?= count($active_bookings) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-bookings" type="button" role="tab" aria-controls="history-bookings" aria-selected="false">
                    Riwayat / Selesai <span class="badge bg-secondary ms-1"><?= count($history_bookings) ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="bookingTabsContent">
            
            <!-- Tab Booking Aktif -->
            <div class="tab-pane fade show active" id="active-bookings" role="tabpanel" aria-labelledby="active-tab">
                <?php if(empty($active_bookings)): ?>
                    <div class="text-center py-5">
                        <i class="far fa-calendar-times fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Tidak ada booking aktif</h4>
                        <a href="booking.php" class="btn btn-warning mt-3 rounded-pill px-4">Booking Sekarang</a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach($active_bookings as $row): 
                            $status_class = 'badge-' . str_replace(' ', '_', strtolower($row['status']));
                            $payment_status_class = 'badge-' . str_replace(' ', '_', strtolower($row['status_pembayaran'] ?? 'menunggu pembayaran'));
                            
                            $jam_tampil = substr($row['jam_booking'], 0, 5);
                            $tgl_tampil = date('d M Y', strtotime($row['tanggal_booking']));
                            
                            $pesan_wa = "Halo Admin Vijer Barbershop, saya ingin konfirmasi booking saya:%0A"
                                      . "- Kode Booking: *" . $row['kode_booking'] . "*%0A"
                                      . "- Layanan: " . $row['nama_layanan'] . "%0A"
                                      . "- Tanggal: " . $tgl_tampil . "%0A"
                                      . "- Jam: " . $jam_tampil . "%0A"
                                      . "- Barber: " . $row['nama_barber'] . "%0A"
                                      . "Terima kasih.";
                        ?>
                        <div class="col-lg-6">
                            <div class="history-card p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <span class="text-muted small">Kode Booking</span>
                                        <h4 class="text-gold mb-0 fw-bold"><?= $row['kode_booking'] ?></h4>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge rounded-pill px-3 py-2 <?= $status_class ?> mb-1 d-block">
                                            Status: <?= strtoupper($row['status']) ?>
                                        </span>
                                        <?php if(isset($row['status_pembayaran'])): ?>
                                        <span class="badge rounded-pill px-3 py-2 <?= $payment_status_class ?> d-block">
                                            Bayar: <?= strtoupper($row['status_pembayaran']) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <ul class="list-unstyled text-muted">
                                            <li class="mb-2"><i class="fas fa-calendar-alt text-gold me-2 w-20px"></i> <?= $tgl_tampil ?> | Jam <?= $jam_tampil ?> WIB</li>
                                            <li class="mb-2"><i class="fas fa-cut text-gold me-2 w-20px"></i> <?= $row['nama_layanan'] ?></li>
                                            <li class="mb-2"><i class="fas fa-magic text-gold me-2 w-20px"></i> <?= $row['nama_gaya'] ?? 'Tidak memilih gaya khusus' ?></li>
                                            <li class="mb-2"><i class="fas fa-user-tie text-gold me-2 w-20px"></i> Barber: <?= $row['nama_barber'] ?></li>
                                            <li class="mb-2"><i class="fas fa-money-bill-wave text-gold me-2 w-20px"></i> Rp <?= number_format($row['total_harga'], 0, ',', '.') ?> (<?= $row['metode_pembayaran'] ?? '-' ?>)</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-4 text-center text-md-end">
                                        <div class="qr-box mb-2">
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?= $row['kode_booking'] ?>" alt="QR Code" class="img-fluid">
                                        </div>
                                        <p class="small text-muted mb-0">Scan saat tiba</p>
                                    </div>
                                </div>

                                <hr class="border-secondary my-3">
                                
                                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                                    <span class="small text-muted">Dipesan pada: <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></span>
                                    <div class="d-flex gap-2">
                                        <?php if(isset($row['status_pembayaran']) && $row['status_pembayaran'] === 'Menunggu Pembayaran' && in_array($row['metode_pembayaran'], ['Transfer', 'QRIS'])): ?>
                                            <a href="upload_bukti.php?id=<?= $row['id_booking'] ?>" class="btn btn-gold btn-sm rounded-pill px-3">
                                                <i class="fas fa-upload me-1"></i> Upload Bukti
                                            </a>
                                        <?php endif; ?>
                                        <a href="https://wa.me/<?= $no_wa_admin ?>?text=<?= $pesan_wa ?>" target="_blank" class="btn btn-wa btn-sm rounded-pill px-3">
                                            <i class="fab fa-whatsapp me-1"></i> Hubungi Admin
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab Riwayat / Selesai -->
            <div class="tab-pane fade" id="history-bookings" role="tabpanel" aria-labelledby="history-tab">
                <?php if(empty($history_bookings)): ?>
                    <div class="text-center py-5">
                        <i class="far fa-folder-open fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Belum ada riwayat booking</h4>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach($history_bookings as $row): 
                            $status_class = 'badge-' . str_replace(' ', '_', strtolower($row['status']));
                            $jam_tampil = substr($row['jam_booking'], 0, 5);
                            $tgl_tampil = date('d M Y', strtotime($row['tanggal_booking']));
                        ?>
                        <div class="col-lg-6">
                            <div class="history-card p-4 opacity-75">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <span class="text-muted small">Kode Booking</span>
                                        <h4 class="text-gold mb-0 fw-bold"><?= $row['kode_booking'] ?></h4>
                                    </div>
                                    <span class="badge rounded-pill px-3 py-2 <?= $status_class ?>">
                                        <?= strtoupper($row['status']) ?>
                                    </span>
                                </div>
                                
                                <div class="row align-items-center">
                                    <div class="col-md-12">
                                        <ul class="list-unstyled text-muted mb-0">
                                            <li class="mb-2"><i class="fas fa-calendar-alt text-gold me-2 w-20px"></i> <?= $tgl_tampil ?> | Jam <?= $jam_tampil ?> WIB</li>
                                            <li class="mb-2"><i class="fas fa-cut text-gold me-2 w-20px"></i> <?= $row['nama_layanan'] ?></li>
                                            <li class="mb-2"><i class="fas fa-user-tie text-gold me-2 w-20px"></i> Barber: <?= $row['nama_barber'] ?></li>
                                            <li class="mb-2"><i class="fas fa-money-bill-wave text-gold me-2 w-20px"></i> Rp <?= number_format($row['total_harga'], 0, ',', '.') ?></li>
                                        </ul>
                                    </div>
                                </div>
                                <hr class="border-secondary my-3">
                                <div class="text-end">
                                    <span class="small text-muted">Diselesaikan pada: <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>.w-20px { width: 20px; text-align: center; }</style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggle = document.getElementById('themeToggle');
            const icon = themeToggle.querySelector('i');
            const htmlElement = document.documentElement;
            const savedTheme = localStorage.getItem('theme') || 'dark';
            setTheme(savedTheme);
            themeToggle.addEventListener('click', () => {
                const currentTheme = htmlElement.getAttribute('data-theme');
                setTheme(currentTheme === 'dark' ? 'light' : 'dark');
            });
            function setTheme(theme) {
                htmlElement.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
                icon.className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
            }
        });
    </script>
</body>
</html>
