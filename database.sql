CREATE TABLE IF NOT EXISTS `pesanan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `total_harga` decimal(10,2) NOT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `status` enum('Menunggu Konfirmasi','Dikonfirmasi','Ditolak') DEFAULT 'Menunggu Konfirmasi',
  `tanggal_pesanan` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data dummy untuk testing
INSERT INTO `pesanan` (`id_user`, `total_harga`, `bukti_pembayaran`, `status`) VALUES
(1, 150000.00, 'bukti_1.jpg', 'Menunggu Konfirmasi'),
(2, 200000.00, 'bukti_2.jpg', 'Dikonfirmasi'),
(3, 75000.00, 'bukti_3.jpg', 'Menunggu Konfirmasi');
