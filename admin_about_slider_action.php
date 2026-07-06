<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$action = $_POST['action'] ?? '';
file_put_contents(__DIR__ . '/upload_debug.txt', "REQUEST RECEIVED. Action: '$action'\nPOST: " . print_r($_POST, true) . "\nFILES: " . print_r($_FILES, true) . "\n\n", FILE_APPEND);

// Check if post_max_size was exceeded (POST request, but $_POST and $_FILES are empty)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    $maxSize = ini_get('post_max_size');
    header('Location: admin.php?tab=konten&error=' . urlencode("Ukuran file terlalu besar melebihi batas sistem ($maxSize)."));
    exit();
}

// Simpan gambar (tanpa kompresi GD karena ekstensi belum aktif)
function saveUploadedImage($tmpPath, $destinationPath) {
    return move_uploaded_file($tmpPath, $destinationPath);
}

if ($action === 'add') {
    file_put_contents(__DIR__ . '/upload_debug.txt', "Action ADD triggered.\nFILES: " . print_r($_FILES, true) . "\n", FILE_APPEND);
    if (isset($_FILES['slider_image'])) {
        if ($_FILES['slider_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/homepage/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileInfo = pathinfo($_FILES['slider_image']['name']);
            $ext = strtolower($fileInfo['extension']);
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($ext, $allowed)) {
                $newFileName = 'about_slider_' . time() . '_' . uniqid() . '.' . $ext;
                $destPath = $uploadDir . $newFileName;
                $relativePath = 'uploads/homepage/' . $newFileName;
                
                if (saveUploadedImage($_FILES['slider_image']['tmp_name'], $destPath)) {
                    $stmt = $conn->prepare("INSERT INTO about_slider_photos (file_path) VALUES (?)");
                    $stmt->bind_param("s", $relativePath);
                    $stmt->execute();
                    file_put_contents(__DIR__ . '/upload_debug.txt', "Upload Success! Path: $relativePath\n", FILE_APPEND);
                    header('Location: admin.php?tab=konten&success=' . urlencode('Foto slider berhasil ditambahkan.'));
                    exit();
                } else {
                    file_put_contents(__DIR__ . '/upload_debug.txt', "move_uploaded_file failed for: " . $_FILES['slider_image']['tmp_name'] . " to $destPath\n", FILE_APPEND);
                    header('Location: admin.php?tab=konten&error=' . urlencode('Gagal memproses dan menyimpan file gambar.'));
                    exit();
                }
            } else {
                header('Location: admin.php?tab=konten&error=' . urlencode('Format file tidak didukung.'));
                exit();
            }
        } else {
            $errCode = $_FILES['slider_image']['error'];
            $errMsg = 'Gagal upload. Kode error: ' . $errCode;
            if ($errCode == UPLOAD_ERR_INI_SIZE) $errMsg = 'Ukuran file terlalu besar melebihi batas sistem.';
            header('Location: admin.php?tab=konten&error=' . urlencode($errMsg));
            exit();
        }
    } else {
        header('Location: admin.php?tab=konten&error=' . urlencode('Harap pilih foto.'));
        exit();
    }
} elseif ($action === 'delete') {
    $id = $_POST['id'];
    
    // Get file path to delete from disk
    $stmt = $conn->prepare("SELECT file_path FROM about_slider_photos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $filePath = __DIR__ . '/' . $row['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    $del = $conn->prepare("DELETE FROM about_slider_photos WHERE id = ?");
    $del->bind_param("i", $id);
    $del->execute();
    
    header('Location: admin.php?tab=konten&success=' . urlencode('Foto slider berhasil dihapus.'));
    exit();
}

header('Location: admin.php?tab=konten');
exit();
