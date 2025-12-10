-- PERBAIKAN: sp_proses_penjualan_transaksi tanpa ORDER BY
-- ORDER BY dipindahkan ke backend PHP untuk efisiensi
-- Backend akan mengambil stok terbaru dan mengirimkan ke procedure

DELIMITER $$
DROP PROCEDURE IF EXISTS sp_proses_penjualan_transaksi $$
CREATE PROCEDURE sp_proses_penjualan_transaksi(
   IN p_idpenjualan INT,
   IN p_idbarang INT,
   IN p_jumlah_keluar INT,
   IN p_stok_sebelumnya INT
)
BEGIN
   DECLARE stok_sekarang INT;

   -- Calculate the new stock level after the sale.
   SET stok_sekarang = p_stok_sebelumnya - p_jumlah_keluar;

   -- Insert the new record into the stock card for the sales transaction.
   -- 'K' signifies stock 'Keluar' (Out).
   INSERT INTO kartu_stok (idbarang, jenis_transaksi, idtransaksi, keluar, masuk, stok, created_at)
   VALUES (p_idbarang, 'K', p_idpenjualan, p_jumlah_keluar, 0, stok_sekarang, NOW());
END $$
DELIMITER ;

-- PERBAIKAN: sp_proses_pembatalan_penjualan tanpa ORDER BY
-- ORDER BY dipindahkan ke backend PHP untuk efisiensi
-- Backend akan mengambil stok terbaru dan mengirimkan ke procedure

DELIMITER $$
DROP PROCEDURE IF EXISTS sp_proses_pembatalan_penjualan $$
CREATE PROCEDURE sp_proses_pembatalan_penjualan(
    IN p_idpenjualan INT,
    IN p_idbarang INT,
    IN p_jumlah_masuk INT,
    IN p_stok_sebelumnya INT
)
BEGIN
    DECLARE stok_sekarang INT;
    
    -- Calculate the new stock level after the reversal.
    SET stok_sekarang = p_stok_sebelumnya + p_jumlah_masuk;

    -- Insert reversal record into stock card
    -- 'B' signifies stock 'Batal Jual' (Sale Reversal).
    INSERT INTO kartu_stok (idbarang, jenis_transaksi, idtransaksi, keluar, masuk, stok, created_at)
    VALUES (p_idbarang, 'B', p_idpenjualan, 0, p_jumlah_masuk, stok_sekarang, NOW());
END $$
DELIMITER ;
