<?php
session_start();
require_once 'koneksi.php';

// Cek admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Query Laporan
if ($bulan === 'all') {
    $query = "SELECT b.*, l.nama_layanan, br.nama_barber 
              FROM booking b 
              JOIN layanan l ON b.id_layanan = l.id_layanan 
              JOIN barber br ON b.id_barber = br.id_barber 
              WHERE b.status = 'selesai' 
              AND YEAR(b.tanggal_booking) = ?
              ORDER BY b.tanggal_booking ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $tahun);
} else {
    $query = "SELECT b.*, l.nama_layanan, br.nama_barber 
              FROM booking b 
              JOIN layanan l ON b.id_layanan = l.id_layanan 
              JOIN barber br ON b.id_barber = br.id_barber 
              WHERE b.status = 'selesai' 
              AND MONTH(b.tanggal_booking) = ? 
              AND YEAR(b.tanggal_booking) = ?
              ORDER BY b.tanggal_booking ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $bulan, $tahun);
}
$stmt->execute();
$result = $stmt->get_result();

$laporan_data = [];
$total_pendapatan = 0;
while ($row = $result->fetch_assoc()) {
    $laporan_data[] = $row;
    $total_pendapatan += $row['total_harga'];
}

$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$nama_bulan_short = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];

// ── Data Diagram: Pendapatan & Jumlah Booking per Bulan (dalam 1 tahun) ──
$chart_monthly = [];
for ($m = 1; $m <= 12; $m++) {
    $chart_monthly[$m] = ['pendapatan' => 0, 'jumlah' => 0];
}
$q_chart = $conn->prepare(
    "SELECT MONTH(tanggal_booking) AS bln, COUNT(*) AS jml, SUM(total_harga) AS total
     FROM booking
     WHERE status = 'selesai' AND YEAR(tanggal_booking) = ?
     GROUP BY MONTH(tanggal_booking)"
);
$q_chart->bind_param("i", $tahun);
$q_chart->execute();
$r_chart = $q_chart->get_result();
while ($rc = $r_chart->fetch_assoc()) {
    $chart_monthly[(int)$rc['bln']] = [
        'pendapatan' => (int)$rc['total'],
        'jumlah'     => (int)$rc['jml']
    ];
}

// ── Data Diagram: Layanan Terpopuler ──
$q_svc = $conn->prepare(
    "SELECT l.nama_layanan, COUNT(*) AS jml
     FROM booking b
     JOIN layanan l ON b.id_layanan = l.id_layanan
     WHERE b.status = 'selesai' AND YEAR(b.tanggal_booking) = ?
     GROUP BY b.id_layanan, l.nama_layanan
     ORDER BY jml DESC
     LIMIT 6"
);
$q_svc->bind_param("i", $tahun);
$q_svc->execute();
$r_svc = $q_svc->get_result();
$chart_services = [];
while ($rs = $r_svc->fetch_assoc()) {
    $chart_services[] = $rs;
}

// ── Data Diagram: Barber Terlaris ──
$q_barber = $conn->prepare(
    "SELECT br.nama_barber, COUNT(*) AS jml
     FROM booking b
     JOIN barber br ON b.id_barber = br.id_barber
     WHERE b.status = 'selesai' AND YEAR(b.tanggal_booking) = ?
     GROUP BY b.id_barber, br.nama_barber
     ORDER BY jml DESC
     LIMIT 6"
);
$q_barber->bind_param("i", $tahun);
$q_barber->execute();
$r_barber = $q_barber->get_result();
$chart_barbers = [];
while ($rb = $r_barber->fetch_assoc()) {
    $chart_barbers[] = $rb;
}

// Handle Export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require_once 'SimpleXLSXGen.php';
    
    $periode_title = ($bulan === 'all') ? "Tahun " . $tahun : $nama_bulan[sprintf("%02d", $bulan)] . ' ' . $tahun;
    
    // Siapkan data untuk Excel
    $excelData = [];
    $excelData[] = ['Laporan Booking - ' . $periode_title];
    $excelData[] = ['']; // Empty row
    $excelData[] = ['No', 'Kode Booking', 'Tanggal', 'Nama Pelanggan', 'Layanan', 'Barber', 'Total Harga'];
    
    if (empty($laporan_data)) {
        $excelData[] = ['Tidak ada data.'];
    } else {
        $no = 1;
        foreach ($laporan_data as $row) {
            $excelData[] = [
                $no++,
                $row['kode_booking'],
                date('d/m/Y', strtotime($row['tanggal_booking'])),
                $row['nama_pelanggan'],
                $row['nama_layanan'],
                $row['nama_barber'],
                $row['total_harga']
            ];
        }
        $excelData[] = ['', '', '', '', '', 'Total Pendapatan:', $total_pendapatan];
    }
    
    $periode_str = ($bulan === 'all') ? "Tahun_" . $tahun : $nama_bulan[sprintf("%02d", $bulan)] . "_" . $tahun;
    $filename = "Laporan_Booking_" . $periode_str . ".xlsx";
    
    $xlsx = Shuchkin\SimpleXLSXGen::fromArray($excelData);
    $xlsx->downloadAs($filename);
    exit();
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Booking – Vijer Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --bg-base:    #0a0a0f;
            --bg-surface: #12121a;
            --bg-card:    #1e1e2e;
            --gold:       #C9A96E;
            --gold-glow:  rgba(201,169,110,0.15);
            --green:      #00e5a0;
            --red:        #ff4d6d;
            --text-main:  #f0f0f5;
            --text-muted: #7a7a92;
            --border:     rgba(201,169,110,0.15);
            --radius:     12px;
        }
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Outfit',sans-serif; background:var(--bg-base); color:var(--text-main); min-height:100vh; }

        /* Topbar */
        .topbar {
            background:var(--bg-surface); border-bottom:1px solid var(--border);
            padding:0 32px; height:64px; display:flex; align-items:center;
            justify-content:space-between; position:sticky; top:0; z-index:100;
        }
        .topbar-brand { display:flex; align-items:center; gap:10px; font-size:18px; font-weight:700; color:var(--gold); text-decoration:none; }

        /* Cards */
        .glass-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius); }

        /* Summary cards */
        .summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:24px; }
        .summary-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius); padding:18px 20px; transition:all 0.2s; }
        .summary-card:hover { border-color:var(--gold); }
        .s-label { font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; }
        .s-value { font-size:24px; font-weight:700; margin-top:4px; }

        /* Filter */
        .filter-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius); padding:20px 24px; margin-bottom:20px; }
        .f-label { font-size:12px; color:var(--text-muted); margin-bottom:4px; }
        .f-select, .f-input { background:var(--bg-card) !important; border:1px solid var(--border) !important; color:var(--text-main) !important; border-radius:8px !important; }
        .f-select:focus, .f-input:focus { border-color:var(--gold) !important; box-shadow:0 0 0 3px var(--gold-glow) !important; }

        /* Action buttons */
        .btn-filter { background:linear-gradient(135deg,#C9A96E,#a07840); color:#fff; border:none; border-radius:8px; font-weight:600; padding:9px 20px; }
        .btn-excel  { background:rgba(0,229,160,0.15); color:var(--green); border:1px solid rgba(0,229,160,0.35); border-radius:8px; font-weight:600; padding:9px 20px; text-decoration:none; transition:all .2s; }
        .btn-excel:hover { background:rgba(0,229,160,0.25); color:var(--green); }
        .btn-print  { background:rgba(255,77,109,0.15); color:var(--red); border:1px solid rgba(255,77,109,0.35); border-radius:8px; font-weight:600; padding:9px 20px; cursor:pointer; transition:all .2s; }
        .btn-print:hover { background:rgba(255,77,109,0.25); }

        /* Table */
        .table-wrap { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
        .table { margin:0; color:var(--text-main); }
        .table thead th { background:rgba(201,169,110,0.08); color:var(--gold); border-color:var(--border); font-size:11px; text-transform:uppercase; letter-spacing:.5px; padding:13px 16px; }
        .table tbody td { border-color:var(--border); padding:12px 16px; vertical-align:middle; font-size:14px; }
        .table tbody tr:hover { background:rgba(201,169,110,0.04); }
        .total-row td { background:rgba(0,229,160,0.06) !important; font-weight:700; font-size:15px; border-top:2px solid rgba(0,229,160,0.25) !important; }

        /* Empty state */
        .empty-state { text-align:center; padding:60px 20px; color:var(--text-muted); }
        .empty-state i { font-size:52px; opacity:.2; display:block; margin-bottom:14px; }

        /* Chart cards */
        .chart-grid { display:grid; grid-template-columns: 1fr 1fr; gap:18px; margin-bottom:24px; }
        .chart-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius); padding:20px 24px; }
        .chart-card h5 { font-size:14px; font-weight:700; color:var(--gold); margin-bottom:4px; }
        .chart-card .chart-subtitle { font-size:11px; color:var(--text-muted); margin-bottom:16px; }
        .chart-card canvas { width:100% !important; max-height:300px; }
        .chart-grid-3 { display:grid; grid-template-columns: 2fr 1fr 1fr; gap:18px; margin-bottom:24px; }
        @media (max-width:991px) {
            .chart-grid { grid-template-columns:1fr; }
            .chart-grid-3 { grid-template-columns:1fr; }
        }

        /* Print styles */
        @media print {
            .no-print { display:none !important; }
            body { background:#fff; color:#000; }
            .table { color:#000; }
            .table thead th { background:#333 !important; color:#fff !important; }
            .chart-grid, .chart-grid-3 { display:none !important; }
        }
    </style>
</head>
<body>

<div class="topbar no-print">
    <a class="topbar-brand" href="admin.php">
        <i class="bi bi-scissors"></i> Vijer Admin
    </a>
    <div class="d-flex align-items-center gap-3">
        <a href="admin.php" class="btn btn-sm" style="background:rgba(201,169,110,0.15);color:var(--gold);border:1px solid var(--border);border-radius:8px;">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div class="container-fluid py-4" style="max-width:1200px;" id="printArea">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-start mb-4 no-print">
        <div>
            <h1 style="font-size:24px;font-weight:700;color:var(--gold);">
                <i class="bi bi-file-earmark-spreadsheet me-2"></i>Laporan Booking
            </h1>
            <p style="color:var(--text-muted);font-size:13px;margin-top:4px;">Rekapitulasi transaksi booking yang berstatus selesai.</p>
        </div>
        <div class="d-flex gap-2 no-print">
            <button onclick="window.print()" class="btn-print">
                <i class="bi bi-printer me-1"></i> Cetak / PDF
            </button>
            <a href="admin_laporan.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&export=excel" class="btn-excel">
                <i class="bi bi-file-excel me-1"></i> Ekspor Excel
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid no-print">
        <div class="summary-card">
            <div class="s-label">Total Transaksi</div>
            <div class="s-value"><?= count($laporan_data) ?></div>
        </div>
        <div class="summary-card">
            <div class="s-label">Total Pendapatan</div>
            <div class="s-value" style="color:var(--gold);">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
        </div>
        <div class="summary-card">
            <div class="s-label">Periode</div>
            <div class="s-value" style="font-size:16px;margin-top:8px;">
                <?= ($bulan === 'all') ? "Tahun $tahun" : ($nama_bulan[sprintf("%02d",$bulan)] . " $tahun") ?>
            </div>
        </div>
    </div>

    <!-- ── Diagram / Grafik ── -->
    <div class="chart-grid-3 no-print">
        <!-- Chart 1: Pendapatan per Bulan (Bar) -->
        <div class="chart-card">
            <h5><i class="bi bi-bar-chart-line me-1"></i> Pendapatan per Bulan</h5>
            <div class="chart-subtitle">Tahun <?= $tahun ?> – Total: Rp <?= number_format(array_sum(array_column($chart_monthly, 'pendapatan')), 0, ',', '.') ?></div>
            <canvas id="chartPendapatan"></canvas>
        </div>
        <!-- Chart 2: Layanan Terpopuler (Doughnut) -->
        <div class="chart-card">
            <h5><i class="bi bi-pie-chart me-1"></i> Layanan Terpopuler</h5>
            <div class="chart-subtitle">Tahun <?= $tahun ?></div>
            <canvas id="chartLayanan"></canvas>
        </div>
        <!-- Chart 3: Barber Terlaris (Doughnut) -->
        <div class="chart-card">
            <h5><i class="bi bi-person-badge me-1"></i> Barber Terlaris</h5>
            <div class="chart-subtitle">Tahun <?= $tahun ?></div>
            <canvas id="chartBarber"></canvas>
        </div>
    </div>

    <div class="chart-grid no-print">
        <!-- Chart 4: Jumlah Booking per Bulan (Line) -->
        <div class="chart-card">
            <h5><i class="bi bi-graph-up me-1"></i> Jumlah Booking per Bulan</h5>
            <div class="chart-subtitle">Tahun <?= $tahun ?></div>
            <canvas id="chartBooking"></canvas>
        </div>
        <!-- Chart 5: Perbandingan Pendapatan & Booking (Combined) -->
        <div class="chart-card">
            <h5><i class="bi bi-activity me-1"></i> Tren Pendapatan vs Booking</h5>
            <div class="chart-subtitle">Tahun <?= $tahun ?></div>
            <canvas id="chartCombo"></canvas>
        </div>
    </div>

    <!-- Filter -->
    <div class="filter-card no-print">
        <form action="admin_laporan.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <div class="f-label">Bulan</div>
                <select name="bulan" class="form-select f-select">
                    <option value="all" <?= $bulan === 'all' ? 'selected' : '' ?>>Semua Bulan (Laporan Tahunan)</option>
                    <?php foreach ($nama_bulan as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $bulan == $num ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="f-label">Tahun</div>
                <select name="tahun" class="form-select f-select">
                    <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                        <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-5 d-flex gap-2 align-items-end">
                <button type="submit" class="btn-filter">
                    <i class="bi bi-funnel me-1"></i> Tampilkan
                </button>
                <a href="admin_laporan.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&export=excel" class="btn-excel">
                    <i class="bi bi-file-excel me-1"></i> Ekspor Excel
                </a>
                <button onclick="window.print()" type="button" class="btn-print">
                    <i class="bi bi-printer"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Print header (only visible when printing) -->
    <div class="d-none d-print-block mb-3 text-center">
        <h3 style="font-weight:700;">Vijer Barbershop</h3>
        <h5>Laporan Booking – <?= ($bulan === 'all') ? "Tahun $tahun" : ($nama_bulan[sprintf("%02d",$bulan)] . " $tahun") ?></h5>
        <p style="font-size:12px;">Dicetak: <?= date('d M Y H:i:s') ?></p>
        <hr>
    </div>

    <!-- Table -->
    <div class="table-wrap">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode Booking</th>
                        <th>Tanggal</th>
                        <th>Nama Pelanggan</th>
                        <th>Layanan</th>
                        <th>Barber</th>
                        <th>Total Harga</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($laporan_data)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    <p>Tidak ada data booking selesai pada periode ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($laporan_data as $row): ?>
                            <tr>
                                <td style="color:var(--text-muted);"><?= $no++ ?></td>
                                <td><span style="font-weight:700;color:var(--gold);"><?= htmlspecialchars($row['kode_booking'] ?? '-') ?></span></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal_booking'])) ?></td>
                                <td><?= htmlspecialchars($row['nama_pelanggan'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nama_layanan'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nama_barber'] ?? '-') ?></td>
                                <td style="font-weight:600;">Rp <?= number_format($row['total_harga'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="6" style="text-align:right;color:var(--text-muted);">Total Pendapatan</td>
                            <td style="color:var(--green);">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    const bulanLabels = <?= json_encode($nama_bulan_short) ?>;
    const pendapatanData = <?= json_encode(array_values(array_map(function($v){ return $v['pendapatan']; }, $chart_monthly))) ?>;
    const bookingData = <?= json_encode(array_values(array_map(function($v){ return $v['jumlah']; }, $chart_monthly))) ?>;
    const svcLabels = <?= json_encode(array_column($chart_services, 'nama_layanan')) ?>;
    const svcData = <?= json_encode(array_map('intval', array_column($chart_services, 'jml'))) ?>;
    const barberLabels = <?= json_encode(array_column($chart_barbers, 'nama_barber')) ?>;
    const barberData = <?= json_encode(array_map('intval', array_column($chart_barbers, 'jml'))) ?>;

    const goldColor = '#C9A96E';
    const goldLight = '#e5c483';
    const greenColor = '#00e5a0';
    const redColor = '#ff4d6d';
    const textMuted = '#7a7a92';

    const chartFont = { family: 'Outfit, sans-serif', size: 11 };
    const gridColor = 'rgba(201,169,110,0.08)';
    const defaultScales = {
        x: { ticks: { color: textMuted, font: chartFont }, grid: { color: gridColor } },
        y: { ticks: { color: textMuted, font: chartFont }, grid: { color: gridColor }, beginAtZero: true }
    };

    // 1. Pendapatan per Bulan (Bar)
    new Chart(document.getElementById('chartPendapatan'), {
        type: 'bar',
        data: {
            labels: bulanLabels,
            datasets: [{
                label: 'Pendapatan (Rp)',
                data: pendapatanData,
                backgroundColor: pendapatanData.map((v, i) => {
                    const max = Math.max(...pendapatanData);
                    return v === max ? goldColor : 'rgba(201,169,110,0.35)';
                }),
                borderColor: goldColor,
                borderWidth: 1,
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => 'Rp ' + ctx.raw.toLocaleString('id-ID')
                    }
                }
            },
            scales: {
                x: { ticks: { color: textMuted, font: chartFont }, grid: { display: false } },
                y: { ticks: { color: textMuted, font: chartFont, callback: v => 'Rp ' + (v/1000) + 'k' }, grid: { color: gridColor }, beginAtZero: true }
            }
        }
    });

    // 2. Layanan Terpopuler (Doughnut)
    const svcColors = ['#C9A96E','#00e5a0','#ff4d6d','#6C63FF','#FF9F43','#54a0ff'];
    new Chart(document.getElementById('chartLayanan'), {
        type: 'doughnut',
        data: {
            labels: svcLabels,
            datasets: [{
                data: svcData,
                backgroundColor: svcColors.slice(0, svcLabels.length),
                borderColor: '#12121a',
                borderWidth: 3,
            }]
        },
        options: {
            responsive: true,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: textMuted, font: chartFont, padding: 12, usePointStyle: true, pointStyleWidth: 8 }
                }
            }
        }
    });

    // 3. Barber Terlaris (Doughnut)
    const barberColors = ['#C9A96E','#54a0ff','#ff4d6d','#00e5a0','#FF9F43','#6C63FF'];
    new Chart(document.getElementById('chartBarber'), {
        type: 'doughnut',
        data: {
            labels: barberLabels,
            datasets: [{
                data: barberData,
                backgroundColor: barberColors.slice(0, barberLabels.length),
                borderColor: '#12121a',
                borderWidth: 3,
            }]
        },
        options: {
            responsive: true,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: textMuted, font: chartFont, padding: 12, usePointStyle: true, pointStyleWidth: 8 }
                }
            }
        }
    });

    // 4. Jumlah Booking per Bulan (Line)
    new Chart(document.getElementById('chartBooking'), {
        type: 'line',
        data: {
            labels: bulanLabels,
            datasets: [{
                label: 'Jumlah Booking',
                data: bookingData,
                borderColor: greenColor,
                backgroundColor: 'rgba(0,229,160,0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: greenColor,
                pointBorderColor: '#12121a',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: defaultScales
        }
    });

    // 5. Tren Pendapatan vs Booking (Combo)
    new Chart(document.getElementById('chartCombo'), {
        type: 'bar',
        data: {
            labels: bulanLabels,
            datasets: [
                {
                    label: 'Pendapatan (Rp)',
                    data: pendapatanData,
                    backgroundColor: 'rgba(201,169,110,0.3)',
                    borderColor: goldColor,
                    borderWidth: 1,
                    borderRadius: 4,
                    yAxisID: 'y',
                    order: 2,
                },
                {
                    label: 'Jumlah Booking',
                    data: bookingData,
                    type: 'line',
                    borderColor: redColor,
                    backgroundColor: 'rgba(255,77,109,0.1)',
                    tension: 0.4,
                    pointBackgroundColor: redColor,
                    pointBorderColor: '#12121a',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    yAxisID: 'y1',
                    order: 1,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    labels: { color: textMuted, font: chartFont, usePointStyle: true, pointStyleWidth: 8 }
                }
            },
            scales: {
                x: { ticks: { color: textMuted, font: chartFont }, grid: { display: false } },
                y: {
                    position: 'left',
                    ticks: { color: goldColor, font: chartFont, callback: v => 'Rp ' + (v/1000) + 'k' },
                    grid: { color: gridColor },
                    beginAtZero: true,
                },
                y1: {
                    position: 'right',
                    ticks: { color: redColor, font: chartFont },
                    grid: { drawOnChartArea: false },
                    beginAtZero: true,
                }
            }
        }
    });
})();
</script>
</body>
</html>
