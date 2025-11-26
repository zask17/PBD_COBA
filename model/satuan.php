<?php
require_once 'koneksi.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');
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

$dbconn->close();

function handleGet($dbconn) {
    $id = $_GET['id'] ?? null;

    if ($id) {
        // Ambil satu data satuan untuk form edit
        $stmt = $dbconn->prepare("SELECT idsatuan, nama_satuan, status FROM view_satuan WHERE idsatuan = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $result['status'] = ($result['status'] ?? 0) == 1 ? 'aktif' : 'tidak_aktif';
        echo json_encode(['success' => true, 'data' => $result]);
    } else {
        // Ambil semua data satuan untuk tabel
        $result = $dbconn->query("SELECT idsatuan, nama_satuan, status FROM view_satuan ORDER BY idsatuan ASC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $row['status_text'] = $row['status'] == 1 ? 'Aktif' : 'Tidak Aktif';
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

function handlePost($dbconn) {
    $nama_satuan = $_POST['nama_satuan'];
    $status = ($_POST['status'] === 'aktif') ? 1 : 0;

    $stmt = $dbconn->prepare("INSERT INTO satuan (nama_satuan, status) VALUES (?, ?)");
    $stmt->bind_param("si", $nama_satuan, $status);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Satuan berhasil ditambahkan.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan satuan: ' . $stmt->error]);
    }
}

function handlePut($dbconn) {
    $idsatuan = $_POST['idsatuan'];
    $nama_satuan = $_POST['nama_satuan'];
    $status = ($_POST['status'] === 'aktif') ? 1 : 0;

    $stmt = $dbconn->prepare("UPDATE satuan SET nama_satuan = ?, status = ? WHERE idsatuan = ?");
    $stmt->bind_param("sii", $nama_satuan, $status, $idsatuan);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Satuan berhasil diperbarui.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui satuan: ' . $stmt->error]);
    }
}

function handleDelete($dbconn) {
    $idsatuan = $_POST['idsatuan'];

    // Menggunakan soft delete (mengubah status menjadi tidak aktif)
    $stmt = $dbconn->prepare("UPDATE satuan SET status = 0 WHERE idsatuan = ?");
    $stmt->bind_param("i", $idsatuan);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Satuan berhasil dinonaktifkan (soft delete).']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Satuan tidak ditemukan.']);
        }
    } else {
        // Jika gagal karena foreign key (meskipun soft delete jarang kena ini)
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menonaktifkan satuan: ' . $stmt->error]);
    }
}
?>