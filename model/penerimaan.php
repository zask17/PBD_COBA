<?php

require_once 'koneksi.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');
checkAuth(true); // Melindungi API

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Cek jika ada _method untuk simulasi PUT/DELETE dari form
if ($method === 'POST' && isset($input['_method'])) {
    $method = strtoupper($input['_method']);
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
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode tidak didukung']);
        break;
}

$dbconn->close();

function handleGet($dbconn) {
    $action = $_GET['action'] ?? null;

    if ($action === 'get_open_pos') {
        // Ambil daftar PO yang belum sepenuhnya diterima (logic ini bisa disempurnakan)
        $sql = "SELECT p.idpengadaan, p.timestamp, v.nama_vendor 
                FROM pengadaan p 
                JOIN vendor v ON p.vendor_idvendor = v.idvendor 
                WHERE p.status IS NULL OR p.status != 'closed'
                ORDER BY p.timestamp ASC";
        $result = $dbconn->query($sql);
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);

    } elseif ($action === 'get_po_details' && isset($_GET['id'])) {
        // Ambil detail item dari sebuah PO
        $idpengadaan = $_GET['id'];
        $sql = "SELECT 
                    p.vendor_idvendor,
                    dp.idbarang,
                    b.nama as nama_barang,
                    dp.jumlah,
                    dp.harga_satuan
                FROM pengadaan p
                JOIN detail_pengadaan dp ON p.idpengadaan = dp.idpengadaan
                JOIN barang b ON dp.idbarang = b.idbarang
                WHERE p.idpengadaan = ?";
        $stmt = $dbconn->prepare($sql);
        $stmt->bind_param("i", $idpengadaan);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);

    } elseif ($action === 'get_vendors') {
        // Ambil daftar vendor yang aktif
        $result = $dbconn->query("SELECT idvendor, nama_vendor FROM vendor WHERE status = 1 ORDER BY nama_vendor");
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    } elseif ($action === 'get_penerimaan_details' && isset($_GET['id'])) {
        // Ambil detail penerimaan untuk form edit
        $idpenerimaan = $_GET['id'];
        $response = [];
        
        // 1. Ambil Header
        $stmt_header = $dbconn->prepare("SELECT p.idpenerimaan, p.idpengadaan, p.created_at, v.nama_vendor 
                                         FROM penerimaan p 
                                         JOIN pengadaan pg ON p.idpengadaan = pg.idpengadaan
                                         JOIN vendor v ON pg.vendor_idvendor = v.idvendor
                                         WHERE p.idpenerimaan = ?");
        $stmt_header->bind_param("i", $idpenerimaan);
        $stmt_header->execute();
        $response['header'] = $stmt_header->get_result()->fetch_assoc();

        // 2. Ambil Detail Items
        $stmt_detail = $dbconn->prepare("SELECT dp.barang_idbarang as idbarang, b.nama as nama_barang, dp.jumlah_terima as jumlah, dp.harga_satuan_terima as harga_satuan 
                                         FROM detail_penerimaan dp
                                         JOIN barang b ON dp.barang_idbarang = b.idbarang
                                         WHERE dp.idpenerimaan = ?");
        $stmt_detail->bind_param("i", $idpenerimaan);
        $stmt_detail->execute();
        $response['details'] = $stmt_detail->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['success' => true, 'data' => $response]);

     } elseif ($action === 'get_penerimaan') {
        // Ambil data penerimaan untuk ditampilkan di tabel
        $sql = "SELECT idpenerimaan, idpengadaan, iduser, status, created_at FROM penerimaan ORDER BY created_at DESC";
        $sql = "SELECT idpenerimaan, idpengadaan, iduser, status, created_at FROM penerimaan ORDER BY created_at ASC";
        $result = $dbconn->query($sql);
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);


    } elseif ($action === 'search_barang') {
        // Cari barang berdasarkan nama
        $term = $_GET['term'] ?? '';
        $stmt = $dbconn->prepare("SELECT idbarang, nama, harga FROM barang WHERE status = 1 AND nama LIKE ? LIMIT 10");
        $searchTerm = "%" . $term . "%";
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
    }
}

function handlePost($dbconn) {
    $input = json_decode(file_get_contents('php://input'), true);

    $idpengadaan = $input['idpengadaan'] ?? null; // Ambil idpengadaan dari payload
    $iduser = $_SESSION['user_id'];

    // Validasi utama sekarang adalah idpengadaan, karena SP bergantung padanya.
    if (empty($idpengadaan)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap: Anda harus memilih Pengadaan (PO) terlebih dahulu.']);
        return;
    }

    // Memulai transaksi
    $dbconn->begin_transaction();
    try {
        // 1. Buat header penerimaan terlebih dahulu
        $stmt_penerimaan = $dbconn->prepare("INSERT INTO penerimaan (idpengadaan, iduser, status, created_at) VALUES (?, ?, 'P', NOW())");
        $stmt_penerimaan->bind_param("ii", $idpengadaan, $iduser);
        $stmt_penerimaan->execute();
        $idpenerimaan_baru = $dbconn->insert_id;

        if (!$idpenerimaan_baru) {
            throw new Exception("Gagal membuat header transaksi penerimaan.");
        }

        // 2. Insert detail penerimaan. Trigger 'trg_stok_masuk_penerimaan' akan otomatis berjalan untuk setiap insert.
        $items = $input['items'] ?? [];
        $stmt_detail = $dbconn->prepare(
            "INSERT INTO detail_penerimaan (idpenerimaan, barang_idbarang, jumlah_terima, harga_satuan_terima, sub_total_terima) VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($items as $item) {
            if ($item['jumlah'] > 0) { // Hanya proses item yang diterima
                $subtotal = $item['jumlah'] * $item['harga'];
                $stmt_detail->bind_param("iiidd", $idpenerimaan_baru, $item['idbarang'], $item['jumlah'], $item['harga'], $subtotal);
                $stmt_detail->execute();
            }
        }

        // 3. Panggil SP untuk finalisasi status setelah semua detail dimasukkan
        $stmt_finalisasi = $dbconn->prepare("CALL finalisasi_status_penerimaan(?)");
        $stmt_finalisasi->bind_param("i", $idpenerimaan_baru);
        $stmt_finalisasi->execute();

        // Commit transaksi jika berhasil
        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => 'Transaksi penerimaan berhasil disimpan. Stok telah diperbarui.']);

    } catch (Exception $e) {
        // Rollback jika terjadi error
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat menyimpan: ' . $e->getMessage()]);
    }
}

function handlePut($dbconn) {
    $input = json_decode(file_get_contents('php://input'), true);

    $idpenerimaan = $input['idpenerimaan'] ?? null;
    $items = $input['items'] ?? [];

    if (empty($idpenerimaan) || empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap. ID Penerimaan dan item harus ada.']);
        return;
    }

    $dbconn->begin_transaction();
    try {
        // 1. Hapus detail penerimaan yang lama.
        // Pendekatan ini sederhana. Pendekatan yang lebih kompleks akan membandingkan item satu per satu.
        // PENTING: Ini mengasumsikan trigger/logika untuk MENGURANGI stok saat DELETE detail belum ada.
        // Jika ada, logika ini harus disesuaikan.
        $stmt_delete_detail = $dbconn->prepare("DELETE FROM detail_penerimaan WHERE idpenerimaan = ?");
        $stmt_delete_detail->bind_param("i", $idpenerimaan);
        $stmt_delete_detail->execute();

        // Hapus juga dari kartu stok yang terkait dengan penerimaan ini
        $stmt_delete_stok = $dbconn->prepare("DELETE FROM kartu_stok WHERE jenis_transaksi = 'M' AND id_transaksi = ?");
        $stmt_delete_stok->bind_param("i", $idpenerimaan);
        $stmt_delete_stok->execute();

        // 2. Insert detail penerimaan yang baru. Trigger 'trg_stok_masuk_penerimaan' akan berjalan lagi.
        $stmt_detail_insert = $dbconn->prepare(
            "INSERT INTO detail_penerimaan (idpenerimaan, barang_idbarang, jumlah_terima, harga_satuan_terima, sub_total_terima) VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($items as $item) {
            if ($item['jumlah'] > 0) {
                $subtotal = $item['jumlah'] * $item['harga'];
                $stmt_detail_insert->bind_param("iiidd", $idpenerimaan, $item['idbarang'], $item['jumlah'], $item['harga'], $subtotal);
                $stmt_detail_insert->execute();
            }
        }

        // 3. Panggil SP untuk finalisasi status (jika diperlukan)
        $stmt_finalisasi = $dbconn->prepare("CALL finalisasi_status_penerimaan(?)");
        $stmt_finalisasi->bind_param("i", $idpenerimaan);
        $stmt_finalisasi->execute();

        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => 'Penerimaan berhasil diperbarui.']);

    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui penerimaan: ' . $e->getMessage()]);
    }
}
?>