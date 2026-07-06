<?php
// admin_edit_service.php – Handle service price & photo update
session_start();
require_once __DIR__ . '/koneksi.php';

// Admin guard
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit();
}

$id_layanan   = (int)($_POST['id_layanan']   ?? 0);
$harga        = (float)($_POST['harga']        ?? 0);
$nama_layanan = trim($_POST['nama_layanan']    ?? '');

// Basic validation
if ($id_layanan <= 0 || $harga <= 0 || $nama_layanan === '') {
    header('Location: admin.php?error=' . urlencode('Semua field wajib diisi.'));
    exit();
}

// Ensure uploads directory exists and is writable
$uploadDir = __DIR__ . '/uploads/service/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$conn->begin_transaction();
try {
    // ── Handle optional photo upload ───────────────────────────────────────
    $newImagePath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $fileTmp  = $_FILES['photo']['tmp_name'];
        $fileName = $_FILES['photo']['name'];
        $fileSize = $_FILES['photo']['size'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowed  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxSize  = 5 * 1024 * 1024; // 5 MB

        if (!in_array($fileExt, $allowed)) {
            throw new Exception('Format foto tidak didukung. Gunakan JPG, PNG, GIF, atau WEBP.');
        }
        if ($fileSize > $maxSize) {
            throw new Exception('Ukuran foto melebihi batas 5 MB.');
        }
        if (!is_writable($uploadDir)) {
            throw new Exception('Folder uploads tidak dapat ditulisi. Periksa izin folder.');
        }

        // Use a unique filename based on ID + extension so different file types are handled
        $newFileName  = 'service_' . $id_layanan . '.' . $fileExt;
        $destPath     = $uploadDir . $newFileName;
        $relPath      = 'uploads/service/' . $newFileName;

        // Remove old file(s) for this service to avoid stale images
        foreach (glob($uploadDir . 'service_' . $id_layanan . '.*') as $old) {
            @unlink($old);
        }
        // Also remove legacy filename if it exists
        $legacy = $uploadDir . $id_layanan . '.jpg';
        if (file_exists($legacy)) {
            @unlink($legacy);
        }

        if (!move_uploaded_file($fileTmp, $destPath)) {
            throw new Exception('Gagal memindahkan file foto ke server.');
        }

        $newImagePath = $relPath;
    }

    // ── Update layanan row ─────────────────────────────────────────────────
    if ($newImagePath !== null) {
        // Update name, price AND image_path
        $stmt = $conn->prepare(
            'UPDATE layanan SET nama_layanan = ?, harga = ?, image_path = ? WHERE id_layanan = ?'
        );
        $stmt->bind_param('sdsi', $nama_layanan, $harga, $newImagePath, $id_layanan);
    } else {
        // Update name and price only; keep existing image_path untouched
        $stmt = $conn->prepare(
            'UPDATE layanan SET nama_layanan = ?, harga = ? WHERE id_layanan = ?'
        );
        $stmt->bind_param('sdi', $nama_layanan, $harga, $id_layanan);
    }
    $stmt->execute();

    $conn->commit();
    $msg = 'Layanan berhasil diperbarui.' . ($newImagePath ? ' Foto baru telah disimpan.' : '');
    header('Location: admin.php?success=' . urlencode($msg));
    exit();

} catch (Exception $e) {
    $conn->rollback();
    header('Location: admin.php?error=' . urlencode($e->getMessage()));
    exit();
}
