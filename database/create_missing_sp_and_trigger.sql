-- ========================================================================================================================================================================
-- MISSING DATABASE OBJECTS FOR STOCK MANAGEMENT
-- ========================================================================================================================================================================

-- 1. STORED PROCEDURE: Update PO status after receipt
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_update_status_pengadaan_setelah_terima $$
CREATE PROCEDURE sp_update_status_pengadaan_setelah_terima (
    IN p_idpengadaan INT
)
BEGIN
    DECLARE v_total_dipesan_all INT;
    DECLARE v_total_diterima_all INT;
    DECLARE v_status_po CHAR(1);

    -- 1. Hitung total jumlah barang dipesan di PO
    SELECT COALESCE(SUM(dp.jumlah), 0)
    INTO v_total_dipesan_all
    FROM detail_pengadaan dp
    WHERE dp.idpengadaan = p_idpengadaan;

    -- 2. Hitung total jumlah barang yang SUDAH diterima (dari semua penerimaan untuk PO ini)
    SELECT COALESCE(SUM(dpr.jumlah_terima), 0)
    INTO v_total_diterima_all
    FROM penerimaan pr
    JOIN detail_penerimaan dpr ON pr.idpenerimaan = dpr.idpenerimaan
    WHERE pr.idpengadaan = p_idpengadaan;

    -- 3. Tentukan Status Pengadaan (PO)
    IF v_total_diterima_all = v_total_dipesan_all THEN
        SET v_status_po = 'f'; -- F = Full/Diterima Penuh
    ELSEIF v_total_diterima_all > 0 THEN
        SET v_status_po = 's'; -- S = Partial/Sebagian
    ELSE
        SET v_status_po = 'p'; -- P = Process/Dipesan
    END IF;

    -- 4. Update Header Pengadaan
    UPDATE pengadaan
    SET status = v_status_po
    WHERE idpengadaan = p_idpengadaan;
END $$
DELIMITER ;

-- 2. TRIGGER: Automatic stock card update on receipt detail insert
DELIMITER $$
DROP TRIGGER IF EXISTS trg_stok_masuk_penerimaan $$
CREATE TRIGGER trg_stok_masuk_penerimaan
AFTER INSERT ON detail_penerimaan
FOR EACH ROW
BEGIN
    DECLARE v_stok_terakhir INT;
    DECLARE v_harga_pokok_saat_ini INT;

    -- Ambil harga pokok barang saat ini
    SELECT harga INTO v_harga_pokok_saat_ini
    FROM barang
    WHERE idbarang = NEW.barang_idbarang;

    -- Ambil stok terakhir (via function)
    SET v_stok_terakhir = COALESCE(stok_terakhir(NEW.barang_idbarang), 0);

    -- Insert kartu stok
    INSERT INTO kartu_stok (
        idbarang, jenis_transaksi, masuk, keluar, stok, created_at, idtransaksi
    ) VALUES (
        NEW.barang_idbarang,
        'M',
        NEW.jumlah_terima,
        0,
        v_stok_terakhir + NEW.jumlah_terima,
        NOW(),
        NEW.idpenerimaan
    );

    -- Jika harga satuan berubah → update harga pokok
    IF NEW.harga_satuan_terima != v_harga_pokok_saat_ini THEN
        CALL sp_update_harga_jual_barang(NEW.barang_idbarang, NEW.harga_satuan_terima);
    END IF;
END $$
DELIMITER ;

-- 3. TRIGGER: Automatic subtotal calculation for receipt details
DELIMITER $$
DROP TRIGGER IF EXISTS trg_hitung_subtotal_penerimaan $$
CREATE TRIGGER trg_hitung_subtotal_penerimaan
BEFORE INSERT ON detail_penerimaan
FOR EACH ROW
BEGIN
    -- Menghitung sub_total_terima
    SET NEW.sub_total_terima = NEW.jumlah_terima * NEW.harga_satuan_terima;
END $$
DELIMITER ;

-- 4. TRIGGER: Update subtotal on update
DELIMITER $$
DROP TRIGGER IF EXISTS trg_update_subtotal_penerimaan $$
CREATE TRIGGER trg_update_subtotal_penerimaan
BEFORE UPDATE ON detail_penerimaan
FOR EACH ROW
BEGIN
    -- Menghitung sub_total_terima
    SET NEW.sub_total_terima = NEW.jumlah_terima * NEW.harga_satuan_terima;
END $$
DELIMITER ;
