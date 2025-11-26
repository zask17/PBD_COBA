<?php
require_once 'koneksi.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');
// Periksa apakah $dbconn sudah didefinisikan di koneksi.php
if (!isset($dbconn)) {
    // Ini mungkin terjadi jika koneksi gagal di koneksi.php
}
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

// Pengecekan sebelum close() untuk menghindari error 'Found NULL'
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
    $id_int = intval($id);

    if ($id) {
        // --- Ambil satu data satuan (untuk form edit) ---
        // Tetap gunakan tabel dasar 'satuan' untuk mendapatkan data mentah (0 atau 1)
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
            // Mapping status 0/1 ke string 'aktif'/'tidak_aktif' untuk mengisi form edit
            $result['status'] = ($result['status'] == 1) ? 'aktif' : 'tidak_aktif';
            echo json_encode(['success' => true, 'data' => $result]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Satuan tidak ditemukan.']);
        }
    } else {
        // --- Ambil semua data satuan menggunakan V_SATUAN_SEMUA ---
        // Gunakan ALIAS agar nama kolom sesuai dengan yang diharapkan JavaScript:
        // SATUAN (dari view) menjadi nama_satuan (untuk frontend)
        // STATUS SATUAN (dari view) menjadi status_text (untuk frontend)
        $sql = "SELECT idsatuan, 
                       SATUAN AS nama_satuan, 
                       `STATUS SATUAN` AS status_text 
                FROM V_SATUAN_SEMUA 
                ORDER BY idsatuan ASC";

        $result = $dbconn->query($sql);

        if ($result === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Query view gagal dieksekusi: ' . $dbconn->error . '. Pastikan V_SATUAN_SEMUA ada.']);
            return;
        }

        // Karena view sudah menyediakan status_text, kita tidak perlu memprosesnya lagi di PHP
        $data = $result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

function handlePost($dbconn)
{
    $nama_satuan = $_POST['nama_satuan'] ?? null;
    $status = ($_POST['status'] ?? 'tidak_aktif') === 'aktif' ? 1 : 0;

    if (empty($nama_satuan)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nama satuan harus diisi.']);
        return;
    }

    $stmt = $dbconn->prepare("INSERT INTO satuan (nama_satuan, status) VALUES (?, ?)");
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
    $status = ($_POST['status'] ?? 'tidak_aktif') === 'aktif' ? 1 : 0;

    $idsatuan_int = intval($idsatuan);

    if (empty($idsatuan) || empty($nama_satuan)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Satuan dan Nama Satuan tidak boleh kosong.']);
        return;
    }

    $stmt = $dbconn->prepare("UPDATE satuan SET nama_satuan = ?, status = ? WHERE idsatuan = ?");
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

    // Menggunakan soft delete (mengubah status menjadi tidak aktif)
    $stmt = $dbconn->prepare("UPDATE satuan SET status = 0 WHERE idsatuan = ?");
    $stmt->bind_param("i", $idsatuan_int);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Satuan berhasil dinonaktifkan (soft delete).']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Satuan tidak ditemukan.']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menonaktifkan satuan: ' . $stmt->error]);
    }
}