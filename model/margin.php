<?php
require_once 'koneksi.php'; // Menggunakan koneksi.php dari folder yang sama
require_once 'auth.php';   // Menggunakan auth.php dari folder yang sama

header('Content-Type: application/json; charset=utf-8');
checkAuth(true); // Melindungi API

$method = $_SERVER['REQUEST_METHOD'];

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


$dbconn->close();

function handleGet($dbconn) {
    $id = $_GET['id'] ?? null;

    if ($id) {
        // Ambil satu data margin untuk form edit
         try {
            // Perbaikan: Menggunakan view_margin_user dengan query yang benar dan sederhana.
            $stmt = $dbconn->prepare("SELECT idmargin_penjualan, persen, status FROM view_margin_user WHERE idmargin_penjualan = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            // Frontend mengharapkan 'aktif' atau 'tidak_aktif' untuk value select option
            if ($data) {
                $data['status'] = $data['status'] == 1 ? 'aktif' : 'tidak_aktif';
            }
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil data margin: ' . $e->getMessage()]);
        }
    } else {
        // Ambil semua data margin dari view untuk tabel (sudah benar)
        try {
            $sql = "SELECT idmargin_penjualan, persen, status, created_at, updated_at, username FROM view_margin_user ORDER BY created_at DESC";
            $result = $dbconn->query($sql);
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil daftar margin: ' . $e->getMessage()]);
        }
    }
}

function handlePost($dbconn) {
    $input = json_decode(file_get_contents('php://input'), true);

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
        // Jika status baru adalah 'aktif', nonaktifkan semua margin lain
        if ($status == 1) {
            if ($dbconn->query("UPDATE margin_penjualan SET status = 0") === false) {
                throw new Exception("Gagal menonaktifkan margin yang ada: " . $dbconn->error);
            }
        }

        // Since idmargin_penjualan is not AUTO_INCREMENT, we must generate it manually.
        // Find the current max ID and add 1. Use COALESCE to handle the first entry.
        $result = $dbconn->query("SELECT COALESCE(MAX(idmargin_penjualan), 0) + 1 AS next_id FROM margin_penjualan");
        if (!$result) throw new Exception("Gagal mengambil ID margin berikutnya: " . $dbconn->error);
        $next_id = $result->fetch_assoc()['next_id'];

        $stmt = $dbconn->prepare("INSERT INTO margin_penjualan (idmargin_penjualan, persen, status, iduser, created_at) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception('Gagal mempersiapkan statement: ' . $dbconn->error);
        }
        $stmt->bind_param("idii", $next_id, $persen, $status, $iduser);
        if ($stmt->execute() === false) throw new Exception("Gagal mengeksekusi statement: " . $stmt->error);

        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => 'Margin berhasil ditambahkan.']);
    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan margin: ' . $e->getMessage()]);
    }
}

function handlePut($dbconn) {
    $input = json_decode(file_get_contents('php://input'), true);

    $idmargin = $input['idmargin_penjualan'] ?? null;
    $persen = $input['persen'] ?? null;
    $status_text = $input['status'] ?? 'tidak_aktif';
    $status = ($status_text === 'aktif') ? 1 : 0;
    $iduser = $_SESSION['user_id'];

    // Validasi input
    if (empty($idmargin) || !is_numeric($persen)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap atau tidak valid.']);
        return;
    }

    $dbconn->begin_transaction();
    try {
        // Jika status baru adalah 'aktif', nonaktifkan semua margin lain
        if ($status == 1) {
            $stmt_deactivate = $dbconn->prepare("UPDATE margin_penjualan SET status = 0 WHERE idmargin_penjualan != ?");
            if (!$stmt_deactivate) throw new Exception("Gagal mempersiapkan statement penonaktifan: " . $dbconn->error);
            $stmt_deactivate->bind_param("i", $idmargin);
            if ($stmt_deactivate->execute() === false) throw new Exception("Gagal mengeksekusi penonaktifan: " . $stmt_deactivate->error);
        }

        $stmt = $dbconn->prepare("UPDATE margin_penjualan SET persen = ?, status = ?, iduser = ?, updated_at = NOW() WHERE idmargin_penjualan = ?");
        if (!$stmt) throw new Exception("Gagal mempersiapkan statement pembaruan: " . $dbconn->error);
        $stmt->bind_param("diii", $persen, $status, $iduser, $idmargin); // iduser diperbarui saat edit
        if ($stmt->execute() === false) throw new Exception("Gagal mengeksekusi pembaruan: " . $stmt->error);

        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => 'Margin berhasil diperbarui.']);
    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui margin: ' . $e->getMessage()]);
    }
}

function handleDelete($dbconn) {
    $input = json_decode(file_get_contents('php://input'), true);

    $idmargin = $input['idmargin_penjualan'] ?? null;

    if (empty($idmargin)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Margin tidak valid.']);
        return;
    }

    try {
        // Menggunakan soft delete (mengubah status menjadi tidak aktif)
        $stmt = $dbconn->prepare("UPDATE margin_penjualan SET status = 0, updated_at = NOW() WHERE idmargin_penjualan = ?");
        if (!$stmt) {
            throw new Exception("Gagal mempersiapkan statement: " . $dbconn->error);
        }
        $stmt->bind_param("i", $idmargin);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Margin berhasil dinonaktifkan.']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Margin tidak ditemukan.']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menonaktifkan margin: ' . $e->getMessage()]);
    }
}
?>