<?php
error_reporting(0);
session_start();


require_once 'koneksi.php';
require_once 'auth.php';

header('Content-Type: application/json');
checkAuth(true); // Protect the API endpoint

// --- Main Logic ---
$method = $_SERVER['REQUEST_METHOD'];
global $dbconn;

// Router to handle different actions
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;

    if ($id) { // Get specific record detail
        getPengadaanById($id);
    } elseif (isset($_GET['list_data'])) { // Get master data for dropdowns
        getMasterData();
    } else { // Default GET action is to list all records
        getAllPengadaan();
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? null;
    $_method = $data['_method'] ?? 'POST';

    if ($action === 'finalize') {
        handleFinalize($dbconn, $data);
    } elseif ($_method === 'DELETE') {
        deletePengadaan($dbconn, $data);
    } elseif ($_method === 'PUT') {
        updatePengadaan($dbconn, $data);
    } elseif ($_method === 'POST') {
        createPengadaan($dbconn, $data);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode tidak didukung.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak didukung.']);
}

// --- CORE FUNCTIONS ---

function getMasterData() {
    global $dbconn;
    try {
        // Ambil Vendor Aktif
        $vendor_result = $dbconn->query("SELECT idvendor, nama_vendor FROM vendor WHERE status = 'A'");
        $vendors = $vendor_result->fetch_all(MYSQLI_ASSOC);

        // Ambil Barang Aktif (TANPA JOIN ke barang_vendor)
        $barang_sql = "SELECT b.idbarang, b.nama, b.harga FROM barang b WHERE b.status = 1";
        $barang_result = $dbconn->query($barang_sql);
        $barangs = $barang_result->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['success' => true, 'vendors' => $vendors, 'barangs' => $barangs]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil data master: ' . $e->getMessage()]);
    }
}

function getAllPengadaan() {
    global $dbconn;
    // Menggunakan VIEW V_PENGADAAN
    $sql = "SELECT 
                p.idpengadaan, 
                p.tanggal, 
                p.nama_vendor, 
                p.username, 
                p.total_nilai,
                p.total_dipesan,
                p.total_diterima,
                CASE
                    WHEN p.parsial_status IS NOT NULL THEN 'Parsial'
                    ELSE p.display_status
                END AS display_status
            FROM V_PENGADAAN p
            ORDER BY p.tanggal DESC, p.idpengadaan DESC";
            
    $result = $dbconn->query($sql);
    $data = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $data]);
}

function getPengadaanById($id) {
    global $dbconn;
    try {
        // 1. Ambil Header PO
        $stmt_header = $dbconn->prepare("
                    SELECT p.idpengadaan, p.timestamp as tanggal, p.vendor_idvendor, v.nama_vendor, p.user_iduser, u.username, p.status
                    FROM pengadaan p
                    LEFT JOIN vendor v ON p.vendor_idvendor = v.idvendor
                    LEFT JOIN user u ON p.user_iduser = u.iduser
                    WHERE p.idpengadaan = ?
        ");
        $stmt_header->bind_param("i", $id);
        $stmt_header->execute();
        $po = $stmt_header->get_result()->fetch_assoc();

        if (!$po) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => "Pengadaan dengan ID {$id} tidak ditemukan."]);
            return;
        }

        // 2. Ambil Detail PO dan Jumlah Diterima
        $stmt_details = $dbconn->prepare("
            SELECT 
                dp.idbarang,
                b.nama as nama_barang,
                dp.jumlah,
                dp.harga_satuan,
                (dp.jumlah * dp.harga_satuan) AS sub_total, /* PERBAIKAN: Hitung sub_total langsung dari jumlah * harga_satuan */
                COALESCE((SELECT SUM(dpr.jumlah_terima) 
                          FROM detail_penerimaan dpr 
                          JOIN penerimaan pr ON dpr.idpenerimaan = pr.idpenerimaan 
                          WHERE pr.idpengadaan = dp.idpengadaan AND dpr.barang_idbarang = dp.idbarang), 0) as total_diterima
            FROM detail_pengadaan dp
            JOIN barang b ON dp.idbarang = b.idbarang
            WHERE dp.idpengadaan = ?
        ");
        $stmt_details->bind_param("i", $id);
        $stmt_details->execute();
        $po['details'] = $stmt_details->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // 3. Tentukan apakah bisa diubah/dihapus (hanya jika status 'p' dan belum ada penerimaan)
        $po['has_receipts'] = array_sum(array_column($po['details'], 'total_diterima')) > 0;
        $po['can_be_modified'] = ($po['status'] == 'p' && !$po['has_receipts']);
        
        echo json_encode(['success' => true, 'data' => $po]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil detail pengadaan: ' . $e->getMessage()]);
    }
}

function createPengadaan($dbconn, $data) {
    // Validasi Vendor Tunggal
    $idvendor = $data['idvendor'] ?? null;
    $iduser = $data['iduser'] ?? $_SESSION['iduser'] ?? null;
    $tanggal = $data['tanggal'] ?? null;
    $items = $data['items'] ?? [];

    if (empty($iduser) || empty($idvendor) || empty($tanggal) || empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap. User, Vendor, Tanggal, dan Barang harus diisi.']);
        return;
    }

    $dbconn->begin_transaction();
    try {
        // 1. INSERT Header Pengadaan (Status 'p' = Proses)
        $sql_header = "INSERT INTO pengadaan (timestamp, user_iduser, vendor_idvendor, status, subtotal_nilai, ppn, total_nilai) VALUES (?, ?, ?, 'p', ?, ?, ?)";
        $stmt_header = $dbconn->prepare($sql_header);
        $subtotal_nilai = 0; $ppn = 0; $total_nilai = 0;
        $stmt_header->bind_param("siiddd", $tanggal, $iduser, $idvendor, $subtotal_nilai, $ppn, $total_nilai);
        $stmt_header->execute();
        $idpengadaan_baru = $dbconn->insert_id;

        // 2. INSERT Detail Pengadaan
        // Catatan: Kolom sub_total di detail_pengadaan akan terisi otomatis oleh TRIGGER di database.
        $sql_detail = "INSERT INTO detail_pengadaan (idpengadaan, idbarang, harga_satuan, jumlah) VALUES (?, ?, ?, ?)";
        $stmt_detail = $dbconn->prepare($sql_detail);
        foreach ($items as $item) {
            $stmt_detail->bind_param("iidi", $idpengadaan_baru, $item['idbarang'], $item['harga'], $item['jumlah']);
            $stmt_detail->execute();
        }

        // 3. Panggil SP untuk Hitung dan Finalisasi Total (sp_hitung_dan_finalisasi_pengadaan)
        $stmt_sp = $dbconn->prepare("CALL sp_hitung_dan_finalisasi_pengadaan(?)"); 
        $stmt_sp->bind_param("i", $idpengadaan_baru);
        $stmt_sp->execute();
        
        // Update status kembali ke 'p' setelah perhitungan total (jika SP mengeset ke 'F')
        $dbconn->query("UPDATE pengadaan SET status = 'p' WHERE idpengadaan = $idpengadaan_baru");

        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => 'Pengadaan berhasil dibuat!', 'id' => $idpengadaan_baru]);
    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal membuat Pengadaan: ' . $e->getMessage()]);
    }
}

function updatePengadaan($dbconn, $data) {
    // Validasi Vendor Tunggal ASUMSI dilakukan di Frontend
    $idpengadaan = $data['idpengadaan'] ?? null;
    $idvendor = $data['idvendor'] ?? null;
    $tanggal = $data['tanggal'] ?? null;
    $items = $data['items'] ?? [];

    if (!$idpengadaan || empty($idvendor) || empty($tanggal) || empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap untuk update.']);
        return;
    }

    $dbconn->begin_transaction();
    try {
        // 1. Cek apakah ada penerimaan yang sudah tercatat
        $stmt_check = $dbconn->prepare("SELECT COUNT(*) FROM penerimaan WHERE idpengadaan = ?");
        $stmt_check->bind_param("i", $idpengadaan);
        $stmt_check->execute();
        if ($stmt_check->get_result()->fetch_row()[0] > 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Pengadaan tidak dapat diubah karena sudah ada penerimaan yang tercatat.']);
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
        $sql_detail = "INSERT INTO detail_pengadaan (idpengadaan, idbarang, harga_satuan, jumlah) VALUES (?, ?, ?, ?)";
        $stmt_detail = $dbconn->prepare($sql_detail);
        foreach ($items as $item) {
            $stmt_detail->bind_param("iidi", $idpengadaan, $item['idbarang'], $item['harga'], $item['jumlah']);
            $stmt_detail->execute();
        }
        
        // 5. Panggil SP untuk Re-Hitung dan Finalisasi Total
        $stmt_sp = $dbconn->prepare("CALL sp_hitung_dan_finalisasi_pengadaan(?)"); 
        $stmt_sp->bind_param("i", $idpengadaan);
        $stmt_sp->execute();

        // Update status kembali ke 'p' setelah perhitungan total (jika SP mengeset ke 'F')
        $dbconn->query("UPDATE pengadaan SET status = 'p' WHERE idpengadaan = $idpengadaan");
        
        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => "Pengadaan PO-{$idpengadaan} berhasil diperbarui!"]);

    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui Pengadaan: ' . $e->getMessage()]);
    }
}


function deletePengadaan($dbconn, $data) {
    $idpengadaan = $data['idpengadaan'] ?? null;

    if (!$idpengadaan) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Pengadaan tidak valid.']);
        return;
    }
    
    // Cek apakah sudah ada penerimaan
    $stmt_check = $dbconn->prepare("SELECT COUNT(*) FROM penerimaan WHERE idpengadaan = ?");
    $stmt_check->bind_param("i", $idpengadaan);
    $stmt_check->execute();
    if ($stmt_check->get_result()->fetch_row()[0] > 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Pengadaan tidak dapat dihapus karena sudah ada penerimaan yang tercatat.']);
        return;
    }

    $dbconn->begin_transaction();
    try {
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
    // Finalisasi/Tutup PO = Status 'C' (Closed/Cancelled)
    $idpengadaan = $data['idpengadaan'] ?? null;

    if (!$idpengadaan) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Pengadaan tidak valid.']);
        return;
    }

    try {
        $stmt_check = $dbconn->prepare("SELECT status FROM pengadaan WHERE idpengadaan = ?");
        $stmt_check->bind_param("i", $idpengadaan);
        $stmt_check->execute();
        $current_status = $stmt_check->get_result()->fetch_row()[0];

        if ($current_status != 'p') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Pengadaan PO-{$idpengadaan} tidak dapat ditutup. Status saat ini: {$current_status}"]);
            return;
        }

        // Update status ke 'c' (Closed/Cancel)
        $stmt = $dbconn->prepare("UPDATE pengadaan SET status = 'c' WHERE idpengadaan = ?");
        $stmt->bind_param("i", $idpengadaan);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => "Pengadaan PO-{$idpengadaan} berhasil difinalisasi (ditutup)."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memfinalisasi pengadaan: ' . $e->getMessage()]);
    }
}
?>