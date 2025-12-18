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
/**
 * Fungsi untuk menangani permintaan GET (Ambil data)
 */
function handleGet($dbconn) {
    $action = $_GET['action'] ?? null;
    $id = $_GET['id'] ?? null;

    if ($id) {
        // 1. Ambil satu vendor untuk form edit (menggunakan tabel dasar)
        $stmt = $dbconn->prepare("SELECT idvendor, nama_vendor, badan_hukum, status FROM vendor WHERE idvendor = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;
        
        echo json_encode(['success' => true, 'data' => $data]);
        $stmt->close();

    } elseif ($action === 'get_stats') {
        // 2. Ambil statistik untuk dashboard
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
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil statistik: ' . $dbconn->error]);
        }

    } else {
        // 3. Ambil daftar vendor menggunakan View
        $filter = $_GET['filter'] ?? 'semua';
        
        if ($filter === 'aktif') {
            // MENGGUNAKAN V_VENDOR_AKTIF
            $sql = "SELECT 
                        idvendor, 
                        VENDOR AS nama_vendor, 
                        `BADAN HUKUM` AS jenis_badan_hukum,
                        STATUS AS status_aktif
                    FROM V_VENDOR_AKTIF 
                    ORDER BY idvendor ASC";
            
        } else { 
            // MENGGUNAKAN V_VENDOR_SEMUA
            $sql = "SELECT 
                        idvendor, 
                        VENDOR AS nama_vendor, 
                        `BADAN HUKUM` AS jenis_badan_hukum,
                        STATUS AS status_aktif
                    FROM V_VENDOR_SEMUA 
                    ORDER BY idvendor ASC";
        }

        $result = $dbconn->query($sql);

        if ($result) {
            $data_formatted = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $data_formatted]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil data vendor: ' . $dbconn->error]);
        }
    }
}

/**
 * Fungsi untuk menangani permintaan POST (Tambah data)
 */
function handlePost($dbconn) {
    // CREATE VENDOR
    $nama_vendor = $_POST['nama_vendor'] ?? null;
    $badan_hukum = $_POST['badan_hukum'] ?? null;
    $status = $_POST['status'] ?? null; 

    if (empty($nama_vendor) || empty($badan_hukum) || empty($status)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
        return;
    }

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
    $idvendor = $_POST['idvendor'] ?? null;
    $nama_vendor = $_POST['nama_vendor'] ?? null;
    $badan_hukum = $_POST['badan_hukum'] ?? null;
    $status = $_POST['status'] ?? null; // Bisa 'A' atau 'T' dari reactivateVendor

    if (empty($idvendor) || empty($nama_vendor) || empty($badan_hukum) || empty($status)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
        return;
    }
    
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
    // DELETE VENDOR (Soft Delete: Ubah status menjadi 'T'/Non-Aktif)
    $idvendor = $_POST['idvendor'] ?? null;
    
    if (empty($idvendor)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Vendor tidak valid.']);
        return;
    }

    $stmt = $dbconn->prepare("UPDATE vendor SET status = 'T' WHERE idvendor = ? AND status = 'A'"); // Hanya update jika status masih Aktif
    $stmt->bind_param("i", $idvendor);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Vendor berhasil dinonaktifkan.']);
        } else {
            // Cek jika ID tidak ditemukan atau sudah non-aktif
            $check_stmt = $dbconn->prepare("SELECT 1 FROM vendor WHERE idvendor = ?");
            $check_stmt->bind_param("i", $idvendor);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows === 0) {
                 http_response_code(404);
                 echo json_encode(['success' => false, 'message' => 'Vendor tidak ditemukan.']);
            } else {
                 echo json_encode(['success' => true, 'message' => 'Vendor sudah dalam status Non-Aktif.']);
            }
            $check_stmt->close();
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menonaktifkan vendor: ' . $stmt->error]);
    }
    $stmt->close();
}
?>