<?php
require_once 'koneksi.php';
require_once 'auth.php';

// Pastikan Content-Type adalah JSON
header('Content-Type: application/json; charset=utf-8');

// Memeriksa otentikasi
checkAuth(true); // Melindungi API, hanya untuk user yang sudah login

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
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode tidak didukung']);
        break;
}

// Pengecekan sebelum close()
if (isset($dbconn) && $dbconn) {
    $dbconn->close();
}


function handleGet($dbconn)
{
    // Cek koneksi
    if (!$dbconn || $dbconn->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.']);
        return;
    }

    $id = $_GET['id'] ?? null;
    $view = strtolower($_GET['view'] ?? 'semua'); // Ambil parameter view, default 'semua'
    $id_int = intval($id);

    if ($id) {
        // --- Ambil satu data satuan (untuk form edit) ---
        // Digunakan untuk mengisi data saat tombol 'Edit' diklik.
        $stmt = $dbconn->prepare("SELECT idsatuan, nama_satuan, status FROM satuan WHERE idsatuan = ?");
        
        if (!$stmt) {
             http_response_code(500);
             echo json_encode(['success' => false, 'message' => 'Gagal mempersiapkan statement GET single: ' . $dbconn->error]);
             return;
        }

        $stmt->bind_param("i", $id_int);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result) {
            // Mapping status 0/1 ke string 'aktif'/'tidak_aktif'
            $result['status'] = ($result['status'] == 1) ? 'aktif' : 'tidak_aktif';
            echo json_encode(['success' => true, 'data' => $result]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Satuan tidak ditemukan.']);
        }
    } else {
        // --- Ambil semua data satuan berdasarkan filter view ---
        
        $sql = "";
        if ($view === 'aktif') {
            // Menggunakan V_SATUAN_AKTIF: hanya berisi idsatuan dan SATUAN
            $sql = "SELECT idsatuan, 
                           SATUAN AS nama_satuan, 
                           'Aktif' AS status_text
                    FROM V_SATUAN_AKTIF 
                    ORDER BY idsatuan ASC";
        } else {
            // Default, menggunakan V_SATUAN_SEMUA: berisi idsatuan, SATUAN, dan STATUS SATUAN
            $sql = "SELECT idsatuan, 
                           SATUAN AS nama_satuan, 
                           `STATUS SATUAN` AS status_text 
                    FROM V_SATUAN_SEMUA 
                    ORDER BY idsatuan ASC";
        }

        $result = $dbconn->query($sql);

        if ($result === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Query view gagal dieksekusi: ' . $dbconn->error . '. Pastikan V_SATUAN_SEMUA dan V_SATUAN_AKTIF ada.']);
            return;
        }

        $data = $result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

function handlePost($dbconn)
{
    $nama_satuan = $_POST['nama_satuan'] ?? null;
    // Konversi string 'aktif'/'tidak_aktif' ke int 1/0
    $status = ($_POST['status'] ?? 'aktif') === 'aktif' ? 1 : 0; 

    if (empty($nama_satuan)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nama satuan harus diisi.']);
        return;
    }

    $stmt = $dbconn->prepare("INSERT INTO satuan (nama_satuan, status) VALUES (?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mempersiapkan statement POST: ' . $dbconn->error]);
        return;
    }
    $stmt->bind_param("si", $nama_satuan, $status);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Satuan berhasil ditambahkan.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan satuan: ' . $stmt->error]);
    }
}

function handlePut($dbconn)
{
    $idsatuan = $_POST['idsatuan'] ?? null;
    $nama_satuan = $_POST['nama_satuan'] ?? null;
    // Konversi string 'aktif'/'tidak_aktif' ke int 1/0
    $status = ($_POST['status'] ?? 'aktif') === 'aktif' ? 1 : 0;

    $idsatuan_int = intval($idsatuan);

    if (empty($idsatuan) || empty($nama_satuan)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Satuan dan Nama Satuan tidak boleh kosong.']);
        return;
    }

    $stmt = $dbconn->prepare("UPDATE satuan SET nama_satuan = ?, status = ? WHERE idsatuan = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mempersiapkan statement PUT: ' . $dbconn->error]);
        return;
    }
    $stmt->bind_param("sii", $nama_satuan, $status, $idsatuan_int);

    if ($stmt->execute()) {
        if ($stmt->affected_rows === 0 && $stmt->error) { 
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menjalankan update: ' . $stmt->error]);
            return;
        }
        echo json_encode(['success' => true, 'message' => 'Satuan berhasil diperbarui.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui satuan: ' . $stmt->error]);
    }
}

function handleDelete($dbconn)
{
    $idsatuan = $_POST['idsatuan'] ?? null;
    $idsatuan_int = intval($idsatuan);

    if (empty($idsatuan)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Satuan tidak valid.']);
        return;
    }

    // Menggunakan soft delete (mengubah status menjadi tidak aktif / 0)
    $stmt = $dbconn->prepare("UPDATE satuan SET status = 0 WHERE idsatuan = ? AND status = 1"); // Hanya update jika status masih Aktif (1)
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mempersiapkan statement DELETE: ' . $dbconn->error]);
        return;
    }
    $stmt->bind_param("i", $idsatuan_int);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Satuan berhasil dinonaktifkan (soft delete).']);
        } else {
            // Cek jika ID tidak ditemukan atau sudah non-aktif
            $check_stmt = $dbconn->prepare("SELECT 1 FROM satuan WHERE idsatuan = ?");
            $check_stmt->bind_param("i", $idsatuan_int);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows === 0) {
                 http_response_code(404);
                 echo json_encode(['success' => false, 'message' => 'Satuan tidak ditemukan.']);
            } else {
                 echo json_encode(['success' => true, 'message' => 'Satuan sudah dalam status Non-Aktif.']);
            }
            $check_stmt->close();
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menonaktifkan satuan: ' . $stmt->error]);
    }
}
?>