<?php
session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/wa-notifikasi.php';

// Simple admin check
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = $_POST['booking_id'] ?? '';
    $action = $_POST['action'] ?? '';

    if (empty($bookingId) || !in_array($action, ['accept', 'reject', 'delete', 'complete'])) {
        $msg = 'Invalid request.';
        header('Location: admin.php?error=' . urlencode($msg));
        exit();
    }

    if ($action === 'delete') {
        $delete = $conn->prepare('DELETE FROM booking WHERE id_booking = ?');
        $delete->bind_param('i', $bookingId);
        $delete->execute();
        header('Location: admin.php?success=' . urlencode('Booking berhasil dihapus.'));
        exit();
    }

    // ── Mark booking as SELESAI ───────────────────────────────────────────────
    if ($action === 'complete') {
        // Fetch booking details
        $stmt = $conn->prepare('SELECT b.*, l.nama_layanan, br.nama_barber FROM booking b JOIN layanan l ON b.id_layanan = l.id_layanan JOIN barber br ON b.id_barber = br.id_barber WHERE b.id_booking = ?');
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();

        if (!$booking) {
            header('Location: admin.php?error=' . urlencode('Booking tidak ditemukan.'));
            exit();
        }

        // Update status ke 'selesai'
        $update = $conn->prepare('UPDATE booking SET status = ?, tanggal_selesai = NOW() WHERE id_booking = ?');
        $newStatus = 'selesai';
        $update->bind_param('si', $newStatus, $bookingId);
        $update->execute();

        // Kirim notifikasi WhatsApp ke pelanggan
        if (!empty($booking['no_hp'])) {
            $notifMsg = "🎉 Terima kasih telah berkunjung ke Vijer Barbershop! Pesanan Anda (Kode: {$booking['kode_booking']}) telah diselesaikan. Sampai jumpa lagi!";
            
            // Simpan ke tabel notifikasi
            $ins_notif = $conn->prepare("INSERT INTO notifikasi (no_hp, pesan) VALUES (?, ?)");
            $ins_notif->bind_param("ss", $booking['no_hp'], $notifMsg);
            $ins_notif->execute();
            $ins_notif->close();

            kirimNotifikasiWA($booking['no_hp'], $notifMsg);
        }

        header('Location: admin.php?success=' . urlencode('Booking berhasil ditandai Selesai dan masuk ke Riwayat.'));
        exit();
    }

    // Fetch booking details for notifications
    $stmt = $conn->prepare('SELECT b.*, l.nama_layanan, br.nama_barber FROM booking b JOIN layanan l ON b.id_layanan = l.id_layanan JOIN barber br ON b.id_barber = br.id_barber WHERE b.id_booking = ?');
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if ($action === 'accept') {
        $newStatus = 'disetujui';
        $msg = 'Booking diterima.';
    } else {
        $newStatus = 'batal';
        $msg = 'Booking ditolak.';
    }
    // Capture optional rejection reason
    $rejectReason = '';
    if ($action === 'reject' && isset($_POST['alasan_tolak'])) {
        $rejectReason = trim($_POST['alasan_tolak']);
    }

    // Update status
    $update = $conn->prepare('UPDATE booking SET status = ? WHERE id_booking = ?');
    $update->bind_param('si', $newStatus, $bookingId);
    $update->execute();

    // Notify customer via WhatsApp if phone number exists
    if (!empty($booking['no_hp'])) {
        if ($action === 'accept') {
            $notifMsg = "✅ Booking Anda (Kode: {$booking['kode_booking']}) telah *diterima* oleh Vijer Barbershop. Silakan selesaikan pembayaran dan periksa detail di portal Anda.";
            // Simpan ke tabel notifikasi
            $pesanNotif = "Booking Anda dengan kode {$booking['kode_booking']} telah diterima.";
            $no_hp = $booking['no_hp'];
            $ins_notif = $conn->prepare("INSERT INTO notifikasi (no_hp, pesan) VALUES (?, ?)");
            $ins_notif->bind_param("ss", $no_hp, $pesanNotif);
            $ins_notif->execute();
            $ins_notif->close();
        } else {
            $notifMsg = "❌ Booking Anda (Kode: {$booking['kode_booking']}) telah *ditolak* oleh Vijer Barbershop.";
            if (!empty($rejectReason)) {
                $notifMsg .= " Alasan: {$rejectReason}";
            }
            $notifMsg .= " Silakan hubungi kami untuk informasi lebih lanjut.";
        }
        kirimNotifikasiWA($booking['no_hp'], $notifMsg);
    }

    // Optionally notify admin (already in admin area, but keep for completeness)
    // notifAdminBookingBaru([...], $booking['nama_pelanggan']);

    header('Location: admin.php?success=' . urlencode($msg));
    exit();
}
?>
