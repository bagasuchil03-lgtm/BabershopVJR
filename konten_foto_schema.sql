-- ============================================================
-- SCHEMA: Modul Manajemen Konten dengan Multi-Foto
-- Relasi: One-to-Many (konten → konten_foto)
-- Jalankan di phpMyAdmin atau MySQL CLI
-- ============================================================

USE db_booking_barbershop;

-- ------------------------------------------------------------
-- Tabel: konten  (satu konten bisa punya banyak foto)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `konten` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `judul`       VARCHAR(255)  NOT NULL                COMMENT 'Judul konten',
  `deskripsi`   TEXT                                  COMMENT 'Deskripsi / keterangan konten',
  `status`      ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif' COMMENT 'Status tampil konten',
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tabel master konten – satu konten bisa memiliki 2-5 foto';

-- ------------------------------------------------------------
-- Tabel: konten_foto  (foto-foto milik sebuah konten)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `konten_foto` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `id_konten`   INT(11)       NOT NULL                COMMENT 'FK ke tabel konten',
  `nama_file`   VARCHAR(255)  NOT NULL                COMMENT 'Nama file yang disimpan di server',
  `urutan`      TINYINT(4)    NOT NULL DEFAULT 0      COMMENT 'Urutan tampil foto (0 = pertama)',
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_id_konten` (`id_konten`),
  CONSTRAINT `fk_konten_foto_konten`
    FOREIGN KEY (`id_konten`) REFERENCES `konten`(`id`)
    ON DELETE CASCADE   -- Hapus konten → otomatis hapus semua baris foto di tabel ini
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tabel foto konten – Many side dari relasi One-to-Many';

-- ------------------------------------------------------------
-- Verifikasi struktur tabel
-- ------------------------------------------------------------
-- DESCRIBE konten;
-- DESCRIBE konten_foto;
-- SHOW CREATE TABLE konten_foto;
