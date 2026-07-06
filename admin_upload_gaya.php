<?php
// admin_upload_gaya.php – Handler tambah/edit gaya rambut
session_start();
require_once __DIR__ . '/koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php?tab=gaya');
    exit();
}

$uploadDir = __DIR__ . '/uploads/gaya/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$errors = [];
$updated = false;

$action = $_POST['action'] ?? 'edit';
$id_gaya = intval($_POST['id_gaya'] ?? 0);
$nama_gaya = trim($_POST['nama_gaya'] ?? '');
$deskripsi = trim($_POST['deskripsi'] ?? '');

if (empty($nama_gaya)) {
    header('Location: admin.php?tab=gaya&error=' . urlencode('Nama Gaya Rambut wajib diisi.'));
    exit();
}

if ($action === 'edit' && $id_gaya <= 0) {
    header('Location: admin.php?tab=gaya&error=' . urlencode('ID Gaya tidak valid.'));
    exit();
}

$relativePath = ''; 
$photo_updated = false;

if (isset($_FILES['foto_gaya']) && $_FILES['foto_gaya']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['foto_gaya'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Gagal mengupload gambar (Error code: {$file['error']}).";
    } else {
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $errors[] = "Ukuran gambar terlalu besar. Maksimum 5MB.";
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mimeType, $allowedMimes)) {
                $errors[] = "Format gambar tidak didukung. Harap upload JPG, PNG, atau WEBP.";
            } else {
                $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $ext = $extMap[$mimeType] ?? 'jpg';

                $temp_id = ($action === 'add') ? 'new_' . time() : $id_gaya;
                $newFileName = 'gaya_' . $temp_id . '_' . time() . '.' . $ext;
                $destPath = $uploadDir . $newFileName;
                $relativePath = 'uploads/gaya/' . $newFileName;

                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $photo_updated = true;
                } else {
                    $errors[] = "Gagal memindahkan file yang diupload.";
                }
            }
        }
    }
}

if (empty($errors)) {
    if ($action === 'add') {
        $finalPhoto = $photo_updated ? $relativePath : 'default_gaya.png';
        $stmt = $conn->prepare("INSERT INTO gaya_rambut (nama_gaya, deskripsi, foto_gaya) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nama_gaya, $deskripsi, $finalPhoto);
        if ($stmt->execute()) {
            $updated = true;
        } else {
            $errors[] = "Gagal menambahkan gaya rambut.";
        }
        $stmt->close();
    } elseif ($action === 'edit') {
        if ($photo_updated) {
            // Hapus foto lama
            $stmt = $conn->prepare("SELECT foto_gaya FROM gaya_rambut WHERE id_gaya = ?");
            $stmt->bind_param("i", $id_gaya);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $oldPhoto = $row['foto_gaya'];
                if ($oldPhoto && $oldPhoto !== 'default_gaya.png' && !filter_var($oldPhoto, FILTER_VALIDATE_URL) && file_exists(__DIR__ . '/' . $oldPhoto)) {
                    @unlink(__DIR__ . '/' . $oldPhoto);
                }
            }
            $stmt->close();

            $stmt = $conn->prepare("UPDATE gaya_rambut SET nama_gaya = ?, deskripsi = ?, foto_gaya = ? WHERE id_gaya = ?");
            $stmt->bind_param("sssi", $nama_gaya, $deskripsi, $relativePath, $id_gaya);
        } else {
            $stmt = $conn->prepare("UPDATE gaya_rambut SET nama_gaya = ?, deskripsi = ? WHERE id_gaya = ?");
            $stmt->bind_param("ssi", $nama_gaya, $deskripsi, $id_gaya);
        }
        
        if ($stmt->execute()) {
            $updated = true;
        } else {
            $errors[] = "Gagal mengupdate gaya rambut.";
        }
        $stmt->close();
    }
}

if (!empty($errors)) {
    $errorMsg = implode(' | ', $errors);
    header('Location: admin.php?tab=gaya&error=' . urlencode($errorMsg));
} elseif ($updated) {
    header('Location: admin.php?tab=gaya&success=' . urlencode('Data gaya rambut berhasil diperbarui.'));
} else {
    header('Location: admin.php?tab=gaya&status=' . urlencode('Tidak ada perubahan yang disimpan.'));
}
exit();
