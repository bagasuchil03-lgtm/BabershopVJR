<?php
/**
 * setup_init.php – Inisialisasi Pengaturan Awal & Migrasi DB
 * ===========================================================
 * Jalankan sekali via browser: http://localhost/db_booking_barbershop/setup_init.php
 * atau via CLI: php setup_init.php
 *
 * Skrip ini AMAN dijalankan berkali-kali (idempoten):
 *  - Tidak akan menghapus data yang sudah ada.
 *  - Hanya menambahkan kolom / baris jika belum ada.
 */

// ── Keamanan: nonaktifkan setelah setup selesai ────────────────────────────
// Ubah menjadi false setelah pertama kali dijalankan di production.
define('SETUP_ENABLED', true);

if (!SETUP_ENABLED) {
    die('<b style="color:red">Setup sudah dinonaktifkan. Hapus atau ubah SETUP_ENABLED menjadi true untuk mengaktifkan kembali.</b>');
}

session_start();
require_once __DIR__ . '/koneksi.php';

$results = [];
$hasError = false;

// ─────────────────────────────────────────────────────────────────────────────
// FUNGSI HELPER
// ─────────────────────────────────────────────────────────────────────────────

function addResult(array &$results, bool $ok, string $label, string $detail = ''): void {
    $results[] = ['ok' => $ok, 'label' => $label, 'detail' => $detail];
}

// ─────────────────────────────────────────────────────────────────────────────
// LANGKAH 1: Tambahkan kolom `image_path` ke tabel `layanan` jika belum ada
// ─────────────────────────────────────────────────────────────────────────────
$tableLayananExists = $conn->query("SHOW TABLES LIKE 'layanan'");
if ($tableLayananExists && $tableLayananExists->num_rows > 0) {
    $colCheck = $conn->query("SHOW COLUMNS FROM `layanan` LIKE 'image_path'");
    if ($colCheck->num_rows === 0) {
        $res = $conn->query("ALTER TABLE `layanan` ADD COLUMN `image_path` VARCHAR(255) NULL DEFAULT NULL AFTER `durasi_menit`");
        if ($res) {
            addResult($results, true, 'Kolom <code>image_path</code> ditambahkan ke tabel <code>layanan</code>');
        } else {
            addResult($results, false, 'Gagal menambahkan kolom <code>image_path</code>', $conn->error);
            $hasError = true;
        }
    } else {
        addResult($results, true, 'Kolom <code>image_path</code> sudah ada – dilewati');
    }
} else {
    addResult($results, false, 'Tabel <code>layanan</code> belum ada', 'Pastikan database sudah di-import terlebih dahulu.');
    $hasError = true;
}

// ─────────────────────────────────────────────────────────────────────────────
// LANGKAH 1.5: Tambahkan kolom `status` ke tabel `users` jika belum ada
// ─────────────────────────────────────────────────────────────────────────────
$tableUsersExists = $conn->query("SHOW TABLES LIKE 'users'");
if ($tableUsersExists && $tableUsersExists->num_rows > 0) {
    $colCheckStatus = $conn->query("SHOW COLUMNS FROM `users` LIKE 'status'");
    if ($colCheckStatus->num_rows === 0) {
        $resStatus = $conn->query("ALTER TABLE `users` ADD COLUMN `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif' AFTER `role`");
        if ($resStatus) {
            addResult($results, true, 'Kolom <code>status</code> ditambahkan ke tabel <code>users</code>');
        } else {
            addResult($results, false, 'Gagal menambahkan kolom <code>status</code> ke users', $conn->error);
            $hasError = true;
        }
    } else {
        addResult($results, true, 'Kolom <code>status</code> di users sudah ada – dilewati');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// LANGKAH 1.6: Tambahkan kolom baru ke tabel `booking` jika belum ada (Guest Checkout Migration)
// ─────────────────────────────────────────────────────────────────────────────
$tableBookingExists = $conn->query("SHOW TABLES LIKE 'booking'");
if ($tableBookingExists && $tableBookingExists->num_rows > 0) {
    $bookingCols = [
        'kode_booking' => "VARCHAR(20) NULL UNIQUE AFTER id_booking",
        'nama_pelanggan' => "VARCHAR(100) NULL AFTER kode_booking",
        'no_hp' => "VARCHAR(20) NULL AFTER nama_pelanggan",
        'catatan' => "TEXT NULL AFTER total_harga",
        'metode_pembayaran' => "VARCHAR(50) NULL AFTER catatan",
        'status_pembayaran' => "ENUM('Menunggu Pembayaran', 'Menunggu Verifikasi', 'Lunas', 'Ditolak') NOT NULL DEFAULT 'Menunggu Pembayaran' AFTER metode_pembayaran",
        'bukti_pembayaran' => "VARCHAR(255) NULL AFTER status_pembayaran",
        'tanggal_pembayaran' => "DATETIME NULL AFTER bukti_pembayaran",
        'checkin_at' => "DATETIME NULL DEFAULT NULL AFTER tanggal_pembayaran",
        'tanggal_selesai' => "DATETIME NULL DEFAULT NULL AFTER checkin_at"
    ];

    foreach ($bookingCols as $col => $def) {
        $checkCol = $conn->query("SHOW COLUMNS FROM `booking` LIKE '$col'");
        if ($checkCol->num_rows === 0) {
            $resCol = $conn->query("ALTER TABLE `booking` ADD COLUMN `$col` $def");
            if ($resCol) {
                addResult($results, true, "Kolom <code>$col</code> ditambahkan ke tabel <code>booking</code>");
            } else {
                addResult($results, false, "Gagal menambahkan <code>$col</code> ke booking", $conn->error);
                global $hasError;
                $hasError = true;
            }
        }
    }
    addResult($results, true, 'Pengecekan struktur tabel <code>booking</code> selesai');

    // ─────────────────────────────────────────────────────────────────────────────
    // LANGKAH 1.7: Hapus kolom `id_user` dari tabel `booking` jika ada (Sisa versi lama)
    // ─────────────────────────────────────────────────────────────────────────────
    $checkIdUser = $conn->query("SHOW COLUMNS FROM `booking` LIKE 'id_user'");
    if ($checkIdUser->num_rows > 0) {
        try {
            $conn->query("ALTER TABLE `booking` DROP FOREIGN KEY `booking_ibfk_1`");
        } catch (Exception $e) { }
        
        $resDrop = $conn->query("ALTER TABLE `booking` DROP COLUMN `id_user`");
        if ($resDrop) {
            addResult($results, true, 'Sisa kolom lama <code>id_user</code> dan Foreign Key dihapus dari tabel <code>booking</code>');
        } else {
            addResult($results, false, 'Gagal menghapus <code>id_user</code> dari booking', $conn->error);
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// LANGKAH 2: Buat tabel `settings` jika belum ada
// ─────────────────────────────────────────────────────────────────────────────
$createSettings = "
    CREATE TABLE IF NOT EXISTS `settings` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `key`        VARCHAR(100) NOT NULL UNIQUE,
        `value`      TEXT,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if ($conn->query($createSettings)) {
    addResult($results, true, 'Tabel <code>settings</code> siap (dibuat atau sudah ada)');
} else {
    addResult($results, false, 'Gagal membuat tabel <code>settings</code>', $conn->error);
    $hasError = true;
}

// ─────────────────────────────────────────────────────────────────────────────
// LANGKAH 3: Masukkan baris default ke `settings` (idempoten via INSERT IGNORE)
// ─────────────────────────────────────────────────────────────────────────────
$defaults = [
    ['shop_open',     '0'],
    ['bank_account',  'BCA 1234567890 a.n. Vijer Barbershop'],
];

foreach ($defaults as [$key, $val]) {
    $stmt = $conn->prepare("INSERT IGNORE INTO `settings` (`key`, `value`) VALUES (?, ?)");
    $stmt->bind_param('ss', $key, $val);
    if ($stmt->execute()) {
        $action = $stmt->affected_rows > 0 ? 'ditambahkan' : 'sudah ada – dilewati';
        addResult($results, true, "Setting <code>{$key}</code> = <em>{$val}</em> → {$action}");
    } else {
        addResult($results, false, "Gagal memasukkan setting <code>{$key}</code>", $conn->error);
        $hasError = true;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// LANGKAH 4: Buat folder uploads & periksa izin tulis
// ─────────────────────────────────────────────────────────────────────────────
$uploadDir = __DIR__ . '/uploads/service/';

if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        addResult($results, true, "Folder <code>uploads/service/</code> berhasil dibuat");
    } else {
        addResult($results, false, "Gagal membuat folder <code>uploads/service/</code>", 'Buat manual dan set permission 755');
        $hasError = true;
    }
} else {
    addResult($results, true, "Folder <code>uploads/service/</code> sudah ada");
}

if (is_writable($uploadDir)) {
    addResult($results, true, "Folder <code>uploads/service/</code> dapat ditulisi ✔");
} else {
    addResult($results, false, "Folder <code>uploads/service/</code> <strong>TIDAK</strong> dapat ditulisi", 'Jalankan: icacls "' . $uploadDir . '" /grant Everyone:(OI)(CI)F');
    $hasError = true;
}

// ─────────────────────────────────────────────────────────────────────────────
// LANGKAH 5: Migrasikan foto lama (uploads/service/{id}.jpg) ke image_path DB
// ─────────────────────────────────────────────────────────────────────────────
$layananRes = $conn->query("SELECT id_layanan, image_path FROM layanan");
$migrated = 0;
while ($row = $layananRes->fetch_assoc()) {
    // Only migrate if image_path is currently empty
    if (!empty($row['image_path'])) continue;

    $legacyFile = $uploadDir . $row['id_layanan'] . '.jpg';
    if (file_exists($legacyFile)) {
        // Rename to new naming convention
        $newName = 'service_' . $row['id_layanan'] . '.jpg';
        $newPath = $uploadDir . $newName;
        if (!file_exists($newPath)) {
            rename($legacyFile, $newPath);
        }
        $relPath = 'uploads/service/' . $newName;
        $stmt = $conn->prepare("UPDATE layanan SET image_path = ? WHERE id_layanan = ?");
        $stmt->bind_param('si', $relPath, $row['id_layanan']);
        $stmt->execute();
        $migrated++;
    }
}
addResult($results, true, "Migrasi foto lama: <strong>{$migrated}</strong> foto diperbarui ke kolom <code>image_path</code>");

// ─────────────────────────────────────────────────────────────────────────────
// LANGKAH 6: Buat tabel `homepage_gallery` jika belum ada
// ─────────────────────────────────────────────────────────────────────────────
$createGallery = "
    CREATE TABLE IF NOT EXISTS `homepage_gallery` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `image_path` VARCHAR(255) NOT NULL,
        `status`     ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if ($conn->query($createGallery)) {
    addResult($results, true, 'Tabel <code>homepage_gallery</code> siap (dibuat atau sudah ada)');
} else {
    addResult($results, false, 'Gagal membuat tabel <code>homepage_gallery</code>', $conn->error);
    $hasError = true;
}

// ─────────────────────────────────────────────────────────────────────────────
// LANGKAH 7: Buat tabel `notifikasi` jika belum ada
// ─────────────────────────────────────────────────────────────────────────────
$createNotifikasi = "
    CREATE TABLE IF NOT EXISTS `notifikasi` (
        `id`             INT AUTO_INCREMENT PRIMARY KEY,
        `no_hp`          VARCHAR(20) NOT NULL,
        `pesan`          TEXT NOT NULL,
        `status_baca`    TINYINT(1) DEFAULT 0,
        `tanggal_dibuat` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if ($conn->query($createNotifikasi)) {
    addResult($results, true, 'Tabel <code>notifikasi</code> siap (dibuat atau sudah ada)');
} else {
    addResult($results, false, 'Gagal membuat tabel <code>notifikasi</code>', $conn->error);
    $hasError = true;
}

// ─────────────────────────────────────────────────────────────────────────────
// OUTPUT HTML
// ─────────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup Init – Vijer Barbershop</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --green: #00e5a0; --red: #ff4d6d; --gold: #C9A96E; --bg: #0a0a0f; --card: #1e1e2e; --border: rgba(201,169,110,0.2); }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: #f0f0f5; min-height: 100vh; padding: 40px 20px; }
        .container { max-width: 700px; margin: 0 auto; }
        h1 { font-size: 26px; font-weight: 700; color: var(--gold); margin-bottom: 6px; }
        .subtitle { font-size: 14px; color: #6666888; margin-bottom: 32px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; margin-bottom: 24px; }
        .step { display: flex; align-items: flex-start; gap: 14px; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .step:last-child { border-bottom: none; }
        .icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; margin-top: 1px; }
        .icon.ok  { background: rgba(0,229,160,0.15); color: var(--green); }
        .icon.err { background: rgba(255,77,109,0.15); color: var(--red); }
        .step-body { flex: 1; }
        .step-label { font-size: 14px; font-weight: 500; line-height: 1.5; }
        .step-detail { font-size: 12px; color: #999; margin-top: 4px; font-family: monospace; background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 4px; }
        .summary { display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-radius: 12px; font-size: 15px; font-weight: 600; margin-top: 8px; }
        .summary.ok  { background: rgba(0,229,160,0.08); border: 1px solid rgba(0,229,160,0.3); color: var(--green); }
        .summary.err { background: rgba(255,77,109,0.08); border: 1px solid rgba(255,77,109,0.3); color: var(--red); }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 10px; font-family: 'Outfit', sans-serif; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; border: none; margin-top: 16px; transition: all 0.2s; }
        .btn-gold { background: linear-gradient(135deg, #C9A96E, #a07840); color: #000; }
        .btn-gold:hover { opacity: 0.9; transform: translateY(-1px); }
        code, em { background: rgba(201,169,110,0.1); color: var(--gold); padding: 1px 5px; border-radius: 4px; font-style: normal; }
    </style>
</head>
<body>
<div class="container">
    <h1>⚙️ Setup Inisialisasi</h1>
    <p class="subtitle">Vijer Barbershop – Pengaturan awal database & folder</p>

    <div class="card">
        <?php foreach ($results as $r): ?>
        <div class="step">
            <div class="icon <?= $r['ok'] ? 'ok' : 'err' ?>">
                <?= $r['ok'] ? '✔' : '✗' ?>
            </div>
            <div class="step-body">
                <div class="step-label"><?= $r['label'] ?></div>
                <?php if (!empty($r['detail'])): ?>
                <div class="step-detail"><?= htmlspecialchars($r['detail']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="summary <?= $hasError ? 'err' : 'ok' ?>">
        <?php if ($hasError): ?>
            ❌ Setup selesai dengan error – periksa langkah di atas yang gagal
        <?php else: ?>
            ✅ Semua langkah berhasil! Sistem siap digunakan.
        <?php endif; ?>
    </div>

    <a href="admin.php" class="btn btn-gold">→ Buka Admin Dashboard</a>
</div>
</body>
</html>
