<?php
/**
 * debug_setup.php — Menampilkan error yang sebenarnya dari setup_init.php
 * HAPUS FILE INI SETELAH SELESAI DEBUG!
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<pre style='font-family:monospace; background:#111; color:#0f0; padding:20px;'>\n";
echo "=== DEBUG SETUP ===\n\n";

echo "PHP Version: " . phpversion() . "\n";
echo "Loaded extensions: " . implode(', ', get_loaded_extensions()) . "\n\n";

echo "--- Cek koneksi.php ---\n";
try {
    require_once __DIR__ . '/koneksi.php';
    echo "✅ koneksi.php loaded OK\n";
    echo "   Connection status: " . ($conn->ping() ? 'Connected' : 'Failed') . "\n";
} catch (Throwable $e) {
    echo "❌ Error di koneksi.php: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . " line " . $e->getLine() . "\n";
    die("</pre>");
}

echo "\n--- Cek tabel yang ada ---\n";
$tables = $conn->query("SHOW TABLES");
if ($tables) {
    while ($row = $tables->fetch_row()) {
        echo "   ✅ " . $row[0] . "\n";
    }
}

echo "\n--- Coba jalankan setup_init.php ---\n";
try {
    // Simulasikan apa yang setup_init lakukan, step by step
    
    // Step 1: layanan table
    $check = $conn->query("SHOW TABLES LIKE 'layanan'");
    echo "   Tabel layanan: " . ($check->num_rows > 0 ? "ada" : "TIDAK ADA") . "\n";
    
    // Step 2: users table  
    $check = $conn->query("SHOW TABLES LIKE 'users'");
    echo "   Tabel users: " . ($check->num_rows > 0 ? "ada" : "TIDAK ADA") . "\n";
    
    // Step 3: booking table
    $check = $conn->query("SHOW TABLES LIKE 'booking'");
    echo "   Tabel booking: " . ($check->num_rows > 0 ? "ada" : "TIDAK ADA") . "\n";
    
    // Step 4: session
    echo "   Session: " . (session_status() === PHP_SESSION_ACTIVE ? "aktif" : "belum aktif") . "\n";
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
        echo "   Session started: " . (session_status() === PHP_SESSION_ACTIVE ? "OK" : "GAGAL") . "\n";
    }
    
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . " line " . $e->getLine() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== SELESAI ===\n";
echo "</pre>";
?>
