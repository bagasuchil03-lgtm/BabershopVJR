<?php
session_start();
require_once 'koneksi.php';

// Cek admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$adminName = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Admin';

// Query Riwayat Pelanggan (status = selesai)
$query = "SELECT b.nama_pelanggan, b.tanggal_booking, b.jam_booking, b.total_harga, b.status, l.nama_layanan 
          FROM booking b 
          LEFT JOIN layanan l ON b.id_layanan = l.id_layanan 
          WHERE b.status = 'selesai' 
          ORDER BY b.tanggal_booking DESC, b.jam_booking DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pelanggan - Admin</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --bg-base: #0a0a0f;
            --bg-surface: #12121a;
            --primary: #c39e6d;
            --text-main: #ffffff;
            --text-muted: #8e8e9f;
            --border: rgba(255, 255, 255, 0.1);
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-base);
            color: var(--text-main);
        }
        .navbar-brand, .nav-link, .card-title { color: var(--text-main) !important; }
        .card-custom {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 12px;
        }
        .table { color: var(--text-main); }
        .table th { background-color: rgba(255,255,255,0.05); color: var(--primary); border-color: var(--border); }
        .table td { border-color: var(--border); background-color: transparent; }
        .badge-status { font-size: 0.85em; padding: 0.4em 0.8em; border-radius: 6px; }
    </style>
</head>
<body>
    
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--bg-surface); border-bottom: 1px solid var(--border);">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-uppercase" href="admin.php" style="color: var(--primary) !important;">
            <i class="bi bi-scissors me-2"></i>Vijer Admin
        </a>
        <div class="d-flex align-items-center">
            <span class="me-3 d-none d-md-inline">Halo, <?= htmlspecialchars($adminName) ?></span>
            <a href="admin.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" style="color: var(--primary);">Riwayat Pelanggan</h2>
    </div>

    <div class="card card-custom">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Pelanggan</th>
                            <th>Tanggal Pelayanan</th>
                            <th>Layanan Dipilih</th>
                            <th>Total Pembayaran</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td class="fw-medium"><?= htmlspecialchars($row['nama_pelanggan'] ?? '-') ?></td>
                                    <td><?= date('d M Y', strtotime($row['tanggal_booking'])) ?> <br> <small class="text-muted"><?= substr($row['jam_booking'], 0, 5) ?></small></td>
                                    <td><?= htmlspecialchars($row['nama_layanan'] ?? '-') ?></td>
                                    <td>Rp <?= number_format($row['total_harga'], 0, ',', '.') ?></td>
                                    <td>
                                        <span class="badge bg-success bg-opacity-25 text-success badge-status">
                                            <i class="bi bi-check-circle me-1"></i>Selesai
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada data riwayat pelanggan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
