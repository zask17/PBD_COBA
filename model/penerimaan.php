<?php

require_once 'koneksi.php'; 
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');
checkAuth(true);

$method = $_SERVER['REQUEST_METHOD'];

global $dbconn;

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

// Tidak perlu $dbconn->close() di sini karena koneksi akan ditutup setelah skrip selesai

// ============================================
// HELPER FUNCTIONS - Emulasi VIEW V_PENGADAAN
// ============================================
function buildVPengadaanRow($dbconn, $p) {
    // Hitung total_dipesan menggunakan Subkueri Skalar
    $result_dipesan = $dbconn->query("
        SELECT COALESCE(SUM(dp.jumlah), 0) AS total_dipesan
        FROM detail_pengadaan dp
        WHERE dp.idpengadaan = {$p['idpengadaan']}
    ");
    $row_dipesan = $result_dipesan->fetch_assoc();
    $total_dipesan = $row_dipesan['total_dipesan'];

    // Hitung total_diterima menggunakan Subkueri Skalar
    $result_diterima = $dbconn->query("
        SELECT COALESCE(SUM(dpr.jumlah_terima), 0) AS total_diterima
        FROM penerimaan pr
        JOIN detail_penerimaan dpr ON pr.idpenerimaan = dpr.idpenerimaan
        WHERE pr.idpengadaan = {$p['idpengadaan']}
    ");
    $row_diterima = $result_diterima->fetch_assoc();
    $total_diterima = $row_diterima['total_diterima'];

    // Tentukan display_status
    $display_status = null;
    if ($p['status'] === 'c') {
        $display_status = 'Closed/Batal';
    } elseif ($total_diterima == 0) {
        $display_status = 'Dipesan';
    } elseif ($total_diterima >= $total_dipesan) {
        $display_status = 'Diterima Penuh';
    }

    // Tentukan parsial_status
    $parsial_status = null;
    if ($total_diterima > 0 && $total_diterima < $total_dipesan) {
        $parsial_status = 'Parsial';
    }

    // Tentukan final status yang ditampilkan
    $final_display_status = ($parsial_status !== null) ? $parsial_status : $display_status;

    return [
        'idpengadaan' => $p['idpengadaan'],
        'timestamp' => $p['timestamp'],
        'tanggal' => $p['timestamp'],
        'nama_vendor' => $p['nama_vendor'],
        'username' => $p['username'],
        'total_nilai' => $p['total_nilai'],
        'status' => $p['status'],
        'total_dipesan' => $total_dipesan,
        'total_diterima' => $total_diterima,
        'display_status' => $final_display_status,
        'status_teks' => $final_display_status,
        'parsial_status' => $parsial_status
    ];
}

// ============================================
// HANDLER GET - Ambil Data
// ============================================
function handleGet($dbconn) {
    $action = $_GET['action'] ?? null;
    
    // 1. GET OPEN PO - Ambil PO yang statusnya masih P (Dipesan) atau S (Sebagian)
    if ($action === 'get_open_pos') {
        try {
            $sql = "SELECT 
                        p.idpengadaan, 
                        p.timestamp,
                        p.total_nilai,
                        p.status,
                        v.nama_vendor, 
                        u.username
                    FROM pengadaan p
                    LEFT JOIN vendor v ON p.vendor_idvendor = v.idvendor
                    LEFT JOIN user u ON p.user_iduser = u.iduser
                    WHERE p.status IN ('p', 's')
                    ORDER BY p.timestamp DESC";
            
            $result = $dbconn->query($sql);
            
            if ($result === FALSE) {
                throw new Exception('Query error: ' . $dbconn->error);
            }
            
            $pengadaan_rows = $result->fetch_all(MYSQLI_ASSOC);
            
            $data = [];
            foreach ($pengadaan_rows as $row) {
                $data[] = buildVPengadaanRow($dbconn, $row);
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
    
    // 2. GET PO DETAILS - Ambil detail item dari PO yang dipilih (hanya item yang sisa > 0)
    if ($action === 'get_po_details' && isset($_GET['id'])) {
        try {
            $idpengadaan = (int)$_GET['id'];
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
                // Filter yang hanya ambil item yang masih ada SISA DITERIMA
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
                        p.status as po_status
                    FROM penerimaan pr
                    JOIN user u ON pr.iduser = u.iduser
                    JOIN pengadaan p ON pr.idpengadaan = p.idpengadaan
                    ORDER BY pr.idpenerimaan DESC";
            
            $result = $dbconn->query($sql);
            
            if ($result === FALSE) {
                throw new Exception('Query error: ' . $dbconn->error);
            }
            
            $data = $result->fetch_all(MYSQLI_ASSOC);
            
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
    // Gunakan $_SESSION['iduser'] sesuai DDL Anda
    $idpengadaan = (int)($data['idpengadaan'] ?? 0);
    $iduser = $_SESSION['iduser'] ?? null; 
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
        // Status penerimaan di-set 'P' (Proses)
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
        // TRIGGER akan berjalan otomatis di sini:
        // - Mengisi sub_total_terima
        // - Mengisi kartu_stok ('M')
        // - Mengecek dan memanggil SP sp_update_harga_jual_barang jika harga berbeda (set barang.status = 2)
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
                $subtotal = $jumlah * $harga; // Dihitung di PHP, tetapi akan ditimpa oleh trigger jika ada
                
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
        
        // 3. Panggil stored procedure untuk update status PO setelah penerimaan
        // Memastikan status PO terupdate menjadi 's' atau 'f'
        $stmt_sp = $dbconn->prepare("CALL sp_update_status_pengadaan_setelah_terima(?)");
        if (!$stmt_sp) {
            throw new Exception('Prepare SP update status error: ' . $dbconn->error);
        }
        
        $stmt_sp->bind_param("i", $idpengadaan);
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
        // 0. Ambil idpengadaan sebelum detail penerimaan dan stok dihapus
        $stmt_get_po_before_update = $dbconn->prepare("SELECT idpengadaan FROM penerimaan WHERE idpenerimaan = ?");
        $stmt_get_po_before_update->bind_param("i", $idpenerimaan);
        $stmt_get_po_before_update->execute();
        $idpengadaan_for_status = $stmt_get_po_before_update->get_result()->fetch_row()[0];
        $stmt_get_po_before_update->close();

        // 1. Hapus kartu stok yang terkait (untuk reset stok)
        $sql_delete_stok = "DELETE FROM kartu_stok 
                            WHERE jenis_transaksi = 'M' AND idtransaksi = ?";
        
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
        // TRIGGER akan berjalan otomatis di sini, MENGISI ULANG stok dan mengecek harga
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
        
        // 5. Panggil stored procedure untuk update status PO setelah update penerimaan
        $stmt_sp = $dbconn->prepare("CALL sp_update_status_pengadaan_setelah_terima(?)");
        if (!$stmt_sp) {
            throw new Exception('Prepare SP update status error: ' . $dbconn->error);
        }
        $stmt_sp->bind_param("i", $idpengadaan_for_status);
        $stmt_sp->execute();
        $stmt_sp->close();
        
        $dbconn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Penerimaan berhasil diperbarui. Stok dan status PO telah disesuaikan.'
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