<?php
require_once 'koneksi.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');
checkAuth(true); // Melindungi API

$method = $_SERVER['REQUEST_METHOD'];

// Membaca input JSON atau form data untuk POST/PUT/DELETE
$input = [];
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    if (isset($input['_method'])) {
        $method = strtoupper($input['_method']);
    }
} elseif ($method === 'PUT') {
     $input = json_decode(file_get_contents('php://input'), true);
}


switch ($method) {
    case 'GET':
        handleGet($dbconn);
        break;
    case 'POST':
        handlePost($dbconn, $input);
        break;
    case 'PUT':
        handlePut($dbconn, $input);
        break;
    case 'DELETE':
        handleDelete($dbconn, $input);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode tidak didukung']);
        break;
}


$dbconn->close();

function handleGet($dbconn) {
    $id = $_GET['id'] ?? null;

    if ($id) {
        // Ambil satu data margin untuk form edit (dari tabel dasar)
        try {
            $stmt = $dbconn->prepare("SELECT idmargin_penjualan, persen, status FROM margin_penjualan WHERE idmargin_penjualan = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            
            if ($data) {
                $data['status'] = $data['status'] == 1 ? 'aktif' : 'tidak_aktif';
            }
            echo json_encode(['success' => true, 'data' => $data]);
            $stmt->close();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil data margin: ' . $e->getMessage()]);
        }
    } else {
        // Ambil daftar semua margin (menggunakan V_MARGIN_SEMUA)
        try {
            // QUERY DISESUAIKAN: Menggunakan V_MARGIN_SEMUA
            $sql = "SELECT 
                        idmargin_penjualan, 
                        PERSEN_MARGIN AS persen, 
                        STATUS, 
                        DIBUAT AS created_at, 
                        DIUPDATE AS updated_at, 
                        `DIBUAT OLEH` AS username 
                    FROM V_MARGIN_SEMUA 
                    ORDER BY DIBUAT DESC"; // Menggunakan DIBUAT untuk ORDER BY

            $result = $dbconn->query($sql);
            $data_raw = $result->fetch_all(MYSQLI_ASSOC);

            // Mapping data agar konsisten dengan JS
            $data = array_map(function($item) {
                // Konversi STATUS dari VIEW (string 'AKTIF'/'TIDAK AKTIF') ke integer (1/0)
                $item['status'] = ($item['STATUS'] === 'AKTIF') ? 1 : 0;
                $item['status_int'] = (int)$item['status'];
                return $item;
            }, $data_raw);
            
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil daftar margin: ' . $e->getMessage()]);
        }
    }
}

function handlePost($dbconn, $input) {
    $persen = $input['persen'] ?? null;
    $status_text = $input['status'] ?? 'tidak_aktif';
    $status = ($status_text === 'aktif') ? 1 : 0;
    $iduser = $_SESSION['user_id'];

    if (!is_numeric($persen)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data tidak valid: Persentase margin harus berupa angka.']);
        return;
    }

    $dbconn->begin_transaction();
    try {
        if ($status == 1) {
            if ($dbconn->query("UPDATE margin_penjualan SET status = 0") === false) {
                throw new Exception("Gagal menonaktifkan margin yang ada: " . $dbconn->error);
            }
        }

        $result = $dbconn->query("SELECT COALESCE(MAX(idmargin_penjualan), 0) + 1 AS next_id FROM margin_penjualan");
        if (!$result) throw new Exception("Gagal mengambil ID margin berikutnya: " . $dbconn->error);
        $next_id = $result->fetch_assoc()['next_id'];

        $stmt = $dbconn->prepare("INSERT INTO margin_penjualan (idmargin_penjualan, persen, status, iduser, created_at) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) throw new Exception('Gagal mempersiapkan statement: ' . $dbconn->error);
        
        $stmt->bind_param("idii", $next_id, $persen, $status, $iduser);
        if ($stmt->execute() === false) throw new Exception("Gagal mengeksekusi statement: " . $stmt->error);

        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => 'Margin berhasil ditambahkan.']);
        $stmt->close();
    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan margin: ' . $e->getMessage()]);
    }
}

function handlePut($dbconn, $input) {
    $idmargin = $input['idmargin_penjualan'] ?? null;
    $persen = $input['persen'] ?? null;
    $status_text = $input['status'] ?? 'tidak_aktif';
    $status = ($status_text === 'aktif') ? 1 : 0;
    $iduser = $_SESSION['user_id'];

    if (empty($idmargin) || !is_numeric($persen)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap atau tidak valid.']);
        return;
    }

    $dbconn->begin_transaction();
    try {
        if ($status == 1) {
            $stmt_deactivate = $dbconn->prepare("UPDATE margin_penjualan SET status = 0 WHERE idmargin_penjualan != ?");
            if (!$stmt_deactivate) throw new Exception("Gagal mempersiapkan statement penonaktifan: " . $dbconn->error);
            $stmt_deactivate->bind_param("i", $idmargin);
            if ($stmt_deactivate->execute() === false) throw new Exception("Gagal mengeksekusi penonaktifan: " . $stmt_deactivate->error);
            $stmt_deactivate->close();
        }

        $stmt = $dbconn->prepare("UPDATE margin_penjualan SET persen = ?, status = ?, iduser = ?, updated_at = NOW() WHERE idmargin_penjualan = ?");
        if (!$stmt) throw new Exception("Gagal mempersiapkan statement pembaruan: " . $dbconn->error);
        $stmt->bind_param("diii", $persen, $status, $iduser, $idmargin);
        if ($stmt->execute() === false) throw new Exception("Gagal mengeksekusi pembaruan: " . $stmt->error);
        $stmt->close();

        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => 'Margin berhasil diperbarui.']);
    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui margin: ' . $e->getMessage()]);
    }
}

function handleDelete($dbconn, $input) {
    $idmargin = $input['idmargin_penjualan'] ?? null;

    if (empty($idmargin)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Margin tidak valid.']);
        return;
    }

    try {
        $stmt = $dbconn->prepare("UPDATE margin_penjualan SET status = 0, updated_at = NOW() WHERE idmargin_penjualan = ?");
        if (!$stmt) throw new Exception("Gagal mempersiapkan statement: " . $dbconn->error);
        
        $stmt->bind_param("i", $idmargin);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Margin berhasil dinonaktifkan.']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Margin tidak ditemukan.']);
        }
        $stmt->close();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menonaktifkan margin: ' . $e->getMessage()]);
    }
}
?>