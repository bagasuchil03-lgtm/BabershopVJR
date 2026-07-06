<?php
// Koneksi ke Database
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_booking_barbershop"; // Sesuaikan dengan nama database Anda

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Menghitung Total Pemasukan dari pesanan yang sukses (Dikonfirmasi)
$sql_pemasukan = "SELECT SUM(total_harga) AS total_pemasukan FROM pesanan WHERE status = 'Dikonfirmasi'";
$result_pemasukan = $conn->query($sql_pemasukan);
$row_pemasukan = $result_pemasukan->fetch_assoc();
$total_pemasukan = $row_pemasukan['total_pemasukan'] ?? 0;

// Mengambil daftar pesanan
$sql_pesanan = "SELECT * FROM pesanan ORDER BY tanggal_pesanan DESC";
$result_pesanan = $conn->query($sql_pesanan);

// Fungsi format Rupiah
function formatRupiah($angka){
    return "Rp " . number_format($angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pesanan Admin</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <h2 class="mb-4">Manajemen Pesanan</h2>

    <?php if(isset($_GET['pesan'])): ?>
        <?php if($_GET['pesan'] == 'sukses_update'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Status pesanan berhasil diperbarui!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif($_GET['pesan'] == 'gagal_update'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Gagal memperbarui status pesanan.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Card Total Pemasukan -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-success shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Total Pemasukan</h5>
                    <h3 class="card-text"><?= formatRupiah($total_pemasukan) ?></h3>
                    <small>Dari pesanan yang dikonfirmasi</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar Pesanan -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Daftar Pesanan Terbaru</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Total Harga</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_pesanan->num_rows > 0): ?>
                            <?php while($row = $result_pesanan->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= formatRupiah($row['total_harga']) ?></td>
                                    <td>
                                        <?php if($row['status'] == 'Menunggu Konfirmasi'): ?>
                                            <span class="badge bg-warning text-dark"><?= $row['status'] ?></span>
                                        <?php elseif($row['status'] == 'Dikonfirmasi'): ?>
                                            <span class="badge bg-success"><?= $row['status'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><?= $row['status'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d-m-Y H:i', strtotime($row['tanggal_pesanan'])) ?></td>
                                    <td>
                                        <?php if($row['status'] == 'Menunggu Konfirmasi'): ?>
                                            <!-- Tombol untuk memicu Modal -->
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCekBukti<?= $row['id'] ?>">
                                                Cek Bukti Pembayaran
                                            </button>

                                            <!-- Modal Bootstrap -->
                                            <div class="modal fade" id="modalCekBukti<?= $row['id'] ?>" tabindex="-1" aria-labelledby="modalLabel<?= $row['id'] ?>" aria-hidden="true">
                                              <div class="modal-dialog">
                                                <div class="modal-content">
                                                  <div class="modal-header">
                                                    <h5 class="modal-title" id="modalLabel<?= $row['id'] ?>">Bukti Pembayaran - Order #<?= $row['id'] ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                  </div>
                                                  <div class="modal-body text-center">
                                                    <!-- Tampilkan gambar bukti pembayaran, sesuaikan path foldernya -->
                                                    <p class="text-muted small mb-2">File: <?= htmlspecialchars($row['bukti_pembayaran']) ?></p>
                                                    <div class="bg-light p-3 border rounded mb-3">
                                                        <span class="text-muted">Ilustrasi Gambar Bukti (Sesuaikan dengan direktori upload Anda)</span>
                                                    </div>
                                                    <p class="mb-0"><strong>Total Tagihan:</strong> <?= formatRupiah($row['total_harga']) ?></p>
                                                  </div>
                                                  <div class="modal-footer justify-content-between">
                                                    <!-- Form untuk aksi Tolak / Konfirmasi -->
                                                    <form action="proses_pembayaran.php" method="POST" class="w-100 d-flex justify-content-between">
                                                        <input type="hidden" name="id_pesanan" value="<?= $row['id'] ?>">
                                                        <button type="submit" name="aksi" value="tolak" class="btn btn-danger">Tolak Pembayaran</button>
                                                        <button type="submit" name="aksi" value="konfirmasi" class="btn btn-success">Konfirmasi Pembayaran</button>
                                                    </form>
                                                  </div>
                                                </div>
                                              </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center">Belum ada pesanan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
