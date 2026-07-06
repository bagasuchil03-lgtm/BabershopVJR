<?php
// =============================================================
// admin_konten_detail.php  –  Detail Konten + Manajemen Foto
// =============================================================
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$adminName = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Admin';
$id_konten = (int) ($_GET['id'] ?? 0);

if ($id_konten <= 0) {
    header('Location: admin_konten.php');
    exit();
}

// Ambil data konten
$stmt = $conn->prepare("SELECT * FROM konten WHERE id = ?");
$stmt->bind_param('i', $id_konten);
$stmt->execute();
$konten = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$konten) {
    $_SESSION['flash_error'] = 'Konten tidak ditemukan.';
    header('Location: admin_konten.php');
    exit();
}

// Ambil semua foto milik konten ini
$stmt_foto = $conn->prepare("SELECT * FROM konten_foto WHERE id_konten = ? ORDER BY urutan ASC, id ASC");
$stmt_foto->bind_param('i', $id_konten);
$stmt_foto->execute();
$foto_result = $stmt_foto->get_result();
$fotos = [];
while ($f = $foto_result->fetch_assoc()) {
    $fotos[] = $f;
}
$stmt_foto->close();

$jumlah_foto  = count($fotos);
$sisa_slot    = 6 - $jumlah_foto;    // Slot foto yang masih tersedia
$bisa_hapus   = $jumlah_foto > 4;    // Bisa hapus jika > 4 foto
$bisa_tambah  = $jumlah_foto < 6;    // Bisa tambah jika < 6 foto

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($konten['judul']) ?> – Detail Konten | Vijer Admin</title>
    <meta name="description" content="Manajemen foto untuk konten: <?= htmlspecialchars($konten['judul']) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-base:     #07070e;
            --bg-surface:  #10101a;
            --bg-card:     #18182a;
            --gold:        #C9A96E;
            --gold-light:  #e4c98a;
            --gold-glow:   rgba(201,169,110,0.18);
            --green:       #00e5a0;
            --red:         #ff4d6d;
            --blue:        #4d9fff;
            --orange:      #ff9a3c;
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
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 200;
            backdrop-filter: blur(10px);
        }
        .topbar-brand {
            display: flex; align-items: center; gap: 10px;
            font-size: 18px; font-weight: 700; color: var(--gold);
            text-decoration: none;
        }
        .topbar-nav { display: flex; align-items: center; gap: 10px; }

        /* ── Layout ── */
        .page-wrap { max-width: 1280px; margin: 0 auto; padding: 32px 24px; }

        /* ── Breadcrumb ── */
        .breadcrumb-nav {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; color: var(--text-2);
            margin-bottom: 24px;
        }
        .breadcrumb-nav a { color: var(--text-2); text-decoration: none; transition: var(--t); }
        .breadcrumb-nav a:hover { color: var(--gold); }
        .breadcrumb-nav .sep { opacity: .4; }
        .breadcrumb-nav .current { color: var(--text-1); font-weight: 600; }

        /* ── Konten info card ── */
        .info-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            margin-bottom: 28px;
            display: flex; align-items: flex-start; gap: 24px;
            position: relative;
            overflow: hidden;
        }
        .info-card::before {
            content: '';
            position: absolute; top: 0; left: 0;
            width: 4px; height: 100%;
            background: linear-gradient(180deg, var(--gold), transparent);
        }
        .info-card-icon {
            width: 56px; height: 56px; border-radius: 14px;
            background: var(--gold-glow);
            color: var(--gold);
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; flex-shrink: 0;
        }
        .info-card-body { flex: 1; }
        .info-card-title { font-size: 20px; font-weight: 800; color: var(--text-1); margin-bottom: 4px; }
        .info-card-desc  { font-size: 13px; color: var(--text-2); line-height: 1.6; margin-bottom: 12px; }
        .info-card-meta  { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .meta-pill {
            display: flex; align-items: center; gap: 5px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 12px; color: var(--text-2);
            padding: 4px 12px;
        }
        .meta-pill.green { color: var(--green); border-color: rgba(0,229,160,.3); background: rgba(0,229,160,.08); }
        .meta-pill.gray  { color: var(--text-2); }

        /* ── Section card ── */
        .section-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            margin-bottom: 28px;
        }
        .section-title {
            font-size: 15px; font-weight: 700; color: var(--gold);
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }

        /* ── Foto Grid ── */
        .foto-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }
        .foto-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            overflow: hidden;
            transition: var(--t);
            position: relative;
        }
        .foto-card:hover { border-color: var(--gold); transform: translateY(-2px); box-shadow: var(--shadow); }
        .foto-card img {
            width: 100%; height: 150px; object-fit: cover;
            display: block;
            transition: transform .4s ease;
        }
        .foto-card:hover img { transform: scale(1.05); }
        .foto-card-body {
            padding: 10px 12px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 8px;
        }
        .foto-order {
            position: absolute; top: 8px; left: 8px;
            width: 26px; height: 26px;
            background: rgba(0,0,0,.7);
            color: var(--gold);
            border-radius: 50%;
            font-size: 12px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .foto-lightbox-btn {
            position: absolute; top: 8px; right: 8px;
            width: 30px; height: 30px;
            background: rgba(0,0,0,.6);
            color: #fff;
            border: none; border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 14px;
            opacity: 0; transition: var(--t);
        }
        .foto-card:hover .foto-lightbox-btn { opacity: 1; }

        .foto-name {
            font-size: 11px; color: var(--text-2);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            max-width: 120px;
        }

        /* ── Buttons ── */
        .btn-gold {
            background: linear-gradient(135deg, var(--gold), #a07840);
            color: #000; font-weight: 700; border: none;
            border-radius: var(--radius-sm);
            padding: 10px 22px; font-size: 14px;
            cursor: pointer; transition: var(--t);
            display: inline-flex; align-items: center; gap: 7px;
            font-family: 'Outfit', sans-serif;
        }
        .btn-gold:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(201,169,110,.35); }
        .btn-gold:disabled { opacity: .45; cursor: not-allowed; transform: none; }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-2); border-radius: var(--radius-sm);
            padding: 8px 16px; font-size: 13px;
            cursor: pointer; transition: var(--t);
            display: inline-flex; align-items: center; gap: 6px;
            font-family: 'Outfit', sans-serif; text-decoration: none;
        }
        .btn-outline:hover { border-color: var(--gold); color: var(--gold); }

        .btn-red {
            background: transparent;
            border: 1px solid rgba(255,77,109,.4);
            color: var(--red);
            border-radius: var(--radius-sm);
            padding: 7px 12px; font-size: 13px;
            cursor: pointer; transition: var(--t);
            font-family: 'Outfit', sans-serif;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-red:hover { background: rgba(255,77,109,.12); border-color: var(--red); }
        .btn-red:disabled { opacity: .4; cursor: not-allowed; }

        /* ── Flash messages ── */
        .flash {
            padding: 14px 20px; border-radius: var(--radius-md);
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
            font-size: 14px; font-weight: 500;
            animation: slideDown .3s ease;
        }
        .flash-success { background: rgba(0,229,160,.1); border: 1px solid rgba(0,229,160,.3); color: var(--green); }
        .flash-error   { background: rgba(255,77,109,.1); border: 1px solid rgba(255,77,109,.3); color: var(--red); }
        @keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

        /* ── Slot indicator ── */
        .slot-bar {
            display: flex; gap: 6px; align-items: center;
            margin-bottom: 20px;
        }
        .slot-dot {
            width: 28px; height: 8px; border-radius: 4px;
            background: var(--bg-card); border: 1px solid var(--border);
            transition: var(--t);
        }
        .slot-dot.filled { background: var(--gold); border-color: var(--gold); }
        .slot-dot.empty  { background: var(--bg-card); }

        /* ── Upload tambahan ── */
        .add-foto-zone {
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 32px 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--t);
            position: relative;
            background: rgba(201,169,110,.03);
        }
        .add-foto-zone:hover, .add-foto-zone.drag-over {
            border-color: var(--gold);
            background: var(--gold-glow);
        }
        .add-foto-zone input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .add-foto-zone.disabled {
            opacity: .5; cursor: not-allowed; pointer-events: none;
        }

        /* ── Add preview ── */
        #add-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .add-prev-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            overflow: hidden;
            position: relative;
            animation: popIn .2s ease;
        }
        @keyframes popIn { from{opacity:0;transform:scale(.85)} to{opacity:1;transform:scale(1)} }
        .add-prev-card img { width:100%; height:80px; object-fit:cover; display:block; }
        .add-prev-remove {
            position: absolute; top: 4px; right: 4px;
            width: 22px; height: 22px;
            background: rgba(255,77,109,.9);
            color: #fff; border: none; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 13px;
        }
        .add-prev-name {
            font-size: 10px; color: var(--text-2);
            padding: 4px 6px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* ── Lightbox ── */
        .lightbox {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.92);
            z-index: 9999;
            display: none; align-items: center; justify-content: center;
            backdrop-filter: blur(6px);
        }
        .lightbox.show { display: flex; }
        .lightbox img {
            max-width: 90vw; max-height: 85vh;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }
        .lightbox-close {
            position: fixed; top: 20px; right: 20px;
            width: 44px; height: 44px;
            background: rgba(255,255,255,.1);
            border: none; color: #fff; border-radius: 50%;
            font-size: 22px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: var(--t);
        }
        .lightbox-close:hover { background: var(--red); }

        /* ── Form ── */
        .form-label { font-size: 13px; color: var(--text-2); font-weight: 500; margin-bottom: 6px; display: block; }
        .val-bar-add { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .val-item {
            display: flex; align-items: center; gap: 5px;
            font-size: 12px; color: var(--text-2);
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px; padding: 4px 12px;
        }
        .val-item.ok  { color: var(--green); border-color: rgba(0,229,160,.3); background: rgba(0,229,160,.08); }
        .val-item.err { color: var(--red);   border-color: rgba(255,77,109,.3); background: rgba(255,77,109,.08); }

        @media (max-width: 768px) {
            .topbar { padding: 0 16px; }
            .page-wrap { padding: 20px 16px; }
            .info-card { flex-direction: column; gap: 16px; }
            .foto-grid { grid-template-columns: repeat(2, 1fr); }
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
        <a href="admin_konten.php" class="btn-outline">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div class="page-wrap">

    <!-- Breadcrumb -->
    <nav class="breadcrumb-nav" aria-label="breadcrumb">
        <a href="admin.php"><i class="bi bi-house"></i></a>
        <span class="sep">/</span>
        <a href="admin_konten.php">Manajemen Konten</a>
        <span class="sep">/</span>
        <span class="current"><?= htmlspecialchars($konten['judul']) ?></span>
    </nav>

    <!-- Flash -->
    <?php if ($flashSuccess): ?>
        <div class="flash flash-success">
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($flashSuccess) ?>
        </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="flash flash-error">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= htmlspecialchars($flashError) ?>
        </div>
    <?php endif; ?>

    <!-- Konten Info Card -->
    <div class="info-card">
        <div class="info-card-icon"><i class="bi bi-collection"></i></div>
        <div class="info-card-body">
            <div class="info-card-title"><?= htmlspecialchars($konten['judul']) ?></div>
            <div class="info-card-desc">
                <?= $konten['deskripsi'] ? nl2br(htmlspecialchars($konten['deskripsi'])) : '<em>Tidak ada deskripsi.</em>' ?>
            </div>
            <div class="info-card-meta">
                <span class="meta-pill <?= $konten['status'] === 'aktif' ? 'green' : 'gray' ?>">
                    <i class="bi bi-<?= $konten['status'] === 'aktif' ? 'eye' : 'eye-slash' ?>"></i>
                    <?= $konten['status'] === 'aktif' ? 'Aktif' : 'Non-Aktif' ?>
                </span>
                <span class="meta-pill">
                    <i class="bi bi-images"></i> <?= $jumlah_foto ?> foto
                </span>
                <span class="meta-pill">
                    <i class="bi bi-calendar3"></i>
                    <?= date('d M Y, H:i', strtotime($konten['created_at'])) ?>
                </span>
                <span class="meta-pill" style="<?= $sisa_slot > 0 ? 'color:var(--orange)' : 'color:var(--green)' ?>">
                    <i class="bi bi-plus-square"></i>
                    Sisa slot: <?= $sisa_slot ?>
                </span>
            </div>
        </div>
        <!-- Quick actions -->
        <div style="display:flex; flex-direction:column; gap:8px; flex-shrink:0;">
            <form method="POST" action="admin_konten_action.php" style="margin:0;">
                <input type="hidden" name="action"    value="toggle_status">
                <input type="hidden" name="id_konten" value="<?= $id_konten ?>">
                <button type="submit" class="btn-outline" style="width:100%;">
                    <i class="bi bi-toggle-<?= $konten['status'] === 'aktif' ? 'on' : 'off' ?>"></i>
                    <?= $konten['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                </button>
            </form>
            <form method="POST" action="admin_konten_action.php" style="margin:0;"
                  onsubmit="return confirm('Hapus konten ini beserta SEMUA fotonya?')">
                <input type="hidden" name="action"    value="hapus_konten">
                <input type="hidden" name="id_konten" value="<?= $id_konten ?>">
                <button type="submit" class="btn-red" style="width:100%;">
                    <i class="bi bi-trash"></i> Hapus Konten
                </button>
            </form>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
         GALERI FOTO KONTEN
    ═══════════════════════════════════════════════════ -->
    <div class="section-card">
        <div class="section-title">
            <i class="bi bi-images"></i>
            Foto Konten
            <span style="font-size:12px; font-weight:400; color:var(--text-2); margin-left:4px;"><?= $jumlah_foto ?> dari 5 slot terpakai</span>
        </div>

        <!-- Slot indicator -->
        <div class="slot-bar">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <div class="slot-dot <?= $i <= $jumlah_foto ? 'filled' : 'empty' ?>" title="Slot <?= $i ?>: <?= $i <= $jumlah_foto ? 'Terpakai' : 'Kosong' ?>"></div>
            <?php endfor; ?>
            <span style="font-size:12px; color:var(--text-2); margin-left:6px;"><?= $jumlah_foto ?>/5 foto</span>
            <?php if (!$bisa_hapus): ?>
            <span style="font-size:12px; color:var(--orange); margin-left:8px;">
                <i class="bi bi-info-circle me-1"></i>Min. 2 foto, foto tidak bisa dihapus lebih lanjut.
            </span>
            <?php endif; ?>
        </div>

        <!-- Foto grid -->
        <?php if (empty($fotos)): ?>
        <div style="text-align:center; padding:40px; color:var(--text-2);">
            <i class="bi bi-image" style="font-size:48px; opacity:.2; display:block; margin-bottom:12px;"></i>
            <p>Belum ada foto.</p>
        </div>
        <?php else: ?>
        <div class="foto-grid">
            <?php foreach ($fotos as $idx => $foto): ?>
            <?php
                $file_url  = 'uploads/konten/' . htmlspecialchars($foto['nama_file']);
                $file_path = __DIR__ . '/uploads/konten/' . $foto['nama_file'];
                $exists    = file_exists($file_path);
            ?>
            <div class="foto-card">
                <?php if ($exists): ?>
                    <img src="<?= $file_url ?>"
                         alt="Foto <?= $idx + 1 ?>"
                         loading="lazy"
                         onclick="openLightbox('<?= $file_url ?>')"
                         style="cursor:zoom-in;">
                    <button class="foto-lightbox-btn" onclick="openLightbox('<?= $file_url ?>')" type="button" title="Lihat penuh">
                        <i class="bi bi-fullscreen"></i>
                    </button>
                <?php else: ?>
                    <div style="width:100%; height:150px; background:var(--bg-card); display:flex; align-items:center; justify-content:center; color:var(--red);">
                        <i class="bi bi-exclamation-triangle" style="font-size:28px;"></i>
                    </div>
                <?php endif; ?>

                <span class="foto-order"><?= $idx + 1 ?></span>

                <div class="foto-card-body">
                    <span class="foto-name" title="<?= htmlspecialchars($foto['nama_file']) ?>">
                        <?= htmlspecialchars($foto['nama_file']) ?>
                    </span>
                    <!-- Tombol hapus foto -->
                    <form method="POST" action="admin_konten_action.php" style="margin:0;"
                          onsubmit="return confirmHapusFoto(<?= $jumlah_foto ?>)">
                        <input type="hidden" name="action"    value="hapus_foto">
                        <input type="hidden" name="id_foto"   value="<?= $foto['id'] ?>">
                        <input type="hidden" name="id_konten" value="<?= $id_konten ?>">
                        <button type="submit" class="btn-red"
                                <?= !$bisa_hapus ? 'disabled title="Tidak bisa dihapus, minimal 2 foto"' : 'title="Hapus foto ini"' ?>>
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════
         TAMBAH FOTO BARU
    ═══════════════════════════════════════════════════ -->
    <div class="section-card">
        <div class="section-title">
            <i class="bi bi-plus-circle"></i>
            Tambah Foto ke Konten Ini
            <?php if (!$bisa_tambah): ?>
            <span style="margin-left:auto; font-size:12px; font-weight:400; color:var(--red);">
                <i class="bi bi-ban me-1"></i>Slot penuh (5/5 foto)
            </span>
            <?php else: ?>
            <span style="margin-left:auto; font-size:12px; font-weight:400; color:var(--text-2);">
                Sisa slot: <?= $sisa_slot ?> foto lagi
            </span>
            <?php endif; ?>
        </div>

        <?php if (!$bisa_tambah): ?>
        <div style="text-align:center; padding:32px; background:rgba(255,77,109,.06); border:1px dashed rgba(255,77,109,.3); border-radius:var(--radius-md);">
            <i class="bi bi-images" style="font-size:40px; color:var(--red); opacity:.5; display:block; margin-bottom:10px;"></i>
            <p style="font-size:14px; color:var(--text-2);">Konten ini sudah mencapai batas maksimal <strong style="color:var(--gold);">5 foto</strong>.<br>Hapus foto yang tidak diperlukan untuk menambah foto baru.</p>
        </div>
        <?php else: ?>

        <form id="form-tambah-foto" action="admin_konten_action.php" method="POST" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="action"    value="tambah_foto">
            <input type="hidden" name="id_konten" value="<?= $id_konten ?>">

            <!-- Drop zone -->
            <div class="add-foto-zone" id="add-drop-zone">
                <input type="file" name="fotos_tambahan[]" id="add-input-fotos"
                       multiple accept=".jpg,.jpeg,.png"
                       onchange="addHandleSelect(this)">
                <i class="bi bi-cloud-plus" style="font-size:36px; color:var(--text-2); display:block; margin-bottom:10px;"></i>
                <div style="font-size:14px; font-weight:600; margin-bottom:4px;">Klik atau seret foto ke sini</div>
                <div style="font-size:12px; color:var(--text-2);">
                    Format JPG / PNG · Maks. 2MB per foto · Maks. tambah <strong style="color:var(--gold);"><?= $sisa_slot ?></strong> foto lagi
                </div>
            </div>

            <!-- Validation bar -->
            <div class="val-bar-add" id="add-val-bar" style="display:none; margin-top:10px;">
                <div class="val-item" id="add-val-jumlah"><i class="bi bi-hash"></i> <span id="add-val-txt">0 dipilih</span></div>
                <div class="val-item" id="add-val-format"><i class="bi bi-file-earmark-image"></i> Format JPG/PNG</div>
                <div class="val-item" id="add-val-ukuran"><i class="bi bi-hdd"></i> Maks 2MB</div>
            </div>

            <!-- Error -->
            <div id="add-error" class="flash flash-error" style="display:none; margin-top:10px;">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span id="add-error-text"></span>
            </div>

            <!-- Preview grid -->
            <div id="add-preview-grid"></div>

            <!-- Submit -->
            <div style="display:flex; gap:10px; margin-top:18px; justify-content:flex-end;">
                <button type="button" class="btn-outline" id="add-reset-btn" style="display:none;" onclick="addReset()">
                    <i class="bi bi-x"></i> Reset
                </button>
                <button type="submit" class="btn-gold" id="add-submit-btn" disabled>
                    <i class="bi bi-cloud-upload"></i> Tambah Foto
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

</div><!-- /page-wrap -->

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" type="button">&times;</button>
    <img id="lightbox-img" src="" alt="Preview foto">
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Konfigurasi ──
const SISA_SLOT   = <?= $sisa_slot ?>;
const MAX_SIZE_MB = 2;
const ALLOWED_EXT = ['jpg', 'jpeg', 'png'];

// ── State untuk tambah foto ──
let addFiles = [];

// ── Lightbox ──
function openLightbox(url) {
    document.getElementById('lightbox-img').src = url;
    document.getElementById('lightbox').classList.add('show');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('show');
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeLightbox();
});

// ── Konfirmasi hapus foto ──
function confirmHapusFoto(total) {
    if (total <= 4) {
        alert('Tidak bisa menghapus foto. Minimal 4 foto harus ada di setiap konten.');
        return false;
    }
    return confirm('Hapus foto ini dari konten? Tindakan ini tidak bisa dibatalkan.');
}

// ── Drag & drop untuk tambah foto ──
const addDropZone = document.getElementById('add-drop-zone');
if (addDropZone) {
    addDropZone.addEventListener('dragover', e => {
        e.preventDefault();
        addDropZone.classList.add('drag-over');
    });
    addDropZone.addEventListener('dragleave', () => addDropZone.classList.remove('drag-over'));
    addDropZone.addEventListener('drop', e => {
        e.preventDefault();
        addDropZone.classList.remove('drag-over');
        addPushFiles(Array.from(e.dataTransfer.files));
    });
}

function addHandleSelect(input) {
    addPushFiles(Array.from(input.files));
    input.value = '';
}

function addPushFiles(newFiles) {
    newFiles.forEach(f => {
        const isDupe = addFiles.some(x => x.name === f.name && x.size === f.size);
        if (!isDupe) addFiles.push(f);
    });
    addRenderPreviews();
    addValidate();
}

function addRemoveFile(i) {
    addFiles.splice(i, 1);
    addRenderPreviews();
    addValidate();
}

function addReset() {
    addFiles = [];
    addRenderPreviews();
    addValidate();
    document.getElementById('add-reset-btn').style.display = 'none';
}

function addRenderPreviews() {
    const grid = document.getElementById('add-preview-grid');
    if (!grid) return;
    grid.innerHTML = '';

    addFiles.forEach((file, i) => {
        const card = document.createElement('div');
        card.className = 'add-prev-card';

        const img = document.createElement('img');
        img.alt = file.name;
        const r = new FileReader();
        r.onload = e => img.src = e.target.result;
        r.readAsDataURL(file);

        const rmBtn = document.createElement('button');
        rmBtn.className = 'add-prev-remove';
        rmBtn.type = 'button';
        rmBtn.innerHTML = '&times;';
        rmBtn.onclick = () => addRemoveFile(i);

        const name = document.createElement('div');
        name.className = 'add-prev-name';
        name.textContent = file.name;

        card.append(img, rmBtn, name);
        grid.appendChild(card);
    });

    const resetBtn = document.getElementById('add-reset-btn');
    if (resetBtn) resetBtn.style.display = addFiles.length > 0 ? 'inline-flex' : 'none';
}

function addValidate() {
    const valBar   = document.getElementById('add-val-bar');
    const submitBtn = document.getElementById('add-submit-btn');
    const errBox   = document.getElementById('add-error');
    const errText  = document.getElementById('add-error-text');

    if (!valBar) return;

    const n = addFiles.length;
    valBar.style.display = n > 0 ? 'flex' : 'none';

    let ok = true;
    let errMsg = '';

    // Jumlah
    const valJml = document.getElementById('add-val-jumlah');
    const valTxt = document.getElementById('add-val-txt');
    if (valTxt) valTxt.textContent = `${n} dipilih (maks +${SISA_SLOT})`;

    if (n === 0) {
        valJml.className = 'val-item';
        ok = false;
    } else if (n > SISA_SLOT) {
        valJml.className = 'val-item err';
        ok = false;
        errMsg = `Terlalu banyak! Hanya bisa tambah ${SISA_SLOT} foto lagi.`;
    } else {
        valJml.className = 'val-item ok';
    }

    // Format
    const valFmt = document.getElementById('add-val-format');
    const badFmt = addFiles.filter(f => {
        const ext = f.name.split('.').pop().toLowerCase();
        return !ALLOWED_EXT.includes(ext);
    });
    if (badFmt.length > 0) {
        valFmt.className = 'val-item err';
        ok = false;
        errMsg = errMsg || `"${badFmt[0].name}" bukan JPG/PNG.`;
    } else {
        valFmt.className = n > 0 ? 'val-item ok' : 'val-item';
    }

    // Ukuran
    const valSize = document.getElementById('add-val-ukuran');
    const bigFiles = addFiles.filter(f => f.size > MAX_SIZE_MB * 1024 * 1024);
    if (bigFiles.length > 0) {
        valSize.className = 'val-item err';
        ok = false;
        errMsg = errMsg || `"${bigFiles[0].name}" melebihi ${MAX_SIZE_MB}MB.`;
    } else {
        valSize.className = n > 0 ? 'val-item ok' : 'val-item';
    }

    // Error message
    if (!ok && errMsg) {
        errBox.style.display = 'flex';
        errText.textContent = errMsg;
    } else {
        errBox.style.display = 'none';
    }

    if (submitBtn) submitBtn.disabled = !ok || n === 0;
    return ok;
}

// Submit form tambah foto – inject files
const formTambahFoto = document.getElementById('form-tambah-foto');
if (formTambahFoto) {
    formTambahFoto.addEventListener('submit', function(e) {
        e.preventDefault();
        if (!addValidate() || addFiles.length === 0) return;

        const fd = new FormData(this);
        addFiles.forEach(f => fd.append('fotos_tambahan[]', f));

        fetch('admin_konten_action.php', {
            method: 'POST',
            body: fd
        }).then(r => {
            if (r.redirected) window.location.href = r.url;
            else window.location.reload();
        }).catch(() => this.submit());
    });
}

// Auto-hide flash
setTimeout(() => {
    document.querySelectorAll('.flash').forEach(el => {
        el.style.transition = 'opacity .4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    });
}, 5000);
</script>

</body>
</html>
