<?php
require_once 'koneksi.php';

if (!isset($_GET['id'])) {
    die("ID Booking tidak ditemukan.");
}

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT file_data FROM bukti_pembayaran_files WHERE kode_booking = ? OR id_booking = ?");
$stmt->bind_param("ss", $id, $id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $data = $row['file_data'];
    // format is data:image/jpeg;base64,.....
    if (strpos($data, 'data:') === 0) {
        list($type, $data) = explode(';', $data);
        list(, $data)      = explode(',', $data);
        $type = str_replace('data:', '', $type);
        $decoded = base64_decode($data);
        
        header("Content-Type: $type");
        echo $decoded;
    } else {
        echo "Format file tidak valid.";
    }
} else {
    // Cek apakah ada di file lokal (fallback untuk booking lama yang belum hilang)
    $stmt2 = $conn->prepare("SELECT bukti_pembayaran FROM booking WHERE kode_booking = ? OR id_booking = ?");
    $stmt2->bind_param("ss", $id, $id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    if ($row2 = $res2->fetch_assoc()) {
        $path = 'uploads/bukti/' . $row2['bukti_pembayaran'];
        if (file_exists($path) && !empty($row2['bukti_pembayaran'])) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if($ext == 'pdf') header("Content-Type: application/pdf");
            else if($ext == 'png') header("Content-Type: image/png");
            else header("Content-Type: image/jpeg");
            readfile($path);
        } else {
            echo "File bukti hilang dari server (karena server restart).";
        }
    } else {
        echo "Bukti tidak ditemukan.";
    }
}
?>
