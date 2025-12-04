<?php
require_once 'koneksi.php'; // Pastikan koneksi.php ada dan berfungsi
require_once 'auth.php'; // Pastikan auth.php ada dan berfungsi

// Set header untuk output JSON
header('Content-Type: application/json; charset=utf-8');

// Pastikan user sudah login
checkAuth(true); 

$method = $_SERVER['REQUEST_METHOD'];

// Ambil _method dari POST untuk simulasi PUT/DELETE
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
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

// Pastikan koneksi ditutup setelah semua operasi selesai
if (isset($dbconn) && $dbconn instanceof mysqli) {
    $dbconn->close();
}

/**
 * Fungsi untuk menangani permintaan GET (Ambil data)
 */
function handleGet($dbconn) {
    $action = $_GET['action'] ?? null;
    $id = $_GET['id'] ?? null;

    if ($id) {
        // 1. Ambil satu vendor untuk form edit
        $stmt = $dbconn->prepare("SELECT idvendor, nama_vendor, badan_hukum, status FROM vendor WHERE idvendor = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;
        
        echo json_encode(['success' => true, 'data' => $data]);
        $stmt->close();

    } elseif ($action === 'get_stats') {
        // 2. Ambil statistik untuk dashboard
        // DISESUAIKAN: Menghitung status aktif berdasarkan kode 'A' Anda
        $sql = "SELECT 
                    COUNT(idvendor) as total_vendor,
                    SUM(CASE WHEN status = 'A' THEN 1 ELSE 0 END) as total_aktif
                FROM vendor";
        $result = $dbconn->query($sql);
        if ($result) {
            $stats_data = $result->fetch_assoc();
            $stats = [
                'total_vendor' => $stats_data['total_vendor'] ?? 0,
                'total_aktif' => $stats_data['total_aktif'] ?? 0
            ];
            echo json_encode(['success' => true, 'data' => $stats]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil statistik vendor: ' . $dbconn->error]);
        }

    } else {
        // 3. Ambil daftar semua vendor (atau yang difilter)
        
        $filter = $_GET['filter'] ?? 'aktif'; // Filter default
        
        $sql = "SELECT 
                    idvendor, 
                    nama_vendor, 
                    badan_hukum, 
                    status
                FROM vendor";
        
        $whereClause = [];
        // DISESUAIKAN: Filter status aktif berdasarkan kode 'A' Anda
        if ($filter === 'aktif') {
            $whereClause[] = "status = 'A'";
        }

        if (!empty($whereClause)) {
            $sql .= " WHERE " . implode(" AND ", $whereClause);
        }

        $sql .= " ORDER BY idvendor ASC";

        $result = $dbconn->query($sql);

        if ($result) {
            $data_raw = $result->fetch_all(MYSQLI_ASSOC);
            
            // Lakukan formatting data secara manual (Mapping sesuai skema 'A'/'T' dan 'A'/'N')
            $data_formatted = array_map(function($item) {
                // DISESUAIKAN: Konversi kode badan hukum
                $jenis_badan_hukum = match ($item['badan_hukum']) {
                    'A' => 'Berbadan Hukum (Contoh: PT)',
                    'T' => 'Tidak Berbadan Hukum (Contoh: CV/UD)',
                    default => 'Tidak Diketahui',
                };

                // DISESUAIKAN: Konversi status
                $status_aktif = ($item['status'] === 'A') ? 'Aktif' : 'Non-Aktif';
                
                return [
                    'idvendor' => $item['idvendor'],
                    'nama_vendor' => $item['nama_vendor'],
                    'badan_hukum' => $item['badan_hukum'],
                    'status' => $item['status'],
                    'jenis_badan_hukum' => $jenis_badan_hukum, // Data terformat untuk tampilan
                    'status_aktif' => $status_aktif // Data terformat untuk tampilan
                ];
            }, $data_raw);

            echo json_encode(['success' => true, 'data' => $data_formatted]);
        } else {
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil data vendor: ' . $dbconn->error]);
        }
    }
}

/**
 * Fungsi untuk menangani permintaan POST (Tambah data)
 */
function handlePost($dbconn) {
    // CREATE VENDOR
    // Data yang diterima dari form sudah menggunakan kode 'A'/'T' dan 'A'/'N'
    $nama_vendor = $_POST['nama_vendor'];
    $badan_hukum = $_POST['badan_hukum'];
    $status = $_POST['status']; 

    $stmt = $dbconn->prepare("INSERT INTO vendor (nama_vendor, badan_hukum, status) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nama_vendor, $badan_hukum, $status);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Vendor berhasil ditambahkan.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan vendor: ' . $stmt->error]);
    }
    $stmt->close();
}

/**
 * Fungsi untuk menangani permintaan PUT (Update data, disimulasikan via POST)
 */
function handlePut($dbconn) {
    // UPDATE VENDOR
    $idvendor = $_POST['idvendor'];
    $nama_vendor = $_POST['nama_vendor'];
    $badan_hukum = $_POST['badan_hukum'];
    $status = $_POST['status'];

    $stmt = $dbconn->prepare("UPDATE vendor SET nama_vendor = ?, badan_hukum = ?, status = ? WHERE idvendor = ?");
    $stmt->bind_param("sssi", $nama_vendor, $badan_hukum, $status, $idvendor);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Vendor berhasil diperbarui.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui vendor: ' . $stmt->error]);
    }
    $stmt->close();
}

/**
 * Fungsi untuk menangani permintaan DELETE (Menonaktifkan data, disimulasikan via POST)
 */
function handleDelete($dbconn) {
    // DELETE VENDOR (Soft Delete: Ubah status menjadi 'N'/Non-Aktif)
    $idvendor = $_POST['idvendor'];
    
    // DISESUAIKAN: Mengubah status menjadi 'N'
    $stmt = $dbconn->prepare("UPDATE vendor SET status = 'N' WHERE idvendor = ?");
    $stmt->bind_param("i", $idvendor);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Vendor berhasil dinonaktifkan.']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Vendor tidak ditemukan.']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menonaktifkan vendor: ' . $stmt->error]);
    }
    $stmt->close();
}
?>