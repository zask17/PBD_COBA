<?php
// PERBAIKAN PATH: Menggunakan path relatif yang benar ke model dan koneksi
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
// Asumsi: checkAuth() adalah fungsi yang valid dan sudah didefinisikan di auth.php
checkAuth(true);

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

function handleGet($dbconn)
{
    $id = $_GET['id'] ?? null;
    $id_int = intval($id); // Sanitasi

    if ($id) {
        // Ambil satu role untuk form edit (Menggunakan tabel dasar role)
        $stmt =  $dbconn->prepare("SELECT idrole, nama_role FROM role WHERE idrole = ?");
        $stmt->bind_param("i", $id_int);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        // Ambil semua role untuk tabel (Menggunakan tabel dasar role)
        $sql = "SELECT idrole, nama_role FROM role ORDER BY idrole ASC";
        $result = $dbconn->query($sql);

        if ($result === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Query gagal dieksekusi: ' . $dbconn->error]);
            return;
        }

        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

function handlePost($dbconn)
{
    $nama_role = $_POST['nama_role'] ?? null;

    if (empty($nama_role)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nama role harus diisi.']);
        return;
    }

    $stmt = $dbconn->prepare("INSERT INTO role (nama_role) VALUES (?)");
    $stmt->bind_param("s", $nama_role);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Role berhasil ditambahkan.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan role: ' . $stmt->error]);
    }
}

function handlePut($dbconn)
{
    $idrole = $_POST['idrole'] ?? null;
    $nama_role = $_POST['nama_role'] ?? null;

    $idrole_int = intval($idrole);

    if (empty($idrole) || empty($nama_role)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Role dan Nama Role harus diisi.']);
        return;
    }

    $stmt = $dbconn->prepare("UPDATE role SET nama_role = ? WHERE idrole = ?");
    $stmt->bind_param("si", $nama_role, $idrole_int);

    if ($stmt->execute()) {
        if ($stmt->affected_rows === 0 && $stmt->error) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menjalankan update: ' . $stmt->error]);
            return;
        }
        echo json_encode(['success' => true, 'message' => 'Role berhasil diperbarui.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui role: ' . $stmt->error]);
    }
}

function handleDelete($dbconn)
{
    $idrole = $_POST['idrole'] ?? null;
    $idrole_int = intval($idrole);

    if (empty($idrole)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Role harus diisi.']);
        return;
    }

    // Cek apakah role masih digunakan oleh user (FK check)
    $stmt_check = $dbconn->prepare("SELECT COUNT(*) as count FROM user WHERE idrole = ?");
    $stmt_check->bind_param("i", $idrole_int);
    $stmt_check->execute();
    $count = $stmt_check->get_result()->fetch_assoc()['count'];

    if ($count > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Gagal menghapus: Role ini masih digunakan oleh {$count} user."]);
        return;
    }

    // Jika tidak digunakan, lanjutkan hapus
    $stmt = $dbconn->prepare("DELETE FROM role WHERE idrole = ?");
    $stmt->bind_param("i", $idrole_int);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Role berhasil dihapus.']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Role tidak ditemukan.']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus role: ' . $stmt->error]);
    }
}
