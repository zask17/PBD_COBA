<?php
// model/roles.php (API untuk Manajemen Role)

require_once 'koneksi.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

checkAuth(); 

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
        handlePost($dbconn); // Menangani penambahan data baru (dengan ID manual)
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

function handleGet($dbconn) {
    $id = $_GET['id'] ?? null;

    if ($id) {
        // Ambil satu data role untuk form edit
        $stmt = $dbconn->prepare("SELECT idrole, nama_role FROM role WHERE idrole = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
             echo json_encode(['success' => true, 'data' => $result]);
        } else {
             http_response_code(404);
             echo json_encode(['success' => false, 'message' => 'Role tidak ditemukan.']);
        }
       
    } else {
        // Ambil semua data role untuk tabel
        $result = $dbconn->query("SELECT idrole, ROLE AS nama_role FROM V_ROLE ORDER BY idrole ASC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

function handlePost($dbconn) {
    $nama_role = $_POST['nama_role'] ?? null;

    if (empty($nama_role)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nama role wajib diisi.']);
        return;
    }

    // --- LOGIKA MENCARI ID MAKSIMUM DAN GENERATE ID BARU ---
    $max_id_result = $dbconn->query("SELECT MAX(idrole) AS max_id FROM role");
    $max_id_row = $max_id_result->fetch_assoc();
    $new_id = ($max_id_row['max_id'] ?? 0) + 1; // Jika tabel kosong, mulai dari 1

    // Pastikan tidak ada duplikasi ID (walaupun jarang terjadi)
    // Query yang lebih aman dalam kasus concurrency:
    // INSERT INTO role (idrole, nama_role) VALUES (?, ?) ON DUPLICATE KEY UPDATE nama_role=nama_role
    
    // Karena kita tidak bisa menggunakan ON DUPLICATE KEY UPDATE tanpa transaction (untuk memastikan ID unik):
    try {
        $stmt = $dbconn->prepare("INSERT INTO role (idrole, nama_role) VALUES (?, ?)");
        $stmt->bind_param("is", $new_id, $nama_role);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Role berhasil ditambahkan dengan ID: ' . $new_id]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan role: ' . $stmt->error]);
        }
    } catch (Exception $e) {
         http_response_code(500);
         echo json_encode(['success' => false, 'message' => 'Error database saat mencoba menambah role: ' . $e->getMessage()]);
    }
}

function handlePut($dbconn) {
    // Hanya mengupdate nama_role
    $idrole = $_POST['idrole'];
    $nama_role = $_POST['nama_role'];

    $stmt = $dbconn->prepare("UPDATE role SET nama_role = ? WHERE idrole = ?");
    $stmt->bind_param("si", $nama_role, $idrole);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Role berhasil diperbarui.']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Role berhasil diperbarui, tetapi tidak ada perubahan yang terdeteksi.']); 
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui role: ' . $stmt->error]);
    }
}

function handleDelete($dbconn) {
    $idrole = $_POST['idrole'];
    
    $stmt = $dbconn->prepare("DELETE FROM role WHERE idrole = ?");
    $stmt->bind_param("i", $idrole);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Role berhasil dihapus.']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Role tidak ditemukan.']);
        }
    } else {
        // Cek jika errornya adalah Foreign Key Constraint (code 1451)
        if ($dbconn->errno == 1451) { 
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus role: Masih ada user yang menggunakan role ini.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus role: ' . $stmt->error]);
        }
    }
}
?>