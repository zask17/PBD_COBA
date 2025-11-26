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
    $id_int = intval($id); // Sanitasi ID

    if ($action === 'get_roles') {
        // --- Ambil daftar role untuk dropdown ---
        $result = $dbconn->query("SELECT idrole, nama_role FROM role ORDER BY nama_role");
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    } elseif ($id) {
        // --- Ambil satu user untuk form edit (tanpa password) ---
        $stmt = $dbconn->prepare("SELECT iduser, username, idrole FROM user WHERE iduser = ?");
        $stmt->bind_param("i", $id_int);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();

        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'User tidak ditemukan.']);
        }
    } else {
        // --- Ambil semua user dengan nama role-nya ---
        $sql = "SELECT u.iduser, u.username, r.nama_role 
                FROM user u
                LEFT JOIN role r ON u.idrole = r.idrole
                ORDER BY u.iduser ASC";

        $result = $dbconn->query($sql);
        
        if ($result === false) {
             // Debugging jika query gagal (misalnya koneksi atau tabel error)
             http_response_code(500);
             echo json_encode(['success' => false, 'message' => 'Query gagal dieksekusi: ' . $dbconn->error]);
             return;
        }
        
        $data = $result->fetch_all(MYSQLI_ASSOC);
        
        // Mapping kolom nama_role menjadi ROLE agar konsisten dengan V_USER_ROLE jika view digunakan
        $mapped_data = array_map(function($item) {
            $item['ROLE'] = $item['nama_role'];
            unset($item['nama_role']);
            return $item;
        }, $data);

        echo json_encode(['success' => true, 'data' => $mapped_data]);
    }
}

function handlePost($dbconn) {
    $username = $_POST['username'] ?? null;
    $password = $_POST['password'] ?? null;
    $idrole = $_POST['idrole'] ?? null;

    if (empty($username) || empty($password) || empty($idrole)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Semua field (username, password, role) harus diisi.']);
        return;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $idrole_int = intval($idrole);

    $stmt = $dbconn->prepare("INSERT INTO user (username, password, idrole) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $username, $hashed_password, $idrole_int);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User berhasil ditambahkan.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan user: ' . $stmt->error]);
    }
}

function handlePut($dbconn) {
    $iduser = $_POST['iduser'] ?? null;
    $username = $_POST['username'] ?? null;
    $idrole = $_POST['idrole'] ?? null;
    $password = $_POST['password'] ?? null;

    if (empty($iduser) || empty($username) || empty($idrole)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID User, Username, dan Role tidak boleh kosong.']);
        return;
    }
    
    $iduser_int = intval($iduser);
    $idrole_int = intval($idrole);

    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $dbconn->prepare("UPDATE user SET username = ?, password = ?, idrole = ? WHERE iduser = ?");
        $stmt->bind_param("ssii", $username, $hashed_password, $idrole_int, $iduser_int);
    } else {
        $stmt = $dbconn->prepare("UPDATE user SET username = ?, idrole = ? WHERE iduser = ?");
        $stmt->bind_param("sii", $username, $idrole_int, $iduser_int);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User berhasil diperbarui.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui user: ' . $stmt->error]);
    }
}

function handleDelete($dbconn) {
    $iduser = $_POST['iduser'] ?? null;

    if (empty($iduser)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID User tidak valid.']);
        return;
    }
    
    $iduser_int = intval($iduser);

    // Cek agar tidak menghapus user sendiri
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $iduser_int) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
        return;
    }

    $stmt = $dbconn->prepare("DELETE FROM user WHERE iduser = ?");
    $stmt->bind_param("i", $iduser_int);

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