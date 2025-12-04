<?php
require_once 'koneksi.php';
require_once 'auth.php';

// Set header untuk output JSON
header('Content-Type: application/json; charset=utf-8');

checkAuth(true); 

$raw_method = $_SERVER['REQUEST_METHOD'];
$simulated_method = $_POST['_method'] ?? null;
$method = $raw_method;

// Normalisasi Metode HTTP untuk simulasi PUT/DELETE via POST
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

$dbconn->close();

function mapJenisBarang($jenis_text) {
    // Memastikan Jenis Barang yang disimpan adalah 'J' atau 'B' sesuai DDL
    $jenis_text = trim(strtolower($jenis_text));
    
    if ($jenis_text === 'j' || strpos($jenis_text, 'finished good') !== false) {
        return 'J';
    }
    if ($jenis_text === 'b' || strpos($jenis_text, 'bahan baku') !== false) {
        return 'B';
    }
    return null;
}


function handleGet($dbconn) {
    $action = $_GET['action'] ?? null;
    $id = $_GET['id'] ?? null;

    if ($id) {
        // --- READ: Ambil Detail Barang (Single Item) ---
        $id_int = intval($id);
        
        // Menggunakan subkueri untuk mendapatkan stok terakhir (tanpa function)
        $sql = "SELECT 
                    b.idbarang, b.jenis, b.nama, b.idsatuan, b.status, b.harga,
                    (SELECT k.stok FROM kartu_stok k 
                     WHERE k.idbarang = b.idbarang 
                     ORDER BY k.created_at DESC, k.idkartu_stok DESC LIMIT 1) AS stok_terakhir_val
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
                'jenis_barang' => $result['jenis'], // Mengembalikan kode J/B untuk form edit
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
        $sql = "SELECT 
                    COUNT(idbarang) AS total_barang,
                    SUM(harga) AS total_nilai
                FROM barang WHERE status = 1";
        $result = $dbconn->query($sql)->fetch_assoc();
        
        // Menggunakan function stok_terakhir() untuk menghitung total stok
        $stok_result = $dbconn->query("SELECT COALESCE(SUM(stok_terakhir(idbarang)), 0) AS total_stok FROM barang WHERE status = 1");
        $total_stok = $stok_result->fetch_assoc()['total_stok'] ?? 0;
        
        $data = [
            'total_barang' => $result['total_barang'] ?? 0,
            'total_nilai' => $result['total_nilai'] ?? 0,
            'total_stok' => $total_stok
        ];

        echo json_encode(['success' => true, 'data' => $data]);

    } elseif ($action === 'get_satuan') {
        // --- Ambil Daftar Satuan dari V_SATUAN_AKTIF ---
        $result = $dbconn->query("SELECT idsatuan, SATUAN AS nama_satuan FROM V_SATUAN_AKTIF ORDER BY SATUAN ASC");
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);

    } else {
        // --- READ: Ambil Semua Barang untuk Tabel Utama (Filter) ---
        $filter = $_GET['filter'] ?? 'aktif'; // Filter: 'aktif' atau 'semua'
        
        // Menentukan VIEW yang akan digunakan
        $view_name = ($filter === 'semua') ? 'V_BARANG_SEMUA' : 'V_BARANG_AKTIF';
        
        // Query menggunakan VIEW, JOIN ke tabel barang untuk status/jenis kode, dan function stok_terakhir()
        $sql = "SELECT 
                    vbs.`KODE BARANG` AS idbarang, 
                    vbs.`NAMA BARANG` AS nama_barang, 
                    vbs.`HARGA POKOK` AS harga_pokok, 
                    vbs.SATUAN AS nama_satuan, 
                    vbs.`JENIS BARANG` AS jenis_barang_desc,
                    b.status AS status_kode,
                    COALESCE(stok_terakhir(b.idbarang), 0) AS stok_terakhir_val
                FROM $view_name vbs
                JOIN barang b ON vbs.`KODE BARANG` = b.idbarang
                ORDER BY vbs.`KODE BARANG` ASC"; // Order by di backend

        $result = $dbconn->query($sql);
        
        if ($result === false) {
             http_response_code(500);
             echo json_encode(['success' => false, 'message' => 'Query gagal: ' . $dbconn->error . '. Pastikan V_BARANG_SEMUA/V_BARANG_AKTIF ada.']);
             return;
        }

        $data = []; 

        while ($row = $result->fetch_assoc()) {
            $stok = $row['stok_terakhir_val'] ?? 0;
            
            $data[] = [
                'idbarang' => $row['idbarang'],
                'kode_barang' => $row['idbarang'],
                'nama_barang' => $row['nama_barang'],
                'nama_satuan' => $row['nama_satuan'] ?? '-',
                'jenis_barang' => $row['jenis_barang_desc'],
                'harga_pokok' => $row['harga_pokok'],
                'stok' => $stok, 
                'status' => ($row['status_kode'] == 1) ? 'aktif' : 'non-aktif' // FIX: Konsistensi status text
            ];
        }
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

function handlePost($dbconn) {
    // --- CREATE: Tambah Barang Baru ---
    $nama = $_POST['nama_barang'] ?? null;
    $idsatuan = $_POST['idsatuan'] ?? null;
    $jenis_input = $_POST['jenis_barang'] ?? null; 
    $harga = $_POST['harga_pokok'] ?? null;
    $status = (($_POST['status'] ?? '') === 'aktif') ? 1 : 0;
    $stok_awal = $_POST['stok'] ?? 0; 

    $jenis = mapJenisBarang($jenis_input); 

    $idsatuan_int = intval($idsatuan);
    $harga_int = intval($harga);
    $status_int = intval($status);
    $stok_awal_int = intval($stok_awal);
    
    if (empty($nama) || $idsatuan_int === 0 || empty($jenis) || $harga_int < 0) {
        http_response_code(400);
        $message = 'Data input tidak lengkap atau tidak valid. Pastikan Jenis Barang dipilih dengan benar.';
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
        // Catatan: jenis di DDL adalah CHAR(1) ('J' atau 'B'). Perlu memastikan mapJenisBarang mengembalikan 'J' atau 'B'.
        $stmt->bind_param("sisii", $nama, $idsatuan_int, $jenis, $harga_int, $status_int);
        $stmt->execute();
        
        $new_id = $stmt->insert_id;
        $stmt->close();

        // 2. Tambahkan Stok Awal ke Kartu Stok (Jika Stok > 0)
        if ($stok_awal_int > 0) {
            $stmt_stok = $dbconn->prepare(
                "INSERT INTO kartu_stok (idbarang, jenis_transaksi, masuk, keluar, stok, idtransaksi) 
                 VALUES (?, 'I', ?, 0, ?, ?)" // I = Initial Stock
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
    // --- UPDATE: Edit Data Barang ---
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
    // --- DELETE: Nonaktifkan Barang (Soft Delete) ---
    $idbarang = $_POST['idbarang'] ?? null;
    
    if (empty($idbarang)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Barang tidak boleh kosong.']);
        return;
    }
    
    $idbarang_int = intval($idbarang);

    try {
        $stmt = $dbconn->prepare("UPDATE barang SET status = 0 WHERE idbarang = ?");
        if (!$stmt) {
            throw new Exception('Gagal mempersiapkan statement: ' . $dbconn->error);
        }
        $stmt->bind_param("i", $idbarang_int);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Barang berhasil dinonaktifkan (soft delete).']);

        } else {
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