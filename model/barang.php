<?php
require_once 'koneksi.php';
require_once 'auth.php';

// Set header untuk output JSON
header('Content-Type: application/json; charset=utf-8');

checkAuth(true); 

$raw_method = $_SERVER['REQUEST_METHOD'];
$simulated_method = $_POST['_method'] ?? null;
$method = $raw_method;

// Normalisasi Metode HTTP untuk simulasi PUT/DELETE
if ($raw_method === 'POST' && $simulated_method) {
    $method = strtoupper($simulated_method);
}

switch ($method) {
    case 'GET':
        handleGet($dbconn);
        break;
    case 'POST':
        handlePost($dbconn);
        break;
    case 'PUT':
        handlePut($dbconn);
        break;
    case 'DELETE':
        handleDelete($dbconn);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode ' . $method . ' tidak didukung']);
        break;
}

// Pastikan koneksi ditutup setelah semua operasi selesai
if (isset($dbconn) && $dbconn instanceof mysqli) {
    $dbconn->close();
}

// Map input jenis barang dari form ke kode DDL (J/B)
function mapJenisBarang($jenis_input) {
    $jenis_input = trim(strtoupper($jenis_input));
    
    // Mapping kode yang digunakan di view/form (J/B) ke kode DDL
    if ($jenis_input === 'J' || $jenis_input === 'BARANG JADI') {
        return 'J'; // Barang Jadi (Finished Good)
    }
    if ($jenis_input === 'B' || $jenis_input === 'BAHAN BAKU') {
        return 'B'; // Bahan Baku (Raw Material)
    }
    // Jika frontend mengirim kode satu huruf (J/B), ini juga berfungsi
    return null; 
}


function handleGet($dbconn) {
    $action = $_GET['action'] ?? null;
    $id = $_GET['id'] ?? null;

    if ($id) {
        // --- Ambil Detail Barang (Single Item) ---
        $id_int = intval($id);
        
        $sql = "SELECT 
                    b.idbarang, b.jenis, b.nama, b.idsatuan, b.status, b.harga,
                    COALESCE((SELECT k.stok FROM kartu_stok k 
                              WHERE k.idbarang = b.idbarang 
                              ORDER BY k.created_at DESC, k.idkartu_stok DESC LIMIT 1), 0) AS stok_terakhir_val
                FROM barang b WHERE b.idbarang = ?";

        $stmt = $dbconn->prepare($sql);
        $stmt->bind_param("i", $id_int);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            $data = [
                'idbarang' => $result['idbarang'],
                'kode_barang' => $result['idbarang'],
                'nama_barang' => $result['nama'],
                'idsatuan' => $result['idsatuan'],
                'jenis_barang' => $result['jenis'], // Mengirim kode J/B ke form
                'harga_pokok' => $result['harga'],
                'stok' => $result['stok_terakhir_val'] ?? 0, 
                'status' => ($result['status'] == 1) ? 'aktif' : 'tidak_aktif'
            ];
            echo json_encode(['success' => true, 'data' => $data]);

        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan.']);
        }

    } elseif ($action === 'get_stats') {
        // --- Ambil Statistik untuk Dashboard ---
        // Menggunakan FUNCTION stok_terakhir() yang sudah ada di DDL
        $sql = "SELECT 
                    COUNT(idbarang) AS total_barang,
                    SUM(harga) AS total_nilai,
                    COALESCE(SUM(stok_terakhir(idbarang)), 0) AS total_stok
                FROM barang WHERE status = 1";
                
        $result = $dbconn->query($sql)->fetch_assoc();
        
        $data = [
            'total_barang' => $result['total_barang'] ?? 0,
            'total_nilai' => $result['total_nilai'] ?? 0,
            'total_stok' => $result['total_stok'] ?? 0
        ];

        echo json_encode(['success' => true, 'data' => $data]);

    } elseif ($action === 'get_satuan') {
        // --- Ambil Daftar Satuan dari V_SATUAN_AKTIF ---
        $result = $dbconn->query("SELECT idsatuan, SATUAN AS nama_satuan FROM V_SATUAN_AKTIF ORDER BY SATUAN");
        if (!$result) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil satuan: ' . $dbconn->error]);
            return;
        }
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);

    } else {
        // --- Ambil Semua Barang untuk Tabel Utama (Menggunakan View) ---
        $filter = $_GET['filter'] ?? 'aktif'; // Filter default: 'aktif'
        
        // Kita gunakan V_BARANG_SEMUA karena V_BARANG_AKTIF tidak mencakup semua kolom yang kita butuhkan.
        // Filtering dilakukan dengan WHERE clause
        $sql = "SELECT 
                    vbs.`KODE BARANG` AS idbarang, 
                    vbs.`NAMA BARANG` AS nama_barang, 
                    vbs.`HARGA POKOK` AS harga_pokok, 
                    vbs.SATUAN AS nama_satuan, 
                    vbs.`JENIS BARANG` AS jenis_barang,
                    vbs.`STATUS BARANG` AS status_barang,
                    b.status AS status_kode,
                    COALESCE(stok_terakhir(vbs.`KODE BARANG`), 0) AS stok
                FROM V_BARANG_SEMUA vbs
                JOIN barang b ON vbs.`KODE BARANG` = b.idbarang";

        $params = [];
        $types = '';

        if ($filter === 'aktif') {
            $sql .= " WHERE b.status = 1";
        }
        // Jika filter = 'semua', tidak ada WHERE clause
        
        $sql .= " ORDER BY vbs.`KODE BARANG` ASC"; 

        $result = $dbconn->query($sql);

        if (!$result) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menjalankan query: ' . $dbconn->error]);
            return;
        }

        $data = []; 
        while ($row = $result->fetch_assoc()) {
            
            $data[] = [
                'idbarang' => $row['idbarang'],
                'kode_barang' => $row['idbarang'],
                'nama_barang' => $row['nama_barang'],
                'nama_satuan' => $row['nama_satuan'] ?? '-',
                'jenis_barang' => $row['jenis_barang'],
                'harga_pokok' => $row['harga_pokok'],
                'stok' => $row['stok'], 
                'status' => ($row['status_kode'] == 1) ? 'aktif' : 'tidak_aktif'
            ];
        }
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

function handlePost($dbconn) {
    $nama = $_POST['nama_barang'] ?? null;
    $idsatuan = $_POST['idsatuan'] ?? null;
    $jenis_input = $_POST['jenis_barang'] ?? null; 
    $harga = $_POST['harga_pokok'] ?? null;
    $status = (($_POST['status'] ?? '') === 'aktif') ? 1 : 0;
    $stok_awal = $_POST['stok'] ?? 0; 

    // Mapping Jenis: J atau B
    $jenis = mapJenisBarang($jenis_input); 

    $idsatuan_int = intval($idsatuan);
    $harga_int = intval($harga);
    $status_int = intval($status);
    $stok_awal_int = intval($stok_awal);
    
    if (empty($nama) || $idsatuan_int === 0 || empty($jenis) || $harga_int < 0) {
        http_response_code(400);
        $message = 'Data input tidak lengkap atau tidak valid. Pastikan Nama, Satuan, Jenis, dan Harga Pokok diisi.';
        echo json_encode(['success' => false, 'message' => $message]);
        return;
    }

    try {
        $dbconn->begin_transaction(); 

        // 1. INSERT ke Tabel Barang
        $stmt = $dbconn->prepare("INSERT INTO barang (nama, idsatuan, jenis, harga, status) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception('Gagal mempersiapkan statement: ' . $dbconn->error);
        }
        $stmt->bind_param("sisii", $nama, $idsatuan_int, $jenis, $harga_int, $status_int);
        $stmt->execute();
        
        $new_id = $stmt->insert_id;
        $stmt->close();

        // 2. Tambahkan Stok Awal ke Kartu Stok (Jika Stok > 0)
        if ($stok_awal_int > 0) {
            $stmt_stok = $dbconn->prepare(
                // idtransaksi diisi dengan idbarang itu sendiri untuk Initial Stock (jenis_transaksi='I')
                "INSERT INTO kartu_stok (idbarang, jenis_transaksi, masuk, keluar, stok, idtransaksi) 
                 VALUES (?, 'I', ?, 0, ?, ?)" 
            );
            $stmt_stok->bind_param("iiii", $new_id, $stok_awal_int, $stok_awal_int, $new_id);
            $stmt_stok->execute();
            $stmt_stok->close();
        }

        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => 'Barang berhasil ditambahkan.', 'id' => $new_id]);
    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan barang: ' . $e->getMessage()]);
    }
}

function handlePut($dbconn) {
    $idbarang = $_POST['idbarang'] ?? null;
    $nama = $_POST['nama_barang'] ?? null;
    $idsatuan = $_POST['idsatuan'] ?? null;
    $jenis_input = $_POST['jenis_barang'] ?? null;
    $harga = $_POST['harga_pokok'] ?? null;
    $status = (($_POST['status'] ?? '') === 'aktif') ? 1 : 0;
    
    $jenis = mapJenisBarang($jenis_input); 
    
    $idbarang_int = intval($idbarang);
    $idsatuan_int = intval($idsatuan);
    $harga_int = intval($harga);
    $status_int = intval($status);
    
    if (empty($idbarang) || empty($nama) || $idsatuan_int === 0 || empty($jenis) || $harga_int < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data input tidak lengkap atau tidak valid.']);
        return;
    }

    try {
        $stmt = $dbconn->prepare("UPDATE barang SET nama = ?, idsatuan = ?, jenis = ?, harga = ?, status = ? WHERE idbarang = ?");
        if (!$stmt) {
            throw new Exception('Gagal mempersiapkan statement: ' . $dbconn->error);
        }
        $stmt->bind_param("sisiii", $nama, $idsatuan_int, $jenis, $harga_int, $status_int, $idbarang_int); 
        $stmt->execute();

        if ($stmt->error) {
             throw new Exception('Gagal menjalankan update: ' . $stmt->error);
        }

        echo json_encode(['success' => true, 'message' => 'Barang berhasil diperbarui.']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui barang: ' . $e->getMessage()]);
    }
}

function handleDelete($dbconn) {
    $idbarang = $_POST['idbarang'] ?? null;
    
    if (empty($idbarang)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Barang tidak boleh kosong.']);
        return;
    }
    
    $idbarang_int = intval($idbarang);

    try {
        // Soft Delete: Ubah status menjadi 0
        $stmt = $dbconn->prepare("UPDATE barang SET status = 0 WHERE idbarang = ?");
        if (!$stmt) {
            throw new Exception('Gagal mempersiapkan statement: ' . $dbconn->error);
        }
        $stmt->bind_param("i", $idbarang_int);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Barang berhasil dinonaktifkan (soft delete).']);

        } else {
            // Cek apakah barangnya memang tidak ada atau sudah nonaktif
            $check_stmt = $dbconn->prepare("SELECT 1 FROM barang WHERE idbarang = ?");
            $check_stmt->bind_param("i", $idbarang_int);
            $check_stmt->execute();

            if ($check_stmt->get_result()->num_rows === 0) {
                 http_response_code(404);
                 echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan.']);
                 
            } else {
                 echo json_encode(['success' => true, 'message' => 'Barang sudah tidak aktif.']);
            }
            $check_stmt->close();
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menonaktifkan barang: ' . $e->getMessage()]);
    }
}
?>