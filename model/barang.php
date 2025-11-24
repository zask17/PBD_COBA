<?php
require_once 'koneksi.php';
require_once 'auth.php';

// Set header untuk output JSON
header('Content-Type: application/json; charset=utf-8');

// Pastikan user sudah login
checkAuth(); 

$method = $_SERVER['REQUEST_METHOD'];

// _method dari POST untuk simulasi PUT/DELETE (mengikuti pola HTTP method spoofing)
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

// Variabel koneksi MySQLi global dari koneksi.php
global $dbconn; 

// Pastikan $dbconn adalah objek mysqli yang valid
if (!isset($dbconn) || !$dbconn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Koneksi database (dbconn) tidak tersedia.']);
    exit();
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
        http_response_code(405); // Method Not Allowed
        echo json_encode(['success' => false, 'message' => 'Metode tidak didukung']);
        break;
}

$dbconn->close();

function handleGet($dbconn) {
    $action = $_GET['action'] ?? null;
    $id = $_GET['id'] ?? null;

    try {
        if ($id) {
            // Ambil satu barang untuk form edit
            $stmt = $dbconn->prepare("SELECT idbarang, nama, idsatuan, jenis, harga, status FROM barang WHERE idbarang = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $result_data = $result->fetch_assoc();
            $stmt->close();
            
            // Cek stok terbaru dari kartu_stok 
            $stok = 0;
            if ($result_data) {
                $stmt_stok = $dbconn->prepare("SELECT stok FROM kartu_stok WHERE idbarang = ? ORDER BY created_at DESC, idkartu_stok DESC LIMIT 1");
                $stmt_stok->bind_param("i", $id);
                $stmt_stok->execute();
                $stok_result = $stmt_stok->get_result();
                $stok_data = $stok_result->fetch_assoc();
                $stok = $stok_data['stok'] ?? 0;
                $stmt_stok->close();
            }

            // Mapping nama kolom agar sesuai dengan frontend
            $data = [
                'idbarang' => $result_data['idbarang'] ?? null,
                'kode_barang' => $result_data['idbarang'] ?? null,
                'nama_barang' => $result_data['nama'] ?? null,
                'idsatuan' => $result_data['idsatuan'] ?? null,
                'jenis_barang' => $result_data['jenis'] ?? null,
                'harga_pokok' => $result_data['harga'] ?? 0,
                'stok' => $stok, 
                'status' => ($result_data['status'] ?? 0) == 1 ? 'aktif' : 'tidak_aktif'
            ];
            echo json_encode(['success' => true, 'data' => $data]);

        } elseif ($action === 'get_stats') {
            // Ambil statistik untuk dashboard
            $sql = "SELECT COUNT(idbarang) as total_barang, SUM(harga) as total_nilai FROM barang WHERE status = 1";
            $result = $dbconn->query($sql)->fetch_assoc();
            $result['total_stok'] = 0; // Placeholder
            echo json_encode(['success' => true, 'data' => $result]);

        } elseif ($action === 'get_satuan') {
            // Ambil daftar satuan untuk dropdown
            $result = $dbconn->query("SELECT idsatuan, nama_satuan FROM satuan WHERE status = 1 ORDER BY nama_satuan");
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);

        } else {
            // Ambil semua barang untuk tabel utama dengan filter
            $filter = $_GET['filter'] ?? 'aktif'; 

            $sql = "SELECT 
                        b.idbarang, 
                        b.nama, 
                        s.nama_satuan, 
                        b.jenis, 
                        b.harga, 
                        b.status,
                        COALESCE((SELECT stok FROM kartu_stok WHERE idbarang = b.idbarang ORDER BY created_at DESC, idkartu_stok DESC LIMIT 1), 0) AS stok
                    FROM barang b
                    LEFT JOIN satuan s ON b.idsatuan = s.idsatuan";

            $params = [];
            $types = '';

            // Tambahkan kondisi WHERE hanya jika filter bukan 'semua'
            if ($filter !== 'semua') {
                $sql .= " WHERE b.status = ?";
                $params[] = 1; 
                $types .= 'i';
            }

            $sql .= " ORDER BY b.idbarang ASC";

            $stmt = $dbconn->prepare($sql);
            if (!$stmt) {
                 throw new Exception('Gagal mempersiapkan statement: ' . $dbconn->error);
            }
            
            if (!empty($params)) {
                // Binding untuk MySQLi (membutuhkan pass-by-reference)
                $bind_params = array_merge([$types], $params);
                call_user_func_array([$stmt, 'bind_param'], refValues($bind_params));
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $results = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            $data = [];
            foreach ($results as $row) {
                // Terjemahkan kode jenis barang ke deskripsi lengkap
                $jenis_desc = $row['jenis'];
                switch (strtolower($row['jenis'])) {
                    case 'm':
                        $jenis_desc = 'Makanan / Minuman (Konsumsi)';
                        break;
                    case 'p':
                        $jenis_desc = 'Perawatan Diri / Personal Care';
                        break;
                    case 'k':
                        $jenis_desc = 'Kebutuhan Dapur';
                        break;
                    default:
                        $jenis_desc = 'Lain-lain';
                }
                
                $data[] = [
                    'idbarang' => $row['idbarang'],
                    'kode_barang' => $row['idbarang'],
                    'nama_barang' => $row['nama'],
                    'nama_satuan' => $row['nama_satuan'] ?? '-', 
                    'jenis_barang' => $jenis_desc,
                    'harga_pokok' => $row['harga'],
                    'stok' => $row['stok'], 
                    'status' => ($row['status'] ?? 0) == 1 ? 'aktif' : 'tidak_aktif'
                ];
            }
            echo json_encode(['success' => true, 'data' => $data]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error database GET: ' . $e->getMessage()]);
    }
}

function handlePost($dbconn) {
    // Ambil data dari form
    $nama = $_POST['nama_barang'];
    $idsatuan = $_POST['idsatuan'];
    $jenis = $_POST['jenis_barang'];
    $harga = $_POST['harga_pokok'];
    $status = ($_POST['status'] === 'aktif') ? 1 : 0;
    
    try {
        $stmt = $dbconn->prepare("INSERT INTO barang (nama, idsatuan, jenis, harga, status) VALUES (?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception('Gagal mempersiapkan statement: ' . $dbconn->error);
        }

        // Tipe data: string, integer, string, integer (harga), integer (status)
        $stmt->bind_param('sissi', $nama, $idsatuan, $jenis, $harga, $status);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Barang berhasil ditambahkan.']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan barang: ' . $e->getMessage()]);
    }
}

function handlePut($dbconn) {
    // Ambil data dari form
    $idbarang = $_POST['idbarang'];
    $nama = $_POST['nama_barang'];
    $idsatuan = $_POST['idsatuan'];
    $jenis = $_POST['jenis_barang'];
    $harga = $_POST['harga_pokok'];
    $status = ($_POST['status'] === 'aktif') ? 1 : 0;
    
    try {
        $stmt = $dbconn->prepare("UPDATE barang SET nama = ?, idsatuan = ?, jenis = ?, harga = ?, status = ? WHERE idbarang = ?");
        
        if (!$stmt) {
            throw new Exception('Gagal mempersiapkan statement: ' . $dbconn->error);
        }

        // Tipe data: string, integer, string, integer (harga), integer (status), integer (idbarang)
        $stmt->bind_param('sissii', $nama, $idsatuan, $jenis, $harga, $status, $idbarang);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Barang berhasil diperbarui.']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui barang: ' . $e->getMessage()]);
    }
}

function handleDelete($dbconn) {
    $idbarang = $_POST['idbarang'];

    // Menggunakan soft delete (mengubah status menjadi 0/tidak aktif)
    try {
        $stmt = $dbconn->prepare("UPDATE barang SET status = 0 WHERE idbarang = ?");
        
        if (!$stmt) {
            throw new Exception('Gagal mempersiapkan statement: ' . $dbconn->error);
        }

        $stmt->bind_param('i', $idbarang);
        $stmt->execute();

        if ($stmt->affected_rows > 0) { // Gunakan affected_rows pada MySQLi
            echo json_encode(['success' => true, 'message' => 'Barang berhasil dinonaktifkan (soft delete).']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan.']);
        }
        $stmt->close();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menonaktifkan barang: ' . $e->getMessage()]);
    }
}

/**
 * Helper function untuk menangani passing by reference yang dibutuhkan oleh mysqli::bind_param
 * Dibuat karena PHP 5.3+ memerlukan array elemen dibinding dengan referensi.
 */
function refValues($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) {
        $refs = [];
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}
?>