<?php
require_once 'koneksi.php';
require_once 'auth.php';

// Proper error handling for a JSON API
ini_set('display_errors', 0); // Jangan tampilkan error di output
ini_set('log_errors', 1); // Log error ke file
error_reporting(E_ALL);

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
    $action = $_GET['action'] ?? null;
    $id = $_GET['id'] ?? null;

    if ($action === 'get_roles') {
        // Ambil daftar role untuk dropdown
        $result = $dbconn->query("SELECT idrole, nama_role FROM role ORDER BY nama_role");
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    } elseif ($id) {
        // Ambil satu user untuk form edit (tanpa password)
        $stmt = $dbconn->prepare("SELECT iduser, username, idrole FROM user WHERE iduser = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();

        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'User tidak ditemukan.']);
        }
    } else {
        // Ambil semua user dengan nama role-nya
        // Perbaikan: Query salah, seharusnya join tabel user dan role, bukan memanggil view di FROM
        $sql = "SELECT u.iduser, u.username, r.nama_role 
                FROM user u
                LEFT JOIN role r ON u.idrole = r.idrole
                ORDER BY u.iduser ASC";

        $result = $dbconn->query($sql);
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

function handlePost($dbconn) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $idrole = $_POST['idrole'];

    if (empty($username) || empty($password) || empty($idrole)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Semua field (username, password, role) harus diisi.']);
        return;
    }

    // Hash password sebelum disimpan
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $dbconn->prepare("INSERT INTO user (username, password, idrole) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $username, $hashed_password, $idrole);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User berhasil ditambahkan.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan user: ' . $stmt->error]);
    }
}

function handlePut($dbconn) {
    $iduser = $_POST['iduser'];
    $username = $_POST['username'];
    $idrole = $_POST['idrole'];
    $password = $_POST['password'];

    if (empty($iduser) || empty($username) || empty($idrole)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID User, Username, dan Role tidak boleh kosong.']);
        return;
    }

    // Jika password diisi, update password. Jika tidak, jangan update.
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $dbconn->prepare("UPDATE user SET username = ?, password = ?, idrole = ? WHERE iduser = ?");
        $stmt->bind_param("ssii", $username, $hashed_password, $idrole, $iduser);
    } else {
        $stmt = $dbconn->prepare("UPDATE user SET username = ?, idrole = ? WHERE iduser = ?");
        $stmt->bind_param("sii", $username, $idrole, $iduser);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User berhasil diperbarui.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui user: ' . $stmt->error]);
    }
}

function handleDelete($dbconn) {
    $iduser = $_POST['iduser'];

    if (empty($iduser)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID User tidak valid.']);
        return;
    }

    // Peringatan: Ini adalah hard delete.
    // Untuk keamanan, Anda bisa menambahkan validasi agar tidak bisa menghapus user sendiri atau admin utama.
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $iduser) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
        return;
    }

    $stmt = $dbconn->prepare("DELETE FROM user WHERE iduser = ?");
    $stmt->bind_param("i", $iduser);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'User berhasil dihapus.']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User tidak ditemukan.']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus user: ' . $stmt->error]);
    }
}
?>