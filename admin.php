<?php
// admin.php – Admin Dashboard Premium – Vijer Barbershop
// Sembunyikan error dari browser, catat di log
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/settings.php';

// Admin check
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$adminName = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Admin';

// ── Fetch Data ──────────────────────────────────────────────────────────────

// Services
$servicesRes = $conn->query('SELECT * FROM layanan ORDER BY id_layanan ASC');
$services = [];
while ($row = $servicesRes->fetch_assoc()) {
    $services[] = $row;
}

// Barbers
$barberRes = $conn->query('SELECT * FROM barber ORDER BY id ASC');
$barbers = [];
if ($barberRes) {
    while ($row = $barberRes->fetch_assoc()) {
        $barbers[] = $row;
    }
}

// Gaya Rambut
$gayaRes = $conn->query('SELECT * FROM gaya_rambut ORDER BY id_gaya ASC');
$gayaRambut = [];
while ($row = $gayaRes->fetch_assoc()) {
    $gayaRambut[] = $row;
}

// Shop status & bank account
$shopOpen    = get_setting('shop_open')    ?? '0';
$bankAccount = get_setting('bank_account') ?? '-';

// Content management data
$heroImage    = get_setting('homepage_hero_image');
$aboutMain    = get_setting('homepage_about_main');
$aboutSub     = get_setting('homepage_about_sub');
$qrisImage    = get_setting('qris_image');
$qrisMerchant = get_setting('qris_merchant_name') ?? 'NAFIS LAILATUL BADRIYAH';
$qrisBank     = get_setting('qris_merchant_bank') ?? 'BRI (BritAma)';
$qrisAccount  = get_setting('qris_merchant_account') ?? '0022 **** **** 509';

// Homepage Gallery (Photos)
$homepagePhotosRes = $conn->query('SELECT * FROM homepage_photos ORDER BY created_at DESC');
$homepagePhotos = [];
if ($homepagePhotosRes) {
    while ($row = $homepagePhotosRes->fetch_assoc()) {
        $homepagePhotos[] = $row;
    }
}

// About Slider Photos
$aboutSliderRes = $conn->query('SELECT * FROM about_slider_photos ORDER BY created_at DESC');
$aboutSliderPhotos = [];
if ($aboutSliderRes) {
    while ($row = $aboutSliderRes->fetch_assoc()) {
        $aboutSliderPhotos[] = $row;
    }
}

// Pending bookings
$pendingRes = $conn->query(
    "SELECT b.*, br.nama_barber, l.nama_layanan
     FROM booking b
     LEFT JOIN barber br ON b.id_barber = COALESCE(br.id_barber, br.id)
     LEFT JOIN layanan l ON b.id_layanan = l.id_layanan
     WHERE b.status = 'pending'
     ORDER BY b.tanggal_booking ASC, b.jam_booking ASC"
);
$pendingBookings = [];
if ($pendingRes) {
    while ($row = $pendingRes->fetch_assoc()) {
        $pendingBookings[] = $row;
    }
}

// Active (disetujui) bookings – perlu tombol Selesai
$activeRes = $conn->query(
    "SELECT b.*, br.nama_barber, l.nama_layanan
     FROM booking b
     LEFT JOIN barber br ON b.id_barber = COALESCE(br.id_barber, br.id)
     LEFT JOIN layanan l ON b.id_layanan = l.id_layanan
     WHERE b.status = 'disetujui'
     ORDER BY b.tanggal_booking ASC, b.jam_booking ASC"
);
$activeBookings = [];
if ($activeRes) {
    while ($row = $activeRes->fetch_assoc()) {
        $activeBookings[] = $row;
    }
}

// Today's confirmed bookings (for stats)
$todayStr = date('Y-m-d');
$statsRes = $conn->query(
    "SELECT
        COUNT(CASE WHEN status='pending' THEN 1 END)   AS total_pending,
        COUNT(CASE WHEN status='disetujui' AND tanggal_booking='$todayStr' THEN 1 END) AS today_confirmed,
        COUNT(CASE WHEN status='selesai' THEN 1 END)   AS total_selesai
     FROM booking"
);
$stats = $statsRes ? $statsRes->fetch_assoc() : ['total_pending'=>0,'today_confirmed'=>0,'total_selesai'=>0];

// Flash messages
$flashSuccess = isset($_GET['success']) ? htmlspecialchars(urldecode($_GET['success'])) : null;
$flashError   = isset($_GET['error'])   ? htmlspecialchars(urldecode($_GET['error']))   : null;
$flashStatus  = isset($_GET['status'])  ? htmlspecialchars(urldecode($_GET['status']))  : null;
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – Vijer Barbershop</title>
    <meta name="description" content="Panel admin Vijer Barbershop untuk manajemen layanan, booking, dan status toko.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
    /* ── CSS Variables / Design Tokens ─────────────────────────────────── */
    :root {
        --bg-base:        #0a0a0f;
        --bg-surface:     #12121a;
        --bg-elevated:    #1a1a26;
        --bg-card:        #1e1e2e;
        --bg-hover:       #252535;

        --gold-primary:   #C9A96E;
        --gold-light:     #e2c48a;
        --gold-dark:      #a07840;
        --gold-glow:      rgba(201,169,110,0.15);
        --gold-glow-md:   rgba(201,169,110,0.25);

        --accent-green:   #00e5a0;
        --accent-red:     #ff4d6d;
        --accent-blue:    #4d9fff;
        --accent-orange:  #ff9f43;
        --accent-purple:  #a78bfa;

        --text-primary:   #f0f0f5;
        --text-secondary: #9090aa;
        --text-muted:     #5a5a70;

        --border-color:   rgba(201,169,110,0.15);
        --border-strong:  rgba(201,169,110,0.35);

        --sidebar-w:      260px;
        --radius-sm:      8px;
        --radius-md:      14px;
        --radius-lg:      20px;
        --radius-xl:      28px;

        --shadow-card:    0 4px 24px rgba(0,0,0,0.4);
        --shadow-glow:    0 0 30px rgba(201,169,110,0.12);

        --transition:     all 0.25s cubic-bezier(0.4,0,0.2,1);
    }

    /* ── Reset & Base ───────────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html { scroll-behavior: smooth; }

    body {
        font-family: 'Outfit', sans-serif;
        background-color: var(--bg-base);
        color: var(--text-primary);
        display: flex;
        min-height: 100vh;
        overflow-x: hidden;
    }

    /* ── Sidebar ────────────────────────────────────────────────────────── */
    .sidebar {
        width: var(--sidebar-w);
        background: var(--bg-surface);
        border-right: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0; left: 0;
        height: 100vh;
        z-index: 100;
        transition: transform 0.3s ease;
    }

    .sidebar-brand {
        padding: 28px 24px 20px;
        border-bottom: 1px solid var(--border-color);
    }

    .brand-logo {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }

    .brand-icon {
        width: 44px; height: 44px;
        background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        box-shadow: 0 0 20px var(--gold-glow-md);
        flex-shrink: 0;
    }

    .brand-text { flex: 1; }
    .brand-title {
        font-size: 14px; font-weight: 700;
        color: var(--gold-primary);
        letter-spacing: 0.5px;
    }
    .brand-sub {
        font-size: 11px; font-weight: 400;
        color: var(--text-muted);
        margin-top: 1px;
    }

    /* Shop Status Badge in sidebar */
    .shop-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 10px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 100px;
        margin-top: 6px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }
    .shop-status-badge.open  { background: rgba(0,229,160,0.15); color: var(--accent-green); border: 1px solid rgba(0,229,160,0.3); }
    .shop-status-badge.close { background: rgba(255,77,109,0.12); color: var(--accent-red);   border: 1px solid rgba(255,77,109,0.25); }
    .shop-status-badge .dot  { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .shop-status-badge.open .dot { animation: pulse-dot 1.5s infinite; }

    @keyframes pulse-dot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.3; }
    }

    .nav-section {
        padding: 20px 16px 8px;
        font-size: 10px;
        font-weight: 600;
        color: var(--text-muted);
        letter-spacing: 1.2px;
        text-transform: uppercase;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        margin: 2px 8px;
        border-radius: var(--radius-sm);
        text-decoration: none;
        color: var(--text-secondary);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        border: 1px solid transparent;
        transition: var(--transition);
        position: relative;
    }

    .nav-item:hover {
        background: var(--bg-elevated);
        color: var(--text-primary);
        border-color: var(--border-color);
    }

    .nav-item.active {
        background: var(--gold-glow);
        color: var(--gold-primary);
        border-color: var(--border-color);
    }

    .nav-item .nav-icon { font-size: 18px; flex-shrink: 0; }

    .nav-badge {
        margin-left: auto;
        background: var(--accent-red);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        min-width: 20px;
        height: 20px;
        border-radius: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 5px;
    }

    .sidebar-footer {
        margin-top: auto;
        padding: 16px;
        border-top: 1px solid var(--border-color);
    }

    .admin-user {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: var(--radius-sm);
        background: var(--bg-elevated);
        border: 1px solid var(--border-color);
    }

    .admin-avatar {
        width: 36px; height: 36px;
        background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        font-weight: 700;
        color: #000;
        flex-shrink: 0;
    }

    .admin-info { flex: 1; overflow: hidden; }
    .admin-name { font-size: 13px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .admin-role { font-size: 11px; color: var(--gold-primary); }

    /* ── Main Content ───────────────────────────────────────────────────── */
    .main-content {
        margin-left: var(--sidebar-w);
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    /* ── Topbar ─────────────────────────────────────────────────────────── */
    .topbar {
        background: var(--bg-surface);
        border-bottom: 1px solid var(--border-color);
        padding: 16px 32px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 50;
        backdrop-filter: blur(12px);
    }

    .topbar-left h1 {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
    }

    .topbar-left p {
        font-size: 13px;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .topbar-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        font-family: 'Outfit', sans-serif;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        transition: var(--transition);
    }

    .topbar-btn:hover {
        background: var(--bg-elevated);
        color: var(--text-primary);
        border-color: var(--border-strong);
    }

    .topbar-btn.danger:hover {
        background: rgba(255,77,109,0.1);
        color: var(--accent-red);
        border-color: rgba(255,77,109,0.35);
    }

    /* ── Page Body ──────────────────────────────────────────────────────── */
    .page-body {
        padding: 32px;
        flex: 1;
    }

    /* ── Flash Alerts ───────────────────────────────────────────────────── */
    .flash-alert {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        border-radius: var(--radius-md);
        margin-bottom: 24px;
        font-size: 14px;
        font-weight: 500;
        border: 1px solid;
        animation: slideDown 0.4s ease;
    }

    .flash-alert.success {
        background: rgba(0,229,160,0.08);
        border-color: rgba(0,229,160,0.3);
        color: var(--accent-green);
    }

    .flash-alert.error {
        background: rgba(255,77,109,0.08);
        border-color: rgba(255,77,109,0.3);
        color: var(--accent-red);
    }

    .flash-alert.info {
        background: rgba(201,169,110,0.08);
        border-color: var(--border-color);
        color: var(--gold-primary);
    }

    .flash-close {
        margin-left: auto;
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        font-size: 18px;
        opacity: 0.6;
        transition: opacity 0.2s;
    }

    .flash-close:hover { opacity: 1; }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-12px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Section Tabs ───────────────────────────────────────────────────── */
    .section-tabs {
        display: flex;
        gap: 6px;
        padding: 6px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        margin-bottom: 32px;
        width: fit-content;
    }

    .tab-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: var(--radius-sm);
        font-family: 'Outfit', sans-serif;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid transparent;
        background: transparent;
        color: var(--text-secondary);
        transition: var(--transition);
        position: relative;
    }

    .tab-btn:hover {
        background: var(--bg-elevated);
        color: var(--text-primary);
    }

    .tab-btn.active {
        background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
        color: #000;
        border-color: var(--gold-primary);
        box-shadow: 0 4px 16px var(--gold-glow-md);
    }

    .tab-btn .badge {
        background: var(--accent-red);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 100px;
    }

    .tab-btn.active .badge {
        background: rgba(0,0,0,0.25);
        color: #000;
    }

    /* ── Tab Panels ─────────────────────────────────────────────────────── */
    .tab-panel { display: none; animation: fadeUp 0.35s ease; }
    .tab-panel.active { display: block; }

    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(16px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Stat Cards ─────────────────────────────────────────────────────── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 32px;
    }

    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 24px;
        position: relative;
        overflow: hidden;
        transition: var(--transition);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 2px;
        background: linear-gradient(90deg, var(--gold-primary), transparent);
    }

    .stat-card:hover {
        border-color: var(--border-strong);
        transform: translateY(-2px);
        box-shadow: var(--shadow-card);
    }

    .stat-icon {
        width: 48px; height: 48px;
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        margin-bottom: 16px;
    }

    .stat-icon.gold    { background: rgba(201,169,110,0.15); color: var(--gold-primary); }
    .stat-icon.green   { background: rgba(0,229,160,0.12);   color: var(--accent-green); }
    .stat-icon.red     { background: rgba(255,77,109,0.12);   color: var(--accent-red);   }

    .stat-value {
        font-size: 36px;
        font-weight: 800;
        color: var(--text-primary);
        line-height: 1;
        margin-bottom: 4px;
    }

    .stat-label {
        font-size: 13px;
        color: var(--text-muted);
        font-weight: 400;
    }

    /* ── Panel Headers ──────────────────────────────────────────────────── */
    .panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
    }

    .panel-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-primary);
    }

    .panel-subtitle {
        font-size: 13px;
        color: var(--text-muted);
        margin-top: 3px;
    }

    /* ── Service Cards Grid ─────────────────────────────────────────────── */
    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .service-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        overflow: hidden;
        transition: var(--transition);
        position: relative;
    }

    .service-card:hover {
        border-color: var(--border-strong);
        box-shadow: var(--shadow-card), var(--shadow-glow);
        transform: translateY(-3px);
    }

    .service-img-wrap {
        position: relative;
        height: 160px;
        background: var(--bg-elevated);
        overflow: hidden;
    }

    .service-img-wrap img {
        width: 100%; height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .service-card:hover .service-img-wrap img {
        transform: scale(1.06);
    }

    .service-img-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to bottom, transparent 40%, rgba(0,0,0,0.7));
    }

    .service-img-edit-btn {
        position: absolute;
        top: 10px; right: 10px;
        background: rgba(0,0,0,0.7);
        border: 1px solid var(--border-color);
        color: var(--gold-primary);
        border-radius: var(--radius-sm);
        padding: 6px 10px;
        font-size: 13px;
        cursor: pointer;
        backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        gap: 6px;
        transition: var(--transition);
        font-family: 'Outfit', sans-serif;
        font-weight: 500;
        text-decoration: none;
    }

    .service-img-edit-btn:hover {
        background: var(--gold-primary);
        color: #000;
        border-color: var(--gold-primary);
    }

    .service-card-body {
        padding: 18px;
    }

    .service-name {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .service-duration {
        font-size: 12px;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 5px;
        margin-bottom: 14px;
    }

    .service-price-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .service-price {
        font-size: 20px;
        font-weight: 800;
        color: var(--gold-primary);
    }

    .service-price span {
        font-size: 13px;
        font-weight: 500;
        color: var(--text-muted);
    }

    .btn-edit-service {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        background: var(--bg-elevated);
        color: var(--text-secondary);
        font-family: 'Outfit', sans-serif;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
    }

    .btn-edit-service:hover {
        background: var(--gold-glow);
        color: var(--gold-primary);
        border-color: var(--border-strong);
    }

    /* ── Booking Cards ──────────────────────────────────────────────────── */
    .bookings-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .booking-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 22px 24px;
        display: flex;
        align-items: center;
        gap: 20px;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .booking-card::before {
        content: '';
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 3px;
        background: var(--accent-orange);
        border-radius: 3px 0 0 3px;
    }

    .booking-card:hover {
        border-color: var(--border-strong);
        box-shadow: var(--shadow-card);
    }

    .booking-avatar {
        width: 52px; height: 52px;
        background: linear-gradient(135deg, #2a1f0f, #3d2d10);
        border: 2px solid var(--gold-dark);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: 700;
        color: var(--gold-primary);
        flex-shrink: 0;
        text-transform: uppercase;
    }

    .booking-info { flex: 1; min-width: 0; }

    .booking-name {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .booking-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 16px;
        font-size: 13px;
        color: var(--text-muted);
    }

    .booking-meta-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .booking-meta-item i { color: var(--gold-primary); }

    .booking-code {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        background: var(--bg-elevated);
        border: 1px solid var(--border-color);
        padding: 2px 8px;
        border-radius: 100px;
        font-family: monospace;
        margin-top: 6px;
        display: inline-block;
    }

    .booking-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        align-items: flex-end;
        flex-shrink: 0;
    }

    /* ── Action Buttons ─────────────────────────────────────────────────── */
    .btn-accept {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        border-radius: var(--radius-sm);
        border: 1px solid rgba(0,229,160,0.4);
        background: rgba(0,229,160,0.1);
        color: var(--accent-green);
        font-family: 'Outfit', sans-serif;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        white-space: nowrap;
    }

    .btn-accept:hover {
        background: var(--accent-green);
        color: #000;
        border-color: var(--accent-green);
        box-shadow: 0 4px 16px rgba(0,229,160,0.25);
    }

    .btn-reject {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        border-radius: var(--radius-sm);
        border: 1px solid rgba(255,77,109,0.3);
        background: rgba(255,77,109,0.08);
        color: var(--accent-red);
        font-family: 'Outfit', sans-serif;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        white-space: nowrap;
    }

    .btn-reject:hover {
        background: var(--accent-red);
        color: #fff;
        border-color: var(--accent-red);
        box-shadow: 0 4px 16px rgba(255,77,109,0.25);
    }

    /* Reject reason form (inline collapse) */
    .reject-reason-panel {
        display: none;
        margin-top: 10px;
        padding: 14px;
        background: rgba(255,77,109,0.06);
        border: 1px solid rgba(255,77,109,0.25);
        border-radius: var(--radius-md);
        animation: fadeUp 0.3s ease;
    }

    .reject-reason-panel.visible { display: block; }

    .reject-reason-panel textarea {
        width: 100%;
        background: var(--bg-elevated);
        border: 1px solid rgba(255,77,109,0.3);
        border-radius: var(--radius-sm);
        color: var(--text-primary);
        font-family: 'Outfit', sans-serif;
        font-size: 13px;
        padding: 10px 12px;
        resize: none;
        outline: none;
        transition: border-color 0.2s;
        margin-bottom: 10px;
        height: 80px;
    }

    .reject-reason-panel textarea:focus {
        border-color: var(--accent-red);
    }

    .reject-confirm-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 16px;
        background: var(--accent-red);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm);
        font-family: 'Outfit', sans-serif;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
    }

    .reject-confirm-btn:hover {
        background: #e0365a;
        box-shadow: 0 4px 16px rgba(255,77,109,0.35);
    }

    .reject-cancel-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 14px;
        background: transparent;
        color: var(--text-muted);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        font-family: 'Outfit', sans-serif;
        font-size: 13px;
        cursor: pointer;
        transition: var(--transition);
        margin-left: 8px;
    }

    .reject-cancel-btn:hover {
        color: var(--text-primary);
        border-color: var(--border-strong);
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }

    .empty-state i {
        font-size: 52px;
        color: var(--text-muted);
        opacity: 0.4;
        display: block;
        margin-bottom: 16px;
    }

    .empty-state p { font-size: 15px; }

    /* ── Shop Status Panel ──────────────────────────────────────────────── */
    .status-panel {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-xl);
        padding: 36px;
        display: flex;
        align-items: center;
        gap: 32px;
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }

    .status-panel::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 80% 50%, var(--glow-color, rgba(201,169,110,0.04)) 0%, transparent 60%);
        pointer-events: none;
        transition: all 0.5s ease;
    }

    .status-panel.open-state  { --glow-color: rgba(0,229,160,0.06); border-color: rgba(0,229,160,0.2); }
    .status-panel.close-state { --glow-color: rgba(255,77,109,0.05); border-color: rgba(255,77,109,0.2); }

    .status-icon-wrap {
        width: 80px; height: 80px;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        flex-shrink: 0;
        transition: all 0.4s ease;
    }

    .status-icon-wrap.open  { background: rgba(0,229,160,0.12); color: var(--accent-green); box-shadow: 0 0 30px rgba(0,229,160,0.15); }
    .status-icon-wrap.close { background: rgba(255,77,109,0.1);  color: var(--accent-red);   box-shadow: 0 0 30px rgba(255,77,109,0.12); }

    .status-info { flex: 1; }

    .status-main {
        font-size: 26px;
        font-weight: 800;
        margin-bottom: 6px;
        transition: color 0.4s;
    }

    .status-main.open  { color: var(--accent-green); }
    .status-main.close { color: var(--accent-red); }

    .status-desc {
        font-size: 14px;
        color: var(--text-muted);
        line-height: 1.5;
    }

    /* Toggle Switch */
    .toggle-switch-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }

    .toggle-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }

    .toggle-switch {
        position: relative;
        width: 72px;
        height: 38px;
        cursor: pointer;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
        position: absolute;
    }

    .toggle-track {
        position: absolute;
        inset: 0;
        border-radius: 100px;
        background: rgba(255,77,109,0.2);
        border: 2px solid rgba(255,77,109,0.4);
        transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
    }

    .toggle-thumb {
        position: absolute;
        top: 4px; left: 4px;
        width: 26px; height: 26px;
        background: var(--accent-red);
        border-radius: 50%;
        transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
        box-shadow: 0 2px 8px rgba(0,0,0,0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: #fff;
    }

    .toggle-switch input:checked ~ .toggle-track {
        background: rgba(0,229,160,0.2);
        border-color: rgba(0,229,160,0.5);
    }

    .toggle-switch input:checked ~ .toggle-thumb {
        transform: translateX(34px);
        background: var(--accent-green);
    }

    .toggle-switch-form {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
    }

    /* Alert notice */
    .close-notice {
        background: rgba(255,77,109,0.08);
        border: 1px solid rgba(255,77,109,0.25);
        border-radius: var(--radius-md);
        padding: 16px 20px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        font-size: 14px;
        color: var(--text-secondary);
        margin-top: 24px;
    }

    .close-notice i {
        color: var(--accent-red);
        font-size: 22px;
        flex-shrink: 0;
        margin-top: 1px;
    }

    .close-notice strong { color: var(--text-primary); }

    /* Bank Account section */
    .bank-form-group {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 28px;
        margin-top: 24px;
    }

    .form-label-styled {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 8px;
        display: block;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .form-input-dark {
        width: 100%;
        background: var(--bg-elevated);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        color: var(--text-primary);
        font-family: 'Outfit', sans-serif;
        font-size: 15px;
        padding: 12px 16px;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-input-dark:focus {
        border-color: var(--gold-primary);
        box-shadow: 0 0 0 3px var(--gold-glow);
    }

    .btn-gold {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 11px 24px;
        background: linear-gradient(135deg, var(--gold-primary), var(--gold-dark));
        color: #000;
        border: none;
        border-radius: var(--radius-sm);
        font-family: 'Outfit', sans-serif;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition);
        box-shadow: 0 4px 16px var(--gold-glow-md);
    }

    .btn-gold:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 24px rgba(201,169,110,0.35);
        filter: brightness(1.08);
    }

    /* ── Modal ──────────────────────────────────────────────────────────── */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.75);
        backdrop-filter: blur(6px);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }

    .modal-overlay.visible {
        opacity: 1;
        pointer-events: all;
    }

    .modal-box {
        background: var(--bg-card);
        border: 1px solid var(--border-strong);
        border-radius: var(--radius-xl);
        width: 100%;
        max-width: 520px;
        box-shadow: 0 24px 80px rgba(0,0,0,0.6);
        transform: scale(0.94) translateY(16px);
        transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1);
        overflow: hidden;
    }

    .modal-overlay.visible .modal-box {
        transform: scale(1) translateY(0);
    }

    .modal-header {
        padding: 22px 28px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .modal-header-icon {
        width: 42px; height: 42px;
        background: var(--gold-glow);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: var(--gold-primary);
    }

    .modal-header h3 {
        font-size: 17px;
        font-weight: 700;
        color: var(--text-primary);
        flex: 1;
    }

    .modal-close {
        background: none;
        border: none;
        color: var(--text-muted);
        font-size: 22px;
        cursor: pointer;
        width: 34px; height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-sm);
        transition: var(--transition);
    }

    .modal-close:hover {
        background: var(--bg-elevated);
        color: var(--text-primary);
    }

    .modal-body { padding: 28px; }

    .modal-field { margin-bottom: 20px; }
    .modal-field:last-child { margin-bottom: 0; }

    /* Image preview in modal */
    .img-preview-wrap {
        position: relative;
        border-radius: var(--radius-sm);
        overflow: hidden;
        height: 140px;
        background: var(--bg-elevated);
        border: 1px dashed var(--border-color);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 8px;
        transition: border-color 0.2s;
        margin-bottom: 10px;
    }

    .img-preview-wrap:hover {
        border-color: var(--gold-primary);
    }

    .img-preview-wrap img {
        position: absolute;
        inset: 0;
        width: 100%; height: 100%;
        object-fit: cover;
    }

    .img-preview-wrap .upload-hint {
        color: var(--text-muted);
        font-size: 13px;
        text-align: center;
        position: relative;
        z-index: 1;
        pointer-events: none;
    }

    .img-preview-wrap i {
        font-size: 28px;
        color: var(--text-muted);
        position: relative;
        z-index: 1;
        pointer-events: none;
    }

    .img-preview-wrap .img-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 13px;
        font-weight: 500;
        opacity: 0;
        transition: opacity 0.2s;
        pointer-events: none;
        z-index: 2;
        gap: 6px;
    }

    .img-preview-wrap:hover .img-overlay { opacity: 1; }

    .file-input-hidden {
        display: none;
    }

    .new-price-display {
        font-size: 24px;
        font-weight: 800;
        color: var(--gold-primary);
        margin-top: 4px;
    }

    .modal-footer {
        padding: 20px 28px;
        border-top: 1px solid var(--border-color);
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 10px 18px;
        background: transparent;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        color: var(--text-secondary);
        font-family: 'Outfit', sans-serif;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
    }

    .btn-secondary:hover {
        background: var(--bg-elevated);
        color: var(--text-primary);
        border-color: var(--border-strong);
    }

    /* ── Scrollbar ──────────────────────────────────────────────────────── */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: var(--bg-base); }
    ::-webkit-scrollbar-thumb { background: var(--bg-elevated); border-radius: 100px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--border-color); }

    /* ── Responsive ─────────────────────────────────────────────────────── */
    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .sidebar { transform: translateX(-100%); }
        .sidebar.open { transform: translateX(0); }
        .main-content { margin-left: 0; }
        .topbar { padding: 14px 20px; }
        .page-body { padding: 20px; }
    }

    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: 1fr; }
        .section-tabs { flex-wrap: wrap; width: 100%; }
        .status-panel { flex-direction: column; text-align: center; }
        .booking-card { flex-wrap: wrap; }
        .booking-actions { width: 100%; flex-direction: row; justify-content: flex-start; }
    }

    /* Mobile menu toggle */
    .mobile-menu-btn {
        display: none;
        background: none;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        color: var(--text-primary);
        font-size: 20px;
        width: 38px; height: 38px;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition);
    }

    @media (max-width: 1024px) {
        .mobile-menu-btn { display: flex; }
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.6);
        z-index: 99;
    }

    .sidebar-overlay.visible { display: block; }

    /* ── Content Manager ────────────────────────────────────────────── */
    .content-manager-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }

    .upload-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        overflow: hidden;
        transition: var(--transition);
    }

    .upload-card:hover {
        border-color: var(--border-strong);
        box-shadow: var(--shadow-card);
    }

    .upload-card-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .upload-card-header i {
        color: var(--gold-primary);
        font-size: 18px;
    }

    .upload-card-header h4 {
        font-size: 14px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    .upload-card-header span {
        font-size: 11px;
        color: var(--text-muted);
        margin-left: auto;
    }

    .upload-preview-zone {
        position: relative;
        height: 200px;
        background: var(--bg-elevated);
        cursor: pointer;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .upload-preview-zone.tall { height: 300px; }

    .upload-preview-zone img {
        width: 100%; height: 100%;
        object-fit: cover;
        transition: transform 0.4s ease;
    }

    .upload-preview-zone:hover img {
        transform: scale(1.03);
    }

    .upload-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.6);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none;
    }

    .upload-preview-zone:hover .upload-overlay {
        opacity: 1;
    }

    .upload-overlay i {
        font-size: 32px;
        color: var(--gold-primary);
    }

    .upload-overlay span {
        font-size: 13px;
        color: #fff;
        font-weight: 500;
    }

    .upload-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        color: var(--text-muted);
    }

    .upload-placeholder i {
        font-size: 40px;
        opacity: 0.4;
    }

    .upload-placeholder span {
        font-size: 13px;
    }

    .upload-placeholder small {
        font-size: 11px;
        color: var(--text-muted);
    }

    .upload-card-footer {
        padding: 14px 20px;
        border-top: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 12px;
        color: var(--text-muted);
    }

    .upload-card-footer .status-ok {
        color: var(--accent-green);
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .upload-card-footer .status-empty {
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* QRIS Section */
    .qris-manager {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 28px;
    }

    @media (max-width: 768px) {
        .qris-manager { grid-template-columns: 1fr; }
        .content-manager-grid { grid-template-columns: 1fr; }
    }

    .qris-preview-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        overflow: hidden;
    }

    .qris-preview-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .qris-preview-header i { color: var(--gold-primary); font-size: 20px; }
    .qris-preview-header h4 { font-size: 16px; font-weight: 700; margin: 0; color: var(--text-primary); }

    .qris-img-zone {
        padding: 24px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 16px;
    }

    .qris-img-frame {
        background: #fff;
        border-radius: var(--radius-md);
        padding: 16px;
        display: inline-block;
        position: relative;
        cursor: pointer;
        transition: var(--transition);
        border: 2px dashed var(--border-color);
    }

    .qris-img-frame:hover {
        border-color: var(--gold-primary);
        box-shadow: 0 4px 20px var(--gold-glow);
    }

    .qris-img-frame img {
        max-width: 260px;
        max-height: 320px;
        display: block;
    }

    .qris-merchant-info {
        text-align: center;
        padding: 0 24px 24px;
    }

    .qris-merchant-info .merchant-name {
        font-size: 16px;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .qris-merchant-info .merchant-bank {
        font-size: 13px;
        color: var(--text-muted);
    }

    .qris-form-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 28px;
    }

    .qris-form-card h4 {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 6px;
    }

    .qris-form-card .form-desc {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 24px;
    }

    .section-divider {
        display: flex;
        align-items: center;
        gap: 16px;
        margin: 36px 0 28px;
    }

    .section-divider::before,
    .section-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border-color);
    }

    .section-divider span {
        font-size: 12px;
        font-weight: 700;
        color: var(--gold-primary);
        text-transform: uppercase;
        letter-spacing: 1.5px;
        white-space: nowrap;
    }
    </style>
</head>
<body>

<!-- ═══════════════════ SIDEBAR ═══════════════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <a href="admin.php" class="brand-logo">
            <div class="brand-icon">💈</div>
            <div class="brand-text">
                <div class="brand-title">Vijer Barbershop</div>
                <div class="brand-sub">Admin Panel</div>
            </div>
        </a>
        <?php
        $shopOpenBool = ($shopOpen === '1');
        $badgeClass   = $shopOpenBool ? 'open' : 'close';
        $badgeLabel   = $shopOpenBool ? 'BUKA' : 'TUTUP';
        ?>
        <div class="shop-status-badge <?= $badgeClass ?>">
            <span class="dot"></span>
            <?= $badgeLabel ?>
        </div>
    </div>

    <div class="nav-section">Menu Utama</div>

    <a href="#" class="nav-item active" id="nav-layanan" onclick="switchTab('layanan', this); return false;">
        <i class="bi bi-scissors nav-icon"></i>
        Manajemen Layanan
    </a>
    <a href="#" class="nav-item" id="nav-barber" onclick="switchTab('barber', this); return false;">
        <i class="bi bi-person-badge nav-icon"></i>
        Manajemen Barber
    </a>
    <a href="#" class="nav-item" id="nav-booking" onclick="switchTab('booking', this); return false;">
        <i class="bi bi-calendar-check nav-icon"></i>
        Kelola Booking
        <?php if (count($pendingBookings) > 0): ?>
            <span class="nav-badge"><?= count($pendingBookings) ?></span>
        <?php endif; ?>
    </a>
    <a href="#" class="nav-item" id="nav-status" onclick="switchTab('status', this); return false;">
        <i class="bi bi-shop nav-icon"></i>
        Status Toko
    </a>
    <a href="#" class="nav-item" id="nav-konten" onclick="switchTab('konten', this); return false;">
        <i class="bi bi-images nav-icon"></i>
        Manajemen Konten
    </a>

    <div class="nav-section">Fitur Baru</div>
    <a href="admin_pembayaran.php" class="nav-item">
        <i class="bi bi-wallet2 nav-icon"></i>
        Verifikasi Pembayaran
    </a>
    <a href="admin_gallery.php" class="nav-item">
        <i class="bi bi-images nav-icon"></i>
        Foto Beranda
    </a>
    <a href="admin_riwayat_pelanggan.php" class="nav-item">
        <i class="bi bi-people nav-icon"></i>
        Riwayat Pelanggan
    </a>
    <a href="admin_laporan.php" class="nav-item">
        <i class="bi bi-file-earmark-spreadsheet nav-icon"></i>
        Laporan Booking
    </a>

    <div class="nav-section">Navigasi</div>
    <a href="index.php" class="nav-item">
        <i class="bi bi-house nav-icon"></i>
        Lihat Halaman Utama
    </a>
    <a href="riwayat_booking.php" class="nav-item">
        <i class="bi bi-clock-history nav-icon"></i>
        Riwayat Booking
        <?php
        $pending_count = $conn->query("SELECT COUNT(*) as c FROM booking WHERE status='pending'")->fetch_assoc()['c'];
        if ($pending_count > 0): ?>
            <span class="nav-badge"><?= $pending_count ?></span>
        <?php endif; ?>
    </a>

    <div class="sidebar-footer">
        <div class="admin-user">
            <div class="admin-avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
            <div class="admin-info">
                <div class="admin-name"><?= htmlspecialchars($adminName) ?></div>
                <div class="admin-role">Administrator</div>
            </div>
        </div>
        <a href="logout.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;color:var(--accent-red);text-decoration:none;font-size:13px;font-weight:500;margin-top:8px;border-radius:var(--radius-sm);transition:var(--transition);" onmouseover="this.style.background='rgba(255,77,109,0.1)'" onmouseout="this.style.background='transparent'">
            <i class="bi bi-box-arrow-right"></i>
            Keluar
        </a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ═══════════════════ MAIN CONTENT ═══════════════════ -->
<div class="main-content">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left" style="display:flex;align-items:center;gap:16px;">
            <button class="mobile-menu-btn" onclick="openSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <div>
                <h1 id="page-heading">Manajemen Layanan</h1>
                <p id="page-sub">Kelola harga dan foto layanan barbershop</p>
            </div>
        </div>
        <div class="topbar-right">
            <a href="index.php" class="topbar-btn">
                <i class="bi bi-eye"></i>
                <span>Preview Site</span>
            </a>
            <a href="logout.php" class="topbar-btn danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Keluar</span>
            </a>
        </div>
    </div>

    <!-- Page Body -->
    <div class="page-body">

        <!-- Flash Messages -->
        <?php if ($flashSuccess): ?>
        <div class="flash-alert success" id="flash-msg">
            <i class="bi bi-check-circle-fill"></i>
            <?= $flashSuccess ?>
            <button class="flash-close" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>
        </div>
        <?php elseif ($flashError): ?>
        <div class="flash-alert error" id="flash-msg">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= $flashError ?>
            <button class="flash-close" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>
        </div>
        <?php elseif ($flashStatus): ?>
        <div class="flash-alert info" id="flash-msg">
            <i class="bi bi-info-circle-fill"></i>
            <?= $flashStatus ?>
            <button class="flash-close" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon gold"><i class="bi bi-hourglass-split"></i></div>
                <div class="stat-value"><?= $stats['total_pending'] ?></div>
                <div class="stat-label">Booking Menunggu Konfirmasi</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-value"><?= $stats['today_confirmed'] ?></div>
                <div class="stat-label">Booking Terkonfirmasi Hari Ini</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="bi bi-scissors"></i></div>
                <div class="stat-value"><?= count($services) ?></div>
                <div class="stat-label">Total Layanan Tersedia</div>
            </div>
        </div>

        <!-- Section Tabs -->
        <div class="section-tabs" role="tablist">
            <button class="tab-btn active" id="tab-layanan" onclick="switchTab('layanan', null)">
                <i class="bi bi-scissors"></i>
                Layanan & Produk
            </button>
            <button class="tab-btn" id="tab-barber" onclick="switchTab('barber', null)">
                <i class="bi bi-person-badge"></i>
                Manajemen Barber
            </button>
            <button class="tab-btn" id="tab-gaya" onclick="switchTab('gaya', null)">
                <i class="bi bi-stars"></i>
                Gaya Rambut
            </button>
            <button class="tab-btn" id="tab-booking" onclick="switchTab('booking', null)">
                <i class="bi bi-calendar-check"></i>
                Booking Masuk
                <?php if (count($pendingBookings) > 0): ?>
                    <span class="badge"><?= count($pendingBookings) ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn" id="tab-status" onclick="switchTab('status', null)">
                <i class="bi bi-toggle-on"></i>
                Status Operasional
            </button>
            <button class="tab-btn" id="tab-konten" onclick="switchTab('konten', null)">
                <i class="bi bi-images"></i>
                Manajemen Konten
            </button>
        </div>

        <!-- ════════════════════════════════════════════════════════════
             TAB 1 – LAYANAN & PRODUK
             ════════════════════════════════════════════════════════════ -->
        <div class="tab-panel active" id="panel-layanan">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Kelola Layanan & Produk</div>
                    <div class="panel-subtitle">Ubah harga dan foto layanan. Perubahan langsung tampil di halaman pelanggan.</div>
                </div>
            </div>

            <div class="services-grid">
                <?php foreach ($services as $svc): ?>
                <?php
                    // Prioritize image_path column (DB), then legacy file, then placeholder
                    if (!empty($svc['image_path']) && file_exists(__DIR__ . '/' . $svc['image_path'])) {
                        $imgSrc = $svc['image_path'] . '?t=' . filemtime(__DIR__ . '/' . $svc['image_path']);
                    } else {
                        $legacyPath = 'uploads/service/' . $svc['id_layanan'] . '.jpg';
                        $imgSrc = file_exists(__DIR__ . '/' . $legacyPath)
                            ? $legacyPath . '?t=' . filemtime(__DIR__ . '/' . $legacyPath)
                            : 'https://placehold.co/400x200/1a1a26/C9A96E?text=' . urlencode($svc['nama_layanan']);
                    }
                ?>
                <div class="service-card">
                    <div class="service-img-wrap">
                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($svc['nama_layanan']) ?>" id="svc-img-<?= $svc['id_layanan'] ?>">
                        <div class="service-img-overlay"></div>
                        <button class="service-img-edit-btn" onclick="openEditModal(<?= $svc['id_layanan'] ?>, '<?= htmlspecialchars(addslashes($svc['nama_layanan'])) ?>', <?= $svc['harga'] ?>, '<?= htmlspecialchars($imgSrc) ?>')">
                            <i class="bi bi-camera"></i> Ganti Foto
                        </button>
                    </div>
                    <div class="service-card-body">
                        <div class="service-name"><?= htmlspecialchars($svc['nama_layanan']) ?></div>
                        <div class="service-duration">
                            <i class="bi bi-clock"></i>
                            <?= $svc['durasi_menit'] ?? '–' ?> menit
                        </div>
                        <div class="service-price-row">
                            <div class="service-price">
                                <span>Rp</span>
                                <?= number_format($svc['harga'], 0, ',', '.') ?>
                            </div>
                            <button class="btn-edit-service" onclick="openEditModal(<?= $svc['id_layanan'] ?>, '<?= htmlspecialchars(addslashes($svc['nama_layanan'])) ?>', <?= $svc['harga'] ?>, '<?= htmlspecialchars($imgSrc) ?>')">
                                <i class="bi bi-pencil"></i>
                                Edit
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div><!-- end panel-layanan -->

        <!-- ════════════════════════════════════════════════════════════
             TAB BARBER – MANAJEMEN BARBER
             ════════════════════════════════════════════════════════════ -->
        <div class="tab-panel" id="panel-barber">
            <div class="panel-header" style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div class="panel-title">Kelola Barber</div>
                    <div class="panel-subtitle">Ubah foto barber yang akan ditampilkan di halaman depan.</div>
                </div>
                <button class="btn-gold" onclick="openAddBarberModal()" style="padding: 8px 16px; font-size: 14px;">
                    <i class="bi bi-plus-lg"></i> Tambah Barber
                </button>
            </div>

            <div class="services-grid">
                <?php foreach ($barbers as $barb): ?>
                <?php
                    $barbImgSrc = 'default_barber.png';
                    $fotoDb = $barb['foto_barber'] ?? $barb['foto'] ?? '';
                    
                    if (!empty($fotoDb) && filter_var($fotoDb, FILTER_VALIDATE_URL)) {
                        $barbImgSrc = $fotoDb;
                    } elseif (!empty($fotoDb) && $fotoDb !== 'default_barber.png' && file_exists(__DIR__ . '/' . $fotoDb)) {
                        $barbImgSrc = $fotoDb . '?t=' . filemtime(__DIR__ . '/' . $fotoDb);
                    } else {
                        $legacyPaths = ['barber1.png', 'barber2.png', 'barber3.png'];
                        if (strpos(strtolower($barb['nama_barber']), 'jago') !== false) {
                            $barbImgSrc = 'barber1.png';
                        } else if (strpos(strtolower($barb['nama_barber']), 'reno') !== false) {
                            $barbImgSrc = 'barber2.png';
                        } else if (strpos(strtolower($barb['nama_barber']), 'dodi') !== false) {
                            $barbImgSrc = 'barber3.png';
                        }
                    }
                ?>
                <div class="service-card" style="opacity: <?= $barb['status'] === 'nonaktif' ? '0.6' : '1' ?>; transition: opacity 0.3s;">
                    <div class="service-img-wrap" style="height: 220px;">
                        <img src="<?= htmlspecialchars($barbImgSrc) ?>" alt="<?= htmlspecialchars($barb['nama_barber']) ?>" style="object-position: top;">
                        <div class="service-img-overlay"></div>
                        <!-- Badge Status -->
                        <span style="position:absolute;top:10px;left:10px;z-index:3;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:0.5px;
                            background:<?= $barb['status']==='aktif' ? 'rgba(0,229,160,0.2)' : 'rgba(255,77,109,0.2)' ?>;
                            color:<?= $barb['status']==='aktif' ? 'var(--accent-green)' : 'var(--accent-red)' ?>;
                            border:1px solid <?= $barb['status']==='aktif' ? 'rgba(0,229,160,0.4)' : 'rgba(255,77,109,0.4)' ?>;
                            backdrop-filter:blur(4px);">
                            <i class="bi bi-<?= $barb['status']==='aktif' ? 'check-circle' : 'x-circle' ?>"></i>
                            <?= $barb['status'] === 'aktif' ? 'AKTIF' : 'NONAKTIF' ?>
                        </span>
                        <button class="service-img-edit-btn" onclick="openBarberModal(<?= $barb['id_barber'] ?>, '<?= htmlspecialchars(addslashes($barb['nama_barber'])) ?>', '<?= htmlspecialchars($barbImgSrc) ?>', '<?= $barb['status'] ?>', '<?= htmlspecialchars(addslashes($barb['spesialisasi'] ?? '')) ?>')">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                    </div>
                    <div class="service-card-body" style="padding:14px;">
                        <div class="service-name" style="margin-bottom:4px;"><?= htmlspecialchars($barb['nama_barber']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);margin-bottom:14px;"><?= htmlspecialchars($barb['spesialisasi'] ?? 'Barber Profesional') ?></div>
                        <div style="display:flex;gap:8px;">
                            <!-- Toggle Status -->
                            <form method="post" action="admin_upload_barber.php" style="flex:1;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id_barber" value="<?= $barb['id_barber'] ?>">
                                <input type="hidden" name="current_status" value="<?= $barb['status'] ?>">
                                <button type="submit" style="width:100%;padding:7px 0;border-radius:8px;border:1px solid;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.2s;
                                    background:<?= $barb['status']==='aktif' ? 'rgba(255,77,109,0.1)' : 'rgba(0,229,160,0.1)' ?>;
                                    color:<?= $barb['status']==='aktif' ? 'var(--accent-red)' : 'var(--accent-green)' ?>;
                                    border-color:<?= $barb['status']==='aktif' ? 'rgba(255,77,109,0.3)' : 'rgba(0,229,160,0.3)' ?>;">
                                    <i class="bi bi-<?= $barb['status']==='aktif' ? 'eye-slash' : 'eye' ?>"></i>
                                    <?= $barb['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                </button>
                            </form>
                            <!-- Hapus -->
                            <form method="post" action="admin_upload_barber.php" onsubmit="return confirm('Yakin ingin menghapus barber ini?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id_barber" value="<?= $barb['id_barber'] ?>">
                                <button type="submit" style="padding:7px 12px;border-radius:8px;border:1px solid rgba(255,77,109,0.3);background:rgba(255,77,109,0.1);color:var(--accent-red);font-size:12px;cursor:pointer;transition:all 0.2s;">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div><!-- end panel-barber -->

        <!-- ════════════════════════════════════════════════════════════
             TAB GAYA RAMBUT
             ════════════════════════════════════════════════════════════ -->
        <div class="tab-panel" id="panel-gaya">
            <div class="panel-header" style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div class="panel-title">Referensi Gaya Rambut</div>
                    <div class="panel-subtitle">Kelola referensi gaya rambut yang dapat dipilih pelanggan saat booking.</div>
                </div>
                <button class="btn-gold" onclick="openAddGayaModal()" style="padding: 8px 16px; font-size: 14px;">
                    <i class="bi bi-plus-lg"></i> Tambah Gaya
                </button>
            </div>

            <div class="services-grid">
                <?php foreach ($gayaRambut as $gy): ?>
                <?php
                    $gayaImgSrc = 'default_gaya.png';
                    if (!empty($gy['foto_gaya']) && filter_var($gy['foto_gaya'], FILTER_VALIDATE_URL)) {
                        $gayaImgSrc = $gy['foto_gaya'];
                    } elseif (!empty($gy['foto_gaya']) && file_exists(__DIR__ . '/' . $gy['foto_gaya'])) {
                        $gayaImgSrc = $gy['foto_gaya'] . '?t=' . filemtime(__DIR__ . '/' . $gy['foto_gaya']);
                    }
                ?>
                <div class="service-card">
                    <div class="service-img-wrap" style="height: 200px;">
                        <img src="<?= htmlspecialchars($gayaImgSrc) ?>" alt="<?= htmlspecialchars($gy['nama_gaya']) ?>">
                        <div class="service-img-overlay"></div>
                    </div>
                    <div class="service-card-body" style="padding: 16px;">
                        <div class="service-name" style="font-size: 16px; margin-bottom: 6px;"><?= htmlspecialchars($gy['nama_gaya']) ?></div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 12px; line-height: 1.4;">
                            <?= htmlspecialchars(substr($gy['deskripsi'], 0, 80)) . (strlen($gy['deskripsi']) > 80 ? '...' : '') ?>
                        </div>
                        <button class="btn-edit-service" style="width: 100%; border: 1px solid var(--gold-primary); color: var(--gold-primary); background: transparent; padding: 6px; border-radius: 6px; font-size: 13px;" onclick="openGayaModal(<?= $gy['id_gaya'] ?>, '<?= htmlspecialchars(addslashes($gy['nama_gaya'])) ?>', '<?= htmlspecialchars(addslashes($gy['deskripsi'])) ?>', '<?= htmlspecialchars($gayaImgSrc) ?>')">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div><!-- end panel-gaya -->

        <!-- ════════════════════════════════════════════════════════════
             TAB 2 – BOOKING MASUK
             ════════════════════════════════════════════════════════════ -->
        <div class="tab-panel" id="panel-booking">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Kelola Booking</div>
                    <div class="panel-subtitle">Terima, tolak, atau selesaikan booking pelanggan.</div>
                </div>
                <div style="font-size:13px;color:var(--text-muted);">
                    Pending: <strong style="color:var(--gold-primary);"><?= count($pendingBookings) ?></strong>
                    &nbsp;|&nbsp; Aktif: <strong style="color:var(--accent-green);"><?= count($activeBookings) ?></strong>
                </div>
            </div>

            <?php if (!empty($activeBookings)): ?>
            <!-- ── Booking AKTIF (Disetujui) – perlu tombol Selesai ────────── -->
            <div style="margin-bottom:28px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                    <span style="background:rgba(0,229,160,0.15);color:var(--accent-green);border:1px solid rgba(0,229,160,0.3);padding:4px 14px;border-radius:20px;font-size:12px;font-weight:600;letter-spacing:0.5px;text-transform:uppercase;"
                    ><i class="bi bi-lightning-charge-fill"></i> Sedang Berjalan (<?= count($activeBookings) ?>)</span>
                </div>
                <div class="bookings-list">
                    <?php foreach ($activeBookings as $bk): ?>
                    <div class="booking-card" id="booking-row-active-<?= $bk['id_booking'] ?>" style="border-left:3px solid var(--accent-green);">
                        <div class="booking-avatar" style="background:linear-gradient(135deg,var(--accent-green),#00a870);">
                            <?= strtoupper(substr($bk['nama_pelanggan'], 0, 1)) ?>
                        </div>
                        <div class="booking-info">
                            <div class="booking-name"><?= htmlspecialchars($bk['nama_pelanggan']) ?></div>
                            <div class="booking-meta">
                                <span class="booking-meta-item"><i class="bi bi-scissors"></i><?= htmlspecialchars($bk['nama_layanan']) ?></span>
                                <span class="booking-meta-item"><i class="bi bi-person-badge"></i><?= htmlspecialchars($bk['nama_barber']) ?></span>
                                <span class="booking-meta-item"><i class="bi bi-calendar3"></i><?= date('d M Y', strtotime($bk['tanggal_booking'])) ?></span>
                                <span class="booking-meta-item"><i class="bi bi-clock"></i><?= substr($bk['jam_booking'], 0, 5) ?></span>
                                <span class="booking-meta-item"><i class="bi bi-telephone"></i><?= htmlspecialchars($bk['no_hp']) ?></span>
                                <span class="booking-meta-item"><i class="bi bi-cash-stack"></i>Rp <?= number_format($bk['total_harga'], 0, ',', '.') ?></span>
                                <?php if (!empty($bk['bukti_pembayaran'])): ?>
                                <span class="booking-meta-item ms-2">
                                    <a href="uploads/bukti/<?= htmlspecialchars($bk['bukti_pembayaran']) ?>" target="_blank"
                                       style="background:rgba(77,159,255,0.2);color:var(--accent-blue);border:1px solid rgba(77,159,255,0.4);padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;text-decoration:none;">
                                        <i class="bi bi-receipt"></i> Lihat Bukti
                                    </a>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="booking-code">#<?= htmlspecialchars($bk['kode_booking']) ?></div>
                        </div>
                        <div class="booking-actions">
                            <!-- Selesai -->
                            <form method="post" action="admin_update_booking.php"
                                  onsubmit="return confirm('Tandai booking #<?= htmlspecialchars($bk['kode_booking']) ?> sebagai SELESAI? Data akan otomatis masuk ke Riwayat & Laporan.')">
                                <input type="hidden" name="booking_id" value="<?= $bk['id_booking'] ?>">
                                <input type="hidden" name="action" value="complete">
                                <button type="submit" class="btn-accept" style="background:linear-gradient(135deg,var(--accent-green),#00a870);border:none;width:100%;">
                                    <i class="bi bi-check2-all"></i>
                                    Selesai
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Booking PENDING (Menunggu Konfirmasi) ─────────────────── -->
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                <span style="background:rgba(255,159,67,0.15);color:var(--accent-orange);border:1px solid rgba(255,159,67,0.3);padding:4px 14px;border-radius:20px;font-size:12px;font-weight:600;letter-spacing:0.5px;text-transform:uppercase;"
                ><i class="bi bi-hourglass-split"></i> Menunggu Konfirmasi (<?= count($pendingBookings) ?>)</span>
            </div>

            <?php if (empty($pendingBookings)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <p>Tidak ada booking yang menunggu konfirmasi saat ini.</p>
            </div>
            <?php else: ?>

            <div class="bookings-list">
                <?php foreach ($pendingBookings as $i => $bk): ?>
                <div class="booking-card" id="booking-row-<?= $bk['id_booking'] ?>">
                    <div class="booking-avatar">
                        <?= strtoupper(substr($bk['nama_pelanggan'], 0, 1)) ?>
                    </div>
                    <div class="booking-info">
                        <div class="booking-name"><?= htmlspecialchars($bk['nama_pelanggan']) ?></div>
                        <div class="booking-meta">
                            <span class="booking-meta-item">
                                <i class="bi bi-scissors"></i>
                                <?= htmlspecialchars($bk['nama_layanan']) ?>
                            </span>
                            <span class="booking-meta-item">
                                <i class="bi bi-person-badge"></i>
                                <?= htmlspecialchars($bk['nama_barber']) ?>
                            </span>
                            <span class="booking-meta-item">
                                <i class="bi bi-calendar3"></i>
                                <?= date('d M Y', strtotime($bk['tanggal_booking'])) ?>
                            </span>
                            <span class="booking-meta-item">
                                <i class="bi bi-clock"></i>
                                <?= substr($bk['jam_booking'], 0, 5) ?>
                            </span>
                            <span class="booking-meta-item">
                                <i class="bi bi-telephone"></i>
                                <?= htmlspecialchars($bk['no_hp']) ?>
                            </span>
                            <span class="booking-meta-item">
                                <i class="bi bi-cash-stack"></i>
                                Rp <?= number_format($bk['total_harga'], 0, ',', '.') ?>
                            </span>
                            <?php if (!empty($bk['bukti_pembayaran'])): ?>
                            <span class="booking-meta-item ms-2">
                                <a href="uploads/bukti/<?= htmlspecialchars($bk['bukti_pembayaran']) ?>" target="_blank" class="badge bg-info text-dark text-decoration-none" style="padding:0.5em 0.8em; font-size:0.9em;">
                                    <i class="bi bi-receipt"></i> Lihat Bukti
                                </a>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="booking-code">#<?= htmlspecialchars($bk['kode_booking']) ?></div>

                        <!-- Reject reason panel (hidden by default) -->
                        <div class="reject-reason-panel" id="reject-panel-<?= $bk['id_booking'] ?>">
                            <form method="post" action="admin_update_booking.php">
                                <input type="hidden" name="booking_id" value="<?= $bk['id_booking'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <textarea name="alasan_tolak" placeholder="Opsional: Tulis alasan penolakan untuk dikirim ke pelanggan melalui WhatsApp..."></textarea>
                                <div>
                                    <button type="submit" class="reject-confirm-btn">
                                        <i class="bi bi-x-circle"></i>
                                        Konfirmasi Tolak
                                    </button>
                                    <button type="button" class="reject-cancel-btn" onclick="hideRejectPanel(<?= $bk['id_booking'] ?>)">
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="booking-actions">
                        <!-- Accept -->
                        <form method="post" action="admin_update_booking.php" onsubmit="return confirmAccept(this)">
                            <input type="hidden" name="booking_id" value="<?= $bk['id_booking'] ?>">
                            <input type="hidden" name="action" value="accept">
                            <button type="submit" class="btn-accept">
                                <i class="bi bi-check-circle"></i>
                                Terima Booking
                            </button>
                        </form>
                        <!-- Reject (show panel) -->
                        <button class="btn-reject" onclick="showRejectPanel(<?= $bk['id_booking'] ?>)">
                            <i class="bi bi-x-circle"></i>
                            Tolak
                        </button>
                        <!-- Delete -->
                        <form method="post" action="admin_update_booking.php" onsubmit="return confirm('Yakin ingin menghapus booking ini secara permanen?');" style="margin-top: 8px;">
                            <input type="hidden" name="booking_id" value="<?= $bk['id_booking'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn-reject" style="background: transparent; border: 1px solid var(--accent-red); color: var(--accent-red); width: 100%;">
                                <i class="bi bi-trash"></i>
                                Hapus
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>
        </div><!-- end panel-booking -->

        <!-- ════════════════════════════════════════════════════════════
             TAB 3 – STATUS OPERASIONAL
             ════════════════════════════════════════════════════════════ -->
        <div class="tab-panel" id="panel-status">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Status Operasional Toko</div>
                    <div class="panel-subtitle">Ubah status toko secara real-time. Pelanggan akan melihat pengumuman ketika toko tutup.</div>
                </div>
            </div>

            <?php $isOpen = ($shopOpen === '1'); ?>

            <!-- Main Status Panel -->
            <div class="status-panel <?= $isOpen ? 'open-state' : 'close-state' ?>" id="status-panel-main">
                <div class="status-icon-wrap <?= $isOpen ? 'open' : 'close' ?>" id="status-icon">
                    <i class="bi bi-<?= $isOpen ? 'shop' : 'shop-window' ?>" id="status-icon-glyph"></i>
                </div>
                <div class="status-info">
                    <div class="status-main <?= $isOpen ? 'open' : 'close' ?>" id="status-main-text">
                        <?= $isOpen ? '🟢 Barbershop SEDANG BUKA' : '🔴 Barbershop SEDANG TUTUP' ?>
                    </div>
                    <div class="status-desc" id="status-desc-text">
                        <?php if ($isOpen): ?>
                            Toko saat ini <strong>menerima booking</strong>. Pelanggan dapat melakukan reservasi kapan saja.
                        <?php else: ?>
                            Toko saat ini <strong>tidak menerima booking</strong>. Pengumuman tutup ditampilkan di halaman pelanggan.
                        <?php endif; ?>
                    </div>
                </div>
                <div class="toggle-switch-wrap">
                    <span class="toggle-label">Status</span>
                    <form method="post" action="admin_toggle_shop.php" id="toggle-form" onsubmit="return confirmToggle()">
                        <label class="toggle-switch" title="Klik untuk mengubah status toko">
                            <input type="checkbox" id="shop-toggle" <?= $isOpen ? 'checked' : '' ?> onchange="document.getElementById('toggle-form').submit()">
                            <div class="toggle-track"></div>
                            <div class="toggle-thumb">
                                <i class="bi bi-power" id="toggle-thumb-icon"></i>
                            </div>
                        </label>
                    </form>
                    <span class="toggle-label" id="toggle-label-text"><?= $isOpen ? 'BUKA' : 'TUTUP' ?></span>
                </div>
            </div>

            <!-- Close Notice (visible when shop is closed) -->
            <?php if (!$isOpen): ?>
            <div class="close-notice" id="close-notice">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    <strong>Toko Sedang Tutup</strong><br>
                    Saat ini halaman booking pelanggan menampilkan pemberitahuan <em>"Barbershop Sedang Tutup"</em> dan tombol pesan dinonaktifkan.
                    Aktifkan kembali toggle di atas untuk membuka toko dan mengizinkan booking.
                </div>
            </div>
            <?php endif; ?>

            <!-- Info Cards -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:24px;">
                <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:20px;">
                    <div style="font-size:13px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:12px;">
                        <i class="bi bi-info-circle"></i> Efek Status BUKA
                    </div>
                    <ul style="list-style:none;display:flex;flex-direction:column;gap:8px;font-size:13px;color:var(--text-secondary);">
                        <li style="display:flex;gap:8px;"><i class="bi bi-check-circle" style="color:var(--accent-green);flex-shrink:0;margin-top:1px;"></i> Halaman booking dapat diakses pelanggan</li>
                        <li style="display:flex;gap:8px;"><i class="bi bi-check-circle" style="color:var(--accent-green);flex-shrink:0;margin-top:1px;"></i> Tombol "Pesan Sekarang" aktif</li>
                        <li style="display:flex;gap:8px;"><i class="bi bi-check-circle" style="color:var(--accent-green);flex-shrink:0;margin-top:1px;"></i> Slot waktu tersedia ditampilkan</li>
                    </ul>
                </div>
                <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:20px;">
                    <div style="font-size:13px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:12px;">
                        <i class="bi bi-exclamation-triangle"></i> Efek Status TUTUP
                    </div>
                    <ul style="list-style:none;display:flex;flex-direction:column;gap:8px;font-size:13px;color:var(--text-secondary);">
                        <li style="display:flex;gap:8px;"><i class="bi bi-x-circle" style="color:var(--accent-red);flex-shrink:0;margin-top:1px;"></i> Banner "Barbershop Tutup" muncul di homepage</li>
                        <li style="display:flex;gap:8px;"><i class="bi bi-x-circle" style="color:var(--accent-red);flex-shrink:0;margin-top:1px;"></i> Tombol booking dinonaktifkan</li>
                        <li style="display:flex;gap:8px;"><i class="bi bi-x-circle" style="color:var(--accent-red);flex-shrink:0;margin-top:1px;"></i> Booking baru tidak dapat dibuat</li>
                    </ul>
                </div>
            </div>

            <!-- Bank Account -->
            <div class="bank-form-group">
                <div style="font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:6px;">
                    <i class="bi bi-bank" style="color:var(--gold-primary);"></i>
                    Rekening Bank Pembayaran
                </div>
                <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px;">
                    Informasi rekening yang ditampilkan kepada pelanggan untuk keperluan pembayaran transfer.
                </p>
                <form method="post" action="admin_update_bank.php" class="row g-3">
                    <div class="modal-field">
                        <label class="form-label-styled">Nomor / Info Rekening</label>
                        <input type="text" name="bank_account" class="form-input-dark" value="<?= htmlspecialchars($bankAccount) ?>" placeholder="Contoh: BCA 1234567890 a.n. Vijer Barbershop" required>
                    </div>
                    <div>
                        <button type="submit" class="btn-gold">
                            <i class="bi bi-floppy2"></i>
                            Simpan Rekening
                        </button>
                    </div>
                </form>
            </div>
        </div><!-- end panel-status -->

        <!-- ════════════════════════════════════════════════════════════
             TAB 4 – MANAJEMEN KONTEN
             ════════════════════════════════════════════════════════════ -->
        <div class="tab-panel" id="panel-konten">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Manajemen Konten Beranda</div>
                    <div class="panel-subtitle">Upload dan kelola foto beranda, banner, dan konten visual lainnya secara real-time.</div>
                </div>
            </div>

            <!-- ── SECTION A: Homepage Banner Manager ────────────── -->
            <form method="post" action="admin_upload_homepage.php" enctype="multipart/form-data" id="homepage-form">
                <div class="content-manager-grid">
                    <!-- Hero Image -->
                    <div class="upload-card">
                        <div class="upload-card-header">
                            <i class="bi bi-display"></i>
                            <h4>Hero Banner Utama</h4>
                            <span>1920 × 1080 px</span>
                        </div>
                        <div class="upload-preview-zone" onclick="document.getElementById('input-hero').click()">
                            <?php
                            $heroSrc = null;
                            if ($heroImage === 'empty') {
                                $heroSrc = 'empty';
                            } elseif ($heroImage && file_exists(__DIR__ . '/' . $heroImage)) {
                                $heroSrc = $heroImage . '?t=' . filemtime(__DIR__ . '/' . $heroImage);
                            }
                            ?>
                            <?php if ($heroSrc === 'empty'): ?>
                                <img src="https://placehold.co/800x400/12121a/5a5a70?text=Tidak+Ada+Gambar" alt="Kosong" id="preview-hero" style="opacity:0.3;">
                            <?php elseif ($heroSrc): ?>
                                <img src="<?= htmlspecialchars($heroSrc) ?>" alt="Hero Image" id="preview-hero">
                            <?php else: ?>
                                <img src="https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=800&q=60" alt="Default Hero" id="preview-hero" style="opacity:0.5;">
                            <?php endif; ?>
                            <div class="upload-overlay">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <span>Klik untuk ganti foto</span>
                            </div>
                        </div>
                        <input type="file" name="hero_image" id="input-hero" class="file-input-hidden" accept="image/jpeg,image/png,image/gif" onchange="previewUpload(this, 'preview-hero', 'status-hero')">
                        <div class="upload-card-footer" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                            <div>
                                <span style="display:block; margin-bottom:4px; font-size:11px; color:var(--text-muted);">JPG, PNG · Maks 5MB</span>
                                <?php if ($heroSrc === 'empty'): ?>
                                    <span class="status-empty" id="status-hero" style="color:var(--accent-red);"><i class="bi bi-x-circle"></i> Kosong</span>
                                <?php elseif ($heroSrc): ?>
                                    <span class="status-ok" id="status-hero"><i class="bi bi-check-circle-fill"></i> Aktif</span>
                                <?php else: ?>
                                    <span class="status-empty" id="status-hero"><i class="bi bi-dash-circle"></i> Default</span>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex; gap:8px;">
                                <button type="button" style="font-size: 11px; padding: 4px 10px; border-radius: 4px; border:none; cursor:pointer; background:rgba(201,169,110,0.15); color:var(--gold-primary);" onclick="document.getElementById('input-hero').click()">
                                    <i class="bi bi-upload"></i> <?= ($heroSrc && $heroSrc !== 'empty') ? 'Ganti Foto' : 'Tambah Foto' ?>
                                </button>
                                <?php if ($heroSrc !== 'empty'): ?>
                                    <a href="admin_upload_homepage.php?action=delete_banner&key=homepage_hero_image" class="btn-sm btn-danger" style="font-size: 11px; padding: 4px 10px; border-radius: 4px; text-decoration: none; border:none;" onclick="return confirm('Hapus Hero Banner?')"><i class="bi bi-trash"></i> Hapus</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:12px;align-items:center;margin-top:24px;">
                    <button type="submit" class="btn-gold">
                        <i class="bi bi-floppy2"></i>
                        Simpan Foto Beranda
                    </button>
                    <span style="font-size:12px;color:var(--text-muted);">Hanya foto hero yang akan diupdate.</span>
                </div>
            </form>

            <!-- ── SECTION: About Slider Manager ───────────────────────── -->
            <div class="section-divider">
                <span><i class="bi bi-images"></i> Manajemen Slider Tentang Kami</span>
            </div>

            <div class="qris-form-card" style="margin-bottom: 24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <div>
                        <h4 style="margin:0;"><i class="bi bi-collection-play" style="color:var(--gold-primary);margin-right:8px;"></i>Foto Slider</h4>
                        <p class="form-desc" style="margin-top:4px;">Tambahkan foto untuk slider (slide-to-slide) pada bagian Tentang Kami. Format JPG/PNG, maks 5MB.</p>
                    </div>
                    <form id="slider-add-form" method="post" action="admin_about_slider_action.php" enctype="multipart/form-data" style="display:flex; gap:12px; align-items:center;">
                        <input type="hidden" name="action" value="add">
                        <input type="file" name="slider_image" id="input-about-slider" style="display:none;" accept="image/jpeg,image/png,image/gif" onchange="document.getElementById('slider-add-form').submit()">
                        <button type="button" class="btn-gold" onclick="document.getElementById('input-about-slider').click()">
                            <i class="bi bi-cloud-arrow-up"></i> Tambah Foto Slider
                        </button>
                    </form>
                </div>

                <?php if (empty($aboutSliderPhotos)): ?>
                    <div class="empty-state" style="padding: 30px; text-align: center; border: 1px dashed rgba(255,255,255,0.1); border-radius: 8px;">
                        <i class="bi bi-image" style="font-size: 32px; color: var(--text-muted); margin-bottom: 12px; display: block;"></i>
                        <h5 style="margin: 0 0 8px 0; color: #fff;">Belum ada foto slider</h5>
                        <p style="margin: 0; color: var(--text-muted); font-size: 13px;">Slider akan menampilkan gambar bawaan (default) jika kosong.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-dark-custom">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Preview</th>
                                    <th>Nama File</th>
                                    <th>Tanggal Upload</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aboutSliderPhotos as $photo): ?>
                                    <tr>
                                        <td>
                                            <div style="width: 60px; height: 40px; border-radius: 4px; overflow: hidden; background: #1a1a24;">
                                                <img src="<?= htmlspecialchars($photo['file_path']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            </div>
                                        </td>
                                        <td style="font-size: 13px; color: #ccc;">
                                            <?= htmlspecialchars(basename($photo['file_path'])) ?>
                                        </td>
                                        <td style="font-size: 13px; color: var(--text-muted);">
                                            <?= date('d M Y H:i', strtotime($photo['created_at'])) ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <form method="post" action="admin_about_slider_action.php" style="display:inline;" onsubmit="return confirm('Hapus foto slider ini?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $photo['id'] ?>">
                                                <button type="submit" class="btn-sm btn-danger" style="padding: 4px 10px; border-radius: 4px; border:none; cursor:pointer;">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── SECTION B: QRIS Manager ───────────────────────── -->
            <div class="section-divider">
                <span><i class="bi bi-qr-code"></i> Manajemen QRIS</span>
            </div>

            <div class="qris-manager">
                <!-- QRIS Preview -->
                <div class="qris-preview-card">
                    <div class="qris-preview-header">
                        <i class="bi bi-qr-code-scan"></i>
                        <h4>Preview QRIS Pelanggan</h4>
                    </div>
                    <div class="qris-img-zone">
                        <?php
                        $qrisSrc = null;
                        if ($qrisImage && file_exists(__DIR__ . '/' . $qrisImage)) {
                            $qrisSrc = $qrisImage . '?t=' . filemtime(__DIR__ . '/' . $qrisImage);
                        }
                        ?>
                        <div class="qris-img-frame" onclick="document.getElementById('input-qris').click()" title="Klik untuk ganti gambar QRIS">
                            <?php if ($qrisSrc): ?>
                                <img src="<?= htmlspecialchars($qrisSrc) ?>" alt="QRIS Code" id="preview-qris">
                            <?php else: ?>
                                <div style="width:260px;height:260px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;color:#999;">
                                    <i class="bi bi-qr-code" style="font-size:64px;opacity:0.3;"></i>
                                    <span style="font-size:13px;">Belum ada gambar QRIS</span>
                                    <span style="font-size:11px;color:#aaa;">Klik untuk upload</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="qris-merchant-info">
                        <div class="merchant-name" id="display-merchant-name"><?= htmlspecialchars($qrisMerchant) ?></div>
                        <div class="merchant-bank" id="display-merchant-bank"><?= htmlspecialchars($qrisBank) ?> – <?= htmlspecialchars($qrisAccount) ?></div>
                    </div>
                </div>

                <!-- QRIS Form -->
                <div class="qris-form-card">
                    <h4><i class="bi bi-pencil-square" style="color:var(--gold-primary);margin-right:8px;"></i>Edit Data QRIS</h4>
                    <p class="form-desc">Upload gambar QRIS baru dan perbarui informasi merchant. Perubahan langsung terlihat oleh pelanggan saat checkout.</p>

                    <form method="post" action="admin_upload_qris.php" enctype="multipart/form-data">
                        <div class="modal-field">
                            <label class="form-label-styled">Gambar QRIS (JPG/PNG)</label>
                            <div class="img-preview-wrap" onclick="document.getElementById('input-qris').click()" style="height:120px;">
                                <?php if ($qrisSrc): ?>
                                    <img src="<?= htmlspecialchars($qrisSrc) ?>" alt="QRIS" id="preview-qris-form">
                                <?php else: ?>
                                    <i class="bi bi-qr-code" id="qris-placeholder-icon"></i>
                                    <div class="upload-hint" id="qris-placeholder-text">Klik untuk upload gambar QRIS<br><small style="color:var(--text-muted);">JPG, PNG · Maks 5MB</small></div>
                                <?php endif; ?>
                                <div class="img-overlay"><i class="bi bi-camera"></i> Ganti gambar QRIS</div>
                            </div>
                            <input type="file" name="qris_image" id="input-qris" class="file-input-hidden" accept="image/jpeg,image/png" onchange="previewQris(this)">
                            <div id="qris-filename" style="font-size:12px;color:var(--text-muted);margin-top:4px;"></div>
                            <?php if ($qrisSrc): ?>
                                <div style="margin-top: 8px;">
                                    <a href="admin_upload_qris.php?action=delete_qris" class="btn-sm btn-danger" style="font-size: 11px; padding: 3px 8px; border-radius: 4px; text-decoration: none; display: inline-block;" onclick="return confirm('Hapus Gambar QRIS?')"><i class="bi bi-trash"></i> Hapus Gambar</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="modal-field">
                            <label class="form-label-styled">Nama Merchant</label>
                            <input type="text" name="qris_merchant_name" class="form-input-dark" value="<?= htmlspecialchars($qrisMerchant) ?>" placeholder="Contoh: NAFIS LAILATUL BADRIYAH" oninput="document.getElementById('display-merchant-name').textContent=this.value">
                        </div>

                        <div class="modal-field">
                            <label class="form-label-styled">Bank / Produk</label>
                            <input type="text" name="qris_merchant_bank" class="form-input-dark" value="<?= htmlspecialchars($qrisBank) ?>" placeholder="Contoh: BRI (BritAma)" oninput="updateMerchantDisplay()"  id="input-qris-bank">
                        </div>

                        <div class="modal-field">
                            <label class="form-label-styled">Nomor Rekening</label>
                            <input type="text" name="qris_merchant_account" class="form-input-dark" value="<?= htmlspecialchars($qrisAccount) ?>" placeholder="Contoh: 0022 **** **** 509" oninput="updateMerchantDisplay()" id="input-qris-account">
                        </div>

                        <button type="submit" class="btn-gold" style="margin-top:8px;">
                            <i class="bi bi-floppy2"></i>
                            Simpan Data QRIS
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- ── SECTION C: Galeri Beranda (Homepage Photos) ────────────── -->
            <div class="section-divider" style="margin-top: 40px;">
                <span><i class="bi bi-images"></i> Galeri Beranda (Katalog Foto)</span>
            </div>
            <div class="qris-form-card" style="margin-bottom: 30px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4><i class="bi bi-camera" style="color:var(--gold-primary);margin-right:8px;"></i>Daftar Foto Galeri Beranda</h4>
                        <p class="form-desc mb-0">Upload foto-foto portofolio atau suasana barbershop. Foto ini akan tampil di bagian bawah halaman beranda pelanggan.</p>
                    </div>
                    <button class="btn-gold" onclick="document.getElementById('addPhotoModal').style.display='flex'">
                        <i class="bi bi-plus-circle"></i> Tambah Foto
                    </button>
                </div>
                
                <div class="table-wrap mt-3">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Preview</th>
                                <th>Status</th>
                                <th>Tanggal Upload</th>
                                <th style="text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($homepagePhotos)): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; padding: 30px; color: var(--text-muted);">Belum ada foto galeri.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($homepagePhotos as $photo): ?>
                                    <tr>
                                        <td>
                                            <div style="width:100px; height:60px; border-radius:6px; overflow:hidden; border:1px solid var(--border-color);">
                                                <img src="<?= htmlspecialchars($photo['file_path']) ?>" alt="Gallery" style="width:100%; height:100%; object-fit:cover;">
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($photo['status'] === 'aktif'): ?>
                                                <span class="badge-status" style="background:rgba(0,229,160,0.15); color:var(--accent-green);">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge-status" style="background:rgba(255,77,109,0.15); color:var(--accent-red);">Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color:var(--text-secondary);"><?= date('d M Y H:i', strtotime($photo['created_at'])) ?></td>
                                        <td style="text-align:right;">
                                            <!-- Toggle Status -->
                                            <form method="post" action="admin_homepage_photos_action.php" style="display:inline-block;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?= $photo['id'] ?>">
                                                <input type="hidden" name="current_status" value="<?= $photo['status'] ?>">
                                                <button type="submit" class="btn-edit-service" title="Ubah Status">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </form>
                                            
                                            <!-- Delete -->
                                            <form method="post" action="admin_homepage_photos_action.php" style="display:inline-block; margin-left: 5px;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $photo['id'] ?>">
                                                <button type="submit" class="btn-edit-service" style="color:var(--accent-red);" title="Hapus Foto" onclick="return confirm('Yakin ingin menghapus foto ini?');">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Info Panel -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:28px;">
                <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:20px;">
                    <div style="font-size:13px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:12px;">
                        <i class="bi bi-info-circle"></i> Tentang Foto Beranda
                    </div>
                    <ul style="list-style:none;display:flex;flex-direction:column;gap:8px;font-size:13px;color:var(--text-secondary);">
                        <li style="display:flex;gap:8px;"><i class="bi bi-check-circle" style="color:var(--accent-green);flex-shrink:0;margin-top:1px;"></i> Foto otomatis dikompresi tanpa mengurangi kualitas</li>
                        <li style="display:flex;gap:8px;"><i class="bi bi-check-circle" style="color:var(--accent-green);flex-shrink:0;margin-top:1px;"></i> Mendukung format JPG, PNG, dan GIF</li>
                        <li style="display:flex;gap:8px;"><i class="bi bi-check-circle" style="color:var(--accent-green);flex-shrink:0;margin-top:1px;"></i> Perubahan langsung tampil di halaman pelanggan</li>
                        <li style="display:flex;gap:8px;"><i class="bi bi-check-circle" style="color:var(--accent-green);flex-shrink:0;margin-top:1px;"></i> Foto lama otomatis dihapus saat diganti</li>
                    </ul>
                </div>
                <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:20px;">
                    <div style="font-size:13px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:12px;">
                        <i class="bi bi-qr-code"></i> Tentang QRIS
                    </div>
                    <ul style="list-style:none;display:flex;flex-direction:column;gap:8px;font-size:13px;color:var(--text-secondary);">
                        <li style="display:flex;gap:8px;"><i class="bi bi-check-circle" style="color:var(--accent-green);flex-shrink:0;margin-top:1px;"></i> Gambar QRIS ditampilkan saat pelanggan checkout</li>
                        <li style="display:flex;gap:8px;"><i class="bi bi-check-circle" style="color:var(--accent-green);flex-shrink:0;margin-top:1px;"></i> Merchant: <?= htmlspecialchars($qrisMerchant) ?></li>
                        <li style="display:flex;gap:8px;"><i class="bi bi-check-circle" style="color:var(--accent-green);flex-shrink:0;margin-top:1px;"></i> Bank: <?= htmlspecialchars($qrisBank) ?> – <?= htmlspecialchars($qrisAccount) ?></li>
                        <li style="display:flex;gap:8px;"><i class="bi bi-exclamation-triangle" style="color:var(--accent-orange);flex-shrink:0;margin-top:1px;"></i> Update berkala jika QRIS berubah</li>
                    </ul>
                </div>
            </div>
        </div><!-- end panel-konten -->

    <!-- Modal Tambah Foto Beranda -->
    <div class="modal-overlay" id="addPhotoModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="bi bi-cloud-upload"></i> Upload Foto Galeri</h3>
                <button type="button" class="modal-close" onclick="document.getElementById('addPhotoModal').style.display='none'"><i class="bi bi-x-lg"></i></button>
            </div>
            <form action="admin_homepage_photos_action.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="modal-field">
                        <label class="form-label-styled">Pilih Foto (JPG/PNG)</label>
                        <input type="file" name="gallery_image" class="form-control form-input-dark" accept="image/jpeg,image/png,image/gif" required>
                        <small style="color:var(--text-muted); display:block; margin-top:5px;">Maksimal 5MB. Direkomendasikan format landscape.</small>
                    </div>
                    <div class="modal-field mt-3">
                        <label class="form-label-styled">Status</label>
                        <select name="status" class="form-select form-input-dark">
                            <option value="aktif">Aktif (Tampil di Beranda)</option>
                            <option value="nonaktif">Nonaktif (Sembunyikan sementara)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="document.getElementById('addPhotoModal').style.display='none'">Batal</button>
                    <button type="submit" class="btn-save"><i class="bi bi-upload"></i> Upload Foto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit/Tambah Gaya Rambut -->
    <div class="modal-overlay" id="editGayaModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modal-title-gaya"><i class="bi bi-stars"></i> Edit Gaya Rambut</h3>
                <button type="button" class="modal-close" onclick="closeGayaModal()"><i class="bi bi-x-lg"></i></button>
            </div>
            
            <form method="post" action="admin_upload_gaya.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="id_gaya" id="modal-gaya-id">
                    <input type="hidden" name="action" id="modal-gaya-action" value="edit">

                    <div class="modal-field">
                        <label class="form-label-styled">Nama Gaya</label>
                        <input type="text" name="nama_gaya" id="modal-gaya-name" class="form-input-dark" required placeholder="Contoh: French Crop">
                    </div>

                    <div class="modal-field">
                        <label class="form-label-styled">Deskripsi Singkat</label>
                        <textarea name="deskripsi" id="modal-gaya-desc" class="form-input-dark" rows="3" placeholder="Gaya rambut pendek yang..."></textarea>
                    </div>

                    <!-- Photo Upload -->
                    <div class="modal-field">
                        <label class="form-label-styled">Upload Foto</label>
                        <div class="img-preview-wrap" id="img-preview-gaya-wrap" onclick="document.getElementById('gaya-photo-input').click()" style="height:180px;">
                            <img src="" id="modal-gaya-img" alt="Preview Foto Gaya" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
                            <div class="img-overlay" style="position:absolute;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:500;opacity:0;transition:opacity 0.2s;pointer-events:none;z-index:2;gap:6px;"><i class="bi bi-camera"></i> Ganti Foto</div>
                        </div>
                        <input type="file" name="foto_gaya" id="gaya-photo-input" class="file-input-hidden" accept="image/jpeg,image/png,image/webp" onchange="previewUpload(this, 'modal-gaya-img', 'gaya-filename-display')">
                        <div id="gaya-filename-display" style="font-size:12px;color:var(--text-muted);margin-top:6px;">Format: JPG, PNG, WEBP. Maks: 5MB.</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeGayaModal()">Batal</button>
                    <button type="submit" class="btn-save" id="btn-save-gaya">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div><!-- End Gaya Modal -->
</div><!-- end main-content -->

<!-- ═══════════════════════════════════════════════════════════
     MODAL – EDIT BARBER
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="editBarberModal" role="dialog" aria-modal="true" aria-labelledby="modal-title-barber">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-icon"><i class="bi bi-person-badge"></i></div>
            <h3 id="modal-title-barber">Edit Foto Barber</h3>
            <button class="modal-close" onclick="closeBarberModal()" aria-label="Tutup modal"><i class="bi bi-x"></i></button>
        </div>
        <form method="post" action="admin_upload_barber.php" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="id_barber" id="modal-barber-id">
                <input type="hidden" name="action" id="modal-barber-action" value="edit">

                <div class="modal-field">
                    <label class="form-label-styled">Nama Barber</label>
                    <input type="text" name="nama_barber" id="modal-barber-name-display" class="form-input-dark" required placeholder="Masukkan nama barber...">
                </div>

                <div class="modal-field">
                    <label class="form-label-styled">Spesialisasi</label>
                    <input type="text" name="keahlian" id="modal-barber-spesialisasi" class="form-input-dark" placeholder="Contoh: Hair Coloring & Treatment">
                </div>

                <div class="modal-field">
                    <label class="form-label-styled">Status</label>
                    <select name="status_barber" id="modal-barber-status" class="form-input-dark" style="cursor:pointer;">
                        <option value="aktif">✅ Aktif (Tampil di website)</option>
                        <option value="nonaktif">❌ Nonaktif (Disembunyikan)</option>
                    </select>
                </div>

                <!-- Photo Upload -->
                <div class="modal-field">
                    <label class="form-label-styled">Upload Foto (Opsional)</label>
                    <div class="img-preview-wrap" id="img-preview-barber-wrap" onclick="document.getElementById('barber-photo-input').click()" style="height:200px;">
                        <img src="" id="modal-barber-img" alt="Preview Foto Barber" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
                        <div class="img-overlay" style="position:absolute;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:500;opacity:0;transition:opacity 0.2s;pointer-events:none;z-index:2;gap:6px;"><i class="bi bi-camera"></i> Ganti Foto</div>
                    </div>
                    <input type="file" name="foto_barber" id="barber-photo-input" class="file-input-hidden" accept="image/jpeg,image/png,image/webp" onchange="previewBarberPhoto(this)">
                    <div id="barber-filename-display" style="font-size:12px;color:var(--text-muted);margin-top:6px;">Format: JPG, PNG, WEBP. Maks: 5MB.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeBarberModal()">Batal</button>
                <button type="submit" class="btn-gold" id="btn-save-barber" disabled>
                    <i class="bi bi-floppy2"></i>
                    Simpan Foto
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL – EDIT LAYANAN (Harga + Foto)
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="editServiceModal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-icon"><i class="bi bi-pencil-square"></i></div>
            <h3 id="modal-title">Edit Layanan</h3>
            <button class="modal-close" onclick="closeEditModal()" aria-label="Tutup modal"><i class="bi bi-x"></i></button>
        </div>
        <form method="post" action="admin_edit_service.php" enctype="multipart/form-data" onsubmit="return validateServiceForm()">
            <div class="modal-body">
                <input type="hidden" name="id_layanan" id="modal-svc-id">
                <input type="hidden" name="nama_layanan" id="modal-svc-name-hidden">

                <!-- Service Name (readonly display) -->
                <div class="modal-field">
                    <label class="form-label-styled">Nama Layanan</label>
                    <div style="background:var(--bg-elevated);border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:11px 16px;font-size:15px;font-weight:600;color:var(--text-primary);" id="modal-svc-name-display">–</div>
                </div>

                <!-- Price -->
                <div class="modal-field">
                    <label class="form-label-styled">Harga Baru (Rp)</label>
                    <input type="number" name="harga" id="modal-svc-price" class="form-input-dark"
                           placeholder="Masukkan harga..." min="0" required
                           oninput="updatePricePreview(this.value)">
                    <div class="new-price-display" id="price-preview">–</div>
                </div>

                <!-- Photo Upload -->
                <div class="modal-field">
                    <label class="form-label-styled">Ganti Foto Layanan (Opsional)</label>
                    <div class="img-preview-wrap" id="img-preview-wrap" onclick="document.getElementById('photo-input').click()">
                        <img src="" alt="Preview" id="img-preview" style="display:none;">
                        <div class="img-overlay"><i class="bi bi-camera"></i> Klik untuk ganti foto</div>
                        <i class="bi bi-image" id="img-placeholder-icon"></i>
                        <div class="upload-hint" id="img-placeholder-text">
                            Klik untuk pilih foto<br>
                            <small style="color:var(--text-muted);">JPG, PNG, GIF · Maks 5MB</small>
                        </div>
                    </div>
                    <input type="file" name="photo" id="photo-input" class="file-input-hidden"
                           accept="image/jpeg,image/png,image/gif" onchange="previewPhoto(this)">
                    <div id="photo-filename" style="font-size:12px;color:var(--text-muted);margin-top:4px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">
                    <i class="bi bi-x"></i>
                    Batal
                </button>
                <button type="submit" class="btn-gold">
                    <i class="bi bi-floppy2"></i>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ═══════════════════════════════════════════════
//  TAB SWITCHING
// ═══════════════════════════════════════════════
const tabMeta = {
    'layanan': { heading: 'Manajemen Layanan', sub: 'Kelola harga dan foto layanan barbershop' },
    'barber':  { heading: 'Manajemen Barber',  sub: 'Kelola foto profil barber' },
    'booking': { heading: 'Kelola Booking',     sub: 'Terima atau tolak permintaan booking pelanggan' },
    'status':  { heading: 'Status Operasional', sub: 'Kontrol apakah barbershop sedang buka atau tutup' },
    'konten':  { heading: 'Manajemen Konten',   sub: 'Upload dan kelola foto beranda, banner, dan QRIS' },
};

function switchTab(name, navEl) {
    // Deactivate all tab buttons & panels
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));

    // Activate selected
    document.getElementById('tab-' + name)?.classList.add('active');
    document.getElementById('panel-' + name)?.classList.add('active');
    document.getElementById('nav-' + name)?.classList.add('active');

    // Update heading
    const meta = tabMeta[name];
    if (meta) {
        document.getElementById('page-heading').textContent = meta.heading;
        document.getElementById('page-sub').textContent     = meta.sub;
    }

    // Update URL parameter so that reload doesn't reset the tab
    const url = new URL(window.location);
    url.searchParams.set('tab', name);
    window.history.replaceState(null, '', url.toString());

    closeSidebar();
}

// ═══════════════════════════════════════════════
//  SIDEBAR (Mobile)
// ═══════════════════════════════════════════════
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('visible');
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('visible');
}

// ═══════════════════════════════════════════════
//  FLASH MESSAGE AUTO-DISMISS
// ═══════════════════════════════════════════════
const flash = document.getElementById('flash-msg');
if (flash) {
    setTimeout(() => {
        flash.style.transition = 'opacity 0.5s ease';
        flash.style.opacity = '0';
        setTimeout(() => flash.remove(), 500);
    }, 5000);
}

// ═══════════════════════════════════════════════
//  EDIT SERVICE MODAL
// ═══════════════════════════════════════════════
function openEditModal(id, name, price, imgSrc) {
    document.getElementById('modal-svc-id').value           = id;
    document.getElementById('modal-svc-name-hidden').value  = name;
    document.getElementById('modal-svc-name-display').textContent = name;
    document.getElementById('modal-svc-price').value        = price;
    updatePricePreview(price);

    // Reset file input & preview
    document.getElementById('photo-input').value = '';
    document.getElementById('photo-filename').textContent = '';

    const previewImg   = document.getElementById('img-preview');
    const placeholder  = document.getElementById('img-placeholder-icon');
    const placeholderT = document.getElementById('img-placeholder-text');

    previewImg.src = imgSrc;
    previewImg.style.display = 'block';
    placeholder.style.display  = 'none';
    placeholderT.style.display = 'none';

    document.getElementById('editServiceModal').classList.add('visible');
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    document.getElementById('editServiceModal').classList.remove('visible');
    document.body.style.overflow = '';
}

// Close on overlay click
document.getElementById('editServiceModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEditModal();
});

function updatePricePreview(val) {
    const num = parseInt(val) || 0;
    document.getElementById('price-preview').textContent =
        num > 0 ? 'Rp ' + num.toLocaleString('id-ID') : '–';
}

function previewPhoto(input) {
    const file = input.files[0];
    if (!file) return;

    const maxSizeMB = 5;
    if (file.size > maxSizeMB * 1024 * 1024) {
        alert('Ukuran file terlalu besar. Maksimum ' + maxSizeMB + 'MB.');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const previewImg   = document.getElementById('img-preview');
        const placeholder  = document.getElementById('img-placeholder-icon');
        const placeholderT = document.getElementById('img-placeholder-text');

        previewImg.src = e.target.result;
        previewImg.style.display = 'block';
        placeholder.style.display  = 'none';
        placeholderT.style.display = 'none';
    };
    reader.readAsDataURL(file);

    document.getElementById('photo-filename').textContent = '✅ ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
}

function validateServiceForm() {
    const price = parseInt(document.getElementById('modal-svc-price').value);
    if (!price || price < 0) {
        alert('Harga harus diisi dengan nilai yang valid.');
        return false;
    }
    return true;
}

// ═══════════════════════════════════════════════
//  EDIT BARBER MODAL
// ═══════════════════════════════════════════════
function openBarberModal(id, name, imgSrc, status, spesialisasi) {
    document.getElementById('modal-barber-id').value = id;
    document.getElementById('modal-barber-name-display').value = name;
    document.getElementById('modal-barber-action').value = 'edit';
    document.getElementById('modal-title-barber').textContent = 'Edit Barber';
    document.getElementById('modal-barber-spesialisasi').value = spesialisasi || '';
    document.getElementById('modal-barber-status').value = status || 'aktif';

    document.getElementById('barber-photo-input').value = '';
    document.getElementById('barber-filename-display').textContent = 'Format: JPG, PNG, WEBP. Maks: 5MB.';
    document.getElementById('btn-save-barber').disabled = false;

    document.getElementById('modal-barber-img').src = imgSrc;

    document.getElementById('editBarberModal').classList.add('visible');
    document.body.style.overflow = 'hidden';
}

function openAddBarberModal() {
    document.getElementById('modal-barber-id').value = '0';
    document.getElementById('modal-barber-name-display').value = '';
    document.getElementById('modal-barber-spesialisasi').value = '';
    document.getElementById('modal-barber-status').value = 'aktif';
    document.getElementById('modal-barber-action').value = 'add';
    document.getElementById('modal-title-barber').textContent = 'Tambah Barber';

    document.getElementById('barber-photo-input').value = '';
    document.getElementById('barber-filename-display').textContent = 'Format: JPG, PNG, WEBP. Maks: 5MB.';
    document.getElementById('btn-save-barber').disabled = false;

    document.getElementById('modal-barber-img').src = 'https://placehold.co/400x500/1a1a26/C9A96E?text=Preview';

    document.getElementById('editBarberModal').classList.add('visible');
    document.body.style.overflow = 'hidden';
}

function closeBarberModal() {
    document.getElementById('editBarberModal').classList.remove('visible');
    document.body.style.overflow = '';
}

//  GAYA RAMBUT MODAL
// ═══════════════════════════════════════════════
function openGayaModal(id, name, desc, imgSrc) {
    document.getElementById('modal-gaya-id').value = id;
    document.getElementById('modal-gaya-name').value = name;
    document.getElementById('modal-gaya-desc').value = desc;
    document.getElementById('modal-gaya-action').value = 'edit';
    document.getElementById('modal-title-gaya').textContent = 'Edit Gaya Rambut';
    
    document.getElementById('gaya-photo-input').value = '';
    document.getElementById('gaya-filename-display').textContent = 'Format: JPG, PNG, WEBP. Maks: 5MB.';

    document.getElementById('modal-gaya-img').src = imgSrc;
    
    document.getElementById('editGayaModal').classList.add('visible');
    document.body.style.overflow = 'hidden';
}

function openAddGayaModal() {
    document.getElementById('modal-gaya-id').value = '0';
    document.getElementById('modal-gaya-name').value = '';
    document.getElementById('modal-gaya-desc').value = '';
    document.getElementById('modal-gaya-action').value = 'add';
    document.getElementById('modal-title-gaya').textContent = 'Tambah Gaya Rambut';
    
    document.getElementById('gaya-photo-input').value = '';
    document.getElementById('gaya-filename-display').textContent = 'Format: JPG, PNG, WEBP. Maks: 5MB.';

    document.getElementById('modal-gaya-img').src = 'https://placehold.co/400x400/1a1a26/C9A96E?text=Preview';
    
    document.getElementById('editGayaModal').classList.add('visible');
    document.body.style.overflow = 'hidden';
}

function closeGayaModal() {
    document.getElementById('editGayaModal').classList.remove('visible');
    document.body.style.overflow = '';
}

document.getElementById('editBarberModal').addEventListener('click', function(e) {
    if (e.target === this) closeBarberModal();
});

function previewBarberPhoto(input) {
    const file = input.files[0];
    if (!file) return;

    const maxSizeMB = 5;
    if (file.size > maxSizeMB * 1024 * 1024) {
        alert('Ukuran file terlalu besar. Maksimum ' + maxSizeMB + 'MB.');
        input.value = '';
        document.getElementById('btn-save-barber').disabled = true;
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('modal-barber-img').src = e.target.result;
    };
    reader.readAsDataURL(file);

    document.getElementById('barber-filename-display').textContent = '✅ ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    document.getElementById('btn-save-barber').disabled = false;
}

// ═══════════════════════════════════════════════
//  BOOKING REJECT PANEL
// ═══════════════════════════════════════════════
function showRejectPanel(id) {
    const panel = document.getElementById('reject-panel-' + id);
    panel.classList.add('visible');
    panel.querySelector('textarea').focus();
}

function hideRejectPanel(id) {
    const panel = document.getElementById('reject-panel-' + id);
    panel.classList.remove('visible');
    panel.querySelector('textarea').value = '';
}

function confirmAccept(form) {
    return confirm('Terima booking ini dan kirim notifikasi WhatsApp ke pelanggan?');
}

// ═══════════════════════════════════════════════
//  SHOP STATUS TOGGLE CONFIRM
// ═══════════════════════════════════════════════
function confirmToggle() {
    const isOpen = document.getElementById('shop-toggle').checked;
    const action = isOpen ? 'MEMBUKA' : 'MENUTUP';
    return confirm('Apakah Anda yakin ingin ' + action + ' toko?\n\nPerubahan ini akan langsung terlihat oleh pelanggan.');
}

// Auto-open tab from URL param
const urlParams = new URLSearchParams(window.location.search);
const tabParam = urlParams.get('tab');
if (tabParam && tabMeta[tabParam]) {
    switchTab(tabParam, null);
}

// ═══════════════════════════════════════════════
//  CONTENT MANAGER – Preview Uploads
// ═══════════════════════════════════════════════
function previewUpload(input, previewId, statusId) {
    const file = input.files[0];
    if (!file) return;

    const maxSizeMB = 5;
    if (file.size > maxSizeMB * 1024 * 1024) {
        alert('Ukuran file terlalu besar. Maksimum ' + maxSizeMB + 'MB.');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const img = document.getElementById(previewId);
        img.src = e.target.result;
        img.style.opacity = '1';

        const status = document.getElementById(statusId);
        if (status) {
            status.className = 'status-ok';
            status.innerHTML = '<i class="bi bi-arrow-repeat"></i> Akan diupdate';
            status.style.color = 'var(--accent-orange)';
        }
    };
    reader.readAsDataURL(file);
}

function previewQris(input) {
    const file = input.files[0];
    if (!file) return;

    const maxSizeMB = 5;
    if (file.size > maxSizeMB * 1024 * 1024) {
        alert('Ukuran gambar QRIS terlalu besar. Maksimum ' + maxSizeMB + 'MB.');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        // Update preview di card kiri
        const previewMain = document.getElementById('preview-qris');
        if (previewMain) {
            previewMain.src = e.target.result;
        } else {
            // Jika belum ada img tag (placeholder), buat
            const frame = document.querySelector('.qris-img-frame');
            frame.innerHTML = '<img src="' + e.target.result + '" alt="QRIS" id="preview-qris">';
        }

        // Update preview di form kanan
        const previewForm = document.getElementById('preview-qris-form');
        if (previewForm) {
            previewForm.src = e.target.result;
            previewForm.style.display = 'block';
        } else {
            const wrap = document.querySelector('.img-preview-wrap');
            if (wrap) {
                const existingIcon = document.getElementById('qris-placeholder-icon');
                const existingText = document.getElementById('qris-placeholder-text');
                if (existingIcon) existingIcon.style.display = 'none';
                if (existingText) existingText.style.display = 'none';
                const newImg = document.createElement('img');
                newImg.src = e.target.result;
                newImg.alt = 'QRIS';
                newImg.id = 'preview-qris-form';
                wrap.insertBefore(newImg, wrap.firstChild);
            }
        }
    };
    reader.readAsDataURL(file);

    document.getElementById('qris-filename').textContent = '✅ ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
}

function updateMerchantDisplay() {
    const bank = document.getElementById('input-qris-bank').value;
    const account = document.getElementById('input-qris-account').value;
    document.getElementById('display-merchant-bank').textContent = bank + ' – ' + account;
}
</script>

<script>
    // Auto-refresh halaman setiap 15 detik untuk update booking real-time
    // Hanya jika tidak ada panel penolakan atau modal yang sedang terbuka
    setInterval(function() {
        let isEditing = false;
        document.querySelectorAll('.reject-reason-panel').forEach(panel => {
            if(panel.classList.contains('active') || panel.style.display === 'block') {
                isEditing = true;
            }
        });
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            if(modal.classList.contains('visible')) {
                isEditing = true;
            }
        });
        
        if(!isEditing) {
            window.location.reload();
        }
    }, 15000);
</script>

</body>
</html>
