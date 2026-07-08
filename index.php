<?php
// index.php - Halaman Landing Page Utama Vijer Barbershop
// Sembunyikan warning di browser, tetap catat di log server
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'koneksi.php';
require_once 'settings.php';

// Fetch layanan aktif dari database
$layanan_query = $conn->query("SELECT * FROM layanan WHERE status = 'aktif' ORDER BY id_layanan ASC");
$layanan_data = [];
if ($layanan_query) {
    while ($row = $layanan_query->fetch_assoc()) {
        $layanan_data[] = $row;
    }
}

// Fetch barber aktif
$barber_query = $conn->query("SELECT * FROM barber WHERE status = 'aktif'");
$barber_data = [];
while ($row = $barber_query->fetch_assoc()) {
    $barber_data[] = $row;
}

// Gambar dummy elegan untuk layanan (karena di DB tidak ada URL gambar layanan)
$service_images = [
    'assets/img/haircut_detail.png',
    'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?auto=format&fit=crop&w=800&q=80',
    'https://images.unsplash.com/photo-1590407180294-1d3c65810e30?auto=format&fit=crop&w=800&q=80',
    'https://images.unsplash.com/photo-1522335789204-8e1c4e986853?auto=format&fit=crop&w=800&q=80',
    'assets/img/shaving_trim.png'
];

// Fungsi untuk mengecek gambar lokal dengan berbagai ekstensi
function get_local_image($name, $fallback) {
    $exts = ['jpg', 'jpeg', 'png', 'webp'];
    foreach($exts as $ext) {
        if(file_exists($name . '.' . $ext)) {
            return $name . '.' . $ext;
        }
    }
    return $fallback;
}

// Gambar fallback elegan untuk barber (akan menggunakan lokal jika ada, jika tidak pakai placeholder)
$barber_images = [
    get_local_image('barber1', 'https://placehold.co/600x800/1a1a1a/c5a059?text=Foto+Barber+1'),
    get_local_image('barber2', 'https://placehold.co/600x800/1a1a1a/c5a059?text=Foto+Barber+2'),
    get_local_image('barber3', 'https://placehold.co/600x800/1a1a1a/c5a059?text=Foto+Barber+3')
];

// Ambil gambar beranda dari settings (admin upload)
$heroImageSetting = get_setting('homepage_hero_image');
$aboutMainSetting = get_setting('homepage_about_main');
$aboutSubSetting  = get_setting('homepage_about_sub');

$heroBackgroundUrl = '';
if ($heroImageSetting === 'empty') {
    $heroBackgroundUrl = '';
} elseif ($heroImageSetting && file_exists($heroImageSetting)) {
    $heroBackgroundUrl = $heroImageSetting . '?t=' . filemtime($heroImageSetting);
} else {
    $heroBackgroundUrl = 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=1920&q=80';
}

$aboutMainUrl = '';
if ($aboutMainSetting === 'empty') {
    $aboutMainUrl = '';
} elseif ($aboutMainSetting && file_exists($aboutMainSetting)) {
    $aboutMainUrl = $aboutMainSetting . '?t=' . filemtime($aboutMainSetting);
} else {
    $aboutMainUrl = 'assets/img/barbershop_interior.png';
}

$aboutSubUrl = '';
if ($aboutSubSetting === 'empty') {
    $aboutSubUrl = '';
} elseif ($aboutSubSetting && file_exists($aboutSubSetting)) {
    $aboutSubUrl = $aboutSubSetting . '?t=' . filemtime($aboutSubSetting);
} else {
    $aboutSubUrl = 'assets/img/haircut_detail.png';
}
// Fetch about slider photos
$aboutSliderPhotos = [];
$slider_check = $conn->query("SHOW TABLES LIKE 'about_slider_photos'");
if ($slider_check && $slider_check->num_rows > 0) {
    $slider_res = $conn->query("SELECT file_path FROM about_slider_photos ORDER BY created_at ASC");
    if ($slider_res) {
        $fallback_slider = [
            'assets/img/barbershop_interior.png',
            'assets/img/shaving_trim.png'
        ];
        $fb_idx = 0;
        while ($r = $slider_res->fetch_assoc()) {
            $path = $r['file_path'];
            if (filter_var($path, FILTER_VALIDATE_URL) || file_exists($path)) {
                $aboutSliderPhotos[] = $path;
            } else {
                $aboutSliderPhotos[] = $fallback_slider[$fb_idx % count($fallback_slider)];
                $fb_idx++;
            }
        }
    }
}

// Fetch active homepage gallery
$gallery_data = [];
$gallery_check = $conn->query("SHOW TABLES LIKE 'homepage_photos'");
if ($gallery_check && $gallery_check->num_rows > 0) {
    $gal_res = $conn->query("SELECT file_path FROM homepage_photos ORDER BY created_at DESC");
    if ($gal_res) {
        $fallback_gallery = [
            'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?auto=format&fit=crop&w=800&q=80'
        ];
        $fb_idx = 0;
        while ($r = $gal_res->fetch_assoc()) {
            $path = $r['file_path'];
            if (filter_var($path, FILTER_VALIDATE_URL) || file_exists($path)) {
                $gallery_data[] = $path;
            } else {
                $gallery_data[] = $fallback_gallery[$fb_idx % count($fallback_gallery)];
                $fb_idx++;
            }
        }
    }
}

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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vijer Barbershop Jepara - The Gentlemen's Choice</title>
    <meta name="description" content="Vijer Barbershop adalah barbershop modern premium di Jepara. Reservasi online 24 jam, layanan berkualitas, dan pengalaman grooming terbaik.">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Montserrat (Modern) & Playfair Display (Klasik/Premium) -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Animate on Scroll -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Swiper JS CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    
    <style>
        /* ==========================================
           PREMIUM BARBERSHOP DESIGN SYSTEM
           ========================================== */
        :root {
            --color-dark: #0a0a0a;
            --color-darker: #050505;
            --color-card: #141414;
            --color-gold: #c5a059;
            --color-gold-light: #e5c483;
            --color-text-main: #f0f0f0;
            --color-text-muted: #999999;
            --color-border: rgba(197, 160, 89, 0.15);
            --nav-bg: rgba(5, 5, 5, 0.85);
            --nav-bg-scrolled: rgba(5, 5, 5, 0.98);
            
            --font-sans: 'Montserrat', sans-serif;
            --font-serif: 'Playfair Display', serif;
            
            --transition-smooth: all 0.5s cubic-bezier(0.25, 1, 0.5, 1);
        }

        [data-theme="light"] {
            --color-dark: #ffffff; /* background */
            --color-darker: #e8f5e9; /* light green accent */
            --color-card: #ffffff;
            --color-gold: #2ecc71; /* green accent */
            --color-text-main: #000000; /* black text */
            --color-text-muted: #555555;
            --color-border: rgba(0, 0, 0, 0.1);
            --nav-bg: rgba(255, 255, 255, 0.9);
            --nav-bg-scrolled: rgba(255, 255, 255, 0.98);
        }

        body {
            background-color: var(--color-darker);
            color: var(--color-text-main);
            font-family: var(--font-sans);
            font-weight: 600; /* bolder fonts */
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--font-serif);
            font-weight: 700;
            color: var(--color-text-main);
            line-height: 1.2;
        }

        .font-sans-title { font-family: var(--font-sans); font-weight: 800; text-transform: uppercase; letter-spacing: 2px; }
        .text-gold { color: var(--color-gold) !important; }

        /* ==========================================
           NAVBAR (GLASSMORPHISM & SLEEK)
           ========================================== */
        .navbar-premium {
            background: var(--nav-bg);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-bottom: 1px solid var(--color-border);
            padding: 15px 0;
            transition: var(--transition-smooth);
        }

        .navbar-premium.scrolled {
            padding: 10px 0;
            background: var(--nav-bg-scrolled);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .brand-logo {
            font-family: var(--font-sans);
            font-weight: 900;
            font-size: 1.5rem;
            color: var(--color-text-main) !important;
            letter-spacing: 4px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .brand-logo span {
            color: var(--color-gold);
        }

        .nav-link-premium {
            font-family: var(--font-sans);
            color: var(--color-text-main) !important;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 8px 20px !important;
            position: relative;
            transition: color 0.3s ease;
        }

        .nav-link-premium::before {
            content: '';
            position: absolute;
            bottom: 0; left: 20px; right: 20px;
            height: 1px;
            background-color: var(--color-gold);
            transform: scaleX(0);
            transition: transform 0.4s ease;
            transform-origin: right;
        }

        .nav-link-premium:hover::before,
        .nav-link-premium.active::before {
            transform: scaleX(1);
            transform-origin: left;
        }
        
        .nav-link-premium:hover,
        .nav-link-premium.active {
            color: var(--color-gold) !important;
        }

        .btn-booking-nav {
            font-family: var(--font-sans);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--color-darker);
            background: var(--color-gold);
            padding: 10px 25px;
            border-radius: 0;
            border: 1px solid var(--color-gold);
            transition: var(--transition-smooth);
            text-decoration: none;
            display: inline-block;
        }

        .btn-booking-nav:hover {
            background: transparent;
            color: var(--color-gold);
            box-shadow: inset 0 0 0 1px var(--color-gold);
        }

        /* ==========================================
           HERO SECTION (CINEMATIC)
           ========================================== */
        .hero {
            height: 100vh;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .hero-bg {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            <?php if ($heroBackgroundUrl): ?>
            background: url('<?= htmlspecialchars($heroBackgroundUrl) ?>') center/cover no-repeat;
            <?php else: ?>
            background: var(--bg-base);
            <?php endif; ?>
            z-index: 1;
        }

        .hero-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to bottom, rgba(5,5,5,0.7) 0%, rgba(5,5,5,0.4) 50%, rgba(5,5,5,1) 100%);
            z-index: 2;
        }

        .hero-content {
            position: relative;
            z-index: 3;
            max-width: 900px;
            padding: 0 20px;
        }

        .hero-subtitle {
            font-family: var(--font-sans);
            color: var(--color-gold);
            font-weight: 600;
            letter-spacing: 4px;
            text-transform: uppercase;
            font-size: 0.9rem;
            margin-bottom: 20px;
            display: block;
        }

        .hero-title {
            font-size: clamp(3rem, 8vw, 5.5rem);
            margin-bottom: 30px;
            text-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .hero-title i {
            font-style: italic;
            color: var(--color-gold);
            font-weight: 400;
        }

        .btn-premium {
            font-family: var(--font-sans);
            display: inline-block;
            background: transparent;
            color: var(--color-gold);
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 16px 40px;
            border: 1px solid var(--color-gold);
            text-decoration: none;
            transition: var(--transition-smooth);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn-premium::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: var(--color-gold);
            z-index: -1;
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.5s cubic-bezier(0.19, 1, 0.22, 1);
        }

        .btn-premium:hover {
            color: var(--color-darker);
        }

        .btn-premium:hover::before {
            transform: scaleX(1);
            transform-origin: left;
        }

        /* ==========================================
           SECTION HEADERS
           ========================================== */
        .section-padding { padding: 120px 0; }
        
        .section-header {
            text-align: center;
            margin-bottom: 80px;
        }

        .section-subtitle {
            font-family: var(--font-sans);
            color: var(--color-gold);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 15px;
            display: block;
        }

        .section-title {
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            margin-bottom: 20px;
        }

        /* ==========================================
           TENTANG KAMI (EDITORIAL LAYOUT)
           ========================================== */
        .about-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .about-swiper {
            width: 100%;
            height: 600px;
            border-radius: 4px;
            overflow: hidden;
        }

        .about-swiper .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: grayscale(10%);
        }

        .about-swiper .swiper-pagination-bullet {
            background: var(--color-text-main);
        }
        .about-swiper .swiper-pagination-bullet-active {
            background: var(--color-gold);
        }

        .about-text p {
            color: var(--color-text-main);
            font-size: 1.1rem;
            line-height: 1.8;
            margin-bottom: 25px;
            font-family: var(--font-sans);
            font-weight: 600;
        }

        .about-features {
            margin-top: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .feature-icon {
            color: var(--color-gold);
            font-size: 1.5rem;
        }

        .feature-item h6 {
            font-family: var(--font-sans);
            font-size: 1rem;
            margin: 0 0 5px 0;
            font-weight: 600;
            letter-spacing: 1px;
        }

        /* ==========================================
           LAYANAN (IMAGE CARDS)
           ========================================== */
        .bg-dark-section { background-color: var(--color-dark); }

        .service-card {
            position: relative;
            height: 450px;
            overflow: hidden;
            display: block;
            text-decoration: none;
            group: hover;
        }

        .service-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 1s cubic-bezier(0.25, 1, 0.5, 1);
            filter: grayscale(50%) brightness(0.6);
        }

        .service-card:hover img {
            transform: scale(1.05);
            filter: grayscale(0%) brightness(0.4);
        }

        .service-content {
            position: absolute;
            bottom: 0; left: 0; width: 100%;
            padding: 40px 30px;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            transition: var(--transition-smooth);
        }

        .service-card:hover .service-content {
            padding-bottom: 50px;
        }

        .service-price {
            font-family: var(--font-sans);
            color: var(--color-gold);
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 10px;
            display: block;
        }

        .service-title {
            font-family: var(--font-serif);
            font-size: 1.8rem;
            color: #fff;
            margin-bottom: 10px;
        }

        .service-meta {
            font-family: var(--font-sans);
            color: #ccc;
            font-size: 0.85rem;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0;
            transform: translateY(20px);
            transition: var(--transition-smooth);
        }

        .service-card:hover .service-meta {
            opacity: 1;
            transform: translateY(0);
        }

        /* ==========================================
           BARBER KAMI
           ========================================== */
        .barber-card {
            text-align: center;
            margin-bottom: 30px;
        }

        .barber-img-wrapper {
            position: relative;
            overflow: hidden;
            margin-bottom: 25px;
            aspect-ratio: 3/4;
        }

        .barber-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: grayscale(100%);
            transition: var(--transition-smooth);
        }

        .barber-card:hover .barber-img-wrapper img {
            filter: grayscale(0%);
            transform: scale(1.03);
        }

        .barber-name {
            font-family: var(--font-serif);
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .barber-title {
            font-family: var(--font-sans);
            color: var(--color-gold);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }

        /* ==========================================
           CARA KERJA (MINIMALIST)
           ========================================== */
        .step-item {
            text-align: center;
            padding: 0 20px;
        }

        .step-number {
            font-family: var(--font-serif);
            font-size: 4rem;
            color: var(--color-gold);
            line-height: 1;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
            font-style: italic;
        }

        .step-title {
            font-family: var(--font-sans);
            font-size: 1.25rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
        }

        .step-desc {
            color: var(--color-text-main);
            font-size: 1.05rem;
            font-weight: 500;
            line-height: 1.6;
        }

        /* ==========================================
           TESTIMONI
           ========================================== */
        .testimonial-card {
            background: var(--color-card);
            padding: 40px;
            border: 1px solid var(--color-border);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .quote-icon {
            color: var(--color-gold);
            font-size: 2rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .testi-text {
            font-family: var(--font-serif);
            font-size: 1.1rem;
            line-height: 1.8;
            font-style: italic;
            color: var(--color-text-main);
            margin-bottom: 30px;
            flex-grow: 1;
        }

        .testi-author {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .testi-avatar {
            width: 50px; height: 50px;
            background: var(--color-darker);
            border: 1px solid var(--color-gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-sans);
            font-weight: 700;
            color: var(--color-gold);
            object-fit: cover;
        }

        .testi-name {
            font-family: var(--font-sans);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
            margin: 0;
        }

        .testi-stars {
            color: #C5A059;
            font-size: 0.8rem;
        }

        /* ==========================================
           FOOTER
           ========================================== */
        .footer-premium {
            background: var(--color-dark);
            padding: 80px 0 30px;
            border-top: 1px solid var(--color-border);
        }

        .footer-brand {
            font-family: var(--font-sans);
            font-weight: 900;
            font-size: 1.5rem;
            letter-spacing: 4px;
            margin-bottom: 20px;
        }
        .footer-brand span { color: var(--color-gold); }

        .footer-title {
            font-family: var(--font-sans);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 0.9rem;
            color: var(--color-gold);
            margin-bottom: 25px;
        }

        .footer-link {
            display: block;
            color: var(--color-text-main);
            text-decoration: none;
            margin-bottom: 12px;
            font-size: 1rem;
            font-weight: 500;
            transition: color 0.3s;
        }

        .footer-link:hover { color: var(--color-gold); }

        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px; height: 40px;
            border: 1px solid var(--color-border);
            color: var(--color-text-main);
            margin-right: 10px;
            transition: var(--transition-smooth);
        }

        .social-icons a:hover {
            background: var(--color-gold);
            border-color: var(--color-gold);
            color: #000;
        }
        
        .theme-toggle-btn {
            background: transparent;
            border: none;
            color: var(--color-text-main);
            font-size: 1.2rem;
            cursor: pointer;
            transition: color 0.3s;
        }
        .theme-toggle-btn:hover { color: var(--color-gold); }

        /* ==========================================
           RESPONSIVE
           ========================================== */
        /* Notification Dropdown */
        .notif-dropdown { width: 320px; max-height: 400px; overflow-y: auto; background-color: var(--color-card); border: 1px solid var(--color-border); }
        .notif-item { border-bottom: 1px solid var(--color-border); padding: 10px 15px; transition: background 0.2s; color: var(--color-text-main); text-decoration: none; display: block; }
        .notif-item:hover { background-color: rgba(197,160,89,0.05); color: var(--color-gold); }
        .notif-unread { background-color: rgba(197,160,89,0.1); font-weight: 600; }
        .notif-time { font-size: 0.75rem; color: var(--color-text-muted); }

        @media (max-width: 991px) {
            .about-grid { grid-template-columns: 1fr; }
            .about-swiper { height: 400px; margin-bottom: 40px; }
            .about-img-sub { width: 60%; height: 250px; }
            .hero-title { font-size: 3rem; }
            .section-padding { padding: 80px 0; }
        }
    </style>
</head>
<body>

    <!-- ========================================
         NAVBAR
         ======================================== -->
    <nav class="navbar navbar-expand-lg fixed-top navbar-premium" id="mainNavbar">
        <div class="container">
            <a class="navbar-brand brand-logo" href="index.php">
                VIJER<span>.</span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fas fa-bars text-white fs-4"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto align-items-center">
                    <li class="nav-item"><a class="nav-link nav-link-premium active" href="#beranda">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-premium" href="#tentang">Tentang</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-premium" href="#layanan">Layanan</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-premium" href="#barber">Barber</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-premium" href="katalog.php">Katalog</a></li>
                </ul>
                <div class="d-flex align-items-center mt-3 mt-lg-0 gap-3">
                    <?php if(isset($_SESSION['id_user']) && $_SESSION['role'] !== 'admin'): ?>
                    <div class="dropdown">
                        <a class="nav-link-premium position-relative dropdown-toggle" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 8px 10px !important;">
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
                    </div>
                    <?php endif; ?>
                    <button class="theme-toggle-btn" id="themeToggle" title="Ganti Tema">
                        <i class="fas fa-moon"></i>
                    </button>
                    <a href="booking.php" class="btn-booking-nav">Reservasi</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ========================================
         HERO SECTION
         ======================================== -->
    <section class="hero" id="beranda">
        <div class="hero-bg"></div>
        <div class="hero-overlay"></div>
        <div class="container hero-content">
            <span class="hero-subtitle" data-aos="fade-down" data-aos-duration="1000">Jepara's Finest Barbershop</span>
            <h1 class="hero-title" data-aos="fade-up" data-aos-duration="1200" data-aos-delay="200">
                The Gentlemen's<br><i>Choice.</i>
            </h1>
            <div class="mt-5" data-aos="fade-up" data-aos-duration="1200" data-aos-delay="400">
                <a href="booking.php" class="btn-premium">Buat Reservasi Sekarang</a>
            </div>
        </div>
    </section>

    <!-- ========================================
         TENTANG KAMI
         ======================================== -->
    <section class="section-padding" id="tentang">
        <div class="container">
            <div class="about-grid">
                <div class="about-swiper swiper" data-aos="fade-right" data-aos-duration="1200">
                    <div class="swiper-wrapper">
                        <?php if (empty($aboutSliderPhotos)): ?>
                            <div class="swiper-slide"><img src="assets/img/barbershop_interior.png" alt="Interior"></div>
                            <div class="swiper-slide"><img src="assets/img/haircut_detail.png" alt="Haircut"></div>
                        <?php else: ?>
                            <?php foreach ($aboutSliderPhotos as $photoPath): ?>
                                <div class="swiper-slide"><img src="<?= htmlspecialchars($photoPath) ?>" alt="About Photo"></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <!-- Pagination -->
                    <div class="swiper-pagination"></div>
                </div>
                <div class="about-text" data-aos="fade-left" data-aos-duration="1200">
                    <span class="section-subtitle">Tentang Kami</span>
                    <h2 class="section-title">Elegansi Tradisional.<br>Eksekusi Modern.</h2>
                    <p>
                        Sejak 2010, Vijer Barbershop telah mendefinisikan ulang standar perawatan pria di Jepara. Kami tidak sekadar memotong rambut; kami memahat rasa percaya diri.
                    </p>
                    <p>
                        Dengan menggabungkan teknik klasik dan tren gaya modern, master barber kami memberikan pengalaman grooming yang dirancang khusus untuk memenuhi standar tertinggi seorang gentleman.
                    </p>
                    
                    <div class="about-features">
                        <div class="feature-item">
                            <i class="fas fa-award feature-icon"></i>
                            <div>
                                <h6>Master Barber</h6>
                                <p class="small text-muted mb-0">Berpengalaman >5 tahun</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-gem feature-icon"></i>
                            <div>
                                <h6>Alat Premium</h6>
                                <p class="small text-muted mb-0">Standar higienis tinggi</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================
         LAYANAN (IMAGE CARDS)
         ======================================== -->
    <section class="section-padding bg-dark-section" id="layanan">
        <div class="container-fluid px-lg-5">
            <div class="section-header" data-aos="fade-up">
                <span class="section-subtitle">Layanan Kami</span>
                <h2 class="section-title">Menu Perawatan</h2>
            </div>
            
            <div class="row g-4 justify-content-center">
<?php if (!empty($layanan_data)): ?>
                <?php foreach($layanan_data as $i => $layanan):
                    $nama    = htmlspecialchars($layanan['nama_layanan'] ?? 'Layanan');
                    $harga   = (float)($layanan['harga'] ?? 0);
                    $durasi  = (int)($layanan['durasi_menit'] ?? 0);
                    $id_lay  = (int)($layanan['id_layanan'] ?? 0);
                    $imgPath = $layanan['image_path'] ?? '';
                    if (!empty($imgPath) && (filter_var($imgPath, FILTER_VALIDATE_URL) || file_exists($imgPath))) {
                        $img = htmlspecialchars($imgPath);
                    } else {
                        $img = $service_images[$i % count($service_images)];
                    }
                ?>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
                    <a href="booking.php?layanan=<?= $id_lay ?>" class="service-card">
                        <img src="<?= $img ?>" alt="<?= $nama ?>">
                        <div class="service-content">
                            <span class="service-price">Rp <?= number_format($harga, 0, ',', '.') ?></span>
                            <h3 class="service-title"><?= $nama ?></h3>
                            <div class="service-meta">
                                <i class="far fa-clock"></i>
                                <?= $durasi > 0 ? $durasi . ' Menit' : '-' ?>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="col-12 text-center py-5">
                    <p style="color: rgba(255,255,255,0.5);">Layanan belum tersedia. Silakan tambahkan melalui panel admin.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-5 pt-4">
                <a href="katalog.php" class="btn-premium" style="padding: 12px 30px; font-size: 0.85rem;">Lihat Katalog Gaya</a>
            </div>
        </div>
    </section>

    <!-- ========================================
         BARBER KAMI
         ======================================== -->
    <section class="section-padding" id="barber">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-subtitle">Para Ahli</span>
                <h2 class="section-title">Master Barber Kami</h2>
            </div>
            
            <div class="row g-5 justify-content-center">
                <?php 
                foreach($barber_data as $i => $barber): 
                    $img = 'default_barber.png';
                    $fotoDb = $barber['foto_barber'] ?? $barber['foto'] ?? '';
                    if (!empty($fotoDb) && filter_var($fotoDb, FILTER_VALIDATE_URL)) {
                        $img = $fotoDb;
                    } elseif (!empty($fotoDb) && $fotoDb !== 'default_barber.png' && file_exists(__DIR__ . '/' . $fotoDb)) {
                        $img = $fotoDb . '?t=' . filemtime(__DIR__ . '/' . $fotoDb);
                    } else {
                        // Fallback checking legacy barber files if any, else default placeholder
                        $legacyPaths = ['barber1.png', 'barber2.png', 'barber3.png'];
                        if (strpos(strtolower($barber['nama_barber']), 'jago') !== false) {
                            $img = 'barber1.png';
                        } else if (strpos(strtolower($barber['nama_barber']), 'reno') !== false) {
                            $img = 'barber2.png';
                        } else if (strpos(strtolower($barber['nama_barber']), 'dodi') !== false) {
                            $img = 'barber3.png';
                        } else {
                            $img = $barber_images[$i % count($barber_images)];
                        }
                    }
                ?>
                <div class="col-lg-3 col-md-4 col-sm-6" data-aos="fade-up" data-aos-delay="<?= $i * 150 ?>">
                    <div class="barber-card">
                        <div class="barber-img-wrapper">
                            <img src="<?= $img ?>" alt="<?= htmlspecialchars($barber['nama_barber']) ?>">
                        </div>
                        <h4 class="barber-name"><?= htmlspecialchars($barber['nama_barber']) ?></h4>
                        <span class="barber-title">Senior Barber</span>
                        <p class="text-muted small mt-2 mb-0" style="opacity: 0.9;">
                            <i class="fas fa-cut me-1 text-gold"></i> Pengalaman: <?= 5 + ($i * 2) ?> Tahun
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ========================================
         CARA KERJA
         ======================================== -->
    <section class="section-padding bg-dark-section">
        <div class="container">
            <div class="row g-5 text-center">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
                    <div class="step-item">
                        <div class="step-number">01</div>
                        <h4 class="step-title">Reservasi Online</h4>
                        <p class="step-desc">Pilih layanan, barber, dan jadwal langsung dari smartphone Anda tanpa perlu mendaftar akun.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="step-item">
                        <div class="step-number">02</div>
                        <h4 class="step-title">Konfirmasi QR</h4>
                        <p class="step-desc">Dapatkan kode QR dan konfirmasi jadwal via WhatsApp. Tersedia opsi bayar di awal (QRIS).</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="step-item">
                        <div class="step-number">03</div>
                        <h4 class="step-title">Nikmati Layanan</h4>
                        <p class="step-desc">Datang tepat waktu, scan QR Anda, dan nikmati pengalaman grooming premium tanpa antre.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($gallery_data)): ?>
    <!-- ========================================
         GALERI KAMI
         ======================================== -->
    <section class="section-padding">
        <div class="container">
            <div class="section-header text-center" data-aos="fade-up">
                <span class="section-subtitle">Karya Terbaik</span>
                <h2 class="section-title">Galeri Barbershop</h2>
            </div>
            <div class="row g-4 justify-content-center">
                <?php foreach ($gallery_data as $i => $img): ?>
                <div class="col-md-4 col-sm-6" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
                    <div class="barber-img-wrapper" style="border-radius: 10px; overflow: hidden; box-shadow: 0 10px 20px rgba(0,0,0,0.2);">
                        <img src="<?= htmlspecialchars($img) ?>" alt="Gallery Image" style="filter: none; transition: transform 0.5s;">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <style>
        .barber-img-wrapper:hover img { transform: scale(1.05); }
    </style>
    <?php endif; ?>

    <!-- ========================================
         TESTIMONI
         ======================================== -->
    <section class="section-padding">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-subtitle">Reputasi Kami</span>
                <h2 class="section-title">Ulasan Pelanggan</h2>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="testimonial-card">
                        <i class="fas fa-quote-left quote-icon"></i>
                        <p class="testi-text">"Kualitas potongan rambutnya luar biasa. Perhatian terhadap detail dari barber sangat tinggi. Suasana klasik tapi elegan."</p>
                        <div class="testi-author">
                            <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=150&q=80" class="testi-avatar" alt="Revan Madara">
                            <div>
                                <h6 class="testi-name">Revan Madara</h6>
                                <div class="testi-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="testimonial-card">
                        <i class="fas fa-quote-left quote-icon"></i>
                        <p class="testi-text">"The best barbershop in town. Sistem bookingnya sangat memudahkan, tidak perlu lagi mengantre lama. Pelayanannya premium."</p>
                        <div class="testi-author">
                            <img src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=150&q=80" class="testi-avatar" alt="Roy Pratama">
                            <div>
                                <h6 class="testi-name">Roy Pratama</h6>
                                <div class="testi-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="testimonial-card">
                        <i class="fas fa-quote-left quote-icon"></i>
                        <p class="testi-text">"Sangat profesional. Alat-alat disterilkan sebelum digunakan dan hasil cukurannya presisi sesuai request. Highly recommended."</p>
                        <div class="testi-author">
                            <img src="https://images.unsplash.com/photo-1531427186611-ecfd6d936c79?auto=format&fit=crop&w=150&q=80" class="testi-avatar" alt="Arja Kesuma">
                            <div>
                                <h6 class="testi-name">Arja Kesuma</h6>
                                <div class="testi-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================
         FOOTER
         ======================================== -->
    <footer class="footer-premium">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-5">
                    <div class="footer-brand">VIJER<span>.</span></div>
                    <p class="pe-lg-5 mb-4" style="line-height: 1.8; font-size: 1.05rem;">
                        Mendefinisikan ulang standar perawatan pria. Kami menyajikan pengalaman barbershop mewah dengan sentuhan tradisional dan eksekusi modern.
                    </p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <h5 class="footer-title">Navigasi</h5>
                    <a href="#beranda" class="footer-link">Beranda</a>
                    <a href="#tentang" class="footer-link">Tentang Kami</a>
                    <a href="#layanan" class="footer-link">Layanan</a>
                    <a href="katalog.php" class="footer-link">Katalog Gaya</a>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <h5 class="footer-title">Kunjungi Kami</h5>
                    <p class="mb-2" style="font-size: 1rem;"><i class="fas fa-map-marker-alt text-gold me-2"></i> <?= SITE_ADDRESS ?></p>
                    <p class="mb-2" style="font-size: 1rem;"><i class="fas fa-phone-alt text-gold me-2"></i> <?= SITE_PHONE ?></p>
                    <p class=""><i class="fas fa-envelope text-gold me-2"></i> <?= SITE_EMAIL ?></p>
                    
                    <div class="mt-4 pt-4 border-top border-dark">
                        <a href="login.php" class="text-decoration-none" style="color: var(--color-text-main); font-size: 0.85rem; opacity: 0.8;"><i class="fas fa-lock me-1"></i> Admin Portal</a>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-5 pt-4 border-top border-dark">
                <p class="small mb-0" style="opacity: 0.8;">&copy; <?= date('Y') ?> Vijer Barbershop. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- ========================================
         SCRIPTS
         ======================================== -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Theme Toggle Script
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
                if (theme === 'dark') {
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                } else {
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                }
            }
        });

        // Initialize AOS
        AOS.init({
            once: true,
            offset: 50,
        });

        // Navbar Scroll Effect
        window.addEventListener('scroll', () => {
            const navbar = document.getElementById('mainNavbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth Scroll & Active Link
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const offset = 70;
                    const top = target.getBoundingClientRect().top + window.scrollY - offset;
                    window.scrollTo({ top, behavior: 'smooth' });
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // Initialize Swiper for About Section
        const aboutSwiper = new Swiper('.about-swiper', {
            loop: true,
            autoplay: {
                delay: 4000,
                disableOnInteraction: false,
            },
            effect: 'slide',
            speed: 800,
            pagination: {
                el: '.about-swiper .swiper-pagination',
                clickable: true,
            },
        });
    </script>
</body>
</html>
