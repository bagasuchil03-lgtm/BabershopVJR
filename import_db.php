<?php
/**
 * import_db.php — Import database SQL ke Railway
 * ================================================
 * Jalankan via browser setelah deploy ke Railway:
 * https://DOMAIN-KAMU.up.railway.app/import_db.php
 * 
 * HAPUS FILE INI SETELAH IMPORT BERHASIL!
 */

// Konfigurasi Railway (internal / public fallback)
$host     = getenv('MYSQLHOST') ?: 'hayabusa.proxy.rlwy.net';
$username = getenv('MYSQLUSER') ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: 'AErAuDgWqwPEDFcTWIMbwrsHZIXBCLEr';
$database = getenv('MYSQLDATABASE') ?: 'railway';
$port     = getenv('MYSQLPORT') ?: 26577;

echo "<pre style='font-family:monospace; background:#111; color:#0f0; padding:20px;'>\n";
echo "=== IMPORT DATABASE KE RAILWAY ===\n\n";

// Koneksi
$conn = new mysqli($host, $username, $password, $database, $port);
if ($conn->connect_error) {
    die("❌ Koneksi gagal: " . $conn->connect_error . "\n");
}
echo "✅ Koneksi ke Railway MySQL berhasil!\n\n";

// Baca file SQL
$sqlFile = __DIR__ . '/export.sql';
if (!file_exists($sqlFile)) {
    die("❌ File export.sql tidak ditemukan!\n");
}

$sql = file_get_contents($sqlFile);
echo "📄 File export.sql ditemukan (" . round(filesize($sqlFile) / 1024, 1) . " KB)\n";
echo "⏳ Mengeksekusi query...\n\n";

// Eksekusi multi query
$conn->set_charset("utf8mb4");

if ($conn->multi_query($sql)) {
    $queryCount = 0;
    do {
        $queryCount++;
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    // Cek error di query terakhir
    if ($conn->errno) {
        echo "⚠️ Warning pada query ke-$queryCount: " . $conn->error . "\n";
    }
    
    echo "✅ Import selesai! Total batch query diproses: $queryCount\n\n";
} else {
    echo "❌ Error: " . $conn->error . "\n\n";
}

// Verifikasi: tampilkan daftar tabel
echo "=== DAFTAR TABEL ===\n";
$tables = $conn->query("SHOW TABLES");
if ($tables) {
    $count = 0;
    while ($row = $tables->fetch_row()) {
        $count++;
        // Hitung jumlah baris
        $countResult = $conn->query("SELECT COUNT(*) as total FROM `{$row[0]}`");
        $total = $countResult ? $countResult->fetch_assoc()['total'] : '?';
        echo "  $count. {$row[0]} ($total baris)\n";
    }
    echo "\nTotal: $count tabel\n";
}

echo "\n=== SELESAI ===\n";
echo "⚠️ HAPUS FILE import_db.php DAN export.sql SETELAH SELESAI!\n";
echo "</pre>";

$conn->close();
?>
