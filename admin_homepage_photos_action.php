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
file_put_contents(__DIR__ . '/upload_debug.txt', "REQUEST RECEIVED GALLERY. Action: '$action'\nPOST: " . print_r($_POST, true) . "\nFILES: " . print_r($_FILES, true) . "\n\n", FILE_APPEND);

if ($action === 'add') {
    if (isset($_FILES['gallery_image']) && $_FILES['gallery_image']['error'] === UPLOAD_ERR_OK) {
        $status = $_POST['status'] ?? 'aktif';
        
        $uploadDir = 'uploads/homepage/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileInfo = pathinfo($_FILES['gallery_image']['name']);
        $ext = strtolower($fileInfo['extension']);
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($ext, $allowed)) {
            $newFileName = 'gallery_' . time() . '_' . uniqid() . '.' . $ext;
            $destination = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['gallery_image']['tmp_name'], $destination)) {
                $stmt = $conn->prepare("INSERT INTO homepage_photos (file_path, status) VALUES (?, ?)");
                $stmt->bind_param("ss", $destination, $status);
                $stmt->execute();
                header('Location: admin.php?tab=konten&success=' . urlencode('Foto galeri berhasil ditambahkan.'));
                exit();
            } else {
                header('Location: admin.php?tab=konten&error=' . urlencode('Gagal memindahkan file yang diupload.'));
                exit();
            }
        } else {
            header('Location: admin.php?tab=konten&error=' . urlencode('Format file tidak didukung.'));
            exit();
        }
    } else {
        header('Location: admin.php?tab=konten&error=' . urlencode('Harap pilih foto.'));
        exit();
    }
} elseif ($action === 'toggle_status') {
    $id = $_POST['id'];
    $current = $_POST['current_status'];
    $newStatus = ($current === 'aktif') ? 'nonaktif' : 'aktif';
    
    $stmt = $conn->prepare("UPDATE homepage_photos SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $newStatus, $id);
    $stmt->execute();
    
    header('Location: admin.php?tab=konten&success=' . urlencode('Status foto berhasil diubah.'));
    exit();
} elseif ($action === 'delete') {
    $id = $_POST['id'];
    
    // Get file path to delete from disk
    $stmt = $conn->prepare("SELECT file_path FROM homepage_photos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $filePath = __DIR__ . '/' . $row['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    $del = $conn->prepare("DELETE FROM homepage_photos WHERE id = ?");
    $del->bind_param("i", $id);
    $del->execute();
    
    header('Location: admin.php?tab=konten&success=' . urlencode('Foto galeri berhasil dihapus.'));
    exit();
}

header('Location: admin.php?tab=konten');
exit();
