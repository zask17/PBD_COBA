<?php
require_once 'koneksi.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');
checkAuth(true);

$method = $_SERVER['REQUEST_METHOD'];

// Simulasi PUT/DELETE via _method di POST
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

// Tutup koneksi jika ada
if (isset($dbconn) && $dbconn) {
    $dbconn->close();
}

function handleGet($dbconn)
{
    $id = $_GET['id'] ?? null;
    $id_int = intval($id);
    $filter = $_GET['filter'] ?? 'semua';

    if ($id_int > 0) {
        // Gunakan tabel dasar untuk edit
        $stmt = $dbconn->prepare("SELECT idmargin_penjualan, persen, status FROM margin_penjualan WHERE idmargin_penjualan = ?");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal prepare statement: ' . $dbconn->error]);
            return;
        }
        $stmt->bind_param("i", $id_int);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result) {
            // Mapping status ke string untuk form
            $result['status'] = ($result['status'] == 1) ? 'aktif' : 'tidak_aktif';
            echo json_encode(['success' => true, 'data' => $result]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Margin tidak ditemukan']);
        }
        $stmt->close();
    } else {
        // Ambil daftar menggunakan VIEW
        $sql = "SELECT idmargin_penjualan, PERSEN_MARGIN AS persen, STATUS AS status_text, DIBUAT AS created_at, DIUPDATE AS updated_at, `DIBUAT OLEH` AS username 
                FROM V_MARGIN_SEMUA";
        if ($filter === 'aktif') {
            $sql .= " WHERE STATUS = 'AKTIF'";
        }
        $sql .= " ORDER BY DIBUAT DESC"; // Order by di backend

        $result = $dbconn->query($sql);
        if ($result === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Query gagal: ' . $dbconn->error . '. Pastikan V_MARGIN_SEMUA ada.']);
            return;
        }

        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

function handlePost($dbconn)
{
    $persen = $_POST['persen'] ?? null;
    $status_text = $_POST['status'] ?? 'tidak_aktif';
    $status = ($status_text === 'aktif') ? 1 : 0;
    $iduser = $_SESSION['iduser'] ?? 0;

    if (!is_numeric($persen) || $persen <= 0 || $iduser === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
        return;
    }

    $dbconn->begin_transaction();
    try {
        // Jika status aktif, nonaktifkan semua yang lain
        if ($status == 1) {
            $dbconn->query("UPDATE margin_penjualan SET status = 0");
        }

        // Insert baru
        $stmt = $dbconn->prepare("INSERT INTO margin_penjualan (persen, status, iduser) VALUES (?, ?, ?)");
        $stmt->bind_param("dii", $persen, $status, $iduser);
        $stmt->execute();

        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => 'Margin berhasil ditambahkan']);
    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal tambah: ' . $e->getMessage()]);
    }
}

function handlePut($dbconn)
{
    $idmargin = intval($_POST['idmargin_penjualan'] ?? 0);
    $persen = $_POST['persen'] ?? null;
    $status_text = $_POST['status'] ?? 'tidak_aktif';
    $status = ($status_text === 'aktif') ? 1 : 0;
    $iduser = $_SESSION['iduser'] ?? 0;

    if ($idmargin < 1 || !is_numeric($persen) || $persen <= 0 || $iduser === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
        return;
    }

    $dbconn->begin_transaction();
    try {
        // Jika set aktif, nonaktifkan semua yang lain
        if ($status == 1) {
            $dbconn->query("UPDATE margin_penjualan SET status = 0 WHERE idmargin_penjualan != $idmargin");
        }

        // Update
        $stmt = $dbconn->prepare("UPDATE margin_penjualan SET persen = ?, status = ?, iduser = ?, updated_at = NOW() WHERE idmargin_penjualan = ?");
        $stmt->bind_param("diii", $persen, $status, $iduser, $idmargin);
        $stmt->execute();

        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => 'Margin berhasil diperbarui']);
    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal update: ' . $e->getMessage()]);
    }
}

function handleDelete($dbconn)
{
    $idmargin = intval($_POST['idmargin_penjualan'] ?? 0);

    if ($idmargin < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        return;
    }

    // Soft delete: set status=0
    $stmt = $dbconn->prepare("UPDATE margin_penjualan SET status = 0, updated_at = NOW() WHERE idmargin_penjualan = ?");
    $stmt->bind_param("i", $idmargin);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Margin dinonaktifkan']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Margin tidak ditemukan']);
    }
}