<?php
require_once 'koneksi.php';

echo "Memulai proses pengisian data dummy...\n\n";

// 1. Seed Layanan
echo "Seeding Layanan...\n";
$conn->query("INSERT IGNORE INTO layanan (id_layanan, nama_layanan, harga, durasi_menit, image_path) VALUES 
(1, 'Premium Haircut', 75000, 45, 'assets/img/haircut1.jpg'),
(2, 'Basic Haircut', 50000, 30, 'assets/img/haircut2.jpg'),
(3, 'Hair Coloring', 150000, 90, 'assets/img/haircut3.jpg'),
(4, 'Beard Trim & Shave', 40000, 20, 'assets/img/shave.jpg')");
echo "- Data layanan berhasil ditambahkan.\n";

// 2. Seed Barber
echo "Seeding Barber...\n";
$conn->query("INSERT IGNORE INTO barber (id, id_barber, nama_barber, spesialisasi, foto_barber) VALUES 
(1, 1, 'Agus Setiawan', 'Fade & Undercut', 'assets/img/barber1.jpg'),
(2, 2, 'Bima Arya', 'Classic Styling', 'assets/img/barber2.jpg'),
(3, 3, 'Reza Rahadian', 'Hair Coloring & Treatment', 'assets/img/barber3.jpg')");
echo "- Data barber berhasil ditambahkan.\n";

// 3. Seed Gaya Rambut
echo "Seeding Gaya Rambut...\n";
$conn->query("INSERT IGNORE INTO gaya_rambut (id_gaya, nama_gaya, deskripsi, foto_gaya) VALUES 
(1, 'French Crop', 'Potongan rambut pendek dengan poni depan, cocok untuk gaya santai.', 'https://images.unsplash.com/photo-1519345182560-3f2917c472ef?auto=format&fit=crop&w=600&q=80'),
(2, 'Two Block', 'Gaya rambut ala Korea dengan samping tipis namun bervolume di atas.', 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=600&q=80'),
(3, 'Pompadour', 'Gaya rambut klasik bervolume di bagian atas, memberi kesan rapi dan elegan.', 'https://images.unsplash.com/photo-1593726852431-182312b91873?auto=format&fit=crop&w=600&q=80')");
echo "- Data gaya rambut berhasil ditambahkan.\n";

// 4. Seed Booking / Riwayat
echo "Seeding Booking (Riwayat & Pesanan)...\n";
$conn->query("INSERT INTO booking (nama_pelanggan, no_hp, tanggal_booking, jam_booking, id_layanan, id_barber, id_gaya, status, total_harga, catatan, metode_pembayaran, status_pembayaran, tanggal_pembayaran, checkin_at, tanggal_selesai, created_at) VALUES 
('Dimas Anggara', '081234567890', CURDATE() - INTERVAL 2 DAY, '10:00', 1, 1, 1, 'Selesai', 75000, 'Tolong cukur rapi', 'Transfer Bank', 'Lunas', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY),
('Rafi Ahmad', '081298765432', CURDATE() - INTERVAL 1 DAY, '14:00', 2, 2, 2, 'Selesai', 50000, '', 'Cash', 'Lunas', NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY),
('Kevin Sanjaya', '085612345678', CURDATE(), '16:00', 1, 3, 3, 'Proses', 75000, 'Jangan terlalu tipis', 'Transfer Bank', 'Menunggu Verifikasi', NOW(), NULL, NULL, NOW()),
('Jonatan Christie', '089912345678', CURDATE() + INTERVAL 1 DAY, '11:00', 3, 1, 1, 'Pending', 150000, '', 'E-Wallet', 'Menunggu Pembayaran', NULL, NULL, NULL, NOW()),
('Anthony Ginting', '081122334455', CURDATE() + INTERVAL 2 DAY, '13:00', 4, 2, NULL, 'Dibatalkan', 40000, 'Batal karena ada urusan mendadak', 'Transfer Bank', 'Ditolak', NULL, NULL, NULL, NOW())
");
echo "- Data booking berhasil ditambahkan.\n";

// 5. Seed Notifikasi
echo "Seeding Notifikasi...\n";
$conn->query("INSERT INTO notifikasi (no_hp, pesan, status_baca, tanggal_dibuat) VALUES 
('081234567890', 'Booking Anda untuk besok jam 10:00 telah dikonfirmasi.', 1, NOW() - INTERVAL 3 DAY),
('089912345678', 'Pembayaran Anda sebesar Rp 150.000 sedang menunggu verifikasi.', 0, NOW())
");
echo "- Data notifikasi berhasil ditambahkan.\n";

echo "\nSelesai! Data dummy berhasil disuntikkan ke database.\n";
