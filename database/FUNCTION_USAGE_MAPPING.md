# Mapping Penggunaan Function dan Stored Procedure

## RINGKASAN LOKASI SEMUA ITEMS

### Database Structure Files (Definisi)
- **`database/update_sp_proses_penjualan_transaksi.sql`** - Definisi sp_proses_penjualan_transaksi & sp_proses_pembatalan_penjualan (DIUPDATE)
- **Database MySQL** - Lokasi asli semua function, procedure, dan trigger

### Backend PHP Files (Penggunaan)
- **`model/pengadaan.php`** - Menggunakan sp_hitung_dan_finalisasi_pengadaan (line 195)
- **`model/penerimaan.php`** - Menggunakan:
  - sp_update_status_pengadaan_setelah_terima (line 404, 525)
  - Trigger trg_stok_masuk_penerimaan (otomatis saat INSERT detail_penerimaan)
- **`model/penjualan.php`** - Menggunakan:
  - sp_proses_penjualan_transaksi (line 215)
  - sp_proses_pembatalan_penjualan (line 274)
- **`model/barang.php`** - Menggunakan:
  - Function stok_terakhir (line 116, 169, 225)
- **`view/manage_kartu_stok.php`** - Menampilkan stok_terakhir (line 143)

---

# Mapping Penggunaan Function dan Stored Procedure

### 1. `hitung_harga_jual_dengan_margin(p_idbarang INT)`
**Deskripsi:** Menghitung harga jual barang berdasarkan harga pokok dan margin penjualan yang aktif
**Formula:** Harga Jual = Harga Pokok + (Harga Pokok × Margin% / 100)

**Status Penggunaan:** ❌ **TIDAK DIGUNAKAN**
- Di backend PHP, harga jual dihitung langsung di aplikasi, bukan via function
- Di `manage_penjualan.php` (line 332): 
  ```javascript
  const harga_jual = harga_dasar * (1 + (marginPersen / 100));
  ```

**Rekomendasi:** Bisa dihapus dari database karena logika sudah di frontend

---

### 2. `stok_terakhir(p_idbarang INT)`
**Deskripsi:** Mendapatkan stok terbaru barang dari kartu_stok berdasarkan created_at dan idkartu_stok DESC

**Status Penggunaan:** ✅ **DIGUNAKAN DI:**
1. **`model/barang.php` (line 116)** - Query get_stats
   ```sql
   (SELECT COALESCE(SUM(stok_terakhir(idbarang)), 0) FROM barang WHERE status = 1) AS total_stok
   ```

2. **`model/barang.php` (line 169)** - handleGet (main table)
   ```sql
   COALESCE(stok_terakhir(b.idbarang), 0) AS stok_terakhir_val
   ```

3. **`model/barang.php` (line 225)** - handleGetActiveStock (kartu_stok)
   ```sql
   stok_terakhir(b.`KODE BARANG`) AS stok_terakhir
   ```

4. **`view/manage_kartu_stok.php` (line 143)** - Display stok di tabel
   ```javascript
   <td>${item.stok_terakhir}</td>
   ```

**Rekomendasi:** Tetap pertahankan, sudah optimal

---

## STORED PROCEDURES (MySQL)

### 1. `sp_hitung_dan_finalisasi_pengadaan(p_idpengadaan INT)`
**Deskripsi:** Menghitung total nilai pengadaan (subtotal + PPN 10%) dan memperbarui header pengadaan

**Status Penggunaan:** ✅ **DIGUNAKAN DI:**
1. **`model/pengadaan.php`** (dalam function `createPengadaan`)
   ```php
   $stmt_sp = $dbconn->prepare("CALL sp_hitung_dan_finalisasi_pengadaan(?)"); 
   ```

**Rekomendasi:** Tetap pertahankan

---

### 2. `sp_update_status_pengadaan_setelah_terima(p_idpengadaan INT)`
**Deskripsi:** Update status pengadaan (p/s/f) berdasarkan jumlah barang diterima vs dipesan

**Status Penggunaan:** ✅ **DIGUNAKAN DI:**
1. **`model/penerimaan.php` (line 404)** - handleCreate (create penerimaan)
   ```php
   $stmt_sp = $dbconn->prepare("CALL sp_update_status_pengadaan_setelah_terima(?)");
   $stmt_sp->bind_param("i", $idpengadaan);
   $stmt_sp->execute();
   ```

2. **`model/penerimaan.php` (line 525)** - handleUpdate (update penerimaan)
   ```php
   $stmt_sp = $dbconn->prepare("CALL sp_update_status_pengadaan_setelah_terima(?)");
   $stmt_sp->bind_param("i", $idpengadaan_for_status);
   $stmt_sp->execute();
   ```

**Rekomendasi:** Tetap pertahankan, sudah digunakan aktif

---

### 3. `sp_finalisasi_status_penerimaan(p_idpenerimaan INT)`
**Deskripsi:** Update status penerimaan dan pengadaan setelah penerimaan selesai

**Status Penggunaan:** ❌ **TIDAK DIGUNAKAN**
- Tidak ditemukan dipanggil di mana pun di backend PHP
- Fungsinya sudah di-cover oleh `sp_update_status_pengadaan_setelah_terima`

**Lokasi DB:** Database (belum ada di file .sql, perlu dicek di database langsung)

**Rekomendasi:** Hapus dari database, redundan dengan sp_update_status_pengadaan_setelah_terima

---

### 4. `sp_update_harga_jual_barang(p_idbarang INT, p_harga_pokok_baru INT)`
**Deskripsi:** Update harga pokok barang dan set status barang menjadi 2 (perlu review margin)

**Status Penggunaan:** ⚠️ **DIPANGGIL DARI TRIGGER**
- Di `trigger trg_stok_masuk_penerimaan` (AFTER INSERT detail_penerimaan)
  ```sql
  IF NEW.harga_satuan_terima != v_harga_pokok_saat_ini THEN
      CALL sp_update_harga_jual_barang(NEW.barang_idbarang, NEW.harga_satuan_terima);
  END IF;
  ```

**Lokasi:** Dipanggil otomatis saat ada penerimaan dengan harga berbeda

**Rekomendasi:** Tetap pertahankan, sudah terintegrasi dengan trigger

---

### 5. `sp_proses_penjualan_transaksi(p_idpenjualan, p_idbarang, p_jumlah_keluar, p_stok_sebelumnya)`
**Deskripsi:** Insert ke kartu_stok untuk transaksi penjualan (stok keluar)

**Status Penggunaan:** ✅ **DIGUNAKAN DI:**
1. **`model/penjualan.php` (line 215)** - handlePost (create penjualan)
   ```php
   $stmtStok = $dbconn->prepare("CALL sp_proses_penjualan_transaksi(?, ?, ?, ?)");
   $stmtStok->bind_param("iiii", $idpenjualan, $i['idbarang'], $i['jumlah'], $stokSebelumnya);
   ```

**Rekomendasi:** Tetap pertahankan, sudah diperbarui (4 parameter + stok_sebelumnya dari backend)

---

### 6. `sp_proses_pembatalan_penjualan(p_idpenjualan, p_idbarang, p_jumlah_masuk, p_stok_sebelumnya)`
**Deskripsi:** Insert ke kartu_stok untuk pembatalan penjualan (stok kembali masuk)

**Status Penggunaan:** ✅ **DIGUNAKAN DI:**
1. **`model/penjualan.php` (line 274)** - handleDelete (pembatalan penjualan)
   ```php
   $stmtStok = $dbconn->prepare("CALL sp_proses_pembatalan_penjualan(?, ?, ?, ?)");
   $stmtStok->bind_param("iiii", $id, $d['idbarang'], $d['jumlah'], $stokSebelumnya);
   ```

**Rekomendasi:** Tetap pertahankan, sudah diperbarui (4 parameter + stok_sebelumnya dari backend)

---

### 7. `sp_update_kartu_stok(p_idpenjualan, p_idbarang, p_jumlah)`
**Deskripsi:** Insert ke kartu_stok (legacy, sebelum ada sp_proses_penjualan_transaksi)

**Status Penggunaan:** ❌ **TIDAK DIGUNAKAN** - Digantikan oleh sp_proses_penjualan_transaksi

**Rekomendasi:** Bisa dihapus

---

## TRIGGERS (MySQL)

### 1. `trg_stok_masuk_penerimaan` (AFTER INSERT ON detail_penerimaan)
**Deskripsi:** Otomatis insert kartu_stok saat ada penerimaan barang dan update harga jika berbeda

**Status Penggunaan:** ✅ **AKTIF** - Akan tertrigger setiap kali ada INSERT detail_penerimaan

**Rekomendasi:** Tetap pertahankan

---

### 2. `trg_hitung_subtotal` (BEFORE INSERT ON detail_pengadaan)
**Deskripsi:** Hitung sub_total otomatis saat INSERT detail_pengadaan

**Status Penggunaan:** ✅ **AKTIF** - Akan tertrigger setiap kali ada INSERT detail_pengadaan

**Rekomendasi:** Tetap pertahankan

---

### 3. `before_insert_detail_pengadaan` & `before_update_detail_pengadaan`
**Deskripsi:** Sama dengan trg_hitung_subtotal (duplicate)

**Status Penggunaan:** ⚠️ **DUPLIKASI** - Ada 2 trigger dengan fungsi sama

**Rekomendasi:** Hapus `trg_hitung_subtotal`, gunakan `before_insert_detail_pengadaan` dan `before_update_detail_pengadaan` saja

---

## RINGKASAN AKSI

| Nama | Tipe | Digunakan | Lokasi | Aksi |
|------|------|----------|--------|------|
| hitung_harga_jual_dengan_margin | FUNCTION | ❌ Tidak | Database | Hapus dari DB |
| stok_terakhir | FUNCTION | ✅ Ya | model/barang.php (3x) | Pertahankan |
| sp_hitung_dan_finalisasi_pengadaan | PROC | ✅ Ya | model/pengadaan.php:195 | Pertahankan |
| sp_update_status_pengadaan_setelah_terima | PROC | ✅ Ya | model/penerimaan.php:404,525 | Pertahankan |
| sp_finalisasi_status_penerimaan | PROC | ❌ Tidak | Database (tidak dipakai) | Hapus dari DB |
| sp_update_harga_jual_barang | PROC | ✅ Ya | trigger trg_stok_masuk_penerimaan | Pertahankan |
| sp_proses_penjualan_transaksi | PROC | ✅ Ya | model/penjualan.php:215 | Pertahankan (sudah update) |
| sp_proses_pembatalan_penjualan | PROC | ✅ Ya | model/penjualan.php:274 | Pertahankan (sudah update) |
| sp_update_kartu_stok | PROC | ❌ Tidak | Database (legacy) | Hapus dari DB |
| trg_stok_masuk_penerimaan | TRIGGER | ✅ Ya | Otomatis saat INSERT detail_penerimaan | Pertahankan |
| trg_hitung_subtotal | TRIGGER | ⚠️ Duplikasi | Database | Hapus (pakai before_insert_detail_pengadaan) |
| before_insert_detail_pengadaan | TRIGGER | ✅ Ya | Otomatis saat INSERT detail_pengadaan | Pertahankan |
| before_update_detail_pengadaan | TRIGGER | ✅ Ya | Otomatis saat UPDATE detail_pengadaan | Pertahankan |

---

## CLEANUP: ITEMS YANG PERLU DIHAPUS DARI DATABASE

**File SQL untuk cleanup:** `database/cleanup_unused_database_objects.sql`

### Hapus FUNCTION:
```sql
DROP FUNCTION IF EXISTS hitung_harga_jual_dengan_margin;
```
**Alasan:** Harga jual dihitung di frontend JavaScript, tidak pernah dipanggil dari database

---

### Hapus PROCEDURES:
```sql
DROP PROCEDURE IF EXISTS sp_finalisasi_status_penerimaan;
DROP PROCEDURE IF EXISTS sp_update_kartu_stok;
```

**Alasan:**
- `sp_finalisasi_status_penerimaan` - Tidak digunakan, fungsinya di-cover oleh sp_update_status_pengadaan_setelah_terima
- `sp_update_kartu_stok` - Legacy/old, digantikan oleh sp_proses_penjualan_transaksi

---

### Hapus TRIGGERS (Duplikat):
```sql
DROP TRIGGER IF EXISTS trg_hitung_subtotal;
```
**Alasan:** Duplikasi dengan `before_insert_detail_pengadaan`, keduanya hitung sub_total saat INSERT detail_pengadaan

---

## SUMMARY: FINAL STATE SETELAH CLEANUP

✅ **TETAP DI DATABASE (8 items):**
- FUNCTION: `stok_terakhir`
- PROCEDURE: `sp_hitung_dan_finalisasi_pengadaan`, `sp_update_status_pengadaan_setelah_terima`, `sp_update_harga_jual_barang`, `sp_proses_penjualan_transaksi`, `sp_proses_pembatalan_penjualan`
- TRIGGER: `trg_stok_masuk_penerimaan`, `before_insert_detail_pengadaan`, `before_update_detail_pengadaan`

❌ **DIHAPUS DARI DATABASE (5 items):**
- FUNCTION: `hitung_harga_jual_dengan_margin`
- PROCEDURE: `sp_finalisasi_status_penerimaan`, `sp_update_kartu_stok`
- TRIGGER: `trg_hitung_subtotal`
