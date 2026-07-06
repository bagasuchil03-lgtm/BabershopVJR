<?php
session_start();
require_once 'koneksi.php';

// Cek admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$adminName = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Admin';

$upload_dir = 'uploads/homepage/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add') {
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $filename = $_FILES['foto']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (in_array($ext, $allowed)) {
                    $new_filename = "gallery_" . time() . "." . $ext;
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $new_filename)) {
                        $path = $upload_dir . $new_filename;
                        $stmt = $conn->prepare("INSERT INTO homepage_photos (file_path, status) VALUES (?, 'aktif')");
                        $stmt->bind_param("s", $path);
                        if ($stmt->execute()) {
                            $_SESSION['flash_success'] = "Foto berhasil ditambahkan.";
                        } else {
                            $_SESSION['flash_error'] = "Gagal menyimpan ke database: " . $conn->error;
                        }
                    } else {
                        $_SESSION['flash_error'] = "Gagal mengupload file.";
                    }
                } else {
                    $_SESSION['flash_error'] = "Format file tidak didukung. Gunakan JPG, PNG, atau WEBP.";
                }
            } else {
                $_SESSION['flash_error'] = "Pilih file untuk diupload.";
            }
        } elseif ($action === 'toggle' && isset($_POST['id'])) {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("SELECT status FROM homepage_photos WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $new_status = $row['status'] === 'aktif' ? 'nonaktif' : 'aktif';
                $upd = $conn->prepare("UPDATE homepage_photos SET status = ? WHERE id = ?");
                $upd->bind_param("si", $new_status, $id);
                $upd->execute();
                $_SESSION['flash_success'] = "Status foto berhasil diubah menjadi " . strtoupper($new_status) . ".";
            }
        } elseif ($action === 'delete' && isset($_POST['id'])) {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("SELECT file_path FROM homepage_photos WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (file_exists($row['file_path'])) {
                    unlink($row['file_path']);
                }
                $del = $conn->prepare("DELETE FROM homepage_photos WHERE id = ?");
                $del->bind_param("i", $id);
                $del->execute();
                $_SESSION['flash_success'] = "Foto berhasil dihapus.";
            }
        } elseif ($action === 'replace' && isset($_POST['id'])) {
            $id = intval($_POST['id']);
            if (isset($_FILES['foto_replace']) && $_FILES['foto_replace']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $filename = $_FILES['foto_replace']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (in_array($ext, $allowed)) {
                    $new_filename = "gallery_" . time() . "_" . uniqid() . "." . $ext;
                    if (move_uploaded_file($_FILES['foto_replace']['tmp_name'], $upload_dir . $new_filename)) {
                        $path = $upload_dir . $new_filename;
                        
                        $stmt = $conn->prepare("SELECT file_path FROM homepage_photos WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            if (file_exists($row['file_path'])) {
                                unlink($row['file_path']);
                            }
                        }

                        $upd = $conn->prepare("UPDATE homepage_photos SET file_path = ? WHERE id = ?");
                        $upd->bind_param("si", $path, $id);
                        if ($upd->execute()) {
                            $_SESSION['flash_success'] = "Foto berhasil diganti.";
                        } else {
                            $_SESSION['flash_error'] = "Gagal mengupdate database.";
                        }
                    }
                } else {
                    $_SESSION['flash_error'] = "Format file tidak didukung.";
                }
            }
        }
    }
    header("Location: admin_gallery.php");
    exit();
}

// Hitung total foto
$total_aktif    = $conn->query("SELECT COUNT(*) AS c FROM homepage_photos WHERE status='aktif'")->fetch_assoc()['c'];
$total_nonaktif = $conn->query("SELECT COUNT(*) AS c FROM homepage_photos WHERE status='nonaktif'")->fetch_assoc()['c'];
$total_semua    = $total_aktif + $total_nonaktif;

// Ambil semua foto
$query  = "SELECT * FROM homepage_photos ORDER BY created_at DESC";
$photos = $conn->query($query);

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Foto Beranda – Vijer Admin</title>
    <meta name="description" content="Kelola foto yang ditampilkan di beranda Vijer Barbershop.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-base:      #0a0a0f;
            --bg-surface:   #12121a;
            --bg-card:      #1e1e2e;
            --bg-hover:     #252535;
            --gold:         #C9A96E;
            --gold-glow:    rgba(201,169,110,0.18);
            --accent-green: #00e5a0;
            --accent-red:   #ff4d6d;
            --accent-blue:  #4d9fff;
            --text-primary: #f0f0f5;
            --text-muted:   #7a7a92;
            --border:       rgba(201,169,110,0.15);
            --radius:       14px;
            --transition:   all 0.25s ease;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-base);
            color: var(--text-primary);
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
            z-index: 100;
        }
        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            color: var(--gold);
            text-decoration: none;
        }
        .topbar-brand i { font-size: 22px; }
        .topbar-nav { display: flex; align-items: center; gap: 12px; }

        /* ── Stat cards ── */
        .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: var(--transition);
        }
        .stat-card:hover { border-color: var(--gold); box-shadow: 0 0 20px var(--gold-glow); }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .stat-icon.gold    { background: rgba(201,169,110,0.15); color: var(--gold); }
        .stat-icon.green   { background: rgba(0,229,160,0.15); color: var(--accent-green); }
        .stat-icon.gray    { background: rgba(122,122,146,0.15); color: var(--text-muted); }
        .stat-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--text-primary); line-height: 1; }

        /* ── Upload card ── */
        .upload-card {
            background: var(--bg-surface);
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            padding: 28px 32px;
            margin-bottom: 28px;
            transition: var(--transition);
        }
        .upload-card:hover { border-color: var(--gold); }
        .upload-card h5 { font-size: 15px; font-weight: 600; color: var(--gold); margin-bottom: 16px; }
        .upload-card .form-control,
        .upload-card .form-select {
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: 8px;
        }
        .upload-card .form-control:focus { border-color: var(--gold); box-shadow: 0 0 0 3px var(--gold-glow); }
        .upload-card .form-control::file-selector-button {
            background: rgba(201,169,110,0.15);
            color: var(--gold);
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            cursor: pointer;
        }

        /* ── Photo grid ── */
        .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; }
        .photo-item {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
        }
        .photo-item:hover { border-color: var(--gold); transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.4); }
        .photo-item img {
            width: 100%; height: 180px;
            object-fit: cover;
            display: block;
        }
        .photo-item .photo-overlay {
            position: absolute;
            top: 10px; right: 10px;
        }
        .photo-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .photo-status.aktif    { background: rgba(0,229,160,0.2); color: var(--accent-green); border: 1px solid var(--accent-green); }
        .photo-status.nonaktif { background: rgba(122,122,146,0.2); color: var(--text-muted); border: 1px solid var(--text-muted); }
        .photo-actions {
            padding: 12px 14px;
            display: flex;
            gap: 8px;
            border-top: 1px solid var(--border);
            background: var(--bg-card);
        }
        .btn-toggle {
            flex: 1;
            padding: 6px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid var(--gold);
            background: transparent;
            color: var(--gold);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-toggle:hover { background: rgba(201,169,110,0.15); }
        .btn-delete {
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid var(--accent-red);
            background: transparent;
            color: var(--accent-red);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-delete:hover { background: rgba(255,77,109,0.15); }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 56px; opacity: 0.3; display: block; margin-bottom: 16px; }
        .empty-state p { font-size: 15px; }

        /* ── Flash messages ── */
        .flash-msg {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 500;
            animation: fadeInDown 0.3s ease;
        }
        .flash-success { background: rgba(0,229,160,0.12); border: 1px solid rgba(0,229,160,0.3); color: var(--accent-green); }
        .flash-error   { background: rgba(255,77,109,0.12);  border: 1px solid rgba(255,77,109,0.3);  color: var(--accent-red); }

        @keyframes fadeInDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .stat-grid { grid-template-columns: 1fr; }
            .topbar { padding: 0 16px; }
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
        <span style="color:var(--text-muted); font-size:13px;">Halo, <?= htmlspecialchars($adminName) ?></span>
        <a href="admin.php" class="btn btn-sm" style="background:rgba(201,169,110,0.15); color:var(--gold); border:1px solid var(--border); border-radius:8px;">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div class="container-fluid py-4" style="max-width: 1200px;">

    <!-- Page Header -->
    <div style="margin-bottom: 28px;">
        <h1 style="font-size:26px; font-weight:700; color:var(--gold);">
            <i class="bi bi-images me-2"></i>Manajemen Foto Beranda
        </h1>
        <p style="color:var(--text-muted); font-size:14px; margin-top:4px;">
            Upload, aktifkan, dan kelola foto yang tampil di halaman beranda website.
        </p>
    </div>

    <!-- Flash Messages -->
    <?php if ($flashSuccess): ?>
        <div class="flash-msg flash-success">
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($flashSuccess) ?>
        </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="flash-msg flash-error">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= htmlspecialchars($flashError) ?>
        </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-collection"></i></div>
            <div>
                <div class="stat-label">Total Foto</div>
                <div class="stat-value"><?= $total_semua ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-eye-fill"></i></div>
            <div>
                <div class="stat-label">Aktif (Tampil)</div>
                <div class="stat-value"><?= $total_aktif ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gray"><i class="bi bi-eye-slash-fill"></i></div>
            <div>
                <div class="stat-label">Non-Aktif</div>
                <div class="stat-value"><?= $total_nonaktif ?></div>
            </div>
        </div>
    </div>

    <!-- Upload Form -->
    <div class="upload-card">
        <h5><i class="bi bi-cloud-upload me-2"></i>Tambah Foto Baru</h5>
        <form action="admin_gallery.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" style="color:var(--text-muted); font-size:13px;">Pilih Foto (JPG, PNG, WEBP – maks 5MB)</label>
                    <input type="file" name="foto" id="foto-input" class="form-control" accept=".jpg,.jpeg,.png,.webp" required onchange="previewPhoto(this)">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn w-100" style="background:linear-gradient(135deg,#C9A96E,#a07840); color:#fff; font-weight:600; border:none; border-radius:8px; padding:10px;">
                        <i class="bi bi-upload me-2"></i>Upload Foto
                    </button>
                </div>
                <div class="col-12" id="preview-wrap" style="display:none;">
                    <img id="preview-img" src="" alt="Preview" style="max-height:160px; border-radius:10px; border:2px solid var(--border);">
                </div>
            </div>
        </form>
    </div>

    <!-- Photo Grid -->
    <?php if ($photos && $photos->num_rows > 0): ?>
        <div class="photo-grid">
            <?php while ($row = $photos->fetch_assoc()): ?>
                <div class="photo-item">
                    <div class="photo-overlay">
                        <span class="photo-status <?= $row['status'] ?>"><?= $row['status'] === 'aktif' ? 'Aktif' : 'Non-Aktif' ?></span>
                    </div>
                    <img src="<?= htmlspecialchars($row['file_path']) ?>" alt="Foto Beranda" loading="lazy">
                    <div class="photo-actions">
                        <form action="admin_gallery.php" method="POST" style="flex:1;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn-toggle w-100">
                                <?php if ($row['status'] === 'aktif'): ?>
                                    <i class="bi bi-eye-slash me-1"></i>Nonaktifkan
                                <?php else: ?>
                                    <i class="bi bi-eye me-1"></i>Aktifkan
                                <?php endif; ?>
                            </button>
                        </form>
                        <form action="admin_gallery.php" method="POST" enctype="multipart/form-data" id="form-replace-<?= $row['id'] ?>" style="display:none;">
                            <input type="hidden" name="action" value="replace">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <input type="file" name="foto_replace" id="input-replace-<?= $row['id'] ?>" accept=".jpg,.jpeg,.png,.webp" onchange="document.getElementById('form-replace-<?= $row['id'] ?>').submit()">
                        </form>
                        <button type="button" class="btn-toggle" style="flex:0; padding:6px 12px; border-color:#4d9fff; color:#4d9fff; margin-left: 8px;" onclick="document.getElementById('input-replace-<?= $row['id'] ?>').click()" title="Ganti Foto">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                        <form action="admin_gallery.php" method="POST" onsubmit="return confirm('Yakin hapus foto ini? Tindakan ini tidak bisa dibatalkan.');" style="margin-left: 8px;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn-delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-images"></i>
            <p>Belum ada foto. Upload foto pertama Anda di atas.</p>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewPhoto(input) {
    const file = input.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran file terlalu besar. Maksimum 5MB.');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('preview-img').src = e.target.result;
        document.getElementById('preview-wrap').style.display = 'block';
    };
    reader.readAsDataURL(file);
}

// Auto hide flash after 4s
setTimeout(() => {
    document.querySelectorAll('.flash-msg').forEach(el => {
        el.style.transition = 'opacity 0.4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    });
}, 4000);
</script>
</body>
</html>
