<?php
session_start();
require_once 'koneksi.php';

// Cek admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle verifikasi pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id_booking'])) {
    $id_booking = intval($_POST['id_booking']);
    $action = $_POST['action'];

    if ($action === 'terima') {
        $stmt = $conn->prepare("UPDATE booking SET status_pembayaran = 'Lunas' WHERE id_booking = ?");
        $stmt->bind_param("i", $id_booking);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Pembayaran untuk Booking ID $id_booking telah diverifikasi (Lunas).";
        }
    } elseif ($action === 'tolak') {
        $stmt = $conn->prepare("UPDATE booking SET status_pembayaran = 'Ditolak' WHERE id_booking = ?");
        $stmt->bind_param("i", $id_booking);
        if ($stmt->execute()) {
            $_SESSION['error'] = "Pembayaran untuk Booking ID $id_booking ditolak.";
        }
    }
    header("Location: admin_pembayaran.php");
    exit();
}

// Ambil data pembayaran
$query = "SELECT b.*, l.nama_layanan, br.nama_barber 
          FROM booking b 
          JOIN layanan l ON b.id_layanan = l.id_layanan 
          JOIN barber br ON b.id_barber = br.id_barber 
          WHERE b.bukti_pembayaran IS NOT NULL AND b.bukti_pembayaran != '' 
          ORDER BY b.tanggal_pembayaran DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Pembayaran - Admin Barbershop</title>
    <!-- Menggunakan Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .badge-menunggu_verifikasi { background-color: #17a2b8; color: #fff; }
        .badge-lunas { background-color: #20c997; color: #fff; }
        .badge-ditolak { background-color: #dc3545; color: #fff; }
        .img-bukti { max-width: 150px; cursor: pointer; transition: transform 0.2s; }
        .img-bukti:hover { transform: scale(1.05); }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0"><i class="bi bi-wallet2 text-primary me-2"></i>Verifikasi Pembayaran</h2>
        <a href="admin.php" class="btn btn-secondary rounded-pill"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
    </div>

    <!-- Alert Sukses / Gagal -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card card-custom p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Kode Booking</th>
                        <th>Pelanggan</th>
                        <th>Total Tagihan</th>
                        <th>Tgl Bayar</th>
                        <th>Bukti</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): 
                            $status_class = 'badge-' . strtolower(str_replace(' ', '_', $row['status_pembayaran']));
                        ?>
                            <tr>
                                <td><span class="fw-bold text-primary"><?= $row['kode_booking'] ?></span></td>
                                <td>
                                    <?= htmlspecialchars($row['nama_pelanggan']) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['no_hp']) ?></small>
                                </td>
                                <td class="fw-bold text-success">Rp <?= number_format($row['total_harga'], 0, ',', '.') ?></td>
                                <td><?= date('d M Y H:i', strtotime($row['tanggal_pembayaran'])) ?></td>
                                <td>
                                    <?php 
                                        $ext = strtolower(pathinfo($row['bukti_pembayaran'], PATHINFO_EXTENSION));
                                        $path = "uploads/bukti/" . htmlspecialchars($row['bukti_pembayaran']);
                                    ?>
                                    <?php if($ext === 'pdf'): ?>
                                        <a href="<?= $path ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf"></i> Lihat PDF</a>
                                    <?php else: ?>
                                        <a href="<?= $path ?>" target="_blank">
                                            <img src="<?= $path ?>" class="img-thumbnail img-bukti" alt="Bukti Pembayaran">
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge rounded-pill px-3 py-2 <?= $status_class ?>"><?= strtoupper($row['status_pembayaran']) ?></span></td>
                                <td>
                                    <?php if ($row['status_pembayaran'] === 'Menunggu Verifikasi'): ?>
                                        <form method="post" class="d-inline-block mb-1">
                                            <input type="hidden" name="id_booking" value="<?= $row['id_booking'] ?>">
                                            <input type="hidden" name="action" value="terima">
                                            <button type="submit" class="btn btn-sm btn-success rounded-pill" onclick="return confirm('Terima pembayaran ini?');">
                                                <i class="bi bi-check"></i> Lunas
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline-block">
                                            <input type="hidden" name="id_booking" value="<?= $row['id_booking'] ?>">
                                            <input type="hidden" name="action" value="tolak">
                                            <button type="submit" class="btn btn-sm btn-danger rounded-pill" onclick="return confirm('Tolak pembayaran ini?');">
                                                <i class="bi bi-x"></i> Tolak
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small"><i class="bi bi-check2-all"></i> Selesai di-review</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Belum ada data pembayaran yang diupload.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
