<?php
require_once 'koneksi.php';
require_once 'auth.php';

// Proper error handling for a JSON API
ini_set('display_errors', 0); // Jangan tampilkan error di output
ini_set('log_errors', 1); // Log error ke file
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
checkAuth(); // Melindungi API, idealnya checkAuth('super administrator')

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

if (isset($dbconn) && $dbconn) {
    $dbconn->close();
}

function handleGet($dbconn) {
    $action = $_GET['action'] ?? null;
    $id = $_GET['id'] ?? null;
    $id_int = intval($id); 

    if ($action === 'get_roles') {
        // --- Ambil daftar role untuk dropdown ---
        // Menggunakan tabel role langsung
        $result = $dbconn->query("SELECT idrole, nama_role FROM role ORDER BY nama_role ASC");
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    } elseif ($id) {
        // --- Ambil satu user untuk form edit (tanpa password) ---
        // Harus menggunakan tabel user untuk mendapatkan idrole dan username
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
        // --- Ambil semua user dengan nama role-nya menggunakan VIEW V_USER_ROLE ---
        // Kolom di view: iduser, NAMA, ROLE
        $sql = "SELECT iduser, 
                       NAMA AS username, 
                       ROLE AS nama_role 
                FROM V_USER_ROLE 
                ORDER BY iduser ASC";

        $result = $dbconn->query($sql);
        
        if ($result === false) {
             http_response_code(500);
             echo json_encode(['success' => false, 'message' => 'Query V_USER_ROLE gagal dieksekusi: ' . $dbconn->error . '. Pastikan V_USER_ROLE ada.']);
             return;
        }
        
        $data = $result->fetch_all(MYSQLI_ASSOC);
        
        // Data sudah di-alias di SQL: NAMA -> username, ROLE -> nama_role
        echo json_encode(['success' => true, 'data' => $data]);
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

    // Menggunakan password_hash() untuk keamanan, atau langsung $password jika PBD menggunakan plain text
    // Asumsi: Kita gunakan password_hash() karena lebih aman.
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
        // Memberikan pesan sukses meskipun affected_rows = 0 (data tidak berubah)
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
    if (isset($_SESSION['iduser']) && $_SESSION['iduser'] == $iduser_int) {
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
?>