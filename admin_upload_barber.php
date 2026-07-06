<?php
// admin_upload_barber.php – Handler tambah/edit barber
session_start();
require_once __DIR__ . '/koneksi.php';

// Admin check
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php?tab=barber');
    exit();
}

// Pastikan direktori upload ada
$uploadDir = __DIR__ . '/uploads/barber/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$errors = [];
$updated = false;

$action = $_POST['action'] ?? 'edit';
$id_barber = intval($_POST['id_barber'] ?? 0);
$nama_barber = trim($_POST['nama_barber'] ?? '');
$keahlian = trim($_POST['keahlian'] ?? '');

if ($action !== 'delete' && $action !== 'toggle_status' && empty($nama_barber)) {
    header('Location: admin.php?tab=barber&error=' . urlencode('Nama Barber wajib diisi.'));
    exit();
}

if (in_array($action, ['edit', 'delete', 'toggle_status']) && $id_barber <= 0) {
    header('Location: admin.php?tab=barber&error=' . urlencode('ID Barber tidak valid.'));
    exit();
}

$relativePath = ''; // Default for no new photo
$photo_updated = false;

// Cek apakah ada upload foto
if (isset($_FILES['foto_barber']) && $_FILES['foto_barber']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['foto_barber'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Gagal mengupload gambar barber (Error code: {$file['error']}).";
    } else {
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $errors[] = "Ukuran gambar terlalu besar. Maksimum 5MB.";
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mimeType, $allowedMimes)) {
                $errors[] = "Format gambar tidak didukung. Harap upload JPG, PNG, atau WEBP.";
            } else {
                $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                $ext = $extMap[$mimeType] ?? 'jpg';

                $temp_id = ($action === 'add') ? 'new_' . time() : $id_barber;
                $newFileName = 'barber_' . $temp_id . '_' . time() . '.' . $ext;
                $destPath = $uploadDir . $newFileName;
                $relativePath = 'uploads/barber/' . $newFileName;

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
        $finalPhoto = $photo_updated ? $relativePath : 'default_barber.png';
        $stmt = $conn->prepare("INSERT INTO barber (nama_barber, spesialisasi, foto_barber, status) VALUES (?, ?, ?, 'aktif')");
        $stmt->bind_param("sss", $nama_barber, $keahlian, $finalPhoto);
        if ($stmt->execute()) {
            $updated = true;
        } else {
            $errors[] = "Gagal menambahkan barber.";
        }
        $stmt->close();
    } elseif ($action === 'edit') {
        if ($photo_updated) {
            // Hapus foto lama
            $stmt = $conn->prepare("SELECT foto_barber FROM barber WHERE id_barber = ?");
            $stmt->bind_param("i", $id_barber);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $oldPhoto = $row['foto_barber'];
                if ($oldPhoto && $oldPhoto !== 'default_barber.png' && file_exists(__DIR__ . '/' . $oldPhoto)) {
                    @unlink(__DIR__ . '/' . $oldPhoto);
                }
            }
            $stmt->close();

            $status_barber = $_POST['status_barber'] ?? 'aktif';
            $stmt = $conn->prepare("UPDATE barber SET nama_barber = ?, spesialisasi = ?, foto_barber = ?, status = ? WHERE id_barber = ?");
            $stmt->bind_param("ssssi", $nama_barber, $keahlian, $relativePath, $status_barber, $id_barber);
        } else {
            $status_barber = $_POST['status_barber'] ?? 'aktif';
            $stmt = $conn->prepare("UPDATE barber SET nama_barber = ?, spesialisasi = ?, status = ? WHERE id_barber = ?");
            $stmt->bind_param("sssi", $nama_barber, $keahlian, $status_barber, $id_barber);
        }
        
        if ($stmt->execute()) {
            $updated = true;
        } else {
            $errors[] = "Gagal mengupdate barber: " . $conn->error;
        }
        $stmt->close();
    } elseif ($action === 'toggle_status') {
        // Toggle aktif <-> nonaktif
        $current_status = $_POST['current_status'] ?? 'aktif';
        $new_status = ($current_status === 'aktif') ? 'nonaktif' : 'aktif';
        $stmt = $conn->prepare("UPDATE barber SET status = ? WHERE id_barber = ?");
        $stmt->bind_param("si", $new_status, $id_barber);
        if ($stmt->execute()) {
            $label = $new_status === 'aktif' ? 'diaktifkan' : 'dinonaktifkan';
            header('Location: admin.php?tab=barber&success=' . urlencode("Barber berhasil $label."));
            exit();
        } else {
            header('Location: admin.php?tab=barber&error=' . urlencode('Gagal mengubah status barber.'));
            exit();
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        // Hapus foto lama
        $stmt = $conn->prepare("SELECT foto_barber FROM barber WHERE id_barber = ?");
        $stmt->bind_param("i", $id_barber);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $oldPhoto = $row['foto_barber'];
            if ($oldPhoto && $oldPhoto !== 'default_barber.png' && file_exists(__DIR__ . '/' . $oldPhoto)) {
                @unlink(__DIR__ . '/' . $oldPhoto);
            }
        }
        $stmt->close();
        
        $stmt = $conn->prepare("DELETE FROM barber WHERE id_barber = ?");
        $stmt->bind_param("i", $id_barber);
        if ($stmt->execute()) {
            header('Location: admin.php?tab=barber&success=' . urlencode('Data barber berhasil dihapus.'));
            exit();
        } else {
            $errors[] = "Gagal menghapus barber.";
        }
        $stmt->close();
    }
}

// Redirect dengan pesan
if (!empty($errors)) {
    $errorMsg = implode(' | ', $errors);
    header('Location: admin.php?tab=barber&error=' . urlencode($errorMsg));
} elseif ($updated) {
    header('Location: admin.php?tab=barber&success=' . urlencode('Data barber berhasil diperbarui.'));
} else {
    header('Location: admin.php?tab=barber&status=' . urlencode('Tidak ada perubahan yang disimpan.'));
}
exit();
