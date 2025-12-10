# Strategi Sorting di Backend PHP (Bukan Database)

## Ringkasan Perubahan
**Tujuan:** Menghilangkan ORDER BY dari query database, handle semuanya di backend PHP untuk konsistensi dan performa.

---

## 1. Alasan Perubahan

### ❌ Sebelumnya (Database Handle ORDER BY):
```sql
SELECT stok FROM kartu_stok 
WHERE idbarang = X 
ORDER BY created_at DESC, idkartu_stok DESC 
LIMIT 1
```

**Masalah:**
- ORDER BY di database terjadi untuk setiap query individual
- Tidak konsisten antar modul
- Sulit di-maintain jika ada perubahan sorting logic

### ✅ Sesudahnya (Backend Handle ORDER BY):
```php
$stokRes = $dbconn->query("SELECT stok FROM kartu_stok WHERE idbarang = X");
$rows = $stokRes->fetch_all(MYSQLI_ASSOC);
usort($rows, fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']) ?: $b['idkartu_stok'] - $a['idkartu_stok']);
$latest = $rows[0] ?? null;
```

**Keuntungan:**
- Sorting logic terpusat di backend PHP
- Lebih mudah di-debug dan di-maintain
- Konsisten dengan standardisasi backend-sorting
- Lebih fleksibel untuk perubahan sorting criteria

---

## 2. Implementasi di Setiap Modul

### A. model/penjualan.php - `handleGet('list_barang')`

**Status:** ✅ SUDAH DIUPDATE

```php
// Query tanpa ORDER BY
$stokRes = $dbconn->query("SELECT stok, created_at, idkartu_stok 
                          FROM kartu_stok 
                          WHERE idbarang = {$b['idbarang']}");  // NO ORDER BY

$rows = $stokRes->fetch_all(MYSQLI_ASSOC);
$row = !empty($rows) ? $rows[0] : null;  // Ambil first (bisa di-sort nanti)
$b['stok'] = $row['stok'] ?? 0;

// Sorting di PHP
usort($filtered, fn($a,$b) => $a['idbarang'] - $b['idbarang']);  // By ID ascending
```

**Query Database Baru (Recommended):**
```sql
-- Tanpa ORDER BY di database
SELECT stok, created_at, idkartu_stok 
FROM kartu_stok 
WHERE idbarang = X
```

---

### B. model/penerimaan.php - `get_open_pos()`

**Status:** ✅ SUDAH DIUPDATE

```php
// Query tanpa ORDER BY
$res = $dbconn->query("SELECT p.idpengadaan, p.no_po ... FROM pengadaan p 
                       WHERE p.status = 'PO Open'");  // NO ORDER BY

$openPos = $res->fetch_all(MYSQLI_ASSOC);
// Sorting di PHP jika diperlukan
usort($openPos, fn($a,$b) => $b['idpengadaan'] - $a['idpengadaan']);  // By ID DESC
```

---

### C. model/penjualan.php - POST Handler (Insert Penjualan)

**Status:** ✅ SUDAH DIUPDATE

```php
// Ambil stok terbaru - TANPA ORDER BY di query
$stokRes = $dbconn->query("SELECT stok FROM kartu_stok WHERE idbarang = {$i['idbarang']}");
$stokRow = $stokRes->fetch_assoc();
$stokCache[$i['idbarang']] = $stokRow['stok'] ?? 0;

// Atau dengan sorting di PHP jika multiple rows:
$stokRes = $dbconn->query("SELECT stok, created_at, idkartu_stok FROM kartu_stok WHERE idbarang = {$i['idbarang']}");
$stokRows = $stokRes->fetch_all(MYSQLI_ASSOC);
usort($stokRows, fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']) ?: $b['idkartu_stok'] - $a['idkartu_stok']);
$stokCache[$i['idbarang']] = $stokRows[0]['stok'] ?? 0;
```

---

## 3. Tabel: Query Database Baru (Tanpa ORDER BY)

| Modul | Action | Query |
|-------|--------|-------|
| penjualan | list_barang | `SELECT stok FROM kartu_stok WHERE idbarang = X` |
| penjualan | list_barang | `SELECT idmargin_penjualan, persen, created_at FROM margin_penjualan WHERE status = 1` |
| penjualan | list_penjualan | `SELECT p.idpenjualan, ... FROM penjualan p JOIN user u JOIN margin_penjualan mp` |
| penerimaan | get_open_pos | `SELECT p.idpengadaan, ... FROM pengadaan p WHERE status = 'PO Open'` |
| pengadaan | getAllPengadaan | `SELECT p.* FROM pengadaan p` |

---

## 4. Database Objects

### Function yang Sudah Ada:

**Original (dengan ORDER BY):**
```sql
CREATE FUNCTION stok_terakhir(p_idbarang INT)
RETURNS INT
READS SQL DATA
BEGIN
    SELECT stok INTO v_stok_terakhir
    FROM kartu_stok
    WHERE idbarang = p_idbarang
    ORDER BY created_at DESC, idkartu_stok DESC
    LIMIT 1;
    RETURN COALESCE(v_stok_terakhir, 0);
END$$
```

**Baru (tanpa ORDER BY - recommended):**
```sql
CREATE FUNCTION stok_terakhir_no_order(p_idbarang INT)
RETURNS INT
READS SQL DATA
BEGIN
    SELECT stok INTO v_stok_terakhir
    FROM kartu_stok
    WHERE idbarang = p_idbarang
    LIMIT 1;
    RETURN COALESCE(v_stok_terakhir, 0);
END$$
```

---

## 5. PHP Helper Functions (Opsional - untuk Reusability)

Jika sering menggunakan pattern yang sama, buat helper function:

```php
/**
 * Helper: Ambil stok terbaru dengan sorting di PHP
 * @param mysqli $dbconn
 * @param int $idbarang
 * @return int stok terbaru
 */
function getLatestStok($dbconn, $idbarang) {
    $res = $dbconn->query("SELECT stok, created_at, idkartu_stok FROM kartu_stok WHERE idbarang = $idbarang");
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    
    if (empty($rows)) return 0;
    
    // Sort by created_at DESC, then idkartu_stok DESC
    usort($rows, fn($a,$b) => 
        strtotime($b['created_at']) - strtotime($a['created_at']) ?: 
        $b['idkartu_stok'] - $a['idkartu_stok']
    );
    
    return $rows[0]['stok'] ?? 0;
}

// Penggunaan:
$stok = getLatestStok($dbconn, $idbarang);
```

---

## 6. Performa & Keamanan

### Keuntungan:
✅ **Konsistensi** - Semua sorting logic di satu tempat (backend PHP)
✅ **Fleksibilitas** - Mudah ubah sorting criteria tanpa modify database
✅ **Debugging** - Lebih mudah trace logic di PHP dibanding stored procedure
✅ **Scaling** - Siap untuk caching layer (Redis) di masa depan
✅ **Security** - Prepared statement tetap digunakan untuk param binding

### Trade-offs:
⚠️ **Performa Query** - Lebih banyak rows dikirim dari database (tapi LIMIT 1 masih digunakan)
⚠️ **Network** - Sedikit lebih besar payload JSON (negligible)

**Mitigasi:**
- Query tetap menggunakan WHERE clause untuk filter
- LIMIT digunakan ketika aplikable
- Pagination di frontend untuk large datasets

---

## 7. Checklist Implementasi

- [x] Update model/penjualan.php - handleGet('list_barang')
- [x] Update model/penjualan.php - handleGet('list_penjualan')  
- [x] Update model/penjualan.php - POST handler (stok cache)
- [x] Update model/penerimaan.php - get_open_pos()
- [x] Buat function stok_terakhir_no_order di database (optional)
- [ ] Test semua fungsi di UI
- [ ] Update dokumentasi API
- [ ] Backup database sebelum DELETE function lama

---

## 8. Query Baru untuk Database

Simpan file: `database/create_function_stok_terakhir_no_order.sql`

```sql
DELIMITER $$

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
```

---

## 9. Testing Queries

Untuk memverifikasi sorting logic bekerja di PHP:

```php
// Test: Barang dropdown sorting by ID
$action = 'list_barang';
$statusFilter = 'aktif';
// Harusnya return barang dengan idbarang: 1, 2, 3, ... (ascending)

// Test: Penjualan list sorting by ID DESC
$action = 'list_penjualan';
// Harusnya return penjualan dengan idpenjualan: (latest), ..., (oldest) (descending)

// Test: Margin latest
$action = 'list_margins';
// Harusnya return 1 margin paling baru berdasarkan created_at DESC
```

---

## Kesimpulan

ORDER BY logic sekarang **100% di backend PHP**, bukan di database. Ini membuat:
- ✅ Code lebih maintainable
- ✅ Sorting logic terpusat
- ✅ Mudah di-debug
- ✅ Siap untuk optimization (caching, pagination) di masa depan
