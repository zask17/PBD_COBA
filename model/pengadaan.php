<?php
// model/pengadaan.php
require_once 'koneksi.php'; 
require_once 'auth.php';

// Set header ke JSON dan lindungi endpoint
header('Content-Type: application/json');
checkAuth(true); // Memastikan hanya pengguna terautentikasi yang bisa mengakses

$method = $_SERVER['REQUEST_METHOD'];
$dbconn = $dbconn;

// Ambil data Vendor, Barang, dan User untuk dropdown di frontend (digunakan saat GET dengan parameter 'list_data')
if ($method == 'GET' && isset($_GET['list_data'])) {
    try {
        // 1. Ambil Vendor Aktif
        $vendor_result = $dbconn->query("SELECT idvendor, nama_vendor FROM vendor WHERE status = '1'");
        $vendors = $vendor_result->fetch_all(MYSQLI_ASSOC);

        // 2. Ambil Barang Aktif dan Harga Pokok
        $barang_result = $dbconn->query("SELECT idbarang, nama, harga FROM barang WHERE status = 1");
        $barangs = $barang_result->fetch_all(MYSQLI_ASSOC);

        // 3. Ambil User
        $user_result = $dbconn->query("SELECT iduser, username FROM user");
        $users = $user_result->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['success' => true, 'vendors' => $vendors, 'barangs' => $barangs, 'users' => $users]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil data master: ' . $e->getMessage()]);
    }
    exit;
}

// Logika untuk CREATE (POST) atau READ (GET) atau DELETE/PUT via POST
switch ($method) {
    case 'POST':
        $input_data = json_decode(file_get_contents('php://input'), true);

        // Cek jika ada _method untuk simulasi PUT/DELETE
        if (isset($input_data['_method']) && strtoupper($input_data['_method']) === 'DELETE') {
            handleDelete($dbconn, $input_data);
            return;
        } elseif (isset($input_data['_method']) && strtoupper($input_data['_method']) === 'PUT') {
            handleUpdate($dbconn, $input_data);
            return;
        } elseif (isset($input_data['action']) && $input_data['action'] === 'finalize') {
            handleFinalize($dbconn, $input_data);
            return;
        }

        // --- Logika CREATE PENGADAAN BARU ---
        $data = $input_data;
        $idvendor = $data['idvendor'] ?? null;
        $iduser = $_SESSION['iduser'] ?? null; 
        $tanggal = $data['tanggal'] ?? null;
        $items = $data['items'] ?? [];

        if (empty($idvendor) || empty($tanggal) || empty($items)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap. Vendor, Tanggal, dan Barang harus diisi.']);
            exit;
        }

        $dbconn->begin_transaction();
        try {
            // 1. INSERT ke tabel PENGADAAN (Inisialisasi total 0, Status 'P')
            $sql_header = "INSERT INTO pengadaan (timestamp, user_iduser, vendor_idvendor, status, subtotal_nilai, ppn, total_nilai) VALUES (?, ?, ?, 'P', ?, ?, ?)";
            $stmt_header = $dbconn->prepare($sql_header);
            $subtotal_nilai = 0;
            $ppn = 0;
            $total_nilai = 0;
            // Gunakan 's' untuk string (tanggal), 'i' untuk integer, 'd' untuk double/float
            $stmt_header->bind_param("siiddd", $tanggal, $iduser, $idvendor, $subtotal_nilai, $ppn, $total_nilai);
            $stmt_header->execute();
            
            $idpengadaan_baru = $dbconn->insert_id;

            // 2. INSERT ke tabel DETAIL_PENGADAAN
            $sql_detail = "INSERT INTO detail_pengadaan (idpengadaan, idbarang, harga_satuan, jumlah, sub_total) VALUES (?, ?, ?, ?, ?)";
            $stmt_detail = $dbconn->prepare($sql_detail);
            
            // Re-calculate subtotal for explicit insertion (walaupun ada trigger, kita isi lengkap)
            $subtotal_temp = 0;
            foreach ($items as $item) {
                $subtotal_item = $item['harga'] * $item['jumlah'];
                $subtotal_temp += $subtotal_item;
                $stmt_detail->bind_param("iiidi", $idpengadaan_baru, $item['idbarang'], $item['harga'], $item['jumlah'], $subtotal_item);
                $stmt_detail->execute();
            }

            // 3. Panggil Stored Procedure untuk finalisasi total (termasuk PPN)
            $stmt_sp = $dbconn->prepare("CALL sp_hitung_dan_finalisasi_pengadaan(?)"); 
            $stmt_sp->bind_param("i", $idpengadaan_baru);
            $stmt_sp->execute();
            
            $dbconn->commit();
            echo json_encode(['success' => true, 'message' => 'Pengadaan berhasil dibuat!', 'id' => $idpengadaan_baru]);

        } catch (Exception $e) {
            $dbconn->rollback();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal membuat Pengadaan: ' . $e->getMessage()]);
        }
        break;

    case 'GET':
        $id = $_GET['id'] ?? null;
        if ($id) {
            // --- Logika GET DETAIL PENGADAAN ---
            getDetailPengadaan($dbconn, $id);
        } else {
            // --- Logika GET LIST PENGADAAN ---
            getListPengadaan($dbconn);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode tidak didukung.']);
        break;
}

// --- FUNGSI HANDLER API ---

function getDetailPengadaan($dbconn, $id) {
    try {
        // 1. Fetch PO header
        $stmt_header = $dbconn->prepare("SELECT idpengadaan, DATE(timestamp) as tanggal, vendor_idvendor as idvendor, user_iduser as iduser, status FROM pengadaan WHERE idpengadaan = ?");
        $stmt_header->bind_param("i", $id);
        $stmt_header->execute();
        $po = $stmt_header->get_result()->fetch_assoc();

        if (!$po) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Pengadaan tidak ditemukan.']);
            return;
        }

        // 2. Fetch PO details and received quantities
        $stmt_details = $dbconn->prepare("
            SELECT 
                dp.idbarang, b.nama as nama_barang, dp.jumlah, dp.harga_satuan, dp.sub_total,
                COALESCE((SELECT SUM(dpr.jumlah_terima) FROM detail_penerimaan dpr JOIN penerimaan pr ON dpr.idpenerimaan = pr.idpenerimaan WHERE pr.idpengadaan = dp.idpengadaan AND dpr.barang_idbarang = dp.idbarang), 0) as total_diterima
            FROM detail_pengadaan dp
            JOIN barang b ON dp.idbarang = b.idbarang
            WHERE dp.idpengadaan = ?
        ");
        $stmt_details->bind_param("i", $id);
        $stmt_details->execute();
        $po['details'] = $stmt_details->get_result()->fetch_all(MYSQLI_ASSOC);

        // 3. Check finalization status (Is fully received?)
        $is_finalizable = ($po['status'] == 'P'); // Only editable if status is 'P' (Proses)
        $po['is_fully_received'] = true;
        
        foreach ($po['details'] as $item) {
            if ($item['jumlah'] > $item['total_diterima']) {
                $po['is_fully_received'] = false;
                break;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $po]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil detail pengadaan: ' . $e->getMessage()]);
    }
}

function getListPengadaan($dbconn) {
    try {
        $sql = "SELECT 
                    p.idpengadaan, p.timestamp as tanggal, v.nama_vendor, u.username, p.total_nilai, p.status as db_status,
                    COALESCE((SELECT SUM(dp.jumlah) FROM detail_pengadaan dp WHERE dp.idpengadaan = p.idpengadaan), 0) AS total_dipesan,
                    COALESCE((SELECT SUM(dpr.jumlah_terima) 
                              FROM detail_penerimaan dpr 
                              JOIN penerimaan pr ON dpr.idpenerimaan = pr.idpenerimaan 
                              WHERE pr.idpengadaan = p.idpengadaan), 0) AS total_diterima,
                    CASE
                        WHEN p.status = 'C' THEN 'C' -- Dibatalkan
                        WHEN COALESCE((SELECT SUM(dpr.jumlah_terima) FROM detail_penerimaan dpr JOIN penerimaan pr ON dpr.idpenerimaan = pr.idpenerimaan WHERE pr.idpengadaan = p.idpengadaan), 0) = COALESCE((SELECT SUM(dp.jumlah) FROM detail_pengadaan dp WHERE dp.idpengadaan = p.idpengadaan), 0) AND COALESCE((SELECT SUM(dp.jumlah) FROM detail_pengadaan dp WHERE dp.idpengadaan = p.idpengadaan), 0) > 0 THEN 'F' -- Diterima Penuh
                        WHEN COALESCE((SELECT SUM(dpr.jumlah_terima) FROM detail_penerimaan dpr JOIN penerimaan pr ON dpr.idpenerimaan = pr.idpenerimaan WHERE pr.idpengadaan = p.idpengadaan), 0) > 0 THEN 'S' -- Diterima Sebagian
                        ELSE 'P' -- Proses
                    END AS display_status
                FROM pengadaan p 
                JOIN vendor v ON p.vendor_idvendor = v.idvendor 
                JOIN user u ON p.user_iduser = u.iduser 
                ORDER BY p.timestamp DESC, p.idpengadaan DESC";
        $result = $dbconn->query($sql);
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil list pengadaan: ' . $e->getMessage()]);
    }
}



function handleUpdate($dbconn, $data) {
    // Logika UPDATE
    $idpengadaan = $data['idpengadaan'] ?? null;
    $idvendor = $data['idvendor'] ?? null;
    $tanggal = $data['tanggal'] ?? null;
    $items = $data['items'] ?? [];

    if (!$idpengadaan || empty($idvendor) || empty($tanggal) || empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap untuk update.']);
        exit;
    }

    $dbconn->begin_transaction();
    try {
        // 1. Cek apakah ada barang yang sudah diterima. Jika ada, tidak boleh update item.
        $check_received_sql = "SELECT COUNT(dp.idbarang) FROM detail_pengadaan dp
                               JOIN detail_penerimaan dpr ON dp.idpengadaan = ? AND dp.idbarang = dpr.barang_idbarang
                               WHERE dp.idpengadaan = ?";
        $stmt_check = $dbconn->prepare($check_received_sql);
        $stmt_check->bind_param("ii", $idpengadaan, $idpengadaan);
        $stmt_check->execute();
        $count_received = $stmt_check->get_result()->fetch_row()[0];

        if ($count_received > 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Pengadaan tidak dapat diubah karena sudah ada barang yang diterima.']);
            $dbconn->rollback();
            return;
        }

        // 2. Update Header Pengadaan
        $sql_header = "UPDATE pengadaan SET timestamp = ?, vendor_idvendor = ? WHERE idpengadaan = ?";
        $stmt_header = $dbconn->prepare($sql_header);
        $stmt_header->bind_param("sii", $tanggal, $idvendor, $idpengadaan);
        $stmt_header->execute();

        // 3. Hapus Detail Lama
        $stmt_delete_detail = $dbconn->prepare("DELETE FROM detail_pengadaan WHERE idpengadaan = ?");
        $stmt_delete_detail->bind_param("i", $idpengadaan);
        $stmt_delete_detail->execute();

        // 4. Insert Detail Baru
        $sql_detail = "INSERT INTO detail_pengadaan (idpengadaan, idbarang, harga_satuan, jumlah, sub_total) VALUES (?, ?, ?, ?, ?)";
        $stmt_detail = $dbconn->prepare($sql_detail);
        
        $subtotal_temp = 0;
        foreach ($items as $item) {
            $subtotal_item = $item['harga'] * $item['jumlah'];
            $subtotal_temp += $subtotal_item;
            $stmt_detail->bind_param("iiidi", $idpengadaan, $item['idbarang'], $item['harga'], $item['jumlah'], $subtotal_item);
            $stmt_detail->execute();
        }

        // 5. Panggil Stored Procedure untuk re-finalisasi total
        $stmt_sp = $dbconn->prepare("CALL sp_hitung_dan_finalisasi_pengadaan(?)"); 
        $stmt_sp->bind_param("i", $idpengadaan);
        $stmt_sp->execute();
        
        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => "Pengadaan PO-{$idpengadaan} berhasil diperbarui!"]);

    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui Pengadaan: ' . $e->getMessage()]);
    }
}

function handleDelete($dbconn, $data) {
    // Logika DELETE
    $idpengadaan = $data['idpengadaan'] ?? null;

    if (!$idpengadaan) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Pengadaan tidak valid.']);
        exit;
    }
    
    // Cek apakah sudah ada penerimaan
    $check_received_sql = "SELECT COUNT(*) FROM penerimaan WHERE idpengadaan = ?";
    $stmt_check = $dbconn->prepare($check_received_sql);
    $stmt_check->bind_param("i", $idpengadaan);
    $stmt_check->execute();
    $count_received = $stmt_check->get_result()->fetch_row()[0];

    if ($count_received > 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Pengadaan tidak dapat dihapus karena sudah ada penerimaan yang tercatat.']);
        return;
    }

    $dbconn->begin_transaction();
    try {
        // Hapus detail akan otomatis menghapus detail_pengadaan jika FK set ON DELETE CASCADE
        $stmt_header = $dbconn->prepare("DELETE FROM pengadaan WHERE idpengadaan = ?");
        $stmt_header->bind_param("i", $idpengadaan);
        $stmt_header->execute();

        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => "Pengadaan PO-{$idpengadaan} berhasil dihapus."]);
    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus pengadaan: ' . $e->getMessage()]);
    }
}

function handleFinalize($dbconn, $data) {
    // Finalisasi = Status Cancel 'C' atau 'F' (Finished)
    $idpengadaan = $data['idpengadaan'] ?? null;

    if (!$idpengadaan) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Pengadaan tidak valid.']);
        return;
    }

    try {
        // Asumsi "finalize" di sini berarti menutup PO menjadi "C" (Cancel/Closed)
        $stmt = $dbconn->prepare("UPDATE pengadaan SET status = 'C' WHERE idpengadaan = ?");
        $stmt->bind_param("i", $idpengadaan);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => "Pengadaan PO-{$idpengadaan} berhasil difinalisasi (ditutup)."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memfinalisasi pengadaan: ' . $e->getMessage()]);
    }
}
?>