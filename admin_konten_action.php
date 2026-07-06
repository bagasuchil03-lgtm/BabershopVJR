<?php
// =============================================================
// admin_konten_action.php
// Handler backend untuk semua aksi POST modul Manajemen Konten
// =============================================================
session_start();
require_once 'koneksi.php';

// Guard: harus admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Akses ditolak.');
}

// Konfigurasi upload
define('KONTEN_UPLOAD_DIR', __DIR__ . '/uploads/konten/');
define('KONTEN_UPLOAD_URL', 'uploads/konten/');
define('MAX_FOTO',     6);
define('MIN_FOTO',     4);
define('MAX_SIZE_MB',  2);
define('ALLOWED_EXT',  ['jpg', 'jpeg', 'png']);
define('ALLOWED_MIME', ['image/jpeg', 'image/png']);

// Buat folder jika belum ada
if (!is_dir(KONTEN_UPLOAD_DIR)) {
    mkdir(KONTEN_UPLOAD_DIR, 0755, true);
}

// ─────────────────────────────────────────────────────────────
// Fungsi helper
// ─────────────────────────────────────────────────────────────

/**
 * Validasi satu file upload.
 * @return string|null  Pesan error, atau null jika valid.
 */
function validasi_foto(array $file): ?string
{
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = mime_content_type($file['tmp_name']);

    if (!in_array($ext, ALLOWED_EXT, true)) {
        return "File \"{$file['name']}\" tidak didukung. Hanya JPG/PNG.";
    }
    if (!in_array($mime, ALLOWED_MIME, true)) {
        return "File \"{$file['name']}\" bukan gambar JPG/PNG yang valid.";
    }
    if ($file['size'] > MAX_SIZE_MB * 1024 * 1024) {
        return "File \"{$file['name']}\" melebihi batas " . MAX_SIZE_MB . "MB.";
    }
    return null;
}

/**
 * Pindah file upload ke folder konten, return nama file baru atau false.
 */
function simpan_foto(array $file): string|false
{
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'konten_' . uniqid('', true) . '_' . time() . '.' . $ext;
    $dest     = KONTEN_UPLOAD_DIR . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return $filename;
    }
    return false;
}

/**
 * Hapus file fisik foto dari server.
 */
function hapus_file_foto(string $nama_file): void
{
    $path = KONTEN_UPLOAD_DIR . $nama_file;
    if ($nama_file && file_exists($path)) {
        unlink($path);
    }
}

// ─────────────────────────────────────────────────────────────
// Routing aksi
// ─────────────────────────────────────────────────────────────

$action   = $_POST['action'] ?? '';
$redirect = 'admin_konten.php';

switch ($action) {

    // ═══════════════════════════════════════════════════════
    // AKSI: Tambah Konten Baru (beserta 2-5 foto)
    // ═══════════════════════════════════════════════════════
    case 'tambah_konten':

        $judul     = trim($_POST['judul'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $status    = in_array($_POST['status'] ?? '', ['aktif', 'nonaktif']) ? $_POST['status'] : 'aktif';

        // Validasi judul
        if ($judul === '') {
            $_SESSION['flash_error'] = 'Judul konten tidak boleh kosong.';
            header('Location: ' . $redirect);
            exit();
        }

        // Normalisasi array $_FILES['fotos'] ke array of individual files
        $uploaded_files = [];
        if (isset($_FILES['fotos']) && is_array($_FILES['fotos']['name'])) {
            $total = count($_FILES['fotos']['name']);
            for ($i = 0; $i < $total; $i++) {
                // Skip jika tidak ada file (error = UPLOAD_ERR_NO_FILE)
                if ($_FILES['fotos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $uploaded_files[] = [
                    'name'     => $_FILES['fotos']['name'][$i],
                    'tmp_name' => $_FILES['fotos']['tmp_name'][$i],
                    'size'     => $_FILES['fotos']['size'][$i],
                    'error'    => $_FILES['fotos']['error'][$i],
                ];
            }
        }

        // Validasi jumlah foto
        $jumlah = count($uploaded_files);
        if ($jumlah < MIN_FOTO) {
            $_SESSION['flash_error'] = "Minimal " . MIN_FOTO . " foto harus diunggah (diterima: {$jumlah}).";
            header('Location: ' . $redirect);
            exit();
        }
        if ($jumlah > MAX_FOTO) {
            $_SESSION['flash_error'] = "Maksimal " . MAX_FOTO . " foto per konten (diterima: {$jumlah}).";
            header('Location: ' . $redirect);
            exit();
        }

        // Validasi setiap foto sebelum upload
        foreach ($uploaded_files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['flash_error'] = "Terjadi error saat upload file \"{$file['name']}\".";
                header('Location: ' . $redirect);
                exit();
            }
            $err = validasi_foto($file);
            if ($err) {
                $_SESSION['flash_error'] = $err;
                header('Location: ' . $redirect);
                exit();
            }
        }

        // Mulai transaksi DB
        $conn->begin_transaction();
        try {
            // 1. Insert ke tabel konten
            $stmt = $conn->prepare(
                "INSERT INTO konten (judul, deskripsi, status) VALUES (?, ?, ?)"
            );
            $stmt->bind_param('sss', $judul, $deskripsi, $status);
            $stmt->execute();
            $id_konten = (int) $conn->insert_id;
            $stmt->close();

            // 2. Loop simpan setiap foto
            $saved_files = [];
            $stmt_foto   = $conn->prepare(
                "INSERT INTO konten_foto (id_konten, nama_file, urutan) VALUES (?, ?, ?)"
            );

            foreach ($uploaded_files as $urutan => $file) {
                $nama_file = simpan_foto($file);
                if (!$nama_file) {
                    throw new RuntimeException("Gagal memindahkan file \"{$file['name']}\" ke server.");
                }
                $saved_files[] = $nama_file;

                $stmt_foto->bind_param('isi', $id_konten, $nama_file, $urutan);
                $stmt_foto->execute();
            }
            $stmt_foto->close();

            $conn->commit();
            $_SESSION['flash_success'] = "Konten \"{$judul}\" berhasil ditambahkan dengan {$jumlah} foto.";

        } catch (Throwable $e) {
            $conn->rollback();
            // Hapus file yang sudah terlanjur dipindah
            foreach ($saved_files as $f) {
                hapus_file_foto($f);
            }
            $_SESSION['flash_error'] = 'Gagal menyimpan konten: ' . $e->getMessage();
        }

        header('Location: ' . $redirect);
        exit();


    // ═══════════════════════════════════════════════════════
    // AKSI: Hapus Seluruh Konten (beserta semua fotonya)
    // ═══════════════════════════════════════════════════════
    case 'hapus_konten':

        $id_konten = (int) ($_POST['id_konten'] ?? 0);
        if ($id_konten <= 0) {
            $_SESSION['flash_error'] = 'ID konten tidak valid.';
            header('Location: ' . $redirect);
            exit();
        }

        // Ambil semua nama file foto milik konten ini
        $res = $conn->prepare("SELECT nama_file FROM konten_foto WHERE id_konten = ?");
        $res->bind_param('i', $id_konten);
        $res->execute();
        $result = $res->get_result();
        $file_names = [];
        while ($row = $result->fetch_assoc()) {
            $file_names[] = $row['nama_file'];
        }
        $res->close();

        // Hapus konten dari DB (CASCADE akan hapus konten_foto otomatis)
        $del = $conn->prepare("DELETE FROM konten WHERE id = ?");
        $del->bind_param('i', $id_konten);

        if ($del->execute()) {
            // Hapus file fisik dari server
            foreach ($file_names as $f) {
                hapus_file_foto($f);
            }
            $jumlah_foto = count($file_names);
            $_SESSION['flash_success'] = "Konten beserta {$jumlah_foto} foto berhasil dihapus.";
        } else {
            $_SESSION['flash_error'] = 'Gagal menghapus konten dari database.';
        }
        $del->close();

        header('Location: ' . $redirect);
        exit();


    // ═══════════════════════════════════════════════════════
    // AKSI: Hapus Satu Foto dari Konten
    // ═══════════════════════════════════════════════════════
    case 'hapus_foto':

        $id_foto   = (int) ($_POST['id_foto']   ?? 0);
        $id_konten = (int) ($_POST['id_konten'] ?? 0);
        $redirect  = "admin_konten_detail.php?id={$id_konten}";

        if ($id_foto <= 0 || $id_konten <= 0) {
            $_SESSION['flash_error'] = 'Parameter tidak valid.';
            header('Location: ' . $redirect);
            exit();
        }

        // Cek jumlah foto yang tersisa
        $cek = $conn->prepare("SELECT COUNT(*) AS total FROM konten_foto WHERE id_konten = ?");
        $cek->bind_param('i', $id_konten);
        $cek->execute();
        $total_foto = (int) $cek->get_result()->fetch_assoc()['total'];
        $cek->close();

        if ($total_foto <= MIN_FOTO) {
            $_SESSION['flash_error'] = "Tidak bisa menghapus foto. Minimal " . MIN_FOTO . " foto harus tetap ada.";
            header('Location: ' . $redirect);
            exit();
        }

        // Ambil nama file foto
        $sel = $conn->prepare("SELECT nama_file FROM konten_foto WHERE id = ? AND id_konten = ?");
        $sel->bind_param('ii', $id_foto, $id_konten);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();

        if (!$row) {
            $_SESSION['flash_error'] = 'Foto tidak ditemukan.';
            header('Location: ' . $redirect);
            exit();
        }

        // Hapus dari DB
        $del = $conn->prepare("DELETE FROM konten_foto WHERE id = ?");
        $del->bind_param('i', $id_foto);
        if ($del->execute()) {
            hapus_file_foto($row['nama_file']);
            $_SESSION['flash_success'] = 'Foto berhasil dihapus.';
        } else {
            $_SESSION['flash_error'] = 'Gagal menghapus foto dari database.';
        }
        $del->close();

        header('Location: ' . $redirect);
        exit();


    // ═══════════════════════════════════════════════════════
    // AKSI: Tambah Foto ke Konten yang Sudah Ada
    // ═══════════════════════════════════════════════════════
    case 'tambah_foto':

        $id_konten = (int) ($_POST['id_konten'] ?? 0);
        $redirect  = "admin_konten_detail.php?id={$id_konten}";

        if ($id_konten <= 0) {
            $_SESSION['flash_error'] = 'ID konten tidak valid.';
            header('Location: ' . $redirect);
            exit();
        }

        // Cek jumlah foto saat ini
        $cek = $conn->prepare("SELECT COUNT(*) AS total FROM konten_foto WHERE id_konten = ?");
        $cek->bind_param('i', $id_konten);
        $cek->execute();
        $total_saat_ini = (int) $cek->get_result()->fetch_assoc()['total'];
        $cek->close();

        // Normalisasi file baru
        $new_files = [];
        if (isset($_FILES['fotos_tambahan']) && is_array($_FILES['fotos_tambahan']['name'])) {
            $total = count($_FILES['fotos_tambahan']['name']);
            for ($i = 0; $i < $total; $i++) {
                if ($_FILES['fotos_tambahan']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $new_files[] = [
                    'name'     => $_FILES['fotos_tambahan']['name'][$i],
                    'tmp_name' => $_FILES['fotos_tambahan']['tmp_name'][$i],
                    'size'     => $_FILES['fotos_tambahan']['size'][$i],
                    'error'    => $_FILES['fotos_tambahan']['error'][$i],
                ];
            }
        }

        if (empty($new_files)) {
            $_SESSION['flash_error'] = 'Pilih minimal 1 foto untuk ditambahkan.';
            header('Location: ' . $redirect);
            exit();
        }

        // Validasi total setelah penambahan
        $total_setelah = $total_saat_ini + count($new_files);
        if ($total_setelah > MAX_FOTO) {
            $sisa = MAX_FOTO - $total_saat_ini;
            $_SESSION['flash_error'] = "Tidak bisa menambah " . count($new_files) . " foto. Konten ini sudah memiliki {$total_saat_ini} foto, sisa slot: {$sisa}.";
            header('Location: ' . $redirect);
            exit();
        }

        // Validasi format & ukuran
        foreach ($new_files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['flash_error'] = "Error upload file \"{$file['name']}\".";
                header('Location: ' . $redirect);
                exit();
            }
            $err = validasi_foto($file);
            if ($err) {
                $_SESSION['flash_error'] = $err;
                header('Location: ' . $redirect);
                exit();
            }
        }

        // Ambil urutan tertinggi saat ini
        $ord = $conn->prepare("SELECT COALESCE(MAX(urutan),0) AS max_urutan FROM konten_foto WHERE id_konten = ?");
        $ord->bind_param('i', $id_konten);
        $ord->execute();
        $start_urutan = (int) $ord->get_result()->fetch_assoc()['max_urutan'] + 1;
        $ord->close();

        // Simpan file & insert DB
        $stmt_foto   = $conn->prepare("INSERT INTO konten_foto (id_konten, nama_file, urutan) VALUES (?, ?, ?)");
        $saved_files = [];
        $error_msg   = null;

        foreach ($new_files as $i => $file) {
            $nama_file = simpan_foto($file);
            if (!$nama_file) {
                $error_msg = "Gagal menyimpan file \"{$file['name']}\".";
                break;
            }
            $saved_files[] = $nama_file;
            $urutan        = $start_urutan + $i;
            $stmt_foto->bind_param('isi', $id_konten, $nama_file, $urutan);
            $stmt_foto->execute();
        }
        $stmt_foto->close();

        if ($error_msg) {
            // Rollback file yang sudah tersimpan
            foreach ($saved_files as $f) hapus_file_foto($f);
            $_SESSION['flash_error'] = $error_msg;
        } else {
            $jumlah_baru = count($new_files);
            $_SESSION['flash_success'] = "{$jumlah_baru} foto berhasil ditambahkan ke konten.";
        }

        header('Location: ' . $redirect);
        exit();


    // ═══════════════════════════════════════════════════════
    // AKSI: Toggle Status Konten (aktif ↔ nonaktif)
    // ═══════════════════════════════════════════════════════
    case 'toggle_status':

        $id_konten = (int) ($_POST['id_konten'] ?? 0);
        if ($id_konten <= 0) {
            $_SESSION['flash_error'] = 'ID tidak valid.';
            header('Location: ' . $redirect);
            exit();
        }

        $sel = $conn->prepare("SELECT status FROM konten WHERE id = ?");
        $sel->bind_param('i', $id_konten);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();

        if (!$row) {
            $_SESSION['flash_error'] = 'Konten tidak ditemukan.';
            header('Location: ' . $redirect);
            exit();
        }

        $new_status = $row['status'] === 'aktif' ? 'nonaktif' : 'aktif';
        $upd        = $conn->prepare("UPDATE konten SET status = ? WHERE id = ?");
        $upd->bind_param('si', $new_status, $id_konten);
        $upd->execute();
        $upd->close();

        $_SESSION['flash_success'] = "Status konten diubah menjadi " . strtoupper($new_status) . ".";
        header('Location: ' . $redirect);
        exit();


    default:
        $_SESSION['flash_error'] = 'Aksi tidak dikenal.';
        header('Location: ' . $redirect);
        exit();
}
