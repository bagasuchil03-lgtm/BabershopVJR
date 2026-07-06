<?php
// admin_upload_qris.php – Handler upload gambar QRIS dan info merchant
session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/settings.php';

// Admin check
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_qris') {
    $oldPath = get_setting('qris_image');
    if ($oldPath && file_exists(__DIR__ . '/' . $oldPath)) {
        @unlink(__DIR__ . '/' . $oldPath);
    }
    set_setting('qris_image', '');
    header('Location: admin.php?tab=konten&success=' . urlencode('Gambar QRIS berhasil dihapus.'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php?tab=konten');
    exit();
}

// Pastikan direktori upload ada
$uploadDir = __DIR__ . '/uploads/qris/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$errors = [];
$updated = false;

// ── 1. Update Info Merchant (Teks) ─────────────────────────────────────
$merchantName = trim($_POST['qris_merchant_name'] ?? '');
$merchantBank = trim($_POST['qris_merchant_bank'] ?? '');
$merchantAccount = trim($_POST['qris_merchant_account'] ?? '');

if (!empty($merchantName)) {
    set_setting('qris_merchant_name', $merchantName);
    $updated = true;
}
if (!empty($merchantBank)) {
    set_setting('qris_merchant_bank', $merchantBank);
    $updated = true;
}
if (!empty($merchantAccount)) {
    set_setting('qris_merchant_account', $merchantAccount);
    $updated = true;
}

// ── 2. Upload Gambar QRIS ──────────────────────────────────────────────
if (isset($_FILES['qris_image']) && $_FILES['qris_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['qris_image'];

    // Cek error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Gagal mengupload gambar QRIS (Error code: {$file['error']}).";
    } else {
        // Validasi ukuran (max 5MB)
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $errors[] = "Ukuran gambar QRIS terlalu besar. Maksimum 5MB.";
        } else {
            // Validasi MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($mimeType, $allowedMimes)) {
                $errors[] = "Format gambar QRIS tidak didukung. Harap upload JPG atau PNG.";
            } else {
                // Tentukan ekstensi
                $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
                $ext = $extMap[$mimeType];

                // Generate nama file unik
                $newFileName = 'qris_' . time() . '_' . uniqid() . '.' . $ext;
                $destPath = $uploadDir . $newFileName;
                $relativePath = 'uploads/qris/' . $newFileName;

                // Kompresi gambar QRIS (max 800px width, quality tinggi untuk QR readability)
                $imageInfo = getimagesize($file['tmp_name']);
                if ($imageInfo) {
                    $origWidth = $imageInfo[0];
                    $origHeight = $imageInfo[1];
                    $mime = $imageInfo['mime'];

                    // Buat resource gambar
                    switch ($mime) {
                        case 'image/jpeg':
                            $srcImage = imagecreatefromjpeg($file['tmp_name']);
                            break;
                        case 'image/png':
                            $srcImage = imagecreatefrompng($file['tmp_name']);
                            break;
                        case 'image/gif':
                            $srcImage = imagecreatefromgif($file['tmp_name']);
                            break;
                        default:
                            $srcImage = false;
                    }

                    if ($srcImage) {
                        $maxWidth = 800;
                        $newWidth = $origWidth;
                        $newHeight = $origHeight;

                        if ($origWidth > $maxWidth) {
                            $ratio = $maxWidth / $origWidth;
                            $newWidth = $maxWidth;
                            $newHeight = (int)($origHeight * $ratio);
                        }

                        $dstImage = imagecreatetruecolor($newWidth, $newHeight);

                        // Preserve transparency untuk PNG
                        if ($mime === 'image/png') {
                            imagealphablending($dstImage, false);
                            imagesavealpha($dstImage, true);
                            $transparent = imagecolorallocatealpha($dstImage, 0, 0, 0, 127);
                            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
                        }

                        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

                        // Simpan dengan kualitas tinggi (QR harus jelas)
                        if ($ext === 'png') {
                            imagepng($dstImage, $destPath, 4);
                        } else {
                            imagejpeg($dstImage, $destPath, 92);
                        }

                        imagedestroy($srcImage);
                        imagedestroy($dstImage);

                        // Hapus gambar QRIS lama
                        $oldPath = get_setting('qris_image');
                        if ($oldPath && file_exists(__DIR__ . '/' . $oldPath)) {
                            @unlink(__DIR__ . '/' . $oldPath);
                        }

                        // Simpan path baru
                        set_setting('qris_image', $relativePath);
                        $updated = true;
                    } else {
                        $errors[] = "Gagal memproses gambar QRIS. Pastikan file adalah gambar yang valid.";
                    }
                } else {
                    $errors[] = "File yang diupload bukan gambar yang valid.";
                }
            }
        }
    }
}

// Redirect dengan pesan
if (!empty($errors)) {
    $errorMsg = implode(' | ', $errors);
    header('Location: admin.php?tab=konten&error=' . urlencode($errorMsg));
} elseif ($updated) {
    header('Location: admin.php?tab=konten&success=' . urlencode('Data QRIS berhasil diperbarui.'));
} else {
    header('Location: admin.php?tab=konten&status=' . urlencode('Tidak ada perubahan yang disimpan.'));
}
exit();
?>
