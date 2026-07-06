<?php
// admin/dashboard.php
session_start();
require_once '../koneksi.php';
require_once '../wa-notifikasi.php';

// Auth Check
if (!isset($_SESSION['id_user']) || !in_array($_SESSION['role'], ['admin', 'kasir'])) {
    header("Location: ../login.php");
    exit;
}

$error_msg = '';
$success_msg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Role-based Access Control Middleware for POST actions
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'kasir') {
        $allowed_kasir_actions = ['update_booking_status', 'mark_checkin', 'update_payment_status'];
        if (!in_array($action, $allowed_kasir_actions)) {
            $error_msg = "Akses ditolak: Kasir tidak diizinkan melakukan tindakan ini.";
            $action = ''; // Cancel action
        }
    }

    // 1. Update Shop Info (Name & Logo)
    if ($action === 'update_shop_info') {
        $shop_name = sanitize_input($_POST['shop_name'] ?? '');
        $logo_path = get_setting('shop_logo');
        // Handle logo upload
        if (isset($_FILES['shop_logo']) && $_FILES['shop_logo']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['shop_logo']['tmp_name'];
            $file_name = $_FILES['shop_logo']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            if (in_array($file_ext, $allowed) && strpos($mime, 'image/') === 0) {
                $new_name = uniqid('logo_', true) . '.' . $file_ext;
                $dest = '../uploads/logo/' . $new_name;
                if (!is_dir('../uploads/logo')) {
                    mkdir('../uploads/logo', 0755, true);
                }
                if (move_uploaded_file($file_tmp, $dest)) {
                    $logo_path = $new_name;
                } else {
                    $error_msg = 'Gagal mengunggah logo.';
                }
            } else {
                $error_msg = 'Format logo tidak didukung.';
            }
        }
        if (empty($error_msg)) {
            set_setting('shop_name', $shop_name);
            if ($logo_path) {
                set_setting('shop_logo', $logo_path);
            }
            $success_msg = 'Pengaturan barbershop berhasil disimpan.';
        }
    }

    // 2. Update Booking Status
    elseif ($action === 'update_booking_status') {
        $id_booking = intval($_POST['id_booking']);
        $status = sanitize_input($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE booking SET status = ? WHERE id_booking = ?");
        $stmt->bind_param("si", $status, $id_booking);
        if ($stmt->execute()) {
            $success_msg = "Status booking berhasil diperbarui.";
            
            // Log activity
            $log_activity = "Mengubah status booking ID: $id_booking menjadi $status";
            $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
            $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
            $act_stmt->execute();
        } else {
            $error_msg = "Gagal memperbarui status booking: " . $conn->error;
        }
    }
    
    // 3. Mark Check-in
    elseif ($action === 'mark_checkin') {
        $id_booking = intval($_POST['id_booking']);
        $now = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("UPDATE booking SET checkin_at = ? WHERE id_booking = ?");
        $stmt->bind_param("si", $now, $id_booking);
        if ($stmt->execute()) {
            $success_msg = "Check-in berhasil dicatat.";
            
            // Log activity
            $log_activity = "Mencatat check-in booking ID: $id_booking";
            $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
            $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
            $act_stmt->execute();
            
            // Get booking info to send WA checkin notification
            $info_stmt = $conn->prepare("SELECT b.kode_booking, b.no_hp, bar.nama_barber FROM booking b JOIN barber bar ON b.id_barber = bar.id_barber WHERE b.id_booking = ?");
            $info_stmt->bind_param("i", $id_booking);
            $info_stmt->execute();
            $info = $info_stmt->get_result()->fetch_assoc();
            if ($info) {
                // Send WhatsApp Notification
                kirimNotifikasiWA($info['no_hp'], "Halo! Anda telah berhasil check-in di Vijer Barbershop. Silakan menunggu giliran Anda bersama barber " . $info['nama_barber'] . ". Terima kasih!");
            }
        } else {
            $error_msg = "Gagal melakukan check-in: " . $conn->error;
        }
    }
    
    // 4. Add Barber
    elseif ($action === 'add_barber') {
        $nama_barber = sanitize_input($_POST['nama_barber']);
        $status = sanitize_input($_POST['status']);
        
        $foto = 'default_barber.png';
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['foto']['tmp_name'];
            $file_name = $_FILES['foto']['name'];
            $file_size = $_FILES['foto']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            
            if (!in_array($file_ext, $allowed_exts) || strpos($mime_type, 'image/') !== 0) {
                $error_msg = "Format file tidak didukung. Harap upload gambar (JPG, PNG, GIF).";
            } elseif ($file_size > 2 * 1024 * 1024) {
                $error_msg = "Ukuran file maksimal 2MB.";
            } else {
                $new_file_name = uniqid('barber_', true) . '.' . $file_ext;
                $dest_path = '../uploads/barber/' . $new_file_name;
                if (move_uploaded_file($file_tmp, $dest_path)) {
                    $foto = $new_file_name;
                } else {
                    $error_msg = "Gagal mengunggah foto.";
                }
            }
        }
        
        if (empty($error_msg)) {
            $stmt = $conn->prepare("INSERT INTO barber (nama_barber, status, foto) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nama_barber, $status, $foto);
            if ($stmt->execute()) {
                $success_msg = "Barber baru berhasil ditambahkan.";
                
                // Log activity
                $log_activity = "Menambahkan barber baru: $nama_barber";
                $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
                $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
                $act_stmt->execute();
            } else {
                $error_msg = "Gagal menambahkan barber: " . $conn->error;
            }
        }
    }
    
    // 5. Edit Barber
    elseif ($action === 'edit_barber') {
        $id_barber = intval($_POST['id_barber']);
        $nama_barber = sanitize_input($_POST['nama_barber']);
        $status = sanitize_input($_POST['status']);
        
        // Fetch current photo
        $fetch_stmt = $conn->prepare("SELECT foto FROM barber WHERE id_barber = ?");
        $fetch_stmt->bind_param("i", $id_barber);
        $fetch_stmt->execute();
        $current_foto = $fetch_stmt->get_result()->fetch_assoc()['foto'] ?? 'default_barber.png';
        
        $foto = $current_foto;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['foto']['tmp_name'];
            $file_name = $_FILES['foto']['name'];
            $file_size = $_FILES['foto']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            
            if (!in_array($file_ext, $allowed_exts) || strpos($mime_type, 'image/') !== 0) {
                $error_msg = "Format file tidak didukung. Harap upload gambar (JPG, PNG, GIF).";
            } elseif ($file_size > 2 * 1024 * 1024) {
                $error_msg = "Ukuran file maksimal 2MB.";
            } else {
                $new_file_name = uniqid('barber_', true) . '.' . $file_ext;
                $dest_path = '../uploads/barber/' . $new_file_name;
                if (move_uploaded_file($file_tmp, $dest_path)) {
                    $foto = $new_file_name;
                    // Delete old photo if it wasn't default or URL
                    if ($current_foto !== 'default_barber.png' && strpos($current_foto, 'http') !== 0 && file_exists('../uploads/barber/' . $current_foto)) {
                        unlink('../uploads/barber/' . $current_foto);
                    }
                } else {
                    $error_msg = "Gagal mengunggah foto.";
                }
            }
        }
        
        if (empty($error_msg)) {
            $stmt = $conn->prepare("UPDATE barber SET nama_barber = ?, status = ?, foto = ? WHERE id_barber = ?");
            $stmt->bind_param("sssi", $nama_barber, $status, $foto, $id_barber);
            if ($stmt->execute()) {
                $success_msg = "Barber berhasil diperbarui.";
                
                // Log activity
                $log_activity = "Memperbarui barber: $nama_barber (ID: $id_barber)";
                $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
                $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
                $act_stmt->execute();
            } else {
                $error_msg = "Gagal memperbarui barber: " . $conn->error;
            }
        }
    }
    
    // 6. Toggle Barber Status
    elseif ($action === 'toggle_barber_status') {
        $id_barber = intval($_POST['id_barber']);
        $status = sanitize_input($_POST['status']);
        $new_status = ($status === 'aktif') ? 'cuti' : 'aktif';
        
        $stmt = $conn->prepare("UPDATE barber SET status = ? WHERE id_barber = ?");
        $stmt->bind_param("si", $new_status, $id_barber);
        if ($stmt->execute()) {
            $success_msg = "Status barber berhasil diubah.";
            
            // Log activity
            $log_activity = "Mengubah status barber ID: $id_barber menjadi $new_status";
            $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
            $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
            $act_stmt->execute();
        } else {
            $error_msg = "Gagal mengubah status barber: " . $conn->error;
        }
    }
    
    // 7. Delete Barber
    elseif ($action === 'delete_barber') {
        $id_barber = intval($_POST['id_barber']);
        
        $stmt = $conn->prepare("DELETE FROM barber WHERE id_barber = ?");
        $stmt->bind_param("i", $id_barber);
        if ($stmt->execute()) {
            $success_msg = "Barber berhasil dihapus.";
            
            // Log activity
            $log_activity = "Menghapus barber ID: $id_barber";
            $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
            $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
            $act_stmt->execute();
        } else {
            $error_msg = "Gagal menghapus barber: " . $conn->error;
        }
    }
    
    // 8. Add Layanan
    elseif ($action === 'add_layanan') {
        $nama_layanan = sanitize_input($_POST['nama_layanan']);
        $harga = floatval($_POST['harga']);
        $durasi = intval($_POST['durasi_menit']);
        
        $stmt = $conn->prepare("INSERT INTO layanan (nama_layanan, harga, durasi_menit) VALUES (?, ?, ?)");
        $stmt->bind_param("sdi", $nama_layanan, $harga, $durasi);
        if ($stmt->execute()) {
            $success_msg = "Layanan baru berhasil ditambahkan.";
            
            // Log activity
            $log_activity = "Menambahkan layanan baru: $nama_layanan";
            $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
            $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
            $act_stmt->execute();
        } else {
            $error_msg = "Gagal menambahkan layanan: " . $conn->error;
        }
    }
    
    // 9. Edit Layanan
    elseif ($action === 'edit_layanan') {
        $id_layanan = intval($_POST['id_layanan']);
        $nama_layanan = sanitize_input($_POST['nama_layanan']);
        $harga = floatval($_POST['harga']);
        $durasi = intval($_POST['durasi_menit']);
        
        $stmt = $conn->prepare("UPDATE layanan SET nama_layanan = ?, harga = ?, durasi_menit = ? WHERE id_layanan = ?");
        $stmt->bind_param("sdii", $nama_layanan, $harga, $durasi, $id_layanan);
        if ($stmt->execute()) {
            $success_msg = "Layanan berhasil diperbarui.";
            
            // Log activity
            $log_activity = "Memperbarui layanan: $nama_layanan (ID: $id_layanan)";
            $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
            $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
            $act_stmt->execute();
        } else {
            $error_msg = "Gagal memperbarui layanan: " . $conn->error;
        }
    }
    
    // 10. Delete Layanan
    elseif ($action === 'delete_layanan') {
        $id_layanan = intval($_POST['id_layanan']);
        
        $stmt = $conn->prepare("DELETE FROM layanan WHERE id_layanan = ?");
        $stmt->bind_param("i", $id_layanan);
        if ($stmt->execute()) {
            $success_msg = "Layanan berhasil dihapus.";
            
            // Log activity
            $log_activity = "Menghapus layanan ID: $id_layanan";
            $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
            $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
            $act_stmt->execute();
        } else {
            $error_msg = "Gagal menghapus layanan: " . $conn->error;
        }
    }
    
    // 11. Add Gaya Rambut
    elseif ($action === 'add_gaya') {
        $nama_gaya = sanitize_input($_POST['nama_gaya']);
        $deskripsi = sanitize_input($_POST['deskripsi']);
        $foto_gaya = sanitize_input($_POST['foto_gaya']);
        if (empty($foto_gaya)) {
            $foto_gaya = 'default_gaya.png';
        }
        
        $stmt = $conn->prepare("INSERT INTO gaya_rambut (nama_gaya, deskripsi, foto_gaya) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nama_gaya, $deskripsi, $foto_gaya);
        if ($stmt->execute()) {
            $success_msg = "Gaya rambut baru berhasil ditambahkan.";
            
            // Log activity
            $log_activity = "Menambahkan gaya rambut baru: $nama_gaya";
            $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
            $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
            $act_stmt->execute();
        } else {
            $error_msg = "Gagal menambahkan gaya rambut: " . $conn->error;
        }
    }
    
    // 12. Edit Gaya Rambut
    elseif ($action === 'edit_gaya') {
        $id_gaya = intval($_POST['id_gaya']);
        $nama_gaya = sanitize_input($_POST['nama_gaya']);
        $deskripsi = sanitize_input($_POST['deskripsi']);
        $foto_gaya = sanitize_input($_POST['foto_gaya']);
        if (empty($foto_gaya)) {
            $foto_gaya = 'default_gaya.png';
        }
        
        $stmt = $conn->prepare("UPDATE gaya_rambut SET nama_gaya = ?, deskripsi = ?, foto_gaya = ? WHERE id_gaya = ?");
        $stmt->bind_param("sssi", $nama_gaya, $deskripsi, $foto_gaya, $id_gaya);
        if ($stmt->execute()) {
            $success_msg = "Gaya rambut berhasil diperbarui.";
            
            // Log activity
            $log_activity = "Memperbarui gaya rambut: $nama_gaya (ID: $id_gaya)";
            $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
            $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
            $act_stmt->execute();
        } else {
            $error_msg = "Gagal memperbarui gaya rambut: " . $conn->error;
        }
    }
    
    // 13. Delete Gaya Rambut
    elseif ($action === 'delete_gaya') {
        $id_gaya = intval($_POST['id_gaya']);
        
        $stmt = $conn->prepare("DELETE FROM gaya_rambut WHERE id_gaya = ?");
        $stmt->bind_param("i", $id_gaya);
        if ($stmt->execute()) {
            $success_msg = "Gaya rambut berhasil dihapus.";
            
            // Log activity
            $log_activity = "Menghapus gaya rambut ID: $id_gaya";
            $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
            $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
            $act_stmt->execute();
        } else {
            $error_msg = "Gagal menghapus gaya rambut: " . $conn->error;
        }
    }

    // 14. Update Payment Status (New)
    elseif ($action === 'update_payment_status') {
        $id_booking = intval($_POST['id_booking']);
        $status_pembayaran = sanitize_input($_POST['status_pembayaran']); // Lunas / Ditolak
        
        $booking_status = ($status_pembayaran === 'Lunas') ? 'disetujui' : 'pending';
        
        $stmt = $conn->prepare("UPDATE booking SET status_pembayaran = ?, status = ? WHERE id_booking = ?");
        $stmt->bind_param("ssi", $status_pembayaran, $booking_status, $id_booking);
        if ($stmt->execute()) {
            $success_msg = "Status pembayaran berhasil diperbarui menjadi $status_pembayaran.";
            
            // Log activity
            $log_activity = "Mengubah status pembayaran Booking ID: $id_booking menjadi $status_pembayaran (Status booking: $booking_status)";
            $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
            $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
            $act_stmt->execute();
        } else {
            $error_msg = "Gagal memperbarui status pembayaran: " . $conn->error;
        }
    }

    // 15. Add User (New)
    elseif ($action === 'add_user') {
        $username = sanitize_input($_POST['username']);
        $nama_lengkap = sanitize_input($_POST['nama_lengkap']);
        $no_hp = sanitize_input($_POST['no_hp']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $role = sanitize_input($_POST['role']);
        
        $check = $conn->prepare("SELECT id_user FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error_msg = "Username sudah digunakan!";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, no_hp, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $password, $nama_lengkap, $no_hp, $role);
            if ($stmt->execute()) {
                $success_msg = "User baru berhasil ditambahkan.";
                
                // Log activity
                $log_activity = "Menambahkan pengguna baru: $username ($role)";
                $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
                $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
                $act_stmt->execute();
            } else {
                $error_msg = "Gagal menambahkan user: " . $conn->error;
            }
        }
    }

    // 16. Edit User (New)
    elseif ($action === 'edit_user') {
        $id_user_edit = intval($_POST['id_user']);
        $nama_lengkap = sanitize_input($_POST['nama_lengkap']);
        $no_hp = sanitize_input($_POST['no_hp']);
        $role = sanitize_input($_POST['role']);
        
        $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, no_hp = ?, role = ? WHERE id_user = ?");
        $stmt->bind_param("sssi", $nama_lengkap, $no_hp, $role, $id_user_edit);
        if ($stmt->execute()) {
            $success_msg = "Akun user berhasil diperbarui.";
            
            // Log activity
            $log_activity = "Memperbarui pengguna ID: $id_user_edit";
            $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
            $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
            $act_stmt->execute();
        } else {
            $error_msg = "Gagal memperbarui akun user: " . $conn->error;
        }
    }

    // 17. Reset Password User (New)
    elseif ($action === 'reset_user_password') {
        $id_user_reset = intval($_POST['id_user']);
        $new_password = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id_user = ?");
        $stmt->bind_param("si", $new_password, $id_user_reset);
        if ($stmt->execute()) {
            $success_msg = "Password user berhasil direset.";
            
            // Log activity
            $log_activity = "Mereset password pengguna ID: $id_user_reset";
            $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
            $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
            $act_stmt->execute();
        } else {
            $error_msg = "Gagal mereset password: " . $conn->error;
        }
    }

    // 18. Toggle User Status (New)
    elseif ($action === 'toggle_user_status') {
        $id_user_toggle = intval($_POST['id_user']);
        $status = sanitize_input($_POST['status']);
        $new_status = ($status === 'aktif') ? 'nonaktif' : 'aktif';
        
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id_user = ?");
        $stmt->bind_param("si", $new_status, $id_user_toggle);
        if ($stmt->execute()) {
            $success_msg = "Status akun pengguna berhasil diperbarui menjadi $new_status.";
            
            // Log activity
            $log_activity = "Mengubah status pengguna ID: $id_user_toggle menjadi $new_status";
            $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
            $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
            $act_stmt->execute();
        } else {
            $error_msg = "Gagal mengubah status akun: " . $conn->error;
        }
    }

    // 19. Delete User (New)
    elseif ($action === 'delete_user') {
        $id_user_del = intval($_POST['id_user']);
        
        if ($id_user_del === intval($_SESSION['id_user'])) {
            $error_msg = "Anda tidak dapat menghapus akun Anda sendiri!";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id_user = ?");
            $stmt->bind_param("i", $id_user_del);
            if ($stmt->execute()) {
                $success_msg = "Akun pengguna berhasil dihapus.";
                
                // Log activity
                $log_activity = "Menghapus pengguna ID: $id_user_del";
                $act_stmt = $conn->prepare("INSERT INTO activity_logs (id_user, activity) VALUES (?, ?)");
                $act_stmt->bind_param("is", $_SESSION['id_user'], $log_activity);
                $act_stmt->execute();
            } else {
                $error_msg = "Gagal menghapus user: " . $conn->error;
            }
        }
    }
}

// Fetch Overview Statistics
$total_bookings = $conn->query("SELECT COUNT(*) FROM booking")->fetch_row()[0];
$today_bookings = $conn->query("SELECT COUNT(*) FROM booking WHERE tanggal_booking = CURDATE()")->fetch_row()[0];
$pending_bookings = $conn->query("SELECT COUNT(*) FROM booking WHERE status = 'pending'")->fetch_row()[0];
$total_revenue = $conn->query("SELECT SUM(total_harga) FROM booking WHERE status_pembayaran = 'Lunas'")->fetch_row()[0] ?? 0;
$total_barbers = $conn->query("SELECT COUNT(*) FROM barber WHERE status = 'aktif'")->fetch_row()[0];
$total_customers = $conn->query("SELECT COUNT(DISTINCT no_hp) FROM booking")->fetch_row()[0];

// Query chart data for Statistik & Laporan
// 1. Bookings per Month (past 12 months)
$bookings_monthly_query = $conn->query("
    SELECT DATE_FORMAT(tanggal_booking, '%Y-%m') as bulan, COUNT(*) as jumlah 
    FROM booking 
    GROUP BY bulan 
    ORDER BY bulan ASC 
    LIMIT 12
");
$bookings_months = [];
$bookings_counts = [];
while ($row = $bookings_monthly_query->fetch_assoc()) {
    $dateObj = DateTime::createFromFormat('!Y-m', $row['bulan']);
    $monthName = $dateObj ? $dateObj->format('M Y') : $row['bulan'];
    $bookings_months[] = $monthName;
    $bookings_counts[] = intval($row['jumlah']);
}

// 2. Revenue per Month (past 12 months)
$revenue_monthly_query = $conn->query("
    SELECT DATE_FORMAT(tanggal_booking, '%Y-%m') as bulan, SUM(total_harga) as pendapatan 
    FROM booking 
    WHERE status_pembayaran = 'Lunas' 
    GROUP BY bulan 
    ORDER BY bulan ASC 
    LIMIT 12
");
$revenue_months = [];
$revenue_amounts = [];
while ($row = $revenue_monthly_query->fetch_assoc()) {
    $dateObj = DateTime::createFromFormat('!Y-m', $row['bulan']);
    $monthName = $dateObj ? $dateObj->format('M Y') : $row['bulan'];
    $revenue_months[] = $monthName;
    $revenue_amounts[] = floatval($row['pendapatan']);
}

// 3. Payment Methods Distribution
$payment_methods_query = $conn->query("
    SELECT metode_pembayaran, COUNT(*) as jumlah 
    FROM booking 
    WHERE metode_pembayaran IS NOT NULL AND metode_pembayaran != '' 
    GROUP BY metode_pembayaran 
    ORDER BY jumlah DESC
");
$payment_methods = [];
$payment_counts = [];
while ($row = $payment_methods_query->fetch_assoc()) {
    $payment_methods[] = $row['metode_pembayaran'];
    $payment_counts[] = intval($row['jumlah']);
}

// Fetch Data for Lists
$bookings_query = $conn->query("
    SELECT b.*, bar.nama_barber, l.nama_layanan, g.nama_gaya 
    FROM booking b 
    JOIN barber bar ON b.id_barber = bar.id_barber
    JOIN layanan l ON b.id_layanan = l.id_layanan
    LEFT JOIN gaya_rambut g ON b.id_gaya = g.id_gaya
    ORDER BY b.tanggal_booking DESC, b.jam_booking DESC
");

$barbers_query = $conn->query("SELECT * FROM barber ORDER BY id_barber ASC");
$layanan_query = $conn->query("SELECT * FROM layanan ORDER BY id_layanan ASC");
$gaya_query = $conn->query("SELECT * FROM gaya_rambut ORDER BY id_gaya ASC");

// Fetch Payments List (New)
$payments_query = $conn->query("
    SELECT b.*, bar.nama_barber, l.nama_layanan 
    FROM booking b 
    JOIN barber bar ON b.id_barber = bar.id_barber
    JOIN layanan l ON b.id_layanan = l.id_layanan
    WHERE b.metode_pembayaran IS NOT NULL AND b.metode_pembayaran != ''
    ORDER BY b.created_at DESC
");

// Fetch Users List (New)
$users_query = $conn->query("SELECT * FROM users ORDER BY id_user ASC");
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= htmlspecialchars(get_setting('shop_name') ?? 'Vijer Barbershop') ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;900&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --color-dark: #0a0a0a;
            --color-darker: #050505;
            --color-card: #141414;
            --color-gold: #c5a059;
            --color-gold-light: #e5c483;
            --color-text-main: #f0f0f0;
            --color-text-muted: #999999;
            --color-border: rgba(197, 160, 89, 0.15);
            --nav-bg: rgba(5, 5, 5, 0.85);
            --input-bg: #222;
            --input-border: #333;
        }

        [data-theme="light"] {
            --color-dark: #f8f9fa;
            --color-darker: #ffffff;
            --color-card: #ffffff;
            --color-gold: #b58c42;
            --color-gold-light: #c5a059;
            --color-text-main: #1a1a1a;
            --color-text-muted: #666666;
            --color-border: rgba(0, 0, 0, 0.1);
            --nav-bg: rgba(255, 255, 255, 0.9);
            --input-bg: #f8f9fa;
            --input-border: #dee2e6;
        }

        body {
            background-color: var(--color-darker);
            color: var(--color-text-main);
            font-family: 'Montserrat', sans-serif;
            transition: 0.3s;
        }

        h1, h2, h3, h4, h5, h6, .brand-text {
            font-family: 'Outfit', sans-serif;
            color: var(--color-text-main);
        }

        .text-gold { color: var(--color-gold) !important; }
        .text-muted-custom { color: var(--color-text-muted) !important; }

        /* Top Navbar */
        .admin-nav {
            background-color: var(--color-dark);
            border-bottom: 1px solid var(--color-border);
            padding: 15px 0;
            backdrop-filter: blur(10px);
        }
        .brand-logo {
            font-family: 'Outfit', sans-serif;
            font-weight: 900;
            font-size: 1.6rem;
            color: var(--color-text-main) !important;
            text-decoration: none;
            letter-spacing: 2px;
        }
        .brand-logo span { color: var(--color-gold); }

        /* Stats Cards */
        .stat-card {
            background-color: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: 12px;
            padding: 24px;
            height: 100%;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(197, 160, 89, 0.1);
        }
        .stat-icon {
            font-size: 2.2rem;
            color: var(--color-gold);
            opacity: 0.8;
        }

        /* Sidebar Tabs */
        .nav-pills .nav-link {
            color: var(--color-text-main);
            background-color: transparent;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            border-radius: 8px;
            padding: 12px 20px;
            margin-bottom: 8px;
            text-align: left;
            transition: 0.3s;
            border: 1px solid transparent;
        }
        .nav-pills .nav-link:hover {
            color: var(--color-gold);
            background-color: rgba(197, 160, 89, 0.05);
        }
        .nav-pills .nav-link.active {
            background-color: var(--color-gold);
            color: #000;
            font-weight: 700;
        }

        /* Content Area */
        .content-card {
            background-color: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: 12px;
            padding: 30px;
        }

        /* Custom Table */
        .table-custom {
            color: var(--color-text-main);
            border-color: var(--color-border);
        }
        .table-custom th {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            background-color: var(--color-dark);
            color: var(--color-gold);
            border-color: var(--color-border);
            padding: 14px;
        }
        .table-custom td {
            background-color: transparent;
            border-color: var(--color-border);
            padding: 14px;
            vertical-align: middle;
        }
        .table-hover tbody tr:hover td {
            background-color: rgba(197, 160, 89, 0.03);
        }

        /* Badges */
        .badge-status {
            padding: 6px 12px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        .bg-pending { background-color: rgba(255, 193, 7, 0.15); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.3); }
        .bg-disetujui { background-color: rgba(13, 110, 253, 0.15); color: #0d6efd; border: 1px solid rgba(13, 110, 253, 0.3); }
        .bg-diproses { background-color: rgba(23, 162, 184, 0.15); color: #17a2b8; border: 1px solid rgba(23, 162, 184, 0.3); }
        .bg-selesai { background-color: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid rgba(40, 167, 69, 0.3); }
        .bg-batal { background-color: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.3); }

        /* Form Inputs */
        .form-control, .form-select {
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--color-text-main);
        }
        .form-control:focus, .form-select:focus {
            background-color: var(--input-bg);
            border-color: var(--color-gold);
            color: var(--color-text-main);
            box-shadow: none;
        }
        .form-control::placeholder { color: var(--color-text-muted); }
        .form-select option { background-color: var(--color-card); color: var(--color-text-main); }

        /* Gold buttons */
        .btn-gold {
            background-color: var(--color-gold);
            color: #000;
            font-weight: 600;
            border: none;
            transition: 0.3s;
        }
        .btn-gold:hover {
            background-color: var(--color-gold-light);
            transform: translateY(-1px);
        }
        .btn-outline-gold {
            color: var(--color-gold);
            border: 1px solid var(--color-gold);
            background-color: transparent;
            font-weight: 500;
            transition: 0.3s;
        }
        .btn-outline-gold:hover {
            background-color: var(--color-gold);
            color: #000;
        }

        .theme-toggle-btn {
            background: transparent;
            border: none;
            color: var(--color-text-main);
            font-size: 1.2rem;
            cursor: pointer;
            transition: color 0.3s;
        }
        .theme-toggle-btn:hover { color: var(--color-gold); }

        .modal-content {
            background-color: var(--color-card);
            border: 1px solid var(--color-border);
            color: var(--color-text-main);
        }
        .modal-header { border-bottom: 1px solid var(--color-border); }
        .modal-footer { border-top: 1px solid var(--color-border); }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="admin-nav mb-5">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between">
                <a href="../index.php" class="brand-logo">VIJER<span>.</span>Admin</a>
                
                <div class="d-flex align-items-center gap-4">
                    <button class="theme-toggle-btn" id="themeToggle" title="Ganti Tema">
                        <i class="fas fa-moon"></i>
                    </button>
                    <div class="text-end d-none d-md-block">
                        <span class="fw-bold d-block text-gold"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span>
                        <small class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing:1px;"><?= htmlspecialchars($_SESSION['role']) ?></small>
                    </div>
                    <a href="../logout.php" class="btn btn-outline-danger btn-sm px-3 rounded-pill"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 bg-success bg-opacity-10 text-success p-3 rounded-3 mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $success_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="filter: invert(1);"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 bg-danger bg-opacity-10 text-danger p-3 rounded-3 mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="filter: invert(1);"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="row g-3 mb-5 row-cols-2 row-cols-md-3 row-cols-lg-6">
            <div class="col">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted-custom fw-semibold" style="font-size: 0.8rem;">Total Reservasi</span>
                        <i class="fas fa-calendar-check stat-icon" style="font-size: 1.5rem;"></i>
                    </div>
                    <h3 class="fw-bold m-0"><?= $total_bookings ?></h3>
                </div>
            </div>
            <div class="col">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted-custom fw-semibold" style="font-size: 0.8rem;">Reservasi Hari Ini</span>
                        <i class="fas fa-calendar-day stat-icon text-info" style="font-size: 1.5rem;"></i>
                    </div>
                    <h3 class="fw-bold m-0 text-info"><?= $today_bookings ?></h3>
                </div>
            </div>
            <div class="col">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted-custom fw-semibold" style="font-size: 0.8rem;">Booking Pending</span>
                        <i class="fas fa-clock stat-icon text-warning" style="font-size: 1.5rem;"></i>
                    </div>
                    <h3 class="fw-bold m-0 text-warning"><?= $pending_bookings ?></h3>
                </div>
            </div>
            <div class="col">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted-custom fw-semibold" style="font-size: 0.8rem;">Pendapatan</span>
                        <i class="fas fa-wallet stat-icon text-success" style="font-size: 1.5rem;"></i>
                    </div>
                    <h4 class="fw-bold m-0 text-success" style="font-size: 1.1rem;">Rp <?= number_format($total_revenue, 0, ',', '.') ?></h4>
                </div>
            </div>
            <div class="col">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted-custom fw-semibold" style="font-size: 0.8rem;">Total Barber</span>
                        <i class="fas fa-user-tie stat-icon text-secondary" style="font-size: 1.5rem;"></i>
                    </div>
                    <h3 class="fw-bold m-0"><?= $total_barbers ?></h3>
                </div>
            </div>
            <div class="col">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted-custom fw-semibold" style="font-size: 0.8rem;">Total Pelanggan</span>
                        <i class="fas fa-users stat-icon text-gold" style="font-size: 1.5rem;"></i>
                    </div>
                    <h3 class="fw-bold m-0 text-gold"><?= $total_customers ?></h3>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Sidebar Navigation -->
            <div class="col-lg-3">
                <div class="nav flex-column nav-pills" id="adminTabs" role="tablist" aria-orientation="vertical">
                    <button class="nav-link active" id="tab-bookings-btn" data-bs-toggle="pill" data-bs-target="#tab-bookings" type="button" role="tab" aria-selected="true">
                        <i class="fas fa-list-ul me-2"></i>Booking List
                    </button>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <button class="nav-link" id="tab-barbers-btn" data-bs-toggle="pill" data-bs-target="#tab-barbers" type="button" role="tab" aria-selected="false">
                            <i class="fas fa-cut me-2"></i>Manage Barbers
                        </button>
                        <button class="nav-link" id="tab-services-btn" data-bs-toggle="pill" data-bs-target="#tab-services" type="button" role="tab" aria-selected="false">
                            <i class="fas fa-concierge-bell me-2"></i>Manage Layanan
                        </button>
                        <button class="nav-link" id="tab-styles-btn" data-bs-toggle="pill" data-bs-target="#tab-styles" type="button" role="tab" aria-selected="false">
                            <i class="fas fa-image me-2"></i>Gaya Rambut
                        </button>
                    <?php endif; ?>
                    <button class="nav-link" id="tab-payments-btn" data-bs-toggle="pill" data-bs-target="#tab-payments" type="button" role="tab" aria-selected="false">
                        <i class="fas fa-wallet me-2"></i>Kelola Pembayaran
                    </button>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <button class="nav-link" id="tab-settings-btn" data-bs-toggle="pill" data-bs-target="#tab-settings" type="button" role="tab" aria-selected="false">
                            <i class="fas fa-cog me-2"></i>Pengaturan
                        </button>
                        <button class="nav-link" id="tab-users-btn" data-bs-toggle="pill" data-bs-target="#tab-users" type="button" role="tab" aria-selected="false">
                            <i class="fas fa-users me-2"></i>Kelola Pengguna
                        </button>
                        <button class="nav-link" id="tab-reports-btn" data-bs-toggle="pill" data-bs-target="#tab-reports" type="button" role="tab" aria-selected="false">
                            <i class="fas fa-chart-line me-2"></i>Statistik & Laporan
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="col-lg-9">
                <div class="tab-content" id="adminTabsContent">
                    
                    <!-- 1. Booking List Tab -->
                    <div class="tab-pane fade show active" id="tab-bookings" role="tabpanel">
                        <div class="content-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="fw-bold m-0">Reservasi Pelanggan</h4>
                                <span class="badge bg-gold text-dark"><?= $bookings_query->num_rows ?> data</span>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-custom table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Pelanggan</th>
                                            <th>Detail Layanan</th>
                                            <th>Waktu</th>
                                            <th>Status</th>
                                            <th>Check-In</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($bookings_query->num_rows === 0): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted-custom py-5">Belum ada data booking.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php while ($b = $bookings_query->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <span class="fw-bold text-gold"><?= $b['kode_booking'] ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold"><?= htmlspecialchars($b['nama_pelanggan']) ?></div>
                                                        <a href="https://wa.me/<?= $b['no_hp'] ?>" target="_blank" class="text-success small" style="text-decoration: none;">
                                                            <i class="fab fa-whatsapp me-1"></i><?= $b['no_hp'] ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <div class="small fw-bold text-light"><?= htmlspecialchars($b['nama_layanan']) ?></div>
                                                        <div class="small text-muted">Barber: <?= htmlspecialchars($b['nama_barber']) ?></div>
                                                        <?php if (!empty($b['nama_gaya'])): ?>
                                                            <div class="small text-gold text-opacity-70">Gaya: <?= htmlspecialchars($b['nama_gaya']) ?></div>
                                                        <?php endif; ?>
                                                        <div class="small text-success">Rp <?= number_format($b['total_harga'], 0, ',', '.') ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="small fw-semibold"><?= date('d/m/Y', strtotime($b['tanggal_booking'])) ?></div>
                                                        <div class="small text-muted-custom"><i class="far fa-clock text-gold me-1"></i><?= substr($b['jam_booking'], 0, 5) ?> WIB</div>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-status bg-<?= $b['status'] ?>"><?= $b['status'] ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($b['checkin_at'] === NULL): ?>
                                                            <?php if ($b['status'] !== 'batal'): ?>
                                                                <form action="" method="POST">
                                                                    <input type="hidden" name="action" value="mark_checkin">
                                                                    <input type="hidden" name="id_booking" value="<?= $b['id_booking'] ?>">
                                                                    <button type="submit" class="btn btn-outline-gold btn-sm py-1 px-2 rounded" style="font-size: 0.8rem;">
                                                                        <i class="fas fa-check-double me-1"></i>Check-in
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <span class="text-muted small">-</span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <div class="small text-success fw-bold"><i class="fas fa-check-circle me-1"></i>Sudah</div>
                                                            <div class="text-muted-custom" style="font-size:0.75rem;"><?= date('H:i', strtotime($b['checkin_at'])) ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <!-- Status Update Dropdown -->
                                                        <div class="dropdown">
                                                            <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                Ubah Status
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-dark">
                                                                <li>
                                                                    <form action="" method="POST">
                                                                        <input type="hidden" name="action" value="update_booking_status">
                                                                        <input type="hidden" name="id_booking" value="<?= $b['id_booking'] ?>">
                                                                        <input type="hidden" name="status" value="pending">
                                                                        <button type="submit" class="dropdown-item text-warning"><i class="fas fa-clock me-2"></i>Pending</button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <form action="" method="POST">
                                                                        <input type="hidden" name="action" value="update_booking_status">
                                                                        <input type="hidden" name="id_booking" value="<?= $b['id_booking'] ?>">
                                                                        <input type="hidden" name="status" value="disetujui">
                                                                        <button type="submit" class="dropdown-item text-primary"><i class="fas fa-thumbs-up me-2"></i>Disetujui</button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <form action="" method="POST">
                                                                        <input type="hidden" name="action" value="update_booking_status">
                                                                        <input type="hidden" name="id_booking" value="<?= $b['id_booking'] ?>">
                                                                        <input type="hidden" name="status" value="diproses">
                                                                        <button type="submit" class="dropdown-item text-info"><i class="fas fa-spinner me-2"></i>Diproses</button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <form action="" method="POST">
                                                                        <input type="hidden" name="action" value="update_booking_status">
                                                                        <input type="hidden" name="id_booking" value="<?= $b['id_booking'] ?>">
                                                                        <input type="hidden" name="status" value="selesai">
                                                                        <button type="submit" class="dropdown-item text-success"><i class="fas fa-check-circle me-2"></i>Selesai</button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <form action="" method="POST">
                                                                        <input type="hidden" name="action" value="update_booking_status">
                                                                        <input type="hidden" name="id_booking" value="<?= $b['id_booking'] ?>">
                                                                        <input type="hidden" name="status" value="batal">
                                                                        <button type="submit" class="dropdown-item text-danger"><i class="fas fa-times-circle me-2"></i>Batal</button>
                                                                    </form>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Manage Barbers Tab -->
                    <div class="tab-pane fade" id="tab-barbers" role="tabpanel">
                        <div class="content-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="fw-bold m-0">Manage Barbers</h4>
                                <button type="button" class="btn btn-gold btn-sm px-3 rounded" data-bs-toggle="modal" data-bs-target="#addBarberModal">
                                    <i class="fas fa-plus me-1"></i>Tambah Barber
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-custom table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama Barber</th>
                                            <th>Status Kerja</th>
                                            <th>Terdaftar Pada</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                         <?php 
                                         $barbers_query->data_seek(0);
                                         while ($b = $barbers_query->fetch_assoc()): 
                                         ?>
                                             <tr>
                                                 <td><?= $b['id_barber'] ?></td>
                                                 <td>
                                                     <div class="d-flex align-items-center gap-3">
                                                         <?php 
                                                         $foto_src = (strpos($b['foto'], 'http') === 0) ? htmlspecialchars($b['foto']) : '../uploads/barber/' . htmlspecialchars($b['foto']);
                                                         ?>
                                                         <img src="<?= $foto_src ?>" alt="<?= htmlspecialchars($b['nama_barber']) ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%; border: 1px solid var(--color-border);">
                                                         <span class="fw-bold"><?= htmlspecialchars($b['nama_barber']) ?></span>
                                                     </div>
                                                 </td>
                                                 <td>
                                                     <span class="badge rounded-pill bg-<?= $b['status'] === 'aktif' ? 'success' : 'secondary' ?>">
                                                         <?= htmlspecialchars($b['status']) ?>
                                                     </span>
                                                 </td>
                                                 <td><?= date('d M Y', strtotime($b['created_at'])) ?></td>
                                                 <td>
                                                     <div class="d-flex gap-2">
                                                         <button type="button" class="btn btn-sm btn-outline-gold" data-bs-toggle="modal" data-bs-target="#editBarberModal<?= $b['id_barber'] ?>">
                                                             Edit
                                                         </button>
                                                         <form action="" method="POST">
                                                             <input type="hidden" name="action" value="toggle_barber_status">
                                                             <input type="hidden" name="id_barber" value="<?= $b['id_barber'] ?>">
                                                             <input type="hidden" name="status" value="<?= $b['status'] ?>">
                                                             <button type="submit" class="btn btn-sm btn-outline-gold">
                                                                 Status
                                                             </button>
                                                         </form>
                                                         <form action="" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus barber ini?');">
                                                             <input type="hidden" name="action" value="delete_barber">
                                                             <input type="hidden" name="id_barber" value="<?= $b['id_barber'] ?>">
                                                             <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                 <i class="fas fa-trash"></i>
                                                             </button>
                                                         </form>
                                                     </div>

                                                     <!-- Edit Barber Modal -->
                                                     <div class="modal fade" id="editBarberModal<?= $b['id_barber'] ?>" tabindex="-1" aria-hidden="true">
                                                         <div class="modal-dialog">
                                                             <form action="" method="POST" enctype="multipart/form-data" class="modal-content">
                                                                 <input type="hidden" name="action" value="edit_barber">
                                                                 <input type="hidden" name="id_barber" value="<?= $b['id_barber'] ?>">
                                                                 <div class="modal-header">
                                                                     <h5 class="modal-title">Edit Barber</h5>
                                                                     <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                                                                 </div>
                                                                 <div class="modal-body text-start">
                                                                     <div class="mb-3">
                                                                         <label class="form-label">Nama Lengkap Barber</label>
                                                                         <input type="text" name="nama_barber" class="form-control" value="<?= htmlspecialchars($b['nama_barber']) ?>" required>
                                                                     </div>
                                                                     <div class="mb-3">
                                                                         <label class="form-label">Status</label>
                                                                         <select name="status" class="form-select">
                                                                             <option value="aktif" <?= ($b['status'] === 'aktif') ? 'selected' : '' ?>>Aktif</option>
                                                                             <option value="cuti" <?= ($b['status'] === 'cuti') ? 'selected' : '' ?>>Cuti</option>
                                                                         </select>
                                                                     </div>
                                                                     <div class="mb-3">
                                                                         <label class="form-label">Foto Barber</label>
                                                                         <input type="file" name="foto" class="form-control" accept="image/*" id="editBarberFoto<?= $b['id_barber'] ?>">
                                                                     </div>
                                                                     <div class="mt-2 text-center">
                                                                         <img id="editBarberPreview<?= $b['id_barber'] ?>" src="<?= $foto_src ?>" alt="Preview" style="max-height: 150px; border-radius: 8px; border: 1px solid var(--color-border);">
                                                                     </div>
                                                                 </div>
                                                                 <div class="modal-footer">
                                                                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                     <button type="submit" class="btn btn-gold">Simpan Perubahan</button>
                                                                 </div>
                                                             </form>
                                                         </div>
                                                     </div>
                                                     <script>
                                                         document.getElementById('editBarberFoto<?= $b['id_barber'] ?>').addEventListener('change', function(event) {
                                                             const file = event.target.files[0];
                                                             if (file) {
                                                                 const reader = new FileReader();
                                                                 reader.onload = function(e) {
                                                                     document.getElementById('editBarberPreview<?= $b['id_barber'] ?>').src = e.target.result;
                                                                 };
                                                                 reader.readAsDataURL(file);
                                                             }
                                                         });
                                                     </script>
                                                 </td>
                                             </tr>
                                         <?php endwhile; ?>
                                     </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Manage Layanan Tab -->
                    <div class="tab-pane fade" id="tab-services" role="tabpanel">
                        <div class="content-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="fw-bold m-0">Menu Layanan (Services)</h4>
                                <button type="button" class="btn btn-gold btn-sm px-3 rounded" data-bs-toggle="modal" data-bs-target="#addLayananModal">
                                    <i class="fas fa-plus me-1"></i>Tambah Layanan
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-custom table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama Layanan</th>
                                            <th>Harga</th>
                                            <th>Durasi</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($l = $layanan_query->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= $l['id_layanan'] ?></td>
                                                <td class="fw-bold"><?= htmlspecialchars($l['nama_layanan']) ?></td>
                                                <td class="text-gold fw-bold">Rp <?= number_format($l['harga'], 0, ',', '.') ?></td>
                                                <td><i class="far fa-clock me-1"></i><?= $l['durasi_menit'] ?> Menit</td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-sm btn-outline-gold" data-bs-toggle="modal" data-bs-target="#editLayananModal<?= $l['id_layanan'] ?>">
                                                            Edit
                                                        </button>
                                                        <form action="" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus layanan ini?');">
                                                            <input type="hidden" name="action" value="delete_layanan">
                                                            <input type="hidden" name="id_layanan" value="<?= $l['id_layanan'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>

                                                    <!-- Edit Layanan Modal (Photo disabled) -->
                                                    <div class="modal fade" id="editLayananModal<?= $l['id_layanan'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <form action="" method="POST" class="modal-content">
                                                                <input type="hidden" name="action" value="edit_layanan">
                                                                <input type="hidden" name="id_layanan" value="<?= $l['id_layanan'] ?>">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Edit Layanan</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Nama Layanan</label>
                                                                        <input type="text" name="nama_layanan" class="form-control" value="<?= htmlspecialchars($l['nama_layanan']) ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Harga (Rupiah)</label>
                                                                        <input type="number" name="harga" class="form-control" value="<?= intval($l['harga']) ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Estimasi Durasi (Menit)</label>
                                                                        <input type="number" name="durasi_menit" class="form-control" value="<?= $l['durasi_menit'] ?>" required>
                                                                    </div>
                                                                    <!-- Photo upload removed/disabled -->
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                    <button type="submit" class="btn btn-gold">Simpan Perubahan</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Gaya Rambut Tab -->
                    <div class="tab-pane fade" id="tab-styles" role="tabpanel">
                        <div class="content-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="fw-bold m-0">Katalog Gaya Rambut</h4>
                                <button type="button" class="btn btn-gold btn-sm px-3 rounded" data-bs-toggle="modal" data-bs-target="#addGayaModal">
                                    <i class="fas fa-plus me-1"></i>Tambah Gaya
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-custom table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Preview</th>
                                            <th>Nama Gaya</th>
                                            <th>Deskripsi</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($g = $gaya_query->fetch_assoc()): ?>
                                            <tr>
                                                <td style="width: 100px;">
                                                    <img src="<?= htmlspecialchars($g['foto_gaya']) ?>" alt="Foto Gaya" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid var(--color-border);">
                                                </td>
                                                <td class="fw-bold"><?= htmlspecialchars($g['nama_gaya']) ?></td>
                                                <td><span class="small text-muted-custom"><?= htmlspecialchars(substr($g['deskripsi'], 0, 100)) ?>...</span></td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-sm btn-outline-gold" data-bs-toggle="modal" data-bs-target="#editGayaModal<?= $g['id_gaya'] ?>">
                                                            Edit
                                                        </button>
                                                        <form action="" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus gaya rambut ini?');">
                                                            <input type="hidden" name="action" value="delete_gaya">
                                                            <input type="hidden" name="id_gaya" value="<?= $g['id_gaya'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>

                                                    <!-- Edit Gaya Modal -->
                                                    <div class="modal fade" id="editGayaModal<?= $g['id_gaya'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <form action="" method="POST" class="modal-content">
                                                                <input type="hidden" name="action" value="edit_gaya">
                                                                <input type="hidden" name="id_gaya" value="<?= $g['id_gaya'] ?>">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Edit Gaya Rambut</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Nama Gaya</label>
                                                                        <input type="text" name="nama_gaya" class="form-control" value="<?= htmlspecialchars($g['nama_gaya']) ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Deskripsi</label>
                                                                        <textarea name="deskripsi" class="form-control" rows="3" required><?= htmlspecialchars($g['deskripsi']) ?></textarea>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">URL Foto / Gambar</label>
                                                                        <input type="text" name="foto_gaya" class="form-control" value="<?= htmlspecialchars($g['foto_gaya']) ?>">
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                    <button type="submit" class="btn btn-gold">Simpan Perubahan</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Kelola Pembayaran Tab (New) -->
                    <div class="tab-pane fade" id="tab-payments" role="tabpanel">
                        <div class="content-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="fw-bold m-0">Verifikasi Pembayaran</h4>
                                <span class="badge bg-gold text-dark"><?= $payments_query->num_rows ?> transaksi</span>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-custom table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Kode Booking</th>
                                            <th>Pelanggan</th>
                                            <th>Metode</th>
                                            <th>Jumlah</th>
                                            <th>Bukti</th>
                                            <th>Status</th>
                                            <th>Tanggal</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($payments_query->num_rows === 0): ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted-custom py-5">Belum ada transaksi pembayaran.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php 
                                            $payments_query->data_seek(0);
                                            while ($pay = $payments_query->fetch_assoc()): ?>
                                                <tr>
                                                    <td><span class="fw-bold text-gold"><?= $pay['kode_booking'] ?></span></td>
                                                    <td>
                                                        <div class="fw-semibold"><?= htmlspecialchars($pay['nama_pelanggan']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($pay['no_hp']) ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="small fw-semibold"><?= htmlspecialchars($pay['metode_pembayaran']) ?></span>
                                                    </td>
                                                    <td class="text-gold fw-bold">Rp <?= number_format($pay['total_harga'], 0, ',', '.') ?></td>
                                                    <td>
                                                        <?php if (!empty($pay['bukti_pembayaran'])): ?>
                                                            <a href="../uploads/bukti/<?= htmlspecialchars($pay['bukti_pembayaran']) ?>" target="_blank" class="btn btn-xs btn-outline-gold py-1 px-2 rounded" style="font-size:0.75rem;">
                                                                <i class="fas fa-image me-1"></i>Cek Bukti
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted small">Belum Upload</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_p = $pay['status_pembayaran'];
                                                        $badge_class = 'bg-secondary';
                                                        if ($status_p === 'Lunas') $badge_class = 'bg-success';
                                                        elseif ($status_p === 'Menunggu Verifikasi') $badge_class = 'bg-warning text-dark';
                                                        elseif ($status_p === 'Ditolak') $badge_class = 'bg-danger';
                                                        ?>
                                                        <span class="badge <?= $badge_class ?>"><?= $status_p ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="small text-muted-custom"><?= !empty($pay['tanggal_pembayaran']) ? date('d/m/Y H:i', strtotime($pay['tanggal_pembayaran'])) : '-' ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($status_p === 'Menunggu Verifikasi' || $status_p === 'Ditolak' || $status_p === 'Menunggu Pembayaran'): ?>
                                                            <div class="d-flex gap-1">
                                                                <form action="" method="POST" style="display:inline;">
                                                                    <input type="hidden" name="action" value="update_payment_status">
                                                                    <input type="hidden" name="id_booking" value="<?= $pay['id_booking'] ?>">
                                                                    <input type="hidden" name="status_pembayaran" value="Lunas">
                                                                    <button type="submit" class="btn btn-success btn-sm py-1 px-2 rounded" style="font-size: 0.75rem;">
                                                                        <i class="fas fa-check"></i> Setujui
                                                                    </button>
                                                                </form>
                                                                <form action="" method="POST" style="display:inline;">
                                                                    <input type="hidden" name="action" value="update_payment_status">
                                                                    <input type="hidden" name="id_booking" value="<?= $pay['id_booking'] ?>">
                                                                    <input type="hidden" name="status_pembayaran" value="Ditolak">
                                                                    <button type="submit" class="btn btn-danger btn-sm py-1 px-2 rounded" style="font-size: 0.75rem;">
                                                                        <i class="fas fa-times"></i> Tolak
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-success small"><i class="fas fa-check-circle me-1"></i>Selesai</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <!-- 6. Pengaturan Tab -->
                        <div class="tab-pane fade" id="tab-settings" role="tabpanel">
                            <div class="content-card">
                                <h4 class="fw-bold mb-4">Pengaturan Barbershop</h4>
                                <?php
                                $shop_name = get_setting('shop_name') ?? '';
                                $shop_logo = get_setting('shop_logo') ?? '';
                                ?>
                                <form action="" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="update_shop_info">
                                    <div class="mb-3">
                                        <label class="form-label">Nama Barbershop</label>
                                        <input type="text" name="shop_name" class="form-control" value="<?= htmlspecialchars($shop_name); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Logo Barbershop</label>
                                        <input type="file" name="shop_logo" class="form-control" accept="image/*">
                                        <?php if ($shop_logo): ?>
                                            <div class="mt-2">
                                                <img src="../uploads/logo/<?= htmlspecialchars($shop_logo); ?>" alt="Logo" style="max-height:120px; border:1px solid var(--color-border);">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="submit" class="btn btn-gold">Simpan Pengaturan</button>
                                </form>
                            </div>
                        </div>

                        <!-- 7. Kelola Pengguna Tab -->
                        <div class="tab-pane fade" id="tab-users" role="tabpanel">
                            <div class="content-card">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="fw-bold m-0">Kelola Pengguna (Admin & Kasir)</h4>
                                    <button type="button" class="btn btn-gold btn-sm px-3 rounded" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                        <i class="fas fa-plus me-1"></i>Tambah Pengguna
                                    </button>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-custom table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Username</th>
                                                <th>Nama Lengkap</th>
                                                <th>No. HP</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $users_query->data_seek(0);
                                            while ($usr = $users_query->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= $usr['id_user'] ?></td>
                                                    <td class="fw-bold text-gold"><?= htmlspecialchars($usr['username']) ?></td>
                                                    <td><?= htmlspecialchars($usr['nama_lengkap']) ?></td>
                                                    <td><?= htmlspecialchars($usr['no_hp']) ?></td>
                                                    <td><span class="badge bg-secondary"><?= strtoupper($usr['role']) ?></span></td>
                                                    <td>
                                                        <span class="badge rounded-pill bg-<?= $usr['status'] === 'aktif' ? 'success' : 'danger' ?>">
                                                            <?= strtoupper($usr['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-1">
                                                            <button type="button" class="btn btn-xs btn-outline-gold py-1 px-2 rounded" style="font-size:0.75rem;" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $usr['id_user'] ?>">
                                                                Edit
                                                            </button>
                                                            <button type="button" class="btn btn-xs btn-outline-warning py-1 px-2 rounded" style="font-size:0.75rem;" data-bs-toggle="modal" data-bs-target="#resetPasswordModal<?= $usr['id_user'] ?>">
                                                                Reset PW
                                                            </button>
                                                            <form action="" method="POST" style="display:inline;">
                                                                <input type="hidden" name="action" value="toggle_user_status">
                                                                <input type="hidden" name="id_user" value="<?= $usr['id_user'] ?>">
                                                                <input type="hidden" name="status" value="<?= $usr['status'] ?>">
                                                                <button type="submit" class="btn btn-xs btn-outline-secondary py-1 px-2 rounded" style="font-size:0.75rem;">
                                                                    <?= $usr['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                                </button>
                                                            </form>
                                                            <form action="" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun ini?');" style="display:inline;">
                                                                <input type="hidden" name="action" value="delete_user">
                                                                <input type="hidden" name="id_user" value="<?= $usr['id_user'] ?>">
                                                                <button type="submit" class="btn btn-xs btn-outline-danger py-1 px-2 rounded" style="font-size:0.75rem;">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>

                                                        <!-- Edit User Modal -->
                                                        <div class="modal fade" id="editUserModal<?= $usr['id_user'] ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <form action="" method="POST" class="modal-content">
                                                                    <input type="hidden" name="action" value="edit_user">
                                                                    <input type="hidden" name="id_user" value="<?= $usr['id_user'] ?>">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Edit Pengguna</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                                                                    </div>
                                                                    <div class="modal-body text-start">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Nama Lengkap</label>
                                                                            <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($usr['nama_lengkap']) ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Nomor WhatsApp</label>
                                                                            <input type="tel" name="no_hp" class="form-control" value="<?= htmlspecialchars($usr['no_hp']) ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Role</label>
                                                                            <select name="role" class="form-select">
                                                                                <option value="admin" <?= $usr['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                                                <option value="kasir" <?= $usr['role'] === 'kasir' ? 'selected' : '' ?>>Kasir</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                        <button type="submit" class="btn btn-gold">Simpan Perubahan</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>

                                                        <!-- Reset Password Modal -->
                                                        <div class="modal fade" id="resetPasswordModal<?= $usr['id_user'] ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <form action="" method="POST" class="modal-content">
                                                                    <input type="hidden" name="action" value="reset_user_password">
                                                                    <input type="hidden" name="id_user" value="<?= $usr['id_user'] ?>">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Reset Password: <?= htmlspecialchars($usr['username']) ?></h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                                                                    </div>
                                                                    <div class="modal-body text-start">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Password Baru</label>
                                                                            <input type="password" name="new_password" class="form-control" required placeholder="Masukkan password baru" minlength="4">
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                        <button type="submit" class="btn btn-gold">Reset Password</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>

                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- 7. Statistik & Laporan Tab (New) -->
                        <div class="tab-pane fade" id="tab-reports" role="tabpanel">
                            <div class="content-card">
                                <h4 class="fw-bold mb-4">Statistik & Laporan Analisis</h4>
                                
                                <div class="row g-4 mb-5">
                                    <!-- Chart 1: Bookings per Month -->
                                    <div class="col-md-6">
                                        <div class="p-3 rounded border border-secondary h-100" style="background: rgba(0,0,0,0.2);">
                                            <h6 class="text-gold fw-bold mb-3"><i class="fas fa-calendar-alt me-2"></i>Booking per Bulan</h6>
                                            <div style="height: 250px; position: relative;">
                                                <canvas id="chartBookings"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Chart 2: Revenue per Month -->
                                    <div class="col-md-6">
                                        <div class="p-3 rounded border border-secondary h-100" style="background: rgba(0,0,0,0.2);">
                                            <h6 class="text-gold fw-bold mb-3"><i class="fas fa-wallet me-2"></i>Pendapatan per Bulan</h6>
                                            <div style="height: 250px; position: relative;">
                                                <canvas id="chartRevenue"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Chart 3: Most Used Payment Methods -->
                                    <div class="col-md-6 mx-auto">
                                        <div class="p-3 rounded border border-secondary h-100" style="background: rgba(0,0,0,0.2);">
                                            <h6 class="text-gold fw-bold mb-3"><i class="fas fa-money-check me-2"></i>Metode Pembayaran Terbanyak</h6>
                                            <div style="height: 250px; position: relative;">
                                                <canvas id="chartPayments"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr class="border-secondary mb-4">
                                
                                <!-- Audit & Log Views -->
                                <h5 class="text-gold mb-3"><i class="fas fa-clipboard-list me-2"></i>Log Aktivitas Pengguna (Terbaru)</h5>
                                <div class="table-responsive">
                                    <table class="table table-custom table-hover align-middle small">
                                        <thead>
                                            <tr>
                                                <th>Waktu</th>
                                                <th>Operator</th>
                                                <th>Aktivitas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $act_logs_query = $conn->query("
                                                SELECT a.*, u.nama_lengkap, u.role 
                                                FROM activity_logs a 
                                                LEFT JOIN users u ON a.id_user = u.id_user 
                                                ORDER BY a.created_at DESC LIMIT 15
                                            ");
                                            if ($act_logs_query && $act_logs_query->num_rows > 0):
                                                while ($lg = $act_logs_query->fetch_assoc()):
                                            ?>
                                                <tr>
                                                    <td><?= date('d/m/Y H:i', strtotime($lg['created_at'])) ?></td>
                                                    <td><strong><?= htmlspecialchars($lg['nama_lengkap'] ?? 'System') ?></strong> <span class="badge bg-secondary" style="font-size:0.65rem;"><?= strtoupper($lg['role'] ?? 'System') ?></span></td>
                                                    <td><?= htmlspecialchars($lg['activity']) ?></td>
                                                </tr>
                                            <?php 
                                                endwhile;
                                            else:
                                            ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted-custom">Belum ada log aktivitas.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

    </div>

    <!-- Modals for Adding New Records -->
    <!-- Add Barber Modal -->
    <div class="modal fade" id="addBarberModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="" method="POST" enctype="multipart/form-data" class="modal-content">
                <input type="hidden" name="action" value="add_barber">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Barber Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap Barber</label>
                        <input type="text" name="nama_barber" class="form-control" required placeholder="Contoh: Bang Daus">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status Awal</label>
                        <select name="status" class="form-select">
                            <option value="aktif">Aktif</option>
                            <option value="cuti">Cuti</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Foto Barber</label>
                        <input type="file" name="foto" class="form-control" accept="image/*" id="addBarberFoto" required>
                    </div>
                    <div class="mt-2 text-center">
                        <img id="addBarberPreview" src="" alt="Preview" style="max-height: 150px; display: none; border-radius: 8px; border: 1px solid var(--color-border);">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-gold">Tambah Barber</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        document.getElementById('addBarberFoto').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('addBarberPreview');
                    preview.src = e.target.result;
                    preview.style.display = 'inline-block';
                };
                reader.readAsDataURL(file);
            }
        });
    </script>

    <!-- Add Layanan Modal -->
    <div class="modal fade" id="addLayananModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="" method="POST" class="modal-content">
                <input type="hidden" name="action" value="add_layanan">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Layanan Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Layanan</label>
                        <input type="text" name="nama_layanan" class="form-control" required placeholder="Contoh: Premium Hair Wash">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Harga (Rupiah)</label>
                        <input type="number" name="harga" class="form-control" required placeholder="Contoh: 40000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estimasi Durasi (Menit)</label>
                        <input type="number" name="durasi_menit" class="form-control" required placeholder="Contoh: 30">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-gold">Tambah Layanan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Gaya Modal -->
    <div class="modal fade" id="addGayaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="" method="POST" class="modal-content">
                <input type="hidden" name="action" value="add_gaya">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Referensi Gaya Rambut</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Gaya</label>
                        <input type="text" name="nama_gaya" class="form-control" required placeholder="Contoh: Slickback Fade">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="3" required placeholder="Jelaskan karakteristik potongan gaya rambut ini..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL Foto / Gambar</label>
                        <input type="text" name="foto_gaya" class="form-control" placeholder="https://images.unsplash.com/...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-gold">Tambah Gaya</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add User Modal (New) -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="" method="POST" class="modal-content">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Pengguna Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required placeholder="Contoh: kasir_vijer" minlength="4">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" required placeholder="Contoh: Ahmad Kasir">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nomor WhatsApp</label>
                        <input type="tel" name="no_hp" class="form-control" required placeholder="Contoh: 081234567890">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="Masukkan password" minlength="4">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="kasir">Kasir</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-gold">Tambah Pengguna</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggle = document.getElementById('themeToggle');
            const icon = themeToggle.querySelector('i');
            const htmlElement = document.documentElement;

            const savedTheme = localStorage.getItem('theme') || 'dark';
            setTheme(savedTheme);

            themeToggle.addEventListener('click', () => {
                const currentTheme = htmlElement.getAttribute('data-theme');
                setTheme(currentTheme === 'dark' ? 'light' : 'dark');
            });

            function setTheme(theme) {
                htmlElement.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
                if (theme === 'dark') {
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                } else {
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                }
            }

            // ==========================================
            // CHART.JS INITIALIZATION
            // ==========================================
            const chartBookingsCtx = document.getElementById('chartBookings')?.getContext('2d');
            const chartRevenueCtx = document.getElementById('chartRevenue')?.getContext('2d');
            const chartPaymentsCtx = document.getElementById('chartPayments')?.getContext('2d');

            if (chartBookingsCtx) {
                new Chart(chartBookingsCtx, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($bookings_months) ?>,
                        datasets: [{
                            label: 'Reservasi',
                            data: <?= json_encode($bookings_counts) ?>,
                            borderColor: '#c5a059',
                            backgroundColor: 'rgba(197, 160, 89, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#c5a059',
                            pointBorderColor: '#c5a059',
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: {
                                ticks: { color: '#888888' },
                                grid: { color: 'rgba(197, 160, 89, 0.05)' }
                            },
                            y: {
                                ticks: { color: '#888888', stepSize: 1 },
                                grid: { color: 'rgba(197, 160, 89, 0.05)' }
                            }
                        }
                    }
                });
            }

            if (chartRevenueCtx) {
                new Chart(chartRevenueCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($revenue_months) ?>,
                        datasets: [{
                            label: 'Pendapatan (Rp)',
                            data: <?= json_encode($revenue_amounts) ?>,
                            backgroundColor: 'rgba(40, 167, 69, 0.7)',
                            borderColor: '#28a745',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: {
                                ticks: { color: '#888888' },
                                grid: { color: 'rgba(255, 255, 255, 0.05)' }
                            },
                            y: {
                                ticks: { color: '#888888' },
                                grid: { color: 'rgba(255, 255, 255, 0.05)' }
                            }
                        }
                    }
                });
            }

            if (chartPaymentsCtx) {
                new Chart(chartPaymentsCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($payment_methods) ?>,
                        datasets: [{
                            data: <?= json_encode($payment_counts) ?>,
                            backgroundColor: [
                                '#c5a059',
                                '#e5c483',
                                '#17a2b8',
                                '#28a745',
                                '#0d6efd',
                                '#fd7e14',
                                '#6f42c1',
                                '#20c997'
                            ],
                            borderColor: 'rgba(20, 20, 20, 0.8)',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: '#888888', font: { size: 10 } }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
