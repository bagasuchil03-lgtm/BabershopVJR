<?php
// Sembunyikan warning/error dari browser, catat di log
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'koneksi.php';
require_once 'settings.php';

$shop_open = get_setting('shop_open') ?? '1';

// Ambil parameter dari GET jika ada (dari halaman katalog)
$id_layanan_selected = $_GET['layanan'] ?? '';
$id_gaya_selected = $_GET['gaya'] ?? '';
// Hapus pengecekan login, sistem sekarang menggunakan guest checkout

// Fetch Data untuk dropdown
$layanan_query = $conn->query("SELECT * FROM layanan WHERE status = 'aktif' ORDER BY id_layanan ASC");
if (!$layanan_query) $layanan_query = $conn->query("SELECT id_layanan, nama_layanan, harga, durasi_menit FROM layanan LIMIT 0");

// Query gaya_rambut dengan pengecekan tabel terlebih dahulu
$gaya_query = false;
$gaya_table_check = $conn->query("SHOW TABLES LIKE 'gaya_rambut'");
if ($gaya_table_check && $gaya_table_check->num_rows > 0) {
    $gaya_query = $conn->query("SELECT * FROM gaya_rambut ORDER BY id_gaya ASC");
}

$barber_query = $conn->query("SELECT * FROM barber WHERE status = 'aktif'");

// Gambar fallback elegan untuk gaya rambut
$gaya_images_fallback = [
    'https://images.unsplash.com/photo-1582021544136-6c8ab8b6f96b?auto=format&fit=crop&w=600&q=80', // two block haircut
    'https://images.unsplash.com/photo-1590282216944-5766496e673e?auto=format&fit=crop&w=600&q=80', // french crop
    'https://images.unsplash.com/photo-1515387361122-c8fc0d9610a1?auto=format&fit=crop&w=600&q=80', // classic undercut
    'https://images.unsplash.com/photo-1596473925995-59afcf6c4730?auto=format&fit=crop&w=600&q=80' // modern pompadour
];

// Inisialisasi index manual untuk fallback
$gaya_index = 0;

// Cek notifikasi jika user login
$notif_count = 0;
$notifikasi_list = [];
if (isset($_SESSION['id_user']) && $_SESSION['role'] !== 'admin') {
    $id_user = $_SESSION['id_user'];
    $user_stmt = $conn->prepare("SELECT no_hp FROM users WHERE id_user = ?");
    $user_stmt->bind_param("i", $id_user);
    $user_stmt->execute();
    $user_data = $user_stmt->get_result()->fetch_assoc();
    $no_hp_user = $user_data['no_hp'] ?? '';

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
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Jadwal - Vijer Barbershop</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-color: #111111;
            --text-main: #f8f9fa;
            --text-muted: #adb5bd;
            --gold-primary: #D4AF37;
            --gold-hover: #FFD700;
            --card-bg: #1a1a1a;
            --border-color: rgba(212, 175, 55, 0.3);
            --nav-bg: rgba(17, 17, 17, 0.95);
            --input-bg: #222;
            --input-border: #333;
            --card-radio-bg: #222;
            --card-radio-border: #333;
        }
        [data-theme="light"] {
            --bg-color: #f0f2f5;
            --text-main: #212529;
            --text-muted: #6c757d;
            --gold-primary: #b5952f;
            --gold-hover: #D4AF37;
            --card-bg: #ffffff;
            --border-color: rgba(0, 0, 0, 0.15);
            --nav-bg: rgba(255, 255, 255, 0.95);
            --input-bg: #f8f9fa;
            --input-border: #dee2e6;
            --card-radio-bg: #f8f9fa;
            --card-radio-border: #dee2e6;
        }
        body { background-color: var(--bg-color); color: var(--text-main); font-family: 'Montserrat', sans-serif; padding-top: 80px; transition: background-color 0.3s, color 0.3s; }
        h1, h2, .brand-text { font-family: 'Playfair Display', serif; color: var(--text-main); font-weight: 700; }
        p, label, .form-label { color: var(--text-main); }
        .text-muted { color: var(--text-muted) !important; }
        
        .navbar { background-color: var(--nav-bg); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(212, 175, 55, 0.2); }
        .navbar-brand { color: var(--gold-primary) !important; font-weight: 800; letter-spacing: 1px; }
        .nav-link { color: var(--text-main) !important; transition: color 0.3s ease; }
        .nav-link:hover, .nav-link.active { color: var(--gold-primary) !important; }
        
        .booking-card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 15px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); transition: background-color 0.3s; }
        .form-control, .form-select { background-color: var(--input-bg); border: 1px solid var(--input-border); color: var(--text-main); }
        .form-control:focus, .form-select:focus { background-color: var(--input-bg); border-color: var(--gold-primary); color: var(--text-main); box-shadow: none; }
        .form-control::placeholder { color: var(--text-muted); }
        .form-select option { background-color: var(--card-bg); color: var(--text-main); }
        
        .btn-gold { background-color: var(--gold-primary); color: #000; font-weight: 600; border: none; transition: 0.3s; }
        .btn-gold:hover { background-color: var(--gold-hover); transform: translateY(-2px); }
        .btn-outline-gold { background-color: transparent; color: var(--gold-primary); border: 2px solid var(--gold-primary); font-weight: 600; transition: all 0.3s ease; }
        .btn-outline-gold:hover { background-color: var(--gold-primary); color: #000; }
        
        .text-gold { color: var(--gold-primary) !important; }
        
        /* Jam Selection Buttons */
        .jam-btn { display: inline-block; margin: 5px; cursor: pointer; }
        .jam-btn input[type="radio"] { display: none; }
        .jam-btn label { padding: 10px 20px; border: 2px solid var(--input-border); border-radius: 8px; color: var(--text-muted); background-color: var(--input-bg); transition: 0.3s; cursor: pointer; }
        .jam-btn input[type="radio"]:checked + label { border-color: var(--gold-primary); background-color: var(--gold-primary); color: #000; font-weight: bold; }
        .jam-btn input[type="radio"]:disabled + label { border-color: var(--input-border); color: var(--text-muted); cursor: not-allowed; opacity: 0.5; text-decoration: line-through; }
        
        /* CSS for Selectable Cards */
        .card-radio input[type="radio"] { display: none; }
        .card-radio label { cursor: pointer; border: 2px solid var(--card-radio-border); border-radius: 10px; padding: 15px; width: 100%; transition: 0.3s; background: var(--card-radio-bg); color: var(--text-main); height: 100%; }
        .card-radio input[type="radio"]:checked + label { border-color: var(--gold-primary); background-color: rgba(212, 175, 55, 0.1); }
        .style-img-sm { width: 100%; height: 140px; object-fit: cover; border-radius: 8px; margin-bottom: 10px; border: 1px solid var(--card-radio-border); }
        
        .theme-toggle { cursor: pointer; font-size: 1.2rem; color: var(--text-main); background: none; border: none; padding: 5px 10px; transition: color 0.3s; }
        .theme-toggle:hover { color: var(--gold-primary); }

        /* Notification Dropdown */
        .notif-dropdown { width: 320px; max-height: 400px; overflow-y: auto; background-color: var(--card-bg); border: 1px solid var(--border-color); }
        .notif-item { border-bottom: 1px solid var(--border-color); padding: 10px 15px; transition: background 0.2s; color: var(--text-main); text-decoration: none; display: block; }
        .notif-item:hover { background-color: rgba(212,175,55,0.05); color: var(--gold-primary); }
        .notif-unread { background-color: rgba(212,175,55,0.1); font-weight: 600; }
        .notif-time { font-size: 0.75rem; color: var(--text-muted); }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand fs-3" href="index.php">
                <span style="font-family:'Montserrat', sans-serif; font-weight:900; letter-spacing:3px;">VIJER</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-toggle="target" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon" style="filter: invert(1) grayscale(100%) brightness(200%);"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link" href="katalog.php">Layanan & Gaya</a></li>
                    
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-gold px-4 rounded-pill" href="login.php" style="font-size: 0.85rem;"><i class="fas fa-lock"></i> Admin</a>
                    </li>
                    
                    <?php if(isset($_SESSION['id_user']) && $_SESSION['role'] !== 'admin'): ?>
                    <li class="nav-item dropdown ms-3">
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 8px 10px !important;">
                            <i class="fas fa-bell fs-5"></i>
                            <?php if($notif_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.55rem;">
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
                                        <a href="riwayat.php?mark_read=1" class="dropdown-item notif-item <?= $n['status_baca'] == 0 ? 'notif-unread' : '' ?>">
                                            <div class="text-wrap" style="font-size: 0.85rem;"><?= htmlspecialchars($n['pesan']) ?></div>
                                            <div class="notif-time mt-1"><?= date('d/m/Y H:i', strtotime($n['tanggal_dibuat'])) ?></div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

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
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-4">
                    <h2 class="fw-bold">Buat <span class="text-gold">Reservasi</span></h2>
                    <p class="text-muted">Pilih layanan, barber favoritmu, dan jadwal yang tersedia (Buka 24 Jam).</p>
                </div>

                <?php if(isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger p-3" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
                    </div>
                <?php endif; ?>

                <div class="booking-card">
                    <?php if ($shop_open === '0'): ?>
                        <div class="alert alert-danger p-5 text-center rounded-4 mb-0" style="background-color: rgba(220, 53, 69, 0.1); border: 1px solid rgba(220, 53, 69, 0.3);">
                            <i class="fas fa-store-alt-slash mb-3" style="font-size: 3rem; color: #ff4d6d;"></i>
                            <h3 class="fw-bold mb-3" style="font-family: 'Playfair Display', serif; color: #ff4d6d;">Barbershop Sedang Tutup</h3>
                            <p class="mb-0" style="font-family: 'Montserrat', sans-serif; color: var(--text-main);">Mohon maaf, layanan reservasi saat ini dinonaktifkan karena Vijer Barbershop sedang tutup. Silakan periksa kembali nanti untuk jadwal terbaru kami.</p>
                        </div>
                    <?php else: ?>
                    <form action="proses-booking.php" method="POST" id="bookingForm">
                        
                        <h5 class="text-gold mb-3 border-bottom border-secondary pb-2">1. Data Diri <span class="text-danger">*</span></h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Nama Lengkap</label>
                                <input type="text" name="nama_pelanggan" class="form-control" placeholder="Masukkan nama Anda" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nomor WhatsApp</label>
                                <input type="tel" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx" required>
                            </div>
                        </div>

                        <h5 class="text-gold mb-3 border-bottom border-secondary pb-2">2. Pilih Layanan <span class="text-danger">*</span></h5>
                        <div class="row g-3 mb-4">
                            <?php while($lay = $layanan_query->fetch_assoc()): ?>
                            <div class="col-md-6">
                                <div class="card-radio h-100">
                                    <input type="radio" name="id_layanan" id="layanan_<?= $lay['id_layanan'] ?>" value="<?= $lay['id_layanan'] ?>" required <?= ($id_layanan_selected == $lay['id_layanan']) ? 'checked' : '' ?>>
                                    <label for="layanan_<?= $lay['id_layanan'] ?>" class="d-flex flex-column h-100">
                                        <div class="mb-2">
                                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars($lay['nama_layanan']) ?></h6>
                                        </div>
                                        <div class="text-muted small mb-2"><i class="far fa-clock text-gold"></i> <?= $lay['durasi_menit'] ?> Menit Estimasi</div>
                                        <div class="text-gold fw-bold fs-5 mt-auto">Rp <?= number_format($lay['harga'], 0, ',', '.') ?></div>
                                    </label>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>

                        <h5 class="text-gold mb-3 border-bottom border-secondary pb-2 mt-4">3. Referensi Gaya Rambut <span class="text-muted fs-6 fw-normal">(Opsional)</span></h5>
                        <div class="row g-3 mb-4">
                            <div class="col-6 col-md-4">
                                <div class="card-radio h-100">
                                    <input type="radio" name="id_gaya" id="gaya_none" value="" <?= ($id_gaya_selected == '') ? 'checked' : '' ?>>
                                    <label for="gaya_none" class="text-center d-flex flex-column justify-content-center align-items-center h-100 py-4">
                                        <i class="fas fa-comment-dots fs-1 text-muted mb-3"></i>
                                        <h6 class="mb-0">Konsultasi Langsung / Bebas</h6>
                                    </label>
                                </div>
                            </div>
                            <?php if ($gaya_query && $gaya_query->num_rows > 0): ?>
                            <?php while($gaya = $gaya_query->fetch_assoc()): 
                                $foto_tampil = (!empty($gaya['foto_gaya']) && $gaya['foto_gaya'] !== 'default_gaya.png') 
                                    ? htmlspecialchars($gaya['foto_gaya']) 
                                    : $gaya_images_fallback[$gaya_index % count($gaya_images_fallback)];
                                $gaya_index++;
                            ?>
                            <div class="col-6 col-md-4">
                                <div class="card-radio h-100">
                                    <input type="radio" name="id_gaya" id="gaya_<?= $gaya['id_gaya'] ?>" value="<?= $gaya['id_gaya'] ?>" <?= ($id_gaya_selected == $gaya['id_gaya']) ? 'checked' : '' ?>>
                                    <label for="gaya_<?= $gaya['id_gaya'] ?>" class="text-center h-100 p-2">
                                        <img src="<?= $foto_tampil ?>" alt="<?= htmlspecialchars($gaya['nama_gaya']) ?>" class="style-img-sm">
                                        <h6 class="mb-1 fw-bold mt-2"><?= htmlspecialchars($gaya['nama_gaya']) ?></h6>
                                    </label>
                                </div>
                            </div>
                            <?php endwhile; ?>
                            <?php endif; ?>
                        </div>

                        <h5 class="text-gold mb-3 border-bottom border-secondary pb-2 mt-4">4. Pilih Barber</h5>
                        <div class="mb-4">
                            <label class="form-label">Barber yang Tersedia <span class="text-danger">*</span></label>
                            <select name="id_barber" id="id_barber" class="form-select" required>
                                <option value="">-- Pilih Barber --</option>
                                <?php while($barber = $barber_query->fetch_assoc()): ?>
                                    <option value="<?= $barber['id_barber'] ?? $barber['id'] ?? 0 ?>">
                                        <?= htmlspecialchars($barber['nama_barber'] ?? '') ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <h5 class="text-gold mb-3 border-bottom border-secondary pb-2 mt-4">5. Pilih Waktu</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_booking" id="tanggal_booking" class="form-control" required min="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Jam Tersedia <span class="text-danger">*</span></label>
                            <div id="jamContainer" class="d-flex flex-wrap gap-2 mt-2">
                                <p class="text-muted small w-100"><i class="fas fa-info-circle me-1"></i>Pilih Barber dan Tanggal terlebih dahulu untuk melihat jadwal.</p>
                            </div>
                            <input type="hidden" name="jam_booking" id="jam_booking_hidden" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Catatan Tambahan (Opsional)</label>
                            <textarea name="catatan" class="form-control" rows="2" placeholder="Pesan khusus untuk barber..."></textarea>
                        </div>

                        <h5 class="text-gold mb-3 border-bottom border-secondary pb-2 mt-4">6. Metode Pembayaran <span class="text-danger">*</span></h5>
                        <div class="row g-3 mb-4">
                            <!-- QRIS -->
                            <div class="col-6 col-md-3">
                                <div class="card-radio h-100">
                                    <input type="radio" name="metode_pembayaran" id="pay_qris" value="QRIS" required>
                                    <label for="pay_qris" class="text-center d-flex flex-column justify-content-center align-items-center h-100 py-3">
                                        <i class="fas fa-qrcode fs-3 mb-2 text-gold"></i>
                                        <h6 class="mb-0 fw-bold">QRIS</h6>
                                        <small class="text-muted" style="font-size:0.7rem;">Scan QR Code</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-5">
                            <button type="submit" class="btn btn-gold btn-lg px-5 rounded-pill">
                                Konfirmasi Booking <i class="fas fa-check-circle ms-2"></i>
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Theme Toggle
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
    <script>
        $(document).ready(function() {
            function fetchJamTersedia() {
                var barberId = $('#id_barber').val();
                var tanggal = $('#tanggal_booking').val();
                var container = $('#jamContainer');
                
                // Reset hidden input
                $('#jam_booking_hidden').val('');

                if (barberId && tanggal) {
                    container.html('<div class="spinner-border text-warning spinner-border-sm" role="status"></div><span class="ms-2 text-muted">Mengecek jadwal...</span>');
                    
                    $.ajax({
                        url: 'get-jam-tersedia.php',
                        type: 'GET',
                        data: { id_barber: barberId, tanggal: tanggal },
                        dataType: 'json',
                        success: function(response) {
                            container.empty();
                            if(response.status == 'success') {
                                if(response.data.length > 0) {
                                    // Bikin HTML untuk radio buttons jam
                                    $.each(response.data, function(index, jam) {
                                        var html = '<div class="jam-btn">' +
                                                   '<input type="radio" name="jam_radio" id="jam_'+jam+'" value="'+jam+'">' +
                                                   '<label for="jam_'+jam+'">'+jam+'</label>' +
                                                   '</div>';
                                        container.append(html);
                                    });
                                    
                                    // Tampilkan juga jam yang sudah terpakai sebagai disabled info
                                    $.each(response.terpakai, function(index, jam) {
                                        var html = '<div class="jam-btn">' +
                                                   '<input type="radio" name="jam_radio_disabled" id="jam_d_'+jam+'" value="'+jam+'" disabled>' +
                                                   '<label for="jam_d_'+jam+'" title="Sudah Dibooking">'+jam+'</label>' +
                                                   '</div>';
                                        container.append(html);
                                    });

                                    // Event handler untuk jam yang dipilih
                                    $('input[name="jam_radio"]').change(function() {
                                        $('#jam_booking_hidden').val($(this).val());
                                    });
                                } else {
                                    container.html('<p class="text-danger small w-100"><i class="fas fa-times-circle me-1"></i>Semua jadwal pada hari ini sudah penuh atau telah berlalu.</p>');
                                }
                            } else {
                                container.html('<p class="text-danger">Gagal mengambil data jadwal.</p>');
                            }
                        },
                        error: function() {
                            container.html('<p class="text-danger">Terjadi kesalahan pada server.</p>');
                        }
                    });
                } else {
                    container.html('<p class="text-muted small w-100"><i class="fas fa-info-circle me-1"></i>Pilih Barber dan Tanggal terlebih dahulu untuk melihat jadwal.</p>');
                }
            }

            $('#id_barber, #tanggal_booking').change(function() {
                fetchJamTersedia();
            });
            
            // Validasi sebelum submit
            $('#bookingForm').submit(function(e) {
                if($('#jam_booking_hidden').val() == '') {
                    e.preventDefault();
                    alert("Silakan pilih jam booking terlebih dahulu!");
                }
            });
        });
    </script>
</body>
</html>
