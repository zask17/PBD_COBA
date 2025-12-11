-- ========================================================================================================================================================================
-- SOFT DELETE MIGRATION PLAN
-- ========================================================================================================================================================================
-- Mengubah semua hard delete menjadi soft delete dengan menambahkan kolom deleted_at
-- Data yang di-delete akan tersimpan di database dengan timestamp penghapusan

-- ========================================================================================================================================================================
-- 1. ADD deleted_at COLUMN TO TRANSACTION TABLES
-- ========================================================================================================================================================================

-- PENGADAAN TABLE
ALTER TABLE pengadaan 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;

-- DETAIL_PENGADAAN TABLE  
ALTER TABLE detail_pengadaan 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;

-- PENJUALAN TABLE
ALTER TABLE penjualan 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;

-- DETAIL_PENJUALAN TABLE
ALTER TABLE detail_penjualan 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;

-- PENERIMAAN TABLE
ALTER TABLE penerimaan 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;

-- DETAIL_PENERIMAAN TABLE
ALTER TABLE detail_penerimaan 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;


-- ========================================================================================================================================================================
-- 2. UPDATE PHP QUERIES - SOFT DELETE LOGIC
-- ========================================================================================================================================================================

-- NOTE: Semua SELECT queries harus menambahkan:
-- WHERE deleted_at IS NULL  (untuk hanya menampilkan data yang TIDAK terhapus)

-- Contoh update di model/pengadaan.php untuk deletePengadaan():
-- BEFORE:
--   DELETE FROM pengadaan WHERE idpengadaan = ?
-- AFTER:
--   UPDATE pengadaan SET deleted_at = NOW() WHERE idpengadaan = ?

-- Contoh update di model/penjualan.php untuk handleDelete():
-- BEFORE:
--   DELETE FROM detail_penjualan WHERE penjualan_idpenjualan = $id
--   DELETE FROM penjualan WHERE idpenjualan = $id
--   DELETE FROM kartu_stok WHERE idtransaksi = $id AND jenis_transaksi IN ('K','B')
-- AFTER:
--   UPDATE detail_penjualan SET deleted_at = NOW() WHERE penjualan_idpenjualan = ?
--   UPDATE penjualan SET deleted_at = NOW() WHERE idpenjualan = ?
--   UPDATE kartu_stok SET deleted_at = NOW() WHERE idtransaksi = ? AND jenis_transaksi IN ('K','B')


-- ========================================================================================================================================================================
-- 3. KEUNTUNGAN SOFT DELETE
-- ========================================================================================================================================================================
-- ✅ Data tetap tersimpan untuk audit trail dan laporan historis
-- ✅ Tidak merusak referensi foreign key
-- ✅ Dapat di-restore dengan mudah (SET deleted_at = NULL)
-- ✅ Analisis data historis masih memungkinkan
-- ✅ Compliance dan regulatory requirements (data retention)


-- ========================================================================================================================================================================
-- 4. QUERIES UNTUK SOFT DELETE & RESTORE
-- ========================================================================================================================================================================

-- SOFT DELETE Pengadaan
-- UPDATE pengadaan SET deleted_at = NOW() WHERE idpengadaan = ?;

-- RESTORE Pengadaan (jika diperlukan)
-- UPDATE pengadaan SET deleted_at = NULL WHERE idpengadaan = ?;

-- Lihat semua pengadaan yang BELUM dihapus
-- SELECT * FROM pengadaan WHERE deleted_at IS NULL;

-- Lihat semua pengadaan yang SUDAH dihapus (untuk audit)
-- SELECT * FROM pengadaan WHERE deleted_at IS NOT NULL;


-- ========================================================================================================================================================================
-- 5. AFFECTED PHP FILES FOR UPDATE
-- ========================================================================================================================================================================
-- 1. model/pengadaan.php
--    - deletePengadaan(): Ubah DELETE ke UPDATE soft delete
--    - getAllPengadaan(): Tambah WHERE deleted_at IS NULL

-- 2. model/penjualan.php
--    - handleDelete(): Ubah DELETE ke UPDATE soft delete (detail_penjualan, penjualan, kartu_stok)
--    - handleGet('list_penjualan'): Tambah WHERE deleted_at IS NULL
--    - Update kartu_stok query juga perlu soft delete

-- 3. model/penerimaan.php
--    - handleUpdate(): Ubah DELETE ke UPDATE soft delete (kartu_stok, detail_penerimaan)
--    - handleGet('get_penerimaan'): Tambah WHERE deleted_at IS NULL

-- 4. model/barang.php
--    - get_all_barang_list(): Tambah WHERE deleted_at IS NULL jika ada

-- 5. Query di semua controller yang SELECT dari tabel transaksi
--    - Tambah WHERE deleted_at IS NULL atau WHERE deleted_at IS NULL JOIN
