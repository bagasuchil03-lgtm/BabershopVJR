<?php
session_start();
require_once 'koneksi.php';

// Cek admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$adminName = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Admin';

// Pencarian
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query
$where_parts = [];
$params = [];
$types  = '';

if (!empty($search)) {
    $search_esc = "%$search%";
    $where_parts[] = "(b.nama_pelanggan LIKE ? OR b.kode_booking LIKE ? OR b.no_hp LIKE ?)";
    $params[] = &$search_esc;
    $params[] = &$search_esc;
    $params[] = &$search_esc;
    $types .= 'sss';
}
if ($status_filter !== 'all') {
    $where_parts[] = "b.status = ?";
    $params[] = &$status_filter;
    $types   .= 's';
}

$where_sql = empty($where_parts) ? '' : 'WHERE ' . implode(' AND ', $where_parts);

$query = "SELECT b.id_booking, b.kode_booking, b.nama_pelanggan, b.no_hp,
                 b.status, b.tanggal_booking, b.jam_booking, b.bukti_pembayaran,
                 l.nama_layanan, br.nama_barber
          FROM booking b
          LEFT JOIN layanan l  ON b.id_layanan = l.id_layanan
          LEFT JOIN barber br  ON b.id_barber  = br.id_barber
          $where_sql
          ORDER BY b.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $bind_params = array_merge([$types], $params);
    call_user_func_array([$stmt, 'bind_param'], $bind_params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Stats
$stat = $conn->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status='pending'    THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='disetujui'  THEN 1 ELSE 0 END) as disetujui,
    SUM(CASE WHEN status='selesai'    THEN 1 ELSE 0 END) as selesai,
    SUM(CASE WHEN status='dibatalkan' THEN 1 ELSE 0 END) as dibatalkan
  FROM booking")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Booking – Vijer Admin</title>

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
            --gold:         #C9A96E;
            --gold-glow:    rgba(201,169,110,0.15);
            --green:        #00e5a0;
            --red:          #ff4d6d;
            --blue:         #4d9fff;
            --orange:       #ff9f43;
            --text-main:    #f0f0f5;
            --text-muted:   #7a7a92;
            --border:       rgba(201,169,110,0.15);
            --radius:       12px;
        }

        *, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
        body { font-family:'Outfit',sans-serif; background:var(--bg-base); color:var(--text-main); min-height:100vh; }

        /* Topbar */
        .topbar {
            background:var(--bg-surface); border-bottom:1px solid var(--border);
            padding:0 32px; height:64px; display:flex; align-items:center;
            justify-content:space-between; position:sticky; top:0; z-index:100;
        }
        .topbar-brand { display:flex; align-items:center; gap:10px; font-size:18px; font-weight:700; color:var(--gold); text-decoration:none; }

        /* Stat grid */
        .stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:14px; margin-bottom:24px; }
        .stat-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius); padding:16px 20px; transition:all 0.2s; }
        .stat-card:hover { border-color:var(--gold); box-shadow:0 0 20px var(--gold-glow); }
        .stat-label { font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; }
        .stat-value { font-size:26px; font-weight:700; }

        /* Filter bar */
        .filter-bar { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius); padding:18px 22px; margin-bottom:20px; }
        .filter-input { background:var(--bg-card) !important; border:1px solid var(--border) !important; color:var(--text-main) !important; border-radius:8px !important; }
        .filter-input:focus { border-color:var(--gold) !important; box-shadow:0 0 0 3px var(--gold-glow) !important; }
        .filter-input::placeholder { color:var(--text-muted); }

        /* Table */
        .table-wrap { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
        .table { margin:0; color:var(--text-main); }
        .table thead th { background:rgba(201,169,110,0.08); color:var(--gold); border-color:var(--border); font-size:12px; text-transform:uppercase; letter-spacing:0.5px; padding:14px 16px; }
        .table tbody td { border-color:var(--border); padding:13px 16px; vertical-align:middle; font-size:14px; }
        .table tbody tr { transition:background 0.15s; }
        .table tbody tr:hover { background:rgba(201,169,110,0.05); }

        /* Status badges */
        .badge-status { padding:5px 12px; border-radius:20px; font-size:11px; font-weight:600; letter-spacing:0.5px; }
        .status-pending    { background:rgba(255,159,67,0.15); color:var(--orange); border:1px solid rgba(255,159,67,0.4); }
        .status-disetujui  { background:rgba(77,159,255,0.15); color:var(--blue); border:1px solid rgba(77,159,255,0.4); }
        .status-selesai    { background:rgba(0,229,160,0.15); color:var(--green); border:1px solid rgba(0,229,160,0.4); }
        .status-dibatalkan { background:rgba(255,77,109,0.15); color:var(--red); border:1px solid rgba(255,77,109,0.4); }
        .status-ditolak    { background:rgba(255,77,109,0.15); color:var(--red); border:1px solid rgba(255,77,109,0.4); }

        /* Action buttons */
        .btn-view-bukti {
            display:inline-flex; align-items:center; gap:4px;
            padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600;
            background:rgba(77,159,255,0.15); color:var(--blue); border:1px solid rgba(77,159,255,0.4);
            text-decoration:none; transition:all 0.2s;
        }
        .btn-view-bukti:hover { background:rgba(77,159,255,0.3); color:var(--blue); }

        /* Real-time indicator */
        .live-indicator {
            display:inline-flex; align-items:center; gap:6px;
            font-size:12px; color:var(--green);
            background:rgba(0,229,160,0.1); border:1px solid rgba(0,229,160,0.25);
            padding:4px 12px; border-radius:20px;
        }
        .live-dot { width:7px; height:7px; border-radius:50%; background:var(--green); animation:pulse 1.5s infinite; }
        @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(0.8)} }

        /* Empty state */
        .empty-state { text-align:center; padding:60px 20px; color:var(--text-muted); }
        .empty-state i { font-size:52px; opacity:0.25; display:block; margin-bottom:14px; }

        @media(max-width:768px){ .topbar{ padding:0 16px; } }
    </style>
</head>
<body>

<div class="topbar">
    <a class="topbar-brand" href="admin.php">
        <i class="bi bi-scissors"></i> Vijer Admin
    </a>
    <div class="d-flex align-items-center gap-3">
        <div class="live-indicator">
            <span class="live-dot"></span> Live
        </div>
        <span style="color:var(--text-muted);font-size:13px;">Halo, <?= htmlspecialchars($adminName) ?></span>
        <a href="admin.php" class="btn btn-sm" style="background:rgba(201,169,110,0.15);color:var(--gold);border:1px solid var(--border);border-radius:8px;">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div class="container-fluid py-4" style="max-width:1300px;">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 style="font-size:24px;font-weight:700;color:var(--gold);"><i class="bi bi-clock-history me-2"></i>Riwayat Booking</h1>
            <p style="color:var(--text-muted);font-size:13px;margin-top:4px;">Data diperbarui otomatis setiap 15 detik.</p>
        </div>
    </div>

    <!-- Flash messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert" style="background:rgba(0,229,160,0.1);border:1px solid rgba(0,229,160,0.3);color:var(--green);border-radius:10px;padding:14px 18px;">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-label">Total Booking</div>
            <div class="stat-value"><?= $stat['total'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label" style="color:var(--orange);">Pending</div>
            <div class="stat-value" style="color:var(--orange);"><?= $stat['pending'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label" style="color:var(--blue);">Disetujui</div>
            <div class="stat-value" style="color:var(--blue);"><?= $stat['disetujui'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label" style="color:var(--green);">Selesai</div>
            <div class="stat-value" style="color:var(--green);"><?= $stat['selesai'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label" style="color:var(--red);">Dibatalkan/Ditolak</div>
            <div class="stat-value" style="color:var(--red);"><?= ($stat['dibatalkan'] ?? 0) ?></div>
        </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
        <form method="GET" action="riwayat_booking.php" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Cari Pelanggan / Kode / Kontak</label>
                <input type="text" name="search" class="form-control filter-input" placeholder="Cari..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <label style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Filter Status</label>
                <select name="status" class="form-select filter-input">
                    <option value="all"       <?= $status_filter === 'all'        ? 'selected' : '' ?>>Semua Status</option>
                    <option value="pending"   <?= $status_filter === 'pending'    ? 'selected' : '' ?>>Pending</option>
                    <option value="disetujui" <?= $status_filter === 'disetujui'  ? 'selected' : '' ?>>Disetujui</option>
                    <option value="selesai"   <?= $status_filter === 'selesai'    ? 'selected' : '' ?>>Selesai</option>
                    <option value="dibatalkan"<?= $status_filter === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn flex-grow-1" style="background:linear-gradient(135deg,#C9A96E,#a07840);color:#fff;font-weight:600;border:none;border-radius:8px;">
                    <i class="bi bi-search"></i> Filter
                </button>
                <?php if (!empty($search) || $status_filter !== 'all'): ?>
                    <a href="riwayat_booking.php" class="btn" style="background:var(--bg-card);color:var(--text-muted);border:1px solid var(--border);border-radius:8px;">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-wrap">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode Booking</th>
                        <th>Nama & Kontak</th>
                        <th>Layanan & Barber</th>
                        <th>Jadwal</th>
                        <th>Status</th>
                        <th>Bukti</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td style="color:var(--text-muted);"><?= $no++ ?></td>
                                <td><span style="font-weight:700;color:var(--gold);"><?= htmlspecialchars($row['kode_booking'] ?? '-') ?></span></td>
                                <td>
                                    <div style="font-weight:600;"><?= htmlspecialchars($row['nama_pelanggan'] ?? '-') ?></div>
                                    <small style="color:var(--text-muted);">
                                        <i class="bi bi-telephone"></i> <?= htmlspecialchars($row['no_hp'] ?? '-') ?>
                                    </small>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($row['nama_layanan'] ?? '-') ?></div>
                                    <small style="color:var(--text-muted);">
                                        <i class="bi bi-person-badge"></i> <?= htmlspecialchars($row['nama_barber'] ?? '-') ?>
                                    </small>
                                </td>
                                <td>
                                    <div style="font-weight:500;"><?= date('d M Y', strtotime($row['tanggal_booking'])) ?></div>
                                    <small style="color:var(--text-muted);"><?= substr($row['jam_booking'], 0, 5) ?> WIB</small>
                                </td>
                                <td>
                                    <?php
                                        $s = strtolower($row['status']);
                                        $label = match($s) {
                                            'pending'    => 'Pending',
                                            'disetujui'  => 'Disetujui',
                                            'selesai'    => 'Selesai',
                                            'dibatalkan' => 'Dibatalkan',
                                            'ditolak'    => 'Ditolak',
                                            default      => ucfirst($s)
                                        };
                                    ?>
                                    <span class="badge-status status-<?= $s ?>"><?= $label ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($row['bukti_pembayaran'])): ?>
                                        <a href="lihat_bukti.php?id=<?= $row['id_booking'] ?>" target="_blank" class="btn-view-bukti">
                                            <i class="bi bi-receipt"></i> Lihat
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);font-size:12px;">–</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'pending'): ?>
                                        <form method="post" action="admin_update_booking.php" class="d-inline-block">
                                            <input type="hidden" name="booking_id" value="<?= $row['id_booking'] ?>">
                                            <input type="hidden" name="action" value="accept">
                                            <button type="submit" class="btn btn-sm" style="background:rgba(0,229,160,0.15);color:var(--green);border:1px solid rgba(0,229,160,0.4);border-radius:8px;font-size:12px;" onclick="return confirm('Terima booking ini?')">
                                                <i class="bi bi-check"></i> Terima
                                            </button>
                                        </form>
                                        <form method="post" action="admin_update_booking.php" class="d-inline-block ms-1">
                                            <input type="hidden" name="booking_id" value="<?= $row['id_booking'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-sm" style="background:rgba(255,77,109,0.15);color:var(--red);border:1px solid rgba(255,77,109,0.4);border-radius:8px;font-size:12px;" onclick="return confirm('Tolak booking ini?')">
                                                <i class="bi bi-x"></i> Tolak
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);font-size:12px;"><i class="bi bi-check2-all"></i> Selesai di-review</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="bi bi-calendar-x"></i>
                                    <p>Tidak ada data booking yang cocok dengan filter.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-refresh setiap 15 detik (real-time booking update)
    setInterval(() => { window.location.reload(); }, 15000);

    // Countdown timer kecil di header (opsional – tambah jika diinginkan)
</script>
</body>
</html>
