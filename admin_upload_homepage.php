<?php
// admin_upload_homepage.php – Handler upload foto beranda
session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/settings.php';

// Admin check
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_banner') {
    $key = $_GET['key'];
    $allowedKeys = ['homepage_hero_image', 'homepage_about_main', 'homepage_about_sub'];
    if (in_array($key, $allowedKeys)) {
        $oldPath = get_setting($key);
        if ($oldPath && $oldPath !== 'empty' && file_exists(__DIR__ . '/' . $oldPath)) {
            @unlink(__DIR__ . '/' . $oldPath);
        }
        set_setting($key, 'empty');
        header('Location: admin.php?tab=konten&success=' . urlencode('Banner berhasil dihapus.'));
    } else {
        header('Location: admin.php?tab=konten&error=' . urlencode('Aksi tidak valid.'));
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php?tab=konten');
    exit();
}

// Pastikan direktori upload ada
$uploadDir = __DIR__ . '/uploads/homepage/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/**
 * Fungsi kompresi dan resize gambar menggunakan GD Library
 * - Resize ke max width 1920px (menjaga aspect ratio)
 * - Kompresi JPG quality 85%
 * - Support JPG, PNG, GIF
 */
function compressAndSaveImage($sourcePath, $destPath, $maxWidth = 1920, $quality = 85) {
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) return false;

    $mime = $imageInfo['mime'];
    $origWidth = $imageInfo[0];
    $origHeight = $imageInfo[1];

    // Buat resource gambar berdasarkan tipe MIME
    switch ($mime) {
        case 'image/jpeg':
            $srcImage = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $srcImage = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $srcImage = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }

    if (!$srcImage) return false;

    // Hitung dimensi baru (resize jika lebih besar dari maxWidth)
    $newWidth = $origWidth;
    $newHeight = $origHeight;

    if ($origWidth > $maxWidth) {
        $ratio = $maxWidth / $origWidth;
        $newWidth = $maxWidth;
        $newHeight = (int)($origHeight * $ratio);
    }

    // Buat canvas baru dan resize
    $dstImage = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency untuk PNG
    if ($mime === 'image/png') {
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
        $transparent = imagecolorallocatealpha($dstImage, 0, 0, 0, 127);
        imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    // Simpan berdasarkan ekstensi tujuan (selalu simpan sebagai JPG untuk kompresi optimal, kecuali PNG)
    $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
    $result = false;

    if ($ext === 'png') {
        // PNG compression level 6 (0-9, 9 = max compression)
        $result = imagepng($dstImage, $destPath, 6);
    } elseif ($ext === 'gif') {
        $result = imagegif($dstImage, $destPath);
    } else {
        // Default: save as JPEG
        $result = imagejpeg($dstImage, $destPath, $quality);
    }

    imagedestroy($srcImage);
    imagedestroy($dstImage);

    return $result;
}

/**
 * Proses upload satu file gambar
 * Returns: path relatif ke file yang disimpan, atau null jika tidak ada upload
 */
function processUpload($fieldName, $uploadDir, $settingKey) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // Tidak ada file yang diupload untuk field ini
    }

    $file = $_FILES[$fieldName];

    // Cek error upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Gagal mengupload file untuk $fieldName (Error code: {$file['error']})");
    }

    // Validasi ukuran (max 5MB)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new Exception("Ukuran file $fieldName terlalu besar. Maksimum 5MB.");
    }

    // Validasi MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($mimeType, $allowedMimes)) {
        throw new Exception("Format file $fieldName tidak didukung. Harap upload JPG, PNG, atau GIF.");
    }

    // Tentukan ekstensi berdasarkan mime
    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
    $ext = $extMap[$mimeType];

    // Generate nama file unik
    $newFileName = $settingKey . '_' . time() . '_' . uniqid() . '.' . $ext;
    $destPath = $uploadDir . $newFileName;
    $relativePath = 'uploads/homepage/' . $newFileName;

    // Kompresi dan simpan
    if (!compressAndSaveImage($file['tmp_name'], $destPath)) {
        throw new Exception("Gagal memproses gambar $fieldName. Pastikan file adalah gambar yang valid.");
    }

    // Hapus file lama jika ada
    $oldPath = get_setting($settingKey);
    if ($oldPath && $oldPath !== 'empty' && file_exists(__DIR__ . '/' . $oldPath)) {
        @unlink(__DIR__ . '/' . $oldPath);
    }

    // Simpan setting baru
    set_setting($settingKey, $relativePath);

    return $relativePath;
}

// ── Proses Upload ──────────────────────────────────────────────────
$errors = [];
$successCount = 0;

// Upload Hero Image
try {
    $result = processUpload('hero_image', $uploadDir, 'homepage_hero_image');
    if ($result !== null) $successCount++;
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}



// Redirect dengan pesan
if (!empty($errors)) {
    $errorMsg = implode(' | ', $errors);
    header('Location: admin.php?tab=konten&error=' . urlencode($errorMsg));
} elseif ($successCount > 0) {
    header('Location: admin.php?tab=konten&success=' . urlencode("Berhasil memperbarui $successCount foto beranda."));
} else {
    header('Location: admin.php?tab=konten&status=' . urlencode('Tidak ada foto yang diubah.'));
}
exit();
?>
