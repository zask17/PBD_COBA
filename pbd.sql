-- ========================================================================================================================================================================
-- DDL
-- ========================================================================================================================================================================

-- MASTER TABLE

CREATE TABLE role (
    idrole		INT				NOT NULL,
    nama_role	VARCHAR(100)	NOT NULL,
    CONSTRAINT PK_role PRIMARY KEY (idrole)
);

INSERT INTO role (idrole, nama_role) VALUES
(1, 'super administrator'),
(2, 'administrator');


CREATE TABLE vendor (
    idvendor	INT				NOT NULL AUTO_INCREMENT,
    nama_vendor	VARCHAR(100)	NOT NULL,
    badan_hukum	CHAR(1)			NOT NULL,
    status		CHAR(1)			NOT NULL,
    CONSTRAINT PK_vendor PRIMARY KEY (idvendor)
);
-- badan_hukum = 'A' THEN 'Berbadan Hukum (Contoh: PT)'
-- badan_hukum = 'T' THEN 'Tidak Berbadan Hukum (Contoh: CV/UD)'
-- WHEN status = 'A' THEN 'Aktif'
-- WHEN status = 'T' THEN 'Non-Aktif'

INSERT INTO vendor (nama_vendor, badan_hukum, status) values
('pt berkah selalu', 'A', 'A'),
('cv jaya abadi', 'A', 'A'),
('ud mitra sejahtera', 'T', 'A'),
('toko lima bersaudara', 'T', 'T'),
('pt sinar baja', 'A', 'A');



CREATE TABLE satuan (
    idsatuan	INT				NOT NULL AUTO_INCREMENT,
    nama_satuan	VARCHAR(45)		NOT NULL,
    status		tinyint			NOT NULL,
    CONSTRAINT PK_satuan PRIMARY KEY (idsatuan)
);

INSERT INTO satuan (nama_satuan, status) VALUES
('PCS', 1),
('UNIT', 1),
('BOX', 1),
('ROLL', 1),
('METER', 1),
('PACK', 1),
('Kg', 1),
('gr', 1),
('LITER', 1),
('DUS', 1);


-- ========================================================================================================================================================================
-- (1FK)

CREATE TABLE user (
    iduser		INT				NOT NULL AUTO_INCREMENT,
    username	VARCHAR(45)		NOT NULL UNIQUE,
    password	VARCHAR(100)	NOT NULL,
    idrole		INT				NOT NULL,
	CONSTRAINT PK_user PRIMARY KEY (iduser),
    CONSTRAINT FK_user_role FOREIGN KEY (idrole) REFERENCES role(idrole) on delete restrict
);

INSERT INTO user (username, password, idrole) VALUES
('neikos', 'neikos496', 1), -- Super Administrator
('khaslana', 'khas.33550336', 1), -- Super Administrator
('deyi', 'tenth', 2), -- Administrator
('ren', 'jyyx', 2), -- Administrator
('geppie', 'landau', 2);  -- Administrator


CREATE TABLE barang (
    idbarang	INT			NOT NULL AUTO_INCREMENT,
    jenis		CHAR(1)		NOT NULL,
    nama		VARCHAR(45)	NOT NULL,
    idsatuan	INT			NOT NULL,
    status		tinyint		NOT NULL,
    harga		INT			NOT NULL,
    CONSTRAINT PK_barang PRIMARY KEY (idbarang),
    CONSTRAINT FK_barang_satuan FOREIGN KEY (idsatuan) REFERENCES satuan(idsatuan) on delete restrict
);
-- J = Barang Jadi
-- B = Bahan Baku
-- 1 = Aktif
-- 0 = Tidak Aktif

INSERT INTO barang (jenis, nama, idsatuan, status, harga) VALUES
('J', 'Mie Instan Kuah Rasa Ayam Bawang', 10, 1, 120000), 
('J', 'Minyak Goreng Pouch 2 Liter', 9, 1, 35000), 
('J', 'Sabun Mandi Batang', 1, 1, 3500), 
('B', 'Tepung Terigu Serbaguna 1 Kg', 7, 1, 14000), 
('J', 'Kopi Sachet Mix Box (10 pcs)', 3, 1, 25000);


CREATE TABLE kartu_stok (
    idkartu_stok	INT			NOT NULL AUTO_INCREMENT,
    jenis_transaksi	CHAR(1)		NOT NULL,
    masuk 			INT			NOT NULL,
    keluar 			INT			NOT NULL,
    stok 			INT			NOT NULL,
    created_at 		TIMESTAMP	NOT NULL	DEFAULT CURRENT_TIMESTAMP,
    idtransaksi		INT			NOT NULL,
    idbarang 		INT			NOT NULL,
	CONSTRAINT PK_kartu_stok PRIMARY KEY (idkartu_stok),
    CONSTRAINT FK_kartu_stok_barang FOREIGN KEY (idbarang) REFERENCES barang(idbarang) on delete restrict
);


CREATE TABLE margin_penjualan (
    idmargin_penjualan	INT			NOT NULL AUTO_INCREMENT,
	created_at			TIMESTAMP	NOT NULL	DEFAULT CURRENT_TIMESTAMP,
	persen				DOUBLE		NOT NULL,
    status				tinyint		NOT NULL,
    iduser				INT			NOT NULL,
    updated_at			TIMESTAMP	NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT PK_margin_penjualan PRIMARY KEY (idmargin_penjualan),
    CONSTRAINT FK_margin_penjualan_user FOREIGN KEY (iduser) REFERENCES user(iduser) on delete restrict
);


INSERT INTO margin_penjualan (persen, status, iduser) VALUES
(10, 1, 1),
(15, 0, 1),
(20, 1, 2),
(25, 1, 2),
(30, 1, 3),
(55, 0, 3);

-- ========================================================================================================================================================================
-- 2FK

CREATE TABLE pengadaan (
    idpengadaan		INT		NOT NULL AUTO_INCREMENT,
    timestamp		TIMESTAMP	NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_iduser 	INT			NOT NULL,
    status			CHAR(1)		NOT NULL,
    vendor_idvendor INT			NOT NULL,
    subtotal_nilai	INT			NOT NULL,
    ppn 			INT			NOT NULL,
    total_nilai 	INT			NOT NULL,
    CONSTRAINT PK_pengadaan PRIMARY KEY (idpengadaan),
    CONSTRAINT FK_pengadaan_user FOREIGN KEY (user_iduser) REFERENCES user(iduser) on delete restrict,
    CONSTRAINT FK_pengadaan_vendor FOREIGN KEY (vendor_idvendor) REFERENCES vendor(idvendor) on delete restrict
);


CREATE TABLE detail_pengadaan (
    iddetail_pengadaan	INT		NOT NULL,
    harga_satuan 		INT		NOT NULL,
    jumlah 				INT		NOT NULL,
    sub_total 			INT		NOT NULL,
    idbarang 			INT		NOT NULL,
    idpengadaan 		INT		NOT NULL,
    CONSTRAINT PK_detail_pengadaan PRIMARY KEY (iddetail_pengadaan),
    CONSTRAINT FK_detail_pengadaan_pengadaan FOREIGN KEY (idpengadaan) REFERENCES pengadaan(idpengadaan) on delete cascade,
    CONSTRAINT FK_detail_pengadaan_barang FOREIGN KEY (idbarang) REFERENCES barang(idbarang) on delete restrict
);

ALTER TABLE detail_pengadaan
MODIFY iddetail_pengadaan INT NOT NULL AUTO_INCREMENT;


CREATE TABLE penjualan (
    idpenjualan			INT			NOT NULL AUTO_INCREMENT,
    created_at			TIMESTAMP	NOT NULL DEFAULT CURRENT_TIMESTAMP,
    subtotal_nilai		INT			NOT NULL,
    ppn					INT			NOT NULL,
    total_nilai			INT			NOT NULL,
    iduser				INT			NOT NULL,
    idmargin_penjualan	INT			NOT NULL,	
    CONSTRAINT PK_penjualan PRIMARY KEY (idpenjualan),
    CONSTRAINT FK_penjualan_user FOREIGN KEY (iduser) REFERENCES user(iduser) on delete restrict,
    CONSTRAINT FK_penjualan_margin_penjualan FOREIGN KEY (idmargin_penjualan) REFERENCES margin_penjualan(idmargin_penjualan) on delete restrict
);


CREATE TABLE detail_penjualan (
    iddetail_penjualan 	INT			NOT NULL AUTO_INCREMENT,
    harga_satuan 		INT			NOT NULL,
    jumlah 				INT			NOT NULL,
    subtotal 			INT			NOT NULL,
    penjualan_idpenjualan	INT		NOT NULL,
    idbarang 			INT			NOT NULL,
    CONSTRAINT PK_detail_penjualan PRIMARY KEY (iddetail_penjualan),
    CONSTRAINT FK_detail_penjualan_penjualan FOREIGN KEY (penjualan_idpenjualan) REFERENCES penjualan(idpenjualan) on delete cascade,
    CONSTRAINT FK_detail_penjualan_barang FOREIGN KEY (idbarang) REFERENCES barang(idbarang) on delete restrict
);


CREATE TABLE penerimaan (
    idpenerimaan 	INT			NOT NULL AUTO_INCREMENT,
    created_at 		TIMESTAMP		NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status 			CHAR(1)			NOT NULL,
    idpengadaan 	INT			NOT NULL,
    iduser 			INT				NOT NULL,
    CONSTRAINT PK_penerimaan PRIMARY KEY (idpenerimaan),
    CONSTRAINT FK_penerimaan_user FOREIGN KEY (iduser) REFERENCES user(iduser) on delete restrict,
    CONSTRAINT FK_penerimaan_pengadaan FOREIGN KEY (idpengadaan) REFERENCES pengadaan(idpengadaan) on delete restrict
);


CREATE TABLE detail_penerimaan (
    iddetail_penerimaan	INT		NOT NULL AUTO_INCREMENT,
    idpenerimaan 		INT		NOT NULL,
    barang_idbarang 	INT		NOT NULL,
    jumlah_terima 		INT		NOT NULL,
    harga_satuan_terima	INT		NOT NULL,
    sub_total_terima 	INT		NOT NULL,
    CONSTRAINT PK_detail_penerimaan PRIMARY KEY (iddetail_penerimaan),
    CONSTRAINT FK_detail_penerimaan_penerimaan FOREIGN KEY (idpenerimaan) REFERENCES penerimaan(idpenerimaan) on delete cascade,
	CONSTRAINT FK_detail_penerimaan_barang FOREIGN KEY (barang_idbarang) REFERENCES barang(idbarang) on delete restrict
);


CREATE TABLE retur (
    idretur 		INT			NOT NULL AUTO_INCREMENT,
    created_at 		TIMESTAMP	NOT NULL DEFAULT CURRENT_TIMESTAMP,
    idpenerimaan	INT			NOT NULL,
    iduser 			INT			NOT NULL,
    CONSTRAINT PK_retur PRIMARY KEY (idretur),
    CONSTRAINT FK_retur_penerimaan FOREIGN KEY (idpenerimaan) REFERENCES penerimaan(idpenerimaan) on delete restrict,
    CONSTRAINT FK_retur_user FOREIGN KEY (iduser) REFERENCES user(iduser) on delete restrict
);


CREATE TABLE detail_retur (
    iddetail_retur 	INT				NOT NULL AUTO_INCREMENT,
    jumlah 			INT				NOT NULL,
    alasan 			VARCHAR(200)	NOT NULL,
    idretur 		INT				NOT NULL,
    iddetail_penerimaan	INT			NOT NULL,
    CONSTRAINT PK_detail_retur PRIMARY KEY (iddetail_retur),
    CONSTRAINT FK_detail_retur_retur FOREIGN KEY (idretur) REFERENCES retur(idretur) on delete cascade,
    CONSTRAINT FK_detail_retur_detail_penerimaan FOREIGN KEY (iddetail_penerimaan) REFERENCES detail_penerimaan(iddetail_penerimaan) on delete restrict
);


-- ========================================================================================================================================================================
-- VIEW
-- ========================================================================================================================================================================
-- ====================================================================
-- VIEW MASTER
-- ====================================================================

-- 1. DAFTAR USER
CREATE VIEW V_USER_ROLE AS
SELECT
    u.iduser,
    u.username as NAMA,
--     u.idrole,
    r.nama_role AS ROLE
FROM 
    user u
JOIN 
    role r ON u.idrole = r.idrole;


-- 2. DAFTAR ROLE
CREATE VIEW V_ROLE AS
SELECT
    idrole,
    nama_role as ROLE
FROM 
    role;


-- 3. BARANG SEMUA
CREATE VIEW V_BARANG_SEMUA
AS
SELECT
    b.idbarang AS 'KODE BARANG',
    b.nama AS 'NAMA BARANG',
    b.harga AS 'HARGA POKOK',
    s.nama_satuan AS SATUAN,
    CASE b.jenis
        WHEN 'J' THEN 'BARANG JADI'
        WHEN 'B' THEN 'BAHAN BAKU'
        ELSE 'Lainnya'
    END AS 'JENIS BARANG',
    CASE b.status
        WHEN 1 THEN 'AKTIF'
        ELSE 'NON-AKTIF'
    END AS 'STATUS BARANG'
FROM barang b
LEFT JOIN satuan s ON b.idsatuan = s.idsatuan;


-- 4. SATUAN SEMUA
CREATE VIEW V_SATUAN_SEMUA AS
SELECT
    idsatuan,
    nama_satuan as SATUAN,
    case status
        WHEN 1 THEN 'Aktif'
        WHEN 0 THEN 'Non-Aktif'
        ELSE 'Status Tidak Diketahui'
    END AS 'STATUS SATUAN'
FROM
    satuan;


-- 5. VENDOR SEMUA
CREATE VIEW V_VENDOR_SEMUA AS
SELECT
    idvendor,
    nama_vendor as VENDOR,
    case badan_hukum
        WHEN 'A' THEN 'Berbadan Hukum'
        WHEN 'T' THEN 'Tidak Berbadan Hukum'
        ELSE 'Tidak Diketahui'
    END AS 'BADAN HUKUM',
    case status
        WHEN 'A' THEN 'Aktif'
        WHEN 'T' THEN 'Non-Aktif'
        ELSE 'Status Lain'
    END AS STATUS
FROM
    vendor;


-- badan_hukum = 'A' THEN 'Berbadan Hukum (Contoh: PT)'
-- badan_hukum = 'T' THEN 'Tidak Berbadan Hukum (Contoh: CV/UD)'

-- 6. MARGIN SEMUA
CREATE VIEW V_MARGIN_SEMUA
AS
SELECT
    mp.idmargin_penjualan,
    mp.persen AS PERSEN_MARGIN,
    mp.created_at AS DIBUAT,
    mp.updated_at AS DIUPDATE,
    u.username AS 'DIBUAT OLEH',
    CASE mp.status
        WHEN 1 THEN 'AKTIF'
        ELSE 'TIDAK AKTIF'
    END AS STATUS
FROM margin_penjualan mp
JOIN user u ON mp.iduser = u.iduser;


-- ====================================================================
-- VIEW LAINNYA
-- ====================================================================

-- 1. BARANG AKTIF
CREATE VIEW V_BARANG_AKTIF
AS
SELECT
    b.idbarang AS 'KODE BARANG',
    b.nama AS 'NAMA BARANG',
    b.harga AS 'HARGA POKOK',
    s.nama_satuan AS SATUAN,
    CASE b.jenis
        WHEN 'J' THEN 'BARANG JADI'
        WHEN 'B' THEN 'BAHAN BAKU'
        ELSE 'Lainnya'
    END AS 'JENIS BARANG',
    'AKTIF' as 'STATUS BARANG'
FROM barang b
JOIN satuan s ON b.idsatuan = s.idsatuan
WHERE b.status = 1;


-- 2. MARGIN AKTIF
CREATE VIEW V_MARGIN_AKTIF
AS
SELECT
    mp.idmargin_penjualan,
    mp.persen,
    mp.created_at as DIBUAT,
    u.username AS 'DIBUAT OLEH',
    'AKTIF' AS STATUS
FROM margin_penjualan mp
JOIN user u ON mp.iduser = u.iduser
WHERE mp.status = 1;



-- 3. SATUAN AKTIF
CREATE VIEW V_SATUAN_AKTIF AS
SELECT
    idsatuan,
    nama_satuan as SATUAN
FROM
    satuan
WHERE
    status = 1;




-- 4. VENDOR AKTIF
CREATE OR REPLACE VIEW V_VENDOR_AKTIF AS
SELECT
   idvendor,
   vendor,
   'BADAN HUKUM',
   STATUS
FROM
   V_VENDOR_SEMUA
WHERE
   status= 'Aktif';

select * from V_VENDOR_AKTIF;



-- ========================================================================================================================================================================
-- FUNCTION
-- ========================================================================================================================================================================
-- 1. HITUNG HARGA JUAL (TERMASUK MARGIN)

DROP FUNCTION IF EXISTS hitung_harga_jual_dengan_margin;
--  Harga dihitung di frontend JS

DELIMITER $$
CREATE FUNCTION hitung_harga_jual_dengan_margin(p_idbarang INT)
RETURNS DECIMAL(20,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_harga_pokok DECIMAL(20,2);
    DECLARE v_margin_persen DOUBLE;
    DECLARE v_harga_jual DECIMAL(20,2);

    -- 1. Ambil harga pokok barang
    SELECT harga INTO v_harga_pokok
    FROM barang
    WHERE idbarang = p_idbarang;

    -- 2. Ambil presentase margin (tanpa ORDER BY di fungsi)
    SELECT persen INTO v_margin_persen
    FROM margin_penjualan
    WHERE status = 1
    AND idmargin_penjualan = (
        SELECT MAX(idmargin_penjualan)
        FROM margin_penjualan
        WHERE status = 1
    );
    
    -- 3. Jika tidak ada margin aktif, kembalikan harga pokok
    IF v_margin_persen IS NULL THEN
        RETURN v_harga_pokok;
    END IF;
    
    -- 4. Hitung Harga Jual: Harga Pokok + (Harga Pokok * Margin / 100)
    SET v_harga_jual = v_harga_pokok + (v_harga_pokok * (v_margin_persen / 100));

    RETURN v_harga_jual;
END $$
DELIMITER ;




-- 2. STOK BARANG TERAKHIR (BARANG.PHP, MANAGE_KARTU_STOK.PHP)
DROP FUNCTION IF EXISTS stok_terakhir;

DELIMITER $$
CREATE FUNCTION stok_terakhir(p_idbarang INT)
RETURNS INT
READS SQL DATA
BEGIN
    DECLARE v_stok_terakhir INT;
    DECLARE v_max_id INT;

    -- Dapatkan ID kartu stok terbaru untuk barang ini
    SELECT MAX(idkartu_stok) INTO v_max_id
    FROM kartu_stok
    WHERE idbarang = p_idbarang;
    
    -- Jika tidak ada record, return 0
    IF v_max_id IS NULL THEN
        RETURN 0;
    END IF;
    
    -- Ambil stok berdasarkan ID terbaru
    SELECT stok INTO v_stok_terakhir
    FROM kartu_stok
    WHERE idkartu_stok = v_max_id
    AND idbarang = p_idbarang;
    
    RETURN COALESCE(v_stok_terakhir, 0);
END$$
DELIMITER ;

SHOW CREATE FUNCTION stok_terakhir;


-- 
-- ========================================================================================================================================================================
-- INDEX
-- ========================================================================================================================================================================
-- 

-- Index untuk kartu_stok (kartu_stok.php, stok_terakhir())
CREATE INDEX idx_kartu_stok_idbarang ON kartu_stok(idbarang);
CREATE INDEX idx_kartu_stok_idbarang_idkartu ON kartu_stok(idbarang, idkartu_stok);



-- Index untuk barang (barang.php, V_BARANG_SEMUA)
CREATE INDEX idx_barang_idbarang ON barang(idbarang);



-- Index untuk margin_penjualan (penjualan.php)
CREATE INDEX idx_margin_status_id ON margin_penjualan(status, idmargin_penjualan);
SHOW INDEX FROM margin_penjualan;

-- ========================================================================================================================================================================
-- STORAGE PROCEDURES
-- ========================================================================================================================================================================
-- 
-- 1. STORE PROCEDURE HITUNG DAN FINALISASI PENGADAAN (PENGADAAN.PHP)
DELIMITER $$
CREATE PROCEDURE sp_hitung_dan_finalisasi_pengadaan (
    IN p_idpengadaan INT
)
BEGIN
    DECLARE v_subtotal_nilai INT;
    DECLARE v_ppn INT;
    DECLARE v_total_nilai INT;

    -- 1. Hitung Subtotal Nilai dari detail_pengadaan
    SELECT COALESCE(SUM(sub_total), 0)
    INTO v_subtotal_nilai
    FROM detail_pengadaan
    WHERE idpengadaan = p_idpengadaan;

    -- 2. Hitung PPN (10%) dan Total Akhir
    SET v_ppn = FLOOR(v_subtotal_nilai * 0.10);
    SET v_total_nilai = v_subtotal_nilai + v_ppn;

    -- 3. Update Header Pengadaan
    UPDATE pengadaan
    SET subtotal_nilai = v_subtotal_nilai,
        ppn = v_ppn,
        total_nilai = v_total_nilai
    WHERE idpengadaan = p_idpengadaan;

END$$
DELIMITER ;



-- 2. SP UPDATE STATUS PENGADAAN SETELAH PENERIMAAN (PENERIMAAN.PHP)
DELIMITER $$
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
DELIMITER;



-- 3. FINALISASI STATUS PENERIMAAN
drop PROCEDURE sp_finalisasi_status_penerimaan;
-- pake sp update status pengadaan aja

DELIMITER $$
CREATE PROCEDURE sp_finalisasi_status_penerimaan (
    IN p_idpenerimaan INT
)
BEGIN
--     Deklarasi variabel harus di awal
    DECLARE v_idpengadaan INT;
    DECLARE v_count_pengadaan INT;
    DECLARE v_count_diterima INT;

--     Dapatkan ID Pengadaan yang terkait
    SELECT idpengadaan INTO v_idpengadaan 
    FROM penerimaan 
    WHERE idpenerimaan = p_idpenerimaan;
-- 
--     1. Hitung jumlah item unik yang DI-ORDER
    SELECT COUNT(dp.idbarang) INTO v_count_pengadaan 
    FROM detail_pengadaan dp 
    WHERE dp.idpengadaan = v_idpengadaan;

--     2. Hitung jumlah item yang SUDAH DITERIMA
    SELECT COUNT(dt.barang_idbarang) INTO v_count_diterima
    FROM detail_penerimaan dt 
    WHERE dt.idpenerimaan = p_idpenerimaan;
    
--     3. Tentukan Status Akhir (F, C, atau P)
    IF v_count_diterima = v_count_pengadaan THEN
        UPDATE penerimaan SET status = 'F' WHERE idpenerimaan = p_idpenerimaan;
        UPDATE pengadaan SET status = 'F' WHERE idpengadaan = v_idpengadaan;
        
    ELSEIF v_count_diterima > 0 THEN
        UPDATE penerimaan SET status = 'C' WHERE idpenerimaan = p_idpenerimaan;
        
    ELSE 
        UPDATE penerimaan SET status = 'P' WHERE idpenerimaan = p_idpenerimaan;
    END IF;
END $$
DELIMITER;


-- 
-- 
-- 4. SP UPDATE HARGA JUAL BARANG (trg_stok_masuk_penerimaan)
DELIMITER $$ 
CREATE PROCEDURE sp_update_harga_jual_barang (
    IN p_idbarang INT,
    IN p_harga_pokok_baru INT
)
BEGIN
    
--     1. Update Harga Pokok di Tabel Barang
    UPDATE barang
    SET harga = p_harga_pokok_baru
    WHERE idbarang = p_idbarang;

--     2. Update Status Harga (contoh: status barang menjadi perlu review margin)
--     Ini adalah contoh logika non-transaksional pada master data
    Update barang SET status = 2 
    WHERE idbarang = p_idbarang;
     
END $$
DELIMITER;



-- 
-- 5. SP PROSES PENJUALAN (PENJUAALAN.PHP)
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
-- 
-- 
-- 
-- 6. SP BATAL PENJUALAN
drop procedure sp_proses_pembatalan_penjualan;
-- drop karena dalam transaksi tidak pakai batal

DELIMITER $$
DROP PROCEDURE IF EXISTS sp_proses_pembatalan_penjualan $$
CREATE PROCEDURE sp_proses_pembatalan_penjualan(
    IN p_idpenjualan INT,
    IN p_idbarang INT,
    IN p_jumlah_masuk INT
)
BEGIN
    DECLARE stok_sebelumnya INT;
    DECLARE stok_sekarang INT;
    
    -- 1. Ambil stok terakhir
    SET stok_sebelumnya = COALESCE(stok_terakhir(p_idbarang), 0);
    
    -- 2. Hitung stok baru (Tambah kembali kuantitas)
    SET stok_sekarang = stok_sebelumnya + p_jumlah_masuk; -- Penambahan Stok

    -- 3. Masukkan ke kartu stok
    -- 'B' signifies stock 'Batal Jual' (Sale Reversal).
    INSERT INTO kartu_stok (idbarang, jenis_transaksi, idtransaksi, keluar, masuk, stok, created_at)
    VALUES (p_idbarang, 'B', p_idpenjualan, 0, p_jumlah_masuk, stok_sekarang, NOW());
END $$
DELIMITER ;




-- 7. SP UPDATE KARTU STOK

DROP PROCEDURE IF EXISTS sp_update_kartu_stok;
-- Diganti sp_proses_penjualan_transaksi


DELIMITER $$
CREATE PROCEDURE sp_update_kartu_stok (
    IN p_idpenjualan INT,
    IN p_idbarang INT,
    IN p_jumlah INT
)
BEGIN
    DECLARE v_stok_terakhir INT;
-- 
--     1. Dapatkan stok terakhir, pastikan 0 jika NULL
    SET v_stok_terakhir = COALESCE(stok_terakhir(p_idbarang), 0);
-- 
--     2. Masukkan ke kartu stok dengan nama kolom yang benar ('idtransaksi')
    INSERT INTO kartu_stok (idbarang, jenis_transaksi, masuk, keluar, stok, created_at, idtransaksi)
    VALUES (
        p_idbarang,
        'K', -- K = Keluar (Penjualan)
        0,
        p_jumlah,
        v_stok_terakhir - p_jumlah,
        NOW(),
        p_idpenjualan -- Menggunakan ID Penjualan sebagai ID Transaksi
    );
END $$
DELIMITER;


-- 
-- ========================================================================================================================================================================
-- TRIGGER
-- ========================================================================================================================================================================
-- 
-- 1. TRIGGER STOK MASUK DARI PENERIMAAN 
DELIMITER $$

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



-- 2. Trigger Hitung Sub Total
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



-- 3. Trigger Detail Pengadaan
DELIMITER $$

CREATE TRIGGER before_insert_detail_pengadaan
BEFORE INSERT ON detail_pengadaan
FOR EACH ROW
BEGIN
    SET NEW.sub_total = NEW.jumlah * NEW.harga_satuan;
END$$

CREATE TRIGGER before_update_detail_pengadaan
BEFORE UPDATE ON detail_pengadaan
FOR EACH ROW
BEGIN
    SET NEW.sub_total = NEW.jumlah * NEW.harga_satuan;
END$$
DELIMITER ;
