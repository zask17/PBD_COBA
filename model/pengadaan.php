<?php

require_once 'koneksi.php';
require_once 'auth.php';

header('Content-Type: application/json');

checkAuth(true); // Protect the API endpoint

$method = $_SERVER['REQUEST_METHOD'];

// Ambil data Vendor dan Barang untuk dropdown di frontend
if ($method == 'GET' && isset($_GET['list_data'])) {
    try {
        // Ambil Vendor (menggunakan $dbconn dari dbconnect.php)
        $vendor_result = $dbconn->query("SELECT idvendor, nama_vendor FROM vendor WHERE status = '1'");
        $vendors = $vendor_result->fetch_all(MYSQLI_ASSOC);

        // Ambil Barang (menggunakan $dbconn dari dbconnect.php)
        $barang_result = $dbconn->query("SELECT idbarang, nama, harga FROM barang WHERE status = 1");
        $barangs = $barang_result->fetch_all(MYSQLI_ASSOC);

        // Ambil User (menggunakan $dbconn dari dbconnect.php)
        $user_result = $dbconn->query("SELECT iduser, username FROM user");
        $users = $user_result->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['success' => true, 'vendors' => $vendors, 'barangs' => $barangs, 'users' => $users]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil data master: ' . $e->getMessage()]);
    }
    exit;
}

// Logika untuk CREATE (Membuat Pengadaan Baru) atau READ (List Pengadaan)
switch ($method) {
    case 'POST':
        // Cek jika ada _method untuk simulasi
        $input_data = json_decode(file_get_contents('php://input'), true);
        if (isset($input_data['_method']) && strtoupper($input_data['_method']) === 'DELETE') {
            $method = 'DELETE';
        } elseif (isset($input_data['action']) && $input_data['action'] === 'finalize') {
            handleFinalize($dbconn, $input_data);
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);

        $idvendor = $data['idvendor'] ?? null;
        $iduser = $_SESSION['user_id']; // Ambil user ID dari session yang aktif
        $tanggal = $data['tanggal'] ?? null;
        $items = $data['items'] ?? [];

        if (empty($idvendor) || empty($tanggal) || empty($items)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap. Vendor, Tanggal, dan Barang harus diisi.']);
            exit;
        }

        $dbconn->begin_transaction();
        try {
            // 1. INSERT ke tabel PENGADAAN
            // Status diatur ke 'Dipesan' agar bisa diambil oleh modul Penerimaan.
            $sql_header = "INSERT INTO pengadaan (timestamp, user_iduser, status, vendor_idvendor, subtotal_nilai, ppn, total_nilai) 
                           VALUES (?, ?, 'Dipesan', ?, 0, 0, 0)";
            $stmt_header = $dbconn->prepare($sql_header);
            $stmt_header->bind_param("sii", $tanggal, $iduser, $idvendor);
            $stmt_header->execute();
            
            $idpengadaan_baru = $dbconn->insert_id;

            // 2. INSERT ke tabel DETAIL_PENGADAAN. Kolom 'sub_total' akan dihitung otomatis oleh trigger 'trg_hitung_subtotal'.
            $sql_detail = "INSERT INTO detail_pengadaan (idpengadaan, idbarang, harga_satuan, jumlah) VALUES (?, ?, ?, ?)";
            $stmt_detail = $dbconn->prepare($sql_detail);
            
            foreach ($items as $item) {
                $stmt_detail->bind_param("iiid", $idpengadaan_baru, $item['idbarang'], $item['harga'], $item['jumlah']);
                $stmt_detail->execute();
            }

            // 3. Panggil Stored Procedure untuk finalisasi total
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
            // Logic to get a single PO for editing, including finalization check
            try {
                // Fetch PO header
                $stmt_header = $dbconn->prepare("SELECT idpengadaan, timestamp as tanggal, vendor_idvendor as idvendor, user_iduser as iduser FROM pengadaan WHERE idpengadaan = ?");
                $stmt_header->bind_param("i", $id);
                $stmt_header->execute();
                $po = $stmt_header->get_result()->fetch_assoc();

                // Fetch PO details and received quantities
                $stmt_details = $dbconn->prepare("
                    SELECT 
                        dp.idbarang, b.nama as nama_barang, dp.jumlah, dp.harga_satuan, dp.subtotal,
                        COALESCE((SELECT SUM(dpr.jumlah_terima) FROM detail_penerimaan dpr JOIN penerimaan pr ON dpr.idpenerimaan = pr.idpenerimaan WHERE pr.idpengadaan = dp.idpengadaan AND dpr.barang_idbarang = dp.idbarang), 0) as total_diterima
                    FROM detail_pengadaan dp
                    JOIN barang b ON dp.idbarang = b.idbarang
                    WHERE dp.idpengadaan = ?
                ");
                $stmt_details->bind_param("i", $id);
                $stmt_details->execute();
                $po['details'] = $stmt_details->get_result()->fetch_all(MYSQLI_ASSOC);

                // Check if all items are fully received
                $is_finalizable = true;
                if (empty($po['details'])) $is_finalizable = false;
                foreach ($po['details'] as $item) {
                    if ($item['jumlah'] != $item['total_diterima']) {
                        $is_finalizable = false;
                        break;
                    }
                }
                $po['is_finalizable'] = $is_finalizable;

                echo json_encode(['success' => true, 'data' => $po]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal mengambil detail pengadaan: ' . $e->getMessage()]);
            }
        } else {
            // Logic to list all POs
            $sql = "SELECT p.idpengadaan, p.timestamp as tanggal, v.nama_vendor, u.username, p.total_nilai, p.status FROM pengadaan p JOIN vendor v ON p.vendor_idvendor = v.idvendor JOIN user u ON p.user_iduser = u.iduser ORDER BY p.timestamp DESC, p.idpengadaan DESC";
            $result = $dbconn->query($sql);
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $idpengadaan = $data['idpengadaan'] ?? null;

        if (!$idpengadaan) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID Pengadaan tidak valid.']);
            exit;
        }

        $dbconn->begin_transaction();
        try {
            // Hapus dulu dari tabel detail
            $stmt_detail = $dbconn->prepare("DELETE FROM detail_pengadaan WHERE idpengadaan = ?");
            $stmt_detail->bind_param("i", $idpengadaan);
            $stmt_detail->execute();

            // Hapus dari tabel header
            $stmt_header = $dbconn->prepare("DELETE FROM pengadaan WHERE idpengadaan = ?");
            $stmt_header->bind_param("i", $idpengadaan);
            $stmt_header->execute();

            $dbconn->commit();
            echo json_encode(['success' => true, 'message' => "Pengadaan PO-{$idpengadaan} berhasil dihapus."]);
        } catch (Exception $e) {
            $dbconn->rollback();
            http_response_code(500);
            echo json_encode(['success' => false, 'amessage' => 'Gagal menghapus pengadaan: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode tidak didukung.']);
        break;
}

function handleFinalize($dbconn, $data) {
    $idpengadaan = $data['idpengadaan'] ?? null;

    if (!$idpengadaan) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Pengadaan tidak valid.']);
        return;
    }

    try {
        $stmt = $dbconn->prepare("UPDATE pengadaan SET status = 'closed' WHERE idpengadaan = ?");
        $stmt->bind_param("i", $idpengadaan);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => "Pengadaan PO-{$idpengadaan} berhasil difinalisasi (ditutup)."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memfinalisasi pengadaan: ' . $e->getMessage()]);
    }
}