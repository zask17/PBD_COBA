<?php

require_once 'koneksi.php'; 
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');
checkAuth(true);

$method = $_SERVER['REQUEST_METHOD'];
$dbconn = $dbconn;

// Handler untuk GET dengan parameter khusus
if ($method == 'GET' && isset($_GET['action'])) {
    handleGet($dbconn);
    exit;
}

// Handler untuk POST, PUT, DELETE
switch ($method) {
    case 'POST':
        $input_data = json_decode(file_get_contents('php://input'), true);
        
        // Simulasi PUT via _method
        if (isset($input_data['_method']) && strtoupper($input_data['_method']) === 'PUT') {
            handleUpdate($dbconn, $input_data);
        } else {
            handleCreate($dbconn, $input_data);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode tidak didukung.']);
        break;
}

$dbconn->close();

// ============================================
// HANDLER GET - Ambil Data
// ============================================
function handleGet($dbconn) {
    $action = $_GET['action'] ?? null;
    
    // 1. GET OPEN PO - Ambil PO yang statusnya masih P atau S
    if ($action === 'get_open_pos') {
        try {
            $sql = "SELECT 
                        p.idpengadaan, 
                        p.timestamp, 
                        v.nama_vendor,
                        p.status
                    FROM pengadaan p
                    JOIN vendor v ON p.vendor_idvendor = v.idvendor
                    WHERE p.status IN ('P', 'S')
                    ORDER BY p.timestamp DESC";
            
            $result = $dbconn->query($sql);
            
            if ($result === FALSE) {
                throw new Exception('Query error: ' . $dbconn->error);
            }
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            echo json_encode([
                'success' => true, 
                'data' => $data,
                'count' => count($data)
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Gagal mengambil daftar PO: ' . $e->getMessage()
            ]);
        }
        return;
    }
    
    // 2. GET PO DETAILS - Ambil detail item dari PO yang dipilih
    if ($action === 'get_po_details' && isset($_GET['id'])) {
        try {
            $idpengadaan = (int)$_GET['id'];
            
            // penerimaan.php (GET - get_po_details) - TIDAK ADA PERUBAHAN DIBANDINGKAN KODE YANG SAYA KOREKSI SEBELUMNYA
            $sql = "SELECT 
                        dp.idbarang,
                        b.nama as nama_barang,
                        dp.jumlah as jumlah_dipesan,
                        dp.harga_satuan,
                        COALESCE((
                            SELECT SUM(dpr.jumlah_terima)
                            FROM detail_penerimaan dpr
                            JOIN penerimaan pr ON dpr.idpenerimaan = pr.idpenerimaan
                            WHERE pr.idpengadaan = ? AND dpr.barang_idbarang = dp.idbarang
                        ), 0) AS total_diterima
                    FROM detail_pengadaan dp
                    JOIN barang b ON dp.idbarang = b.idbarang
                    WHERE dp.idpengadaan = ?";
            
            $stmt = $dbconn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Prepare error: ' . $dbconn->error);
            }
            
            $stmt->bind_param("ii", $idpengadaan, $idpengadaan);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $sisa = $row['jumlah_dipesan'] - $row['total_diterima']; 
                if ($sisa > 0) {
                    $data[] = $row;
                }
            }
            
            echo json_encode([
                'success' => true, 
                'data' => $data,
                'count' => count($data)
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Gagal mengambil detail PO: ' . $e->getMessage()
            ]);
        }
        return;
    }
    
    // 3. GET PENERIMAAN LIST - Ambil daftar semua penerimaan
    if ($action === 'get_penerimaan') {
        try {
            $sql = "SELECT 
                        pr.idpenerimaan,
                        pr.idpengadaan,
                        pr.created_at,
                        u.username,
                        pr.status
                    FROM penerimaan pr
                    JOIN user u ON pr.iduser = u.iduser
                    ORDER BY pr.created_at DESC";
            
            $result = $dbconn->query($sql);
            
            if ($result === FALSE) {
                throw new Exception('Query error: ' . $dbconn->error);
            }
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            echo json_encode([
                'success' => true, 
                'data' => $data
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Gagal mengambil daftar penerimaan: ' . $e->getMessage()
            ]);
        }
        return;
    }
    
    // 4. GET PENERIMAAN DETAILS - Ambil detail penerimaan untuk edit
    if ($action === 'get_penerimaan_details' && isset($_GET['id'])) {
        try {
            $idpenerimaan = (int)$_GET['id'];
            
            // Ambil header
            $sql_header = "SELECT 
                            pr.idpenerimaan,
                            pr.idpengadaan,
                            DATE(pr.created_at) as created_at,
                            v.nama_vendor
                        FROM penerimaan pr
                        JOIN pengadaan p ON pr.idpengadaan = p.idpengadaan
                        JOIN vendor v ON p.vendor_idvendor = v.idvendor
                        WHERE pr.idpenerimaan = ?";
            
            $stmt_header = $dbconn->prepare($sql_header);
            if (!$stmt_header) {
                throw new Exception('Prepare header error: ' . $dbconn->error);
            }
            
            $stmt_header->bind_param("i", $idpenerimaan);
            $stmt_header->execute();
            $header = $stmt_header->get_result()->fetch_assoc();
            
            if (!$header) {
                http_response_code(404);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Penerimaan tidak ditemukan.'
                ]);
                return;
            }
            
            // Ambil details
            $sql_details = "SELECT 
                                dpr.barang_idbarang as idbarang,
                                b.nama as nama_barang,
                                dpr.jumlah_terima as jumlah,
                                dpr.harga_satuan_terima as harga_satuan
                            FROM detail_penerimaan dpr
                            JOIN barang b ON dpr.barang_idbarang = b.idbarang
                            WHERE dpr.idpenerimaan = ?";
            
            $stmt_details = $dbconn->prepare($sql_details);
            if (!$stmt_details) {
                throw new Exception('Prepare details error: ' . $dbconn->error);
            }
            
            $stmt_details->bind_param("i", $idpenerimaan);
            $stmt_details->execute();
            $result_details = $stmt_details->get_result();
            
            $details = [];
            while ($row = $result_details->fetch_assoc()) {
                $details[] = $row;
            }
            
            echo json_encode([
                'success' => true, 
                'data' => [
                    'header' => $header,
                    'details' => $details
                ]
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Gagal mengambil detail penerimaan: ' . $e->getMessage()
            ]);
        }
        return;
    }
    
    // Action tidak dikenali
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Action tidak valid.'
    ]);
}

// ============================================
// HANDLER CREATE - Buat Penerimaan Baru
// ============================================
function handleCreate($dbconn, $data) {
    $idpengadaan = (int)($data['idpengadaan'] ?? 0);
    $iduser = $_SESSION['user_id'] ?? null;
    $tanggal = $data['tanggal'] ?? date('Y-m-d H:i:s');
    $items = $data['items'] ?? [];
    
    // Validasi input
    if (empty($idpengadaan) || empty($iduser) || empty($items)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Data tidak lengkap. Pilih PO dan tambahkan minimal satu item.'
        ]);
        return;
    }
    
    $dbconn->begin_transaction();
    
    try {
        // 1. Insert header penerimaan
        $sql_header = "INSERT INTO penerimaan (idpengadaan, iduser, status, created_at) 
                       VALUES (?, ?, 'P', ?)";
        
        $stmt_header = $dbconn->prepare($sql_header);
        if (!$stmt_header) {
            throw new Exception('Prepare header error: ' . $dbconn->error);
        }
        
        $stmt_header->bind_param("iis", $idpengadaan, $iduser, $tanggal);
        $stmt_header->execute();
        
        $idpenerimaan_baru = $dbconn->insert_id;
        
        if (!$idpenerimaan_baru) {
            throw new Exception('Gagal membuat header penerimaan.');
        }
        
        // 2. Insert detail penerimaan
        $sql_detail = "INSERT INTO detail_penerimaan 
                       (idpenerimaan, barang_idbarang, jumlah_terima, harga_satuan_terima, sub_total_terima) 
                       VALUES (?, ?, ?, ?, ?)";
        
        $stmt_detail = $dbconn->prepare($sql_detail);
        if (!$stmt_detail) {
            throw new Exception('Prepare detail error: ' . $dbconn->error);
        }
        
        foreach ($items as $item) {
            if (($item['jumlah'] ?? 0) > 0) {
                $idbarang = (int)$item['idbarang'];
                $jumlah = (int)$item['jumlah'];
                $harga = (int)$item['harga'];
                $subtotal = $jumlah * $harga;
                
                $stmt_detail->bind_param(
                    "iiiii", 
                    $idpenerimaan_baru, 
                    $idbarang, 
                    $jumlah, 
                    $harga, 
                    $subtotal
                );
                $stmt_detail->execute();
            }
        }
        
        // 3. Panggil stored procedure untuk finalisasi
        $stmt_sp = $dbconn->prepare("CALL finalisasi_status_penerimaan(?)");
        if (!$stmt_sp) {
            throw new Exception('Prepare SP error: ' . $dbconn->error);
        }
        
        $stmt_sp->bind_param("i", $idpenerimaan_baru);
        $stmt_sp->execute();
        $stmt_sp->close();
        
        $dbconn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Penerimaan berhasil disimpan. Stok telah diperbarui.',
            'id' => $idpenerimaan_baru
        ]);
        
    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal menyimpan penerimaan: ' . $e->getMessage()
        ]);
    }
}

// ============================================
// HANDLER UPDATE - Update Penerimaan
// ============================================
function handleUpdate($dbconn, $data) {
    $idpenerimaan = (int)($data['idpenerimaan'] ?? 0);
    $tanggal = $data['tanggal'] ?? null;
    $items = $data['items'] ?? [];
    
    // Validasi input
    if (empty($idpenerimaan) || empty($tanggal) || empty($items)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Data tidak lengkap untuk update.'
        ]);
        return;
    }
    
    $dbconn->begin_transaction();
    
    try {
        // 1. Hapus kartu stok yang terkait (untuk reset stok)
        // SESUAI DDL: kolom adalah id_transaksi bukan idtransaksi
        $sql_delete_stok = "DELETE FROM kartu_stok 
                            WHERE jenis_transaksi = 'M' AND id_transaksi = ?";
        
        $stmt_delete_stok = $dbconn->prepare($sql_delete_stok);
        if (!$stmt_delete_stok) {
            throw new Exception('Prepare delete stok error: ' . $dbconn->error);
        }
        
        $stmt_delete_stok->bind_param("i", $idpenerimaan);
        $stmt_delete_stok->execute();
        
        // 2. Hapus detail penerimaan lama
        $sql_delete_detail = "DELETE FROM detail_penerimaan WHERE idpenerimaan = ?";
        
        $stmt_delete_detail = $dbconn->prepare($sql_delete_detail);
        if (!$stmt_delete_detail) {
            throw new Exception('Prepare delete detail error: ' . $dbconn->error);
        }
        
        $stmt_delete_detail->bind_param("i", $idpenerimaan);
        $stmt_delete_detail->execute();
        
        // 3. Update tanggal penerimaan
        $sql_update_header = "UPDATE penerimaan SET created_at = ? WHERE idpenerimaan = ?";
        
        $stmt_update_header = $dbconn->prepare($sql_update_header);
        if (!$stmt_update_header) {
            throw new Exception('Prepare update header error: ' . $dbconn->error);
        }
        
        $stmt_update_header->bind_param("si", $tanggal, $idpenerimaan);
        $stmt_update_header->execute();
        
        // 4. Insert detail penerimaan baru
        $sql_detail = "INSERT INTO detail_penerimaan 
                       (idpenerimaan, barang_idbarang, jumlah_terima, harga_satuan_terima, sub_total_terima) 
                       VALUES (?, ?, ?, ?, ?)";
        
        $stmt_detail = $dbconn->prepare($sql_detail);
        if (!$stmt_detail) {
            throw new Exception('Prepare detail error: ' . $dbconn->error);
        }
        
        foreach ($items as $item) {
            if (($item['jumlah'] ?? 0) > 0) {
                $idbarang = (int)$item['idbarang'];
                $jumlah = (int)$item['jumlah'];
                $harga = (int)$item['harga'];
                $subtotal = $jumlah * $harga;
                
                $stmt_detail->bind_param(
                    "iiiii", 
                    $idpenerimaan, 
                    $idbarang, 
                    $jumlah, 
                    $harga, 
                    $subtotal
                );
                $stmt_detail->execute();
            }
        }
        
        // 5. Panggil stored procedure untuk finalisasi
        $stmt_sp = $dbconn->prepare("CALL finalisasi_status_penerimaan(?)");
        if (!$stmt_sp) {
            throw new Exception('Prepare SP error: ' . $dbconn->error);
        }
        
        $stmt_sp->bind_param("i", $idpenerimaan);
        $stmt_sp->execute();
        $stmt_sp->close();
        
        $dbconn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Penerimaan berhasil diperbarui. Stok telah disesuaikan.'
        ]);
        
    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal memperbarui penerimaan: ' . $e->getMessage()
        ]);
    }
}
?>