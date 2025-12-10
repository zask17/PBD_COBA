-- ================================================================
-- OPTIMIZED QUERIES - ORDER BY Removed (Backend Handle Sorting)
-- ================================================================
-- Tujuan: Kumpulan query optimized tanpa ORDER BY
--         Backend PHP yang handle sorting untuk konsistensi
-- ================================================================

-- ================================================================
-- 1. Get Stok Terbaru (Tanpa ORDER BY)
-- ================================================================
-- Digunakan di: model/penjualan.php, model/penerimaan.php
-- Backend akan sorting: ORDER BY created_at DESC, idkartu_stok DESC
SELECT stok, created_at, idkartu_stok 
FROM kartu_stok 
WHERE idbarang = ?;

-- Versi simple (hanya stok):
SELECT stok 
FROM kartu_stok 
WHERE idbarang = ?
LIMIT 1;

-- ================================================================
-- 2. Get Barang List (Tanpa ORDER BY)
-- ================================================================
-- Digunakan di: model/penjualan.php - handleGet('list_barang')
-- Backend akan sorting: BY idbarang ASC
SELECT b.idbarang, b.nama, b.harga, b.status 
FROM barang b 
JOIN satuan s ON b.idsatuan = s.idsatuan;

-- ================================================================
-- 3. Get Margin Aktif (Tanpa ORDER BY)
-- ================================================================
-- Digunakan di: model/penjualan.php - handleGet('list_margins')
-- Backend akan sorting: BY created_at DESC, ambil 1 latest
SELECT idmargin_penjualan, persen, created_at 
FROM margin_penjualan 
WHERE status = 1;

-- ================================================================
-- 4. Get Penjualan List (Tanpa ORDER BY)
-- ================================================================
-- Digunakan di: model/penjualan.php - handleGet('list_penjualan')
-- Backend akan sorting: BY idpenjualan DESC
SELECT p.idpenjualan, p.created_at, u.username, 
       mp.persen AS margin_persen, p.total_nilai
FROM penjualan p
JOIN user u ON p.iduser = u.iduser
JOIN margin_penjualan mp ON p.idmargin_penjualan = mp.idmargin_penjualan;

-- ================================================================
-- 5. Get Open PO (Tanpa ORDER BY)
-- ================================================================
-- Digunakan di: model/penerimaan.php - get_open_pos()
-- Backend akan sorting: BY idpengadaan DESC
SELECT p.idpengadaan, p.no_po, v.nama AS vendor_nama, p.created_at
FROM pengadaan p
JOIN vendor v ON p.idvendor = v.idvendor
WHERE p.status = 'PO Open';

-- ================================================================
-- 6. Get Pengadaan List (Tanpa ORDER BY)
-- ================================================================
-- Digunakan di: model/pengadaan.php - getAllPengadaan()
-- Backend akan sorting: BY idpengadaan DESC
SELECT p.idpengadaan, p.no_po, v.nama AS vendor_nama, 
       p.created_at, p.status
FROM pengadaan p
JOIN vendor v ON p.idvendor = v.idvendor;

-- ================================================================
-- 7. Get Penerimaan List (Tanpa ORDER BY)
-- ================================================================
-- Digunakan di: model/penerimaan.php - get_penerimaan()
-- Backend akan sorting: BY idpenerimaan DESC
SELECT pr.idpenerimaan, pr.created_at, pr.no_dn, p.no_po, p.status,
       v.nama AS vendor_nama, u.username
FROM penerimaan pr
JOIN pengadaan p ON pr.idpengadaan = p.idpengadaan
JOIN vendor v ON p.idvendor = v.idvendor
JOIN user u ON pr.iduser = u.iduser;

-- ================================================================
-- Database Functions: Tanpa ORDER BY Version
-- ================================================================

DELIMITER $$

-- Function: Get Stok Terakhir (tanpa ORDER BY)
CREATE FUNCTION stok_terakhir_no_order(p_idbarang INT)
RETURNS INT
READS SQL DATA
BEGIN
    DECLARE v_stok_terakhir INT;

    SELECT stok INTO v_stok_terakhir
    FROM kartu_stok
    WHERE idbarang = p_idbarang
    LIMIT 1;
    
    RETURN COALESCE(v_stok_terakhir, 0);
END$$

DELIMITER ;

-- ================================================================
-- Catatan:
-- ================================================================
-- 1. Semua query di atas TANPA ORDER BY di SQL
-- 2. Backend PHP handle sorting menggunakan usort()
-- 3. Ini standardisasi: Sorting logic terpusat di backend
-- 4. Keuntungan: Mudah di-debug, konsisten, fleksibel
-- 5. Database function lama (stok_terakhir) masih bisa dipakai
--    jika butuh ORDER BY di database level
-- ================================================================
