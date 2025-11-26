<?php
require_once 'koneksi.php';
require_once 'auth.php';

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
        echo json_encode(['success' => false, 'message' => 'Metode tidak didukung']);
        break;
}

$dbconn->close();

function handleGet($dbconn) {
    $action = $_GET['action'] ?? null;
    $id = $_GET['id'] ?? null;

    if ($id) {
        // Ambil satu vendor untuk form edit
        $stmt = $dbconn->prepare("SELECT idvendor, nama_vendor, badan_hukum, status FROM vendor WHERE idvendor = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        
        echo json_encode(['success' => true, 'data' => $data]);

    } elseif ($action === 'get_stats') {
        // Ambil statistik untuk dashboard
        $sql = "SELECT 
                    COUNT(idvendor) as total_vendor,
                    SUM(CASE WHEN status = '1' THEN 1 ELSE 0 END) as total_aktif
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
        
        $filter = $_GET['filter'] ?? 'aktif'; // Default filter adalah 'aktif'

        $sql = "SELECT 
                    idvendor, 
                    nama_vendor, 
                    CASE badan_hukum WHEN 'c' THEN 'Perusahaan (C)' WHEN 'p' THEN 'Perorangan (P)' ELSE 'Lainnya' END as jenis_badan_hukum,
                    CASE status WHEN 1 THEN 'Aktif' ELSE 'Non-Aktif' END as status_aktif,
                    status
                FROM view_data_vendor";
        
        // Filter berdasarkan status numerik
        if ($filter === 'aktif') {
            $sql .= " WHERE status = 1";
        }

        $sql .= " ORDER BY idvendor ASC";

        $result = $dbconn->query($sql);

        // Cek apakah query berhasil sebelum mengambil data
        if ($result) {
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            // Jika query gagal, kirim pesan error dalam format JSON
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil data vendor: ' . $dbconn->error]);
        }
    }
}

function handlePost($dbconn) {
    // CREATE VENDOR
    $nama_vendor = $_POST['nama_vendor'];
    $badan_hukum = $_POST['badan_hukum'];
    $status = $_POST['status']; 

    // Asumsi ID Vendor dibuat AUTO_INCREMENT atau di-handle trigger
    $stmt = $dbconn->prepare("INSERT INTO vendor (nama_vendor, badan_hukum, status) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nama_vendor, $badan_hukum, $status);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Vendor berhasil ditambahkan.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan vendor: ' . $stmt->error]);
    }
}

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
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui vendor: ' . $stmt->error]);
    }
}

function handleDelete($dbconn) {
    // DELETE VENDOR
    $idvendor = $_POST['idvendor'];
    
    // Menggunakan soft delete (mengubah status menjadi tidak aktif) untuk menghindari error foreign key.
    $stmt = $dbconn->prepare("UPDATE vendor SET status = 0 WHERE idvendor = ?");
    $stmt->bind_param("i", $idvendor);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Vendor berhasil dinonaktifkan (dihapus).']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Vendor tidak ditemukan.']);
        }
    } else {
        // Jika terjadi error lain saat update
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menonaktifkan vendor: ' . $stmt->error]);
    }
}
?>