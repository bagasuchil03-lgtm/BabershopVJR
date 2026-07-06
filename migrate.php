<?php
// migrate.php
require_once 'koneksi.php';

echo "Starting database migration...\n";

// Helper to check if column exists
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// 1. Alter Booking Table
if (!columnExists($conn, 'booking', 'metode_pembayaran')) {
    $conn->query("ALTER TABLE booking ADD COLUMN metode_pembayaran VARCHAR(50) NULL AFTER catatan");
    echo "- Added column 'metode_pembayaran' to table 'booking'\n";
}
if (!columnExists($conn, 'booking', 'status_pembayaran')) {
    $conn->query("ALTER TABLE booking ADD COLUMN status_pembayaran ENUM('Menunggu Pembayaran', 'Menunggu Verifikasi', 'Lunas', 'Ditolak') NOT NULL DEFAULT 'Menunggu Pembayaran' AFTER metode_pembayaran");
    echo "- Added column 'status_pembayaran' to table 'booking'\n";
}
if (!columnExists($conn, 'booking', 'bukti_pembayaran')) {
    $conn->query("ALTER TABLE booking ADD COLUMN bukti_pembayaran VARCHAR(255) NULL AFTER status_pembayaran");
    echo "- Added column 'bukti_pembayaran' to table 'booking'\n";
}
if (!columnExists($conn, 'booking', 'tanggal_pembayaran')) {
    $conn->query("ALTER TABLE booking ADD COLUMN tanggal_pembayaran DATETIME NULL AFTER bukti_pembayaran");
    echo "- Added column 'tanggal_pembayaran' to table 'booking'\n";
}
if (!columnExists($conn, 'booking', 'tanggal_selesai')) {
    $conn->query("ALTER TABLE booking ADD COLUMN tanggal_selesai DATETIME NULL DEFAULT NULL AFTER checkin_at");
    echo "- Added column 'tanggal_selesai' to table 'booking'\n";
}

// 2. Alter Users Table
if (!columnExists($conn, 'users', 'status')) {
    $conn->query("ALTER TABLE users ADD COLUMN status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif' AFTER role");
    echo "- Added column 'status' to table 'users'\n";
}

// 3. Create user_logs Table
$conn->query("
    CREATE TABLE IF NOT EXISTS user_logs (
        id_log INT AUTO_INCREMENT PRIMARY KEY,
        id_user INT,
        action VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE SET NULL
    ) ENGINE=InnoDB;
");
echo "- Checked / Created table 'user_logs'\n";

// 4. Create activity_logs Table
$conn->query("
    CREATE TABLE IF NOT EXISTS activity_logs (
        id_activity INT AUTO_INCREMENT PRIMARY KEY,
        id_user INT,
        activity TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE SET NULL
    ) ENGINE=InnoDB;
");
echo "- Checked / Created table 'activity_logs'\n";

// 5. Create uploads directories
if (!file_exists('uploads/barber')) {
    mkdir('uploads/barber', 0777, true);
    echo "- Created directory 'uploads/barber'\n";
}
if (!file_exists('uploads/bukti')) {
    mkdir('uploads/bukti', 0777, true);
    echo "- Created directory 'uploads/bukti'\n";
}

// 6. Add image_path column to layanan table
if (!columnExists($conn, 'layanan', 'image_path')) {
    $conn->query("ALTER TABLE layanan ADD COLUMN image_path VARCHAR(255) NULL AFTER durasi_menit");
    echo "- Added column 'image_path' to table 'layanan'\n";
}

// 7. Fix broken gaya rambut foto URLs
$fix_gaya = [
    'Two Block' => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=600&q=80',
    'French Crop' => 'https://images.unsplash.com/photo-1519345182560-3f2917c472ef?auto=format&fit=crop&w=600&q=80',
];
foreach ($fix_gaya as $nama => $url_baru) {
    $stmt = $conn->prepare("SELECT foto_gaya FROM gaya_rambut WHERE nama_gaya = ?");
    $stmt->bind_param("s", $nama);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $foto_lama = $row['foto_gaya'];
        // Update only if the current URL is broken (known broken IDs)
        if (strpos($foto_lama, 'photo-1593726852431') !== false || 
            strpos($foto_lama, 'photo-1512809187303') !== false ||
            $foto_lama === 'default_gaya.png') {
            $upd = $conn->prepare("UPDATE gaya_rambut SET foto_gaya = ? WHERE nama_gaya = ?");
            $upd->bind_param("ss", $url_baru, $nama);
            $upd->execute();
            echo "- Fixed broken foto for gaya rambut: $nama\n";
        }
    }
}

// 8. Create settings table for barbershop profile
$conn->query("
    CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
");
echo "- Checked / Created table 'settings'\n";

// 9. Create uploads directories for content management
if (!file_exists('uploads/homepage')) {
    mkdir('uploads/homepage', 0777, true);
    echo "- Created directory 'uploads/homepage'\n";
}
if (!file_exists('uploads/qris')) {
    mkdir('uploads/qris', 0777, true);
    echo "- Created directory 'uploads/qris'\n";
}
if (!file_exists('uploads/service')) {
    mkdir('uploads/service', 0777, true);
    echo "- Created directory 'uploads/service'\n";
}

// 10. Set default QRIS merchant settings (if not already set)
require_once 'settings.php';
if (get_setting('qris_merchant_name') === null) {
    set_setting('qris_merchant_name', 'NAFIS LAILATUL BADRIYAH');
    echo "- Set default QRIS merchant name\n";
}
if (get_setting('qris_merchant_bank') === null) {
    set_setting('qris_merchant_bank', 'BRI (BritAma)');
    echo "- Set default QRIS merchant bank\n";
}
if (get_setting('qris_merchant_account') === null) {
    set_setting('qris_merchant_account', '0022 **** **** 509');
    echo "- Set default QRIS merchant account\n";
}

// 11. Create homepage_photos table
$conn->query("
    CREATE TABLE IF NOT EXISTS homepage_photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
");
echo "- Checked / Created table 'homepage_photos'\n";

// 12. Create notifikasi table
$conn->query("
    CREATE TABLE IF NOT EXISTS notifikasi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        no_hp VARCHAR(20) NOT NULL,
        pesan TEXT NOT NULL,
        status_baca TINYINT(1) DEFAULT 0,
        tanggal_dibuat TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
");
echo "- Checked / Created table 'notifikasi'\n";

echo "Database migration completed successfully!\n";
?>
