<?php
/**
 * wa-notifikasi.php
 * Helper untuk mengirim notifikasi WhatsApp otomatis via Fonnte API
 * 
 * Cara penggunaan:
 * require_once 'wa-notifikasi.php';
 * kirimNotifikasiWA('6281234567890', 'Pesan Anda');
 */

require_once __DIR__ . '/koneksi.php';

/**
 * Kirim pesan WhatsApp via Fonnte API
 * 
 * @param string $nomor Nomor tujuan (format: 6281xxx)
 * @param string $pesan Isi pesan
 * @return array ['success' => bool, 'message' => string]
 */
function kirimNotifikasiWA($nomor, $pesan) {
    // Cek apakah API Key sudah dikonfigurasi
    if (!defined('WA_API_KEY') || WA_API_KEY === 'ISI_API_KEY_FONNTE_ANDA' || empty(WA_API_KEY)) {
        // Log saja, jangan error
        error_log("[WA-NOTIF] API Key belum dikonfigurasi. Pesan ke $nomor: $pesan");
        return [
            'success' => false, 
            'message' => 'API Key WhatsApp belum dikonfigurasi. Silakan isi WA_API_KEY di koneksi.php'
        ];
    }

    // Format nomor (pastikan pakai kode negara 62)
    $nomor = formatNomorWA($nomor);

    // Kirim via Fonnte API
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => WA_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => [
            'target' => $nomor,
            'message' => $pesan,
            'countryCode' => '62',
        ],
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . WA_API_KEY,
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        error_log("[WA-NOTIF] CURL Error: $err");
        return ['success' => false, 'message' => 'Gagal mengirim: ' . $err];
    }

    $result = json_decode($response, true);
    error_log("[WA-NOTIF] Response: $response");
    
    return [
        'success' => isset($result['status']) && $result['status'] === true,
        'message' => $response
    ];
}

/**
 * Format nomor HP ke format internasional (62xxx)
 */
function formatNomorWA($nomor) {
    // Hapus spasi, strip, kurung
    $nomor = preg_replace('/[\s\-\(\)]/', '', $nomor);
    
    // Ganti awalan 0 dengan 62
    if (substr($nomor, 0, 1) === '0') {
        $nomor = '62' . substr($nomor, 1);
    }
    
    // Ganti awalan +62 dengan 62
    if (substr($nomor, 0, 3) === '+62') {
        $nomor = '62' . substr($nomor, 3);
    }
    
    return $nomor;
}

/**
 * Kirim notifikasi booking baru ke pelanggan
 */
function notifBookingBaru($booking_data, $no_hp_pelanggan) {
    $pesan = "✅ *BOOKING BERHASIL!*\n\n"
           . "Halo, booking Anda telah dikonfirmasi:\n\n"
           . "📋 Kode: *{$booking_data['kode_booking']}*\n"
           . "✂️ Layanan: {$booking_data['nama_layanan']}\n"
           . "👤 Barber: {$booking_data['nama_barber']}\n"
           . "📅 Tanggal: {$booking_data['tanggal']}\n"
           . "🕐 Jam: {$booking_data['jam']}\n"
           . "💰 Harga: Rp " . number_format($booking_data['harga'], 0, ',', '.') . "\n\n"
           . "Silakan datang tepat waktu dan tunjukkan QR Code saat tiba di Vijer Barbershop.\n\n"
           . "Terima kasih! 🙏";

    return kirimNotifikasiWA($no_hp_pelanggan, $pesan);
}

/**
 * Kirim notifikasi ke admin tentang booking baru
 */
function notifAdminBookingBaru($booking_data, $nama_pelanggan) {
    if (!defined('WA_ADMIN_NUMBER') || empty(WA_ADMIN_NUMBER)) {
        return ['success' => false, 'message' => 'Nomor admin belum dikonfigurasi'];
    }

    $pesan = "🔔 *BOOKING BARU MASUK!*\n\n"
           . "📋 Kode: *{$booking_data['kode_booking']}*\n"
           . "👤 Pelanggan: {$nama_pelanggan}\n"
           . "✂️ Layanan: {$booking_data['nama_layanan']}\n"
           . "💇 Barber: {$booking_data['nama_barber']}\n"
           . "📅 Tanggal: {$booking_data['tanggal']}\n"
           . "🕐 Jam: {$booking_data['jam']}\n"
           . "💰 Harga: Rp " . number_format($booking_data['harga'], 0, ',', '.') . "\n\n"
           . "Silakan cek dashboard admin untuk detail.";

    return kirimNotifikasiWA(WA_ADMIN_NUMBER, $pesan);
}

/**
 * Kirim notifikasi check-in ke pelanggan
 */
function notifCheckIn($kode_booking, $no_hp_pelanggan, $nama_barber) {
    $pesan = "✅ *CHECK-IN BERHASIL!*\n\n"
           . "Kode Booking: *{$kode_booking}*\n"
           . "Barber Anda: {$nama_barber}\n\n"
           . "Anda telah berhasil check-in di Vijer Barbershop. Silakan menunggu giliran Anda.\n\n"
           . "Terima kasih sudah berkunjung! 💈";

    return kirimNotifikasiWA($no_hp_pelanggan, $pesan);
}
?>
