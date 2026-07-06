<?php
require_once 'koneksi.php';

$tanggal = $_GET['tanggal'] ?? '';
$id_barber = $_GET['id_barber'] ?? '';

if (empty($tanggal) || empty($id_barber)) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap']);
    exit;
}

// Jam operasional barbershop (09:00 - 22:00) interval 1 jam
$semua_jam = [];
for ($i = 9; $i <= 22; $i++) {
    $semua_jam[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
}

// Ambil jam yang sudah dibooking (kecuali yang batal)
$stmt = $conn->prepare("SELECT jam_booking FROM booking WHERE tanggal_booking = ? AND id_barber = ? AND status != 'batal'");
$stmt->bind_param("si", $tanggal, $id_barber);
$stmt->execute();
$result = $stmt->get_result();

$jam_terpakai = [];
while ($row = $result->fetch_assoc()) {
    // Ambil format H:i dari H:i:s di database (misal 14:00:00 -> 14:00)
    $jam_terpakai[] = substr($row['jam_booking'], 0, 5);
}

$jam_tersedia = [];
foreach ($semua_jam as $jam) {
    if (!in_array($jam, $jam_terpakai)) {
        // Jika tanggal yang dipilih adalah hari ini, kita cek apakah jam tersebut sudah lewat
        $tanggal_hari_ini = date('Y-m-d');
        if ($tanggal == $tanggal_hari_ini) {
            $jam_sekarang = date('H:i');
            if ($jam > $jam_sekarang) {
                $jam_tersedia[] = $jam;
            }
        } else {
            $jam_tersedia[] = $jam;
        }
    }
}

echo json_encode(['status' => 'success', 'data' => $jam_tersedia, 'terpakai' => $jam_terpakai]);
?>
