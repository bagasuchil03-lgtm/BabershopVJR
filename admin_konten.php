<?php
// =============================================================
// admin_konten.php  –  Halaman Utama Manajemen Konten
// Fitur: List semua konten + Form tambah konten (multi-foto)
// =============================================================
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$adminName = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Admin';

// Buat folder upload jika belum ada
$upload_dir = __DIR__ . '/uploads/konten/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Pastikan tabel ada sebelum query
$tabel_ada = $conn->query("SHOW TABLES LIKE 'konten'")->num_rows > 0;

// Hitung statistik
$total_konten  = 0;
$total_aktif   = 0;
$total_nonaktif = 0;
$total_foto    = 0;
$konten_list   = [];

if ($tabel_ada) {
    $total_aktif    = (int) $conn->query("SELECT COUNT(*) AS c FROM konten WHERE status='aktif'")->fetch_assoc()['c'];
    $total_nonaktif = (int) $conn->query("SELECT COUNT(*) AS c FROM konten WHERE status='nonaktif'")->fetch_assoc()['c'];
    $total_konten   = $total_aktif + $total_nonaktif;

    $tabel_foto_ada = $conn->query("SHOW TABLES LIKE 'konten_foto'")->num_rows > 0;
    if ($tabel_foto_ada) {
        $total_foto = (int) $conn->query("SELECT COUNT(*) AS c FROM konten_foto")->fetch_assoc()['c'];

        // Ambil semua konten beserta jumlah foto & thumbnail pertama
        $res = $conn->query("
            SELECT
                k.*,
                COUNT(kf.id)               AS jumlah_foto,
                MIN(kf.nama_file)          AS foto_pertama
            FROM konten k
            LEFT JOIN konten_foto kf ON kf.id_konten = k.id
            GROUP BY k.id
            ORDER BY k.created_at DESC
        ");
        while ($row = $res->fetch_assoc()) {
            $konten_list[] = $row;
        }
    }
}

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Konten – Vijer Admin</title>
    <meta name="description" content="Kelola konten dengan upload banyak foto (4-6 foto) di Vijer Barbershop Admin.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* ── Design Tokens ── */
        :root {
            --bg-base:     #07070e;
            --bg-surface:  #10101a;
            --bg-card:     #18182a;
            --bg-hover:    #20203a;
            --gold:        #C9A96E;
            --gold-light:  #e4c98a;
            --gold-glow:   rgba(201,169,110,0.18);
            --green:       #00e5a0;
            --red:         #ff4d6d;
            --blue:        #4d9fff;
            --purple:      #a855f7;
            --text-1:      #f0f0f8;
            --text-2:      #9898b8;
            --border:      rgba(201,169,110,0.14);
            --radius-lg:   16px;
            --radius-md:   10px;
            --radius-sm:   7px;
            --shadow:      0 8px 32px rgba(0,0,0,0.5);
            --t:           all 0.25s ease;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-base);
            color: var(--text-1);
            min-height: 100vh;
        }

        /* ── Topbar ── */
        .topbar {
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 200;
            backdrop-filter: blur(10px);
        }
        .topbar-brand {
            display: flex; align-items: center; gap: 10px;
            font-size: 18px; font-weight: 700; color: var(--gold);
            text-decoration: none;
        }
        .topbar-nav { display: flex; align-items: center; gap: 10px; }

        /* ── Page wrapper ── */
        .page-wrap { max-width: 1280px; margin: 0 auto; padding: 32px 24px; }

        /* ── Page header ── */
        .page-header { margin-bottom: 32px; }
        .page-header h1 { font-size: 28px; font-weight: 800; color: var(--gold); }
        .page-header p  { color: var(--text-2); font-size: 14px; margin-top: 4px; }

        /* ── Flash messages ── */
        .flash {
            padding: 14px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            display: flex; align-items: center; gap: 10px;
            font-size: 14px; font-weight: 500;
            animation: slideDown .3s ease;
        }
        .flash-success { background: rgba(0,229,160,.1); border: 1px solid rgba(0,229,160,.3); color: var(--green); }
        .flash-error   { background: rgba(255,77,109,.1); border: 1px solid rgba(255,77,109,.3); color: var(--red); }
        @keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

        /* ── Stats grid ── */
        .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 32px; }
        .stat-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 22px 24px;
            display: flex; align-items: center; gap: 16px;
            transition: var(--t);
        }
        .stat-card:hover { border-color: var(--gold); box-shadow: 0 0 24px var(--gold-glow); }
        .stat-icon {
            width: 52px; height: 52px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .ic-gold   { background: rgba(201,169,110,.15); color: var(--gold); }
        .ic-green  { background: rgba(0,229,160,.15);   color: var(--green); }
        .ic-red    { background: rgba(255,77,109,.15);  color: var(--red); }
        .ic-purple { background: rgba(168,85,247,.15);  color: var(--purple); }
        .stat-label { font-size: 11px; color: var(--text-2); text-transform: uppercase; letter-spacing: .6px; }
        .stat-value { font-size: 30px; font-weight: 800; color: var(--text-1); line-height: 1.1; }

        /* ── Section card ── */
        .section-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 28px 32px;
            margin-bottom: 32px;
        }
        .section-title {
            font-size: 15px; font-weight: 700; color: var(--gold);
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 22px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }

        /* ── Upload Drop Zone ── */
        .drop-zone {
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 40px 24px;
            text-align: center;
            cursor: pointer;
            transition: var(--t);
            position: relative;
            background: rgba(201,169,110,.03);
        }
        .drop-zone:hover, .drop-zone.drag-over {
            border-color: var(--gold);
            background: var(--gold-glow);
        }
        .drop-zone input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .drop-zone-icon { font-size: 48px; color: var(--text-2); margin-bottom: 12px; display: block; }
        .drop-zone-text { font-size: 15px; font-weight: 600; color: var(--text-1); margin-bottom: 6px; }
        .drop-zone-hint { font-size: 13px; color: var(--text-2); }
        .drop-zone-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(201,169,110,.12);
            border: 1px solid var(--border);
            color: var(--gold);
            border-radius: 20px;
            font-size: 12px; font-weight: 600;
            padding: 4px 12px;
            margin-top: 10px;
        }

        /* ── Preview Grid ── */
        #preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 14px;
            margin-top: 20px;
        }
        .preview-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            overflow: hidden;
            position: relative;
            animation: popIn .25s ease;
        }
        @keyframes popIn { from{opacity:0;transform:scale(.85)} to{opacity:1;transform:scale(1)} }
        .preview-card img {
            width: 100%; height: 110px; object-fit: cover; display: block;
        }
        .preview-info {
            padding: 7px 10px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .preview-name {
            font-size: 11px; color: var(--text-2);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            max-width: 90px;
        }
        .preview-size { font-size: 10px; color: var(--text-2); }
        .preview-remove {
            position: absolute; top: 6px; right: 6px;
            width: 26px; height: 26px;
            background: rgba(255,77,109,.85);
            color: #fff; border: none; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 14px;
            transition: var(--t);
        }
        .preview-remove:hover { background: var(--red); transform: scale(1.1); }
        .preview-order {
            position: absolute; top: 6px; left: 6px;
            width: 22px; height: 22px;
            background: rgba(201,169,110,.9);
            color: #000; border-radius: 50%;
            font-size: 11px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }

        /* ── Validation bar ── */
        .val-bar {
            display: flex; flex-wrap: wrap; gap: 8px;
            margin-top: 14px;
        }
        .val-item {
            display: flex; align-items: center; gap: 5px;
            font-size: 12px; color: var(--text-2);
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px; padding: 4px 12px;
            transition: var(--t);
        }
        .val-item.ok  { color: var(--green); border-color: rgba(0,229,160,.3); background: rgba(0,229,160,.08); }
        .val-item.err { color: var(--red);   border-color: rgba(255,77,109,.3); background: rgba(255,77,109,.08); }

        /* ── Form inputs ── */
        .form-label { font-size: 13px; color: var(--text-2); font-weight: 500; margin-bottom: 6px; display: block; }
        .form-control, .form-select {
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-1);
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            font-size: 14px;
            font-family: 'Outfit', sans-serif;
            width: 100%;
            transition: var(--t);
        }
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px var(--gold-glow);
        }
        .form-control::placeholder { color: var(--text-2); }
        textarea.form-control { resize: vertical; min-height: 90px; }
        .form-select option { background: var(--bg-card); }

        /* ── Buttons ── */
        .btn-gold {
            background: linear-gradient(135deg, var(--gold), #a07840);
            color: #000; font-weight: 700; border: none;
            border-radius: var(--radius-sm);
            padding: 11px 28px; font-size: 14px;
            cursor: pointer; transition: var(--t);
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-gold:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(201,169,110,.35); }
        .btn-gold:disabled { opacity: .5; cursor: not-allowed; transform: none; }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-2);
            border-radius: var(--radius-sm);
            padding: 8px 16px; font-size: 13px;
            cursor: pointer; transition: var(--t);
            display: inline-flex; align-items: center; gap: 6px;
            font-family: 'Outfit', sans-serif;
        }
        .btn-outline:hover { border-color: var(--gold); color: var(--gold); }
        .btn-red {
            background: transparent;
            border: 1px solid rgba(255,77,109,.4);
            color: var(--red);
            border-radius: var(--radius-sm);
            padding: 7px 14px; font-size: 13px;
            cursor: pointer; transition: var(--t);
            font-family: 'Outfit', sans-serif;
        }
        .btn-red:hover { background: rgba(255,77,109,.12); }

        /* ── Konten grid ── */
        .konten-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .konten-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: var(--t);
            display: flex; flex-direction: column;
        }
        .konten-card:hover { border-color: var(--gold); transform: translateY(-3px); box-shadow: var(--shadow); }

        /* Thumbnail strip */
        .thumb-strip {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            height: 160px;
            gap: 2px;
            background: #000;
        }
        .thumb-strip .ts-main {
            grid-column: 1; grid-row: 1 / 3;
            overflow: hidden;
        }
        .thumb-strip .ts-sub {
            grid-column: 2 / 4;
            overflow: hidden;
        }
        .thumb-strip img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform .4s ease;
        }
        .konten-card:hover .thumb-strip img { transform: scale(1.05); }
        .thumb-placeholder {
            background: var(--bg-card);
            display: flex; align-items: center; justify-content: center;
            color: var(--text-2); font-size: 28px;
        }
        .foto-count-badge {
            position: absolute;
            bottom: 8px; right: 8px;
            background: rgba(0,0,0,.7);
            color: var(--gold);
            border-radius: 20px;
            font-size: 11px; font-weight: 700;
            padding: 3px 10px;
            backdrop-filter: blur(4px);
        }

        .konten-body {
            padding: 16px 18px;
            flex: 1;
            display: flex; flex-direction: column;
        }
        .konten-judul {
            font-size: 15px; font-weight: 700;
            color: var(--text-1);
            margin-bottom: 6px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .konten-desc {
            font-size: 13px; color: var(--text-2);
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 12px;
            flex: 1;
        }
        .konten-meta { display: flex; align-items: center; justify-content: space-between; }
        .status-pill {
            padding: 3px 12px; border-radius: 20px;
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
        }
        .status-aktif    { background: rgba(0,229,160,.15); color: var(--green); border: 1px solid rgba(0,229,160,.3); }
        .status-nonaktif { background: rgba(122,122,146,.15); color: var(--text-2); border: 1px solid rgba(122,122,146,.3); }
        .konten-date { font-size: 11px; color: var(--text-2); }

        .konten-actions {
            padding: 12px 18px;
            display: flex; gap: 8px;
            border-top: 1px solid var(--border);
            background: var(--bg-card);
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center; padding: 72px 20px;
            color: var(--text-2);
        }
        .empty-state i { font-size: 64px; opacity: .2; display: block; margin-bottom: 16px; }
        .empty-state p { font-size: 15px; }

        /* ── No DB warning ── */
        .db-warning {
            background: rgba(255,77,109,.08);
            border: 1px solid rgba(255,77,109,.25);
            border-radius: var(--radius-md);
            padding: 20px 24px;
            margin-bottom: 28px;
        }

        /* ── Modal ── */
        .modal-backdrop-custom {
            position: fixed; inset: 0; background: rgba(0,0,0,.7);
            z-index: 1000; display: none;
            align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal-backdrop-custom.show { display: flex; }
        .modal-box {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 32px;
            width: 100%; max-width: 560px;
            position: relative;
            animation: popIn .2s ease;
        }
        .modal-title {
            font-size: 18px; font-weight: 700; color: var(--gold);
            margin-bottom: 20px;
        }
        .modal-close {
            position: absolute; top: 16px; right: 16px;
            background: var(--bg-card); border: 1px solid var(--border);
            color: var(--text-2); width: 32px; height: 32px;
            border-radius: 50%; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; transition: var(--t);
        }
        .modal-close:hover { border-color: var(--red); color: var(--red); }

        /* ── Progress bar upload ── */
        .upload-progress {
            display: none;
            margin-top: 14px;
        }
        .progress-bar-wrap {
            background: var(--bg-card);
            border-radius: 10px; height: 6px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--gold), var(--green));
            border-radius: 10px;
            transition: width .3s ease;
            width: 0%;
        }

        @media (max-width: 768px) {
            .stat-grid { grid-template-columns: 1fr 1fr; }
            .topbar { padding: 0 16px; }
            .page-wrap { padding: 20px 16px; }
            .section-card { padding: 20px 18px; }
        }
        @media (max-width: 480px) {
            .stat-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
    <a class="topbar-brand" href="admin.php">
        <i class="bi bi-scissors"></i>
        <span>Vijer Admin</span>
    </a>
    <div class="topbar-nav">
        <span style="color:var(--text-2); font-size:13px;">Halo, <?= htmlspecialchars($adminName) ?></span>
        <a href="admin.php" class="btn-outline">
            <i class="bi bi-arrow-left"></i> Dashboard
        </a>
        <button class="btn-gold" onclick="openModal()">
            <i class="bi bi-plus-lg"></i> Tambah Konten
        </button>
    </div>
</div>

<div class="page-wrap">

    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="bi bi-collection me-2"></i>Manajemen Konten</h1>
        <p>Kelola konten artikel / promo dengan dukungan upload banyak foto (4–6 foto per konten).</p>
    </div>

    <!-- Warning: tabel belum ada -->
    <?php if (!$tabel_ada): ?>
    <div class="db-warning">
        <h6 style="color:var(--red); font-weight:700; margin-bottom:6px;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Tabel Database Belum Dibuat
        </h6>
        <p style="font-size:13px; color:var(--text-2); margin-bottom:12px;">
            Tabel <code>konten</code> dan <code>konten_foto</code> belum ada di database.
            Jalankan file <code>konten_foto_schema.sql</code> di phpMyAdmin terlebih dahulu.
        </p>
        <a href="konten_foto_schema.sql" class="btn-outline" download>
            <i class="bi bi-download"></i> Download Schema SQL
        </a>
    </div>
    <?php endif; ?>

    <!-- Flash Messages -->
    <?php if ($flashSuccess): ?>
        <div class="flash flash-success" id="flash-msg">
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($flashSuccess) ?>
        </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="flash flash-error" id="flash-msg">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= htmlspecialchars($flashError) ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon ic-gold"><i class="bi bi-collection"></i></div>
            <div>
                <div class="stat-label">Total Konten</div>
                <div class="stat-value"><?= $total_konten ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon ic-green"><i class="bi bi-eye-fill"></i></div>
            <div>
                <div class="stat-label">Aktif</div>
                <div class="stat-value"><?= $total_aktif ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon ic-red"><i class="bi bi-eye-slash-fill"></i></div>
            <div>
                <div class="stat-label">Non-Aktif</div>
                <div class="stat-value"><?= $total_nonaktif ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon ic-purple"><i class="bi bi-images"></i></div>
            <div>
                <div class="stat-label">Total Foto</div>
                <div class="stat-value"><?= $total_foto ?></div>
            </div>
        </div>
    </div>

    <!-- Konten List -->
    <div class="section-card">
        <div class="section-title">
            <i class="bi bi-grid-3x3-gap-fill"></i>
            Daftar Konten
            <span style="margin-left:auto; font-size:13px; color:var(--text-2); font-weight:400;"><?= $total_konten ?> konten ditemukan</span>
        </div>

        <?php if (empty($konten_list)): ?>
        <div class="empty-state">
            <i class="bi bi-collection"></i>
            <p>Belum ada konten. Klik <strong style="color:var(--gold);">Tambah Konten</strong> untuk memulai.</p>
        </div>
        <?php else: ?>
        <div class="konten-grid">
            <?php foreach ($konten_list as $k): ?>
            <?php
                $foto_url = $k['foto_pertama'] ? 'uploads/konten/' . htmlspecialchars($k['foto_pertama']) : null;
                $jml_foto = (int) $k['jumlah_foto'];
                $tgl = date('d M Y', strtotime($k['created_at']));
            ?>
            <div class="konten-card">
                <!-- Thumbnail -->
                <div style="position:relative;">
                    <div class="thumb-strip" style="<?= $jml_foto < 2 ? 'grid-template-columns:1fr' : '' ?>">
                        <?php if ($foto_url): ?>
                            <div class="ts-main">
                                <img src="<?= $foto_url ?>" alt="Thumbnail" loading="lazy">
                            </div>
                        <?php else: ?>
                            <div class="ts-main thumb-placeholder"><i class="bi bi-image"></i></div>
                        <?php endif; ?>
                    </div>
                    <span class="foto-count-badge"><i class="bi bi-images me-1"></i><?= $jml_foto ?> Foto</span>
                </div>

                <!-- Body -->
                <div class="konten-body">
                    <div class="konten-judul" title="<?= htmlspecialchars($k['judul']) ?>">
                        <?= htmlspecialchars($k['judul']) ?>
                    </div>
                    <div class="konten-desc">
                        <?= $k['deskripsi'] ? htmlspecialchars($k['deskripsi']) : '<em style="color:var(--text-2)">Tidak ada deskripsi</em>' ?>
                    </div>
                    <div class="konten-meta">
                        <span class="status-pill status-<?= $k['status'] ?>">
                            <?= $k['status'] === 'aktif' ? 'Aktif' : 'Non-Aktif' ?>
                        </span>
                        <span class="konten-date"><i class="bi bi-calendar3 me-1"></i><?= $tgl ?></span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="konten-actions">
                    <a href="admin_konten_detail.php?id=<?= $k['id'] ?>" class="btn-outline" style="flex:1; justify-content:center;">
                        <i class="bi bi-eye"></i> Detail
                    </a>
                    <!-- Toggle status -->
                    <form method="POST" action="admin_konten_action.php" style="margin:0;">
                        <input type="hidden" name="action"    value="toggle_status">
                        <input type="hidden" name="id_konten" value="<?= $k['id'] ?>">
                        <button type="submit" class="btn-outline" title="Toggle Status">
                            <i class="bi bi-toggle-<?= $k['status'] === 'aktif' ? 'on text-success' : 'off' ?>"></i>
                        </button>
                    </form>
                    <!-- Hapus konten -->
                    <form method="POST" action="admin_konten_action.php" style="margin:0;"
                          onsubmit="return confirm('Hapus konten ini beserta semua fotonya? Tindakan ini tidak bisa dibatalkan!')">
                        <input type="hidden" name="action"    value="hapus_konten">
                        <input type="hidden" name="id_konten" value="<?= $k['id'] ?>">
                        <button type="submit" class="btn-red" title="Hapus Konten">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /page-wrap -->


<!-- ═══════════════════════════════════════════════
     MODAL: Tambah Konten Baru
═══════════════════════════════════════════════ -->
<div class="modal-backdrop-custom" id="modal-tambah" onclick="handleBackdropClick(event)">
    <div class="modal-box" id="modal-box">
        <button class="modal-close" onclick="closeModal()" type="button">&times;</button>
        <div class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Konten Baru</div>

        <form id="form-tambah" action="admin_konten_action.php" method="POST" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="action" value="tambah_konten">

            <div style="display:flex; flex-direction:column; gap:16px;">

                <!-- Judul -->
                <div>
                    <label class="form-label" for="input-judul">Judul Konten <span style="color:var(--red)">*</span></label>
                    <input type="text" id="input-judul" name="judul" class="form-control"
                           placeholder="Contoh: Promo Ramadan 2025" maxlength="255" required>
                </div>

                <!-- Deskripsi -->
                <div>
                    <label class="form-label" for="input-deskripsi">Deskripsi</label>
                    <textarea id="input-deskripsi" name="deskripsi" class="form-control"
                              placeholder="Deskripsi singkat konten (opsional)..."></textarea>
                </div>

                <!-- Status -->
                <div>
                    <label class="form-label" for="input-status">Status</label>
                    <select id="input-status" name="status" class="form-select">
                        <option value="aktif">Aktif (Tampil)</option>
                        <option value="nonaktif">Non-Aktif (Tersembunyi)</option>
                    </select>
                </div>

                <!-- Upload Foto -->
                <div>
                    <label class="form-label">
                        Foto Konten <span style="color:var(--red)">*</span>
                        <span style="color:var(--text-2); font-weight:400;">(Pilih 4–6 foto)</span>
                    </label>

                    <div class="drop-zone" id="drop-zone">
                        <input type="file" name="fotos[]" id="input-fotos"
                               multiple accept=".jpg,.jpeg,.png"
                               onchange="handleFileSelect(this)">
                        <i class="bi bi-cloud-upload drop-zone-icon"></i>
                        <div class="drop-zone-text">Klik atau seret foto ke sini</div>
                        <div class="drop-zone-hint">Format: JPG / PNG &nbsp;·&nbsp; Maks. 2 MB per foto</div>
                        <div class="drop-zone-badge">
                            <i class="bi bi-images"></i> 4 – 6 foto
                        </div>
                    </div>

                    <!-- Validation Status Bar -->
                    <div class="val-bar" id="val-bar" style="display:none;">
                        <div class="val-item" id="val-jumlah"><i class="bi bi-hash"></i> <span id="val-jumlah-text">0 foto dipilih</span></div>
                        <div class="val-item" id="val-format"><i class="bi bi-file-earmark-image"></i> Format JPG/PNG</div>
                        <div class="val-item" id="val-ukuran"><i class="bi bi-hdd"></i> Ukuran maks 2MB</div>
                    </div>

                    <!-- Preview Grid -->
                    <div id="preview-grid"></div>
                </div>

                <!-- Progress bar -->
                <div class="upload-progress" id="upload-progress">
                    <div style="font-size:12px; color:var(--text-2); margin-bottom:6px;">Mengupload foto…</div>
                    <div class="progress-bar-wrap">
                        <div class="progress-bar-fill" id="progress-fill"></div>
                    </div>
                </div>

                <!-- Error message area -->
                <div id="js-error" style="display:none;" class="flash flash-error" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span id="js-error-text"></span>
                </div>

                <!-- Actions -->
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:6px;">
                    <button type="button" class="btn-outline" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn-gold" id="btn-submit" disabled>
                        <i class="bi bi-cloud-upload"></i> Simpan Konten
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ════════════════════════════════════════════════════════
//  KONFIGURASI VALIDASI (harus sesuai PHP)
// ════════════════════════════════════════════════════════
const MIN_FOTO    = 4;
const MAX_FOTO    = 6;
const MAX_SIZE_MB = 2;
const ALLOWED_EXT = ['jpg', 'jpeg', 'png'];

// ════════════════════════════════════════════════════════
//  STATE
// ════════════════════════════════════════════════════════
let selectedFiles = [];   // Array of File objects

// ════════════════════════════════════════════════════════
//  MODAL
// ════════════════════════════════════════════════════════
function openModal() {
    document.getElementById('modal-tambah').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('modal-tambah').classList.remove('show');
    document.body.style.overflow = '';
}
function handleBackdropClick(e) {
    if (e.target === document.getElementById('modal-tambah')) closeModal();
}

// ════════════════════════════════════════════════════════
//  DRAG & DROP
// ════════════════════════════════════════════════════════
const dropZone = document.getElementById('drop-zone');
dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    addFiles(Array.from(e.dataTransfer.files));
});

// ════════════════════════════════════════════════════════
//  FILE SELECT (input[file])
// ════════════════════════════════════════════════════════
function handleFileSelect(input) {
    addFiles(Array.from(input.files));
    // Reset input value agar bisa pilih file yang sama lagi
    input.value = '';
}

function addFiles(newFiles) {
    newFiles.forEach(file => {
        // Cek duplikasi berdasarkan nama + ukuran
        const isDupe = selectedFiles.some(f => f.name === file.name && f.size === file.size);
        if (!isDupe) {
            selectedFiles.push(file);
        }
    });
    renderPreviews();
    validate();
}

// ════════════════════════════════════════════════════════
//  HAPUS FILE DARI PREVIEW
// ════════════════════════════════════════════════════════
function removeFile(index) {
    selectedFiles.splice(index, 1);
    renderPreviews();
    validate();
}

// ════════════════════════════════════════════════════════
//  RENDER PREVIEW CARDS
// ════════════════════════════════════════════════════════
function renderPreviews() {
    const grid = document.getElementById('preview-grid');
    grid.innerHTML = '';

    selectedFiles.forEach((file, i) => {
        const card = document.createElement('div');
        card.className = 'preview-card';

        const img = document.createElement('img');
        img.alt = file.name;
        const reader = new FileReader();
        reader.onload = e => img.src = e.target.result;
        reader.readAsDataURL(file);

        const order = document.createElement('span');
        order.className = 'preview-order';
        order.textContent = i + 1;

        const removeBtn = document.createElement('button');
        removeBtn.className = 'preview-remove';
        removeBtn.type = 'button';
        removeBtn.innerHTML = '&times;';
        removeBtn.title = 'Hapus foto ini';
        removeBtn.onclick = () => removeFile(i);

        const info = document.createElement('div');
        info.className = 'preview-info';
        info.innerHTML = `
            <span class="preview-name" title="${file.name}">${file.name}</span>
            <span class="preview-size">${formatSize(file.size)}</span>
        `;

        card.append(img, order, removeBtn, info);
        grid.appendChild(card);
    });
}

// ════════════════════════════════════════════════════════
//  VALIDASI
// ════════════════════════════════════════════════════════
function validate() {
    const valBar    = document.getElementById('val-bar');
    const btnSubmit = document.getElementById('btn-submit');
    const errBox    = document.getElementById('js-error');
    const errText   = document.getElementById('js-error-text');

    const n = selectedFiles.length;
    valBar.style.display = n > 0 ? 'flex' : 'none';

    let ok = true;
    let errMsg = '';

    // — Jumlah —
    const valJumlah = document.getElementById('val-jumlah');
    const valJumlahText = document.getElementById('val-jumlah-text');
    valJumlahText.textContent = `${n} foto dipilih (min ${MIN_FOTO}, maks ${MAX_FOTO})`;
    if (n < MIN_FOTO || n > MAX_FOTO) {
        valJumlah.className = 'val-item err';
        ok = false;
        errMsg = n > MAX_FOTO
            ? `Terlalu banyak! Maksimal ${MAX_FOTO} foto.`
            : `Kurang! Minimal ${MIN_FOTO} foto harus dipilih.`;
    } else {
        valJumlah.className = 'val-item ok';
    }

    // — Format —
    const valFormat = document.getElementById('val-format');
    const invalidFormat = selectedFiles.filter(f => {
        const ext = f.name.split('.').pop().toLowerCase();
        return !ALLOWED_EXT.includes(ext);
    });
    if (invalidFormat.length > 0) {
        valFormat.className = 'val-item err';
        ok = false;
        errMsg = errMsg || `File "${invalidFormat[0].name}" bukan format JPG/PNG.`;
    } else {
        valFormat.className = 'val-item ok';
    }

    // — Ukuran —
    const valUkuran = document.getElementById('val-ukuran');
    const oversized = selectedFiles.filter(f => f.size > MAX_SIZE_MB * 1024 * 1024);
    if (oversized.length > 0) {
        valUkuran.className = 'val-item err';
        ok = false;
        errMsg = errMsg || `File "${oversized[0].name}" melebihi batas ${MAX_SIZE_MB}MB.`;
    } else {
        valUkuran.className = n > 0 ? 'val-item ok' : 'val-item';
    }

    // Tampilkan error atau sembunyikan
    if (!ok && errMsg) {
        errBox.style.display = 'flex';
        errText.textContent = errMsg;
    } else {
        errBox.style.display = 'none';
    }

    btnSubmit.disabled = !ok || n === 0;
    return ok;
}

// ════════════════════════════════════════════════════════
//  FORM SUBMIT  – inject files ke FormData
// ════════════════════════════════════════════════════════
document.getElementById('form-tambah').addEventListener('submit', function(e) {
    e.preventDefault();

    if (!validate()) return;

    const judul = document.getElementById('input-judul').value.trim();
    if (!judul) {
        showError('Judul konten tidak boleh kosong.');
        return;
    }

    // Tampilkan progress
    document.getElementById('upload-progress').style.display = 'block';
    simulateProgress();

    // Build FormData manual (agar selectedFiles array yang dipakai)
    const fd = new FormData(this);
    // Hapus field fotos[] lama dari input (mungkin kosong setelah reset)
    // Tambah ulang dari selectedFiles
    // FormData dari form sudah include semua field kecuali file (karena input value direset)
    // Kita tambahkan file secara manual:
    selectedFiles.forEach(file => fd.append('fotos[]', file));

    fetch('admin_konten_action.php', {
        method: 'POST',
        body: fd
    }).then(r => {
        if (r.redirected) {
            window.location.href = r.url;
        } else {
            window.location.reload();
        }
    }).catch(() => {
        // Fallback: submit form biasa
        this.submit();
    });
});

// ════════════════════════════════════════════════════════
//  HELPERS
// ════════════════════════════════════════════════════════
function formatSize(bytes) {
    if (bytes < 1024)       return bytes + ' B';
    if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function showError(msg) {
    const errBox  = document.getElementById('js-error');
    const errText = document.getElementById('js-error-text');
    errBox.style.display = 'flex';
    errText.textContent  = msg;
}

function simulateProgress() {
    const fill = document.getElementById('progress-fill');
    let w = 0;
    const iv = setInterval(() => {
        w = Math.min(w + Math.random() * 15, 90);
        fill.style.width = w + '%';
        if (w >= 90) clearInterval(iv);
    }, 200);
}

// ════════════════════════════════════════════════════════
//  AUTO-HIDE FLASH MESSAGES
// ════════════════════════════════════════════════════════
setTimeout(() => {
    document.querySelectorAll('.flash').forEach(el => {
        el.style.transition = 'opacity .4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    });
}, 5000);

// Buka modal otomatis jika ada error sebelumnya (UX: user tidak kehilangan konteks)
<?php if ($flashError): ?>
// openModal();  // Dinonaktifkan karena form direset saat redirect
<?php endif; ?>
</script>

</body>
</html>
