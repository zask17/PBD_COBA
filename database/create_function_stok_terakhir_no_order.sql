-- ================================================================
-- FUNCTION stok_terakhir - Versi Tanpa ORDER BY (Backend Handle Sorting)
-- ================================================================
-- Purpose: Ambil stok terakhir untuk barang tertentu
-- Note: ORDER BY dihilangkan dari query. Backend (PHP) yang handle sorting
--       untuk performa lebih baik dan konsistensi dengan standardisasi backend sorting
-- ================================================================

DELIMITER $$

CREATE FUNCTION stok_terakhir_no_order(p_idbarang INT)
RETURNS INT
READS SQL DATA
BEGIN
    DECLARE v_stok_terakhir INT;

    -- Ambil stok terakhir
    -- ORDER BY dihilangkan - PHP akan handle sorting berdasarkan created_at DESC, idkartu_stok DESC
    SELECT stok INTO v_stok_terakhir
    FROM kartu_stok
    WHERE idbarang = p_idbarang
    LIMIT 1;
    
    -- Jika tidak ada record, return 0
    RETURN COALESCE(v_stok_terakhir, 0);
END$$

DELIMITER ;

-- ================================================================
-- Catatan Implementasi:
-- ================================================================
-- 1. Function lama (stok_terakhir) masih ada dengan ORDER BY di database
-- 2. Function baru (stok_terakhir_no_order) tanpa ORDER BY
-- 3. Backend PHP harus:
--    - Query: SELECT stok FROM kartu_stok WHERE idbarang = X ORDER BY created_at DESC, idkartu_stok DESC LIMIT 1
--    - Atau gunakan loop dan sort di PHP dengan usort()
-- 4. Kedua function bisa digunakan - bergantung kebutuhan performa
-- ================================================================
