<?php

require_once 'koneksi.php'; // Pastikan koneksi.php ada dan benar
require_once 'auth.php';   // Pastikan auth.php ada dan benar

header('Content-Type: application/json; charset=utf-8');
// checkAuth(true); // Aktifkan jika Anda ingin membatasi akses

$method = $_SERVER['REQUEST_METHOD'];

// Mengambil iduser dari session setelah otentikasi
// Sesuaikan dengan variabel session Anda, misal $_SESSION['iduser']
$iduser = $_SESSION['iduser'] ?? 1; // Default ke user_id 1 jika session tidak ada/belum diset

switch ($method) {
    case 'GET':
        handleGet($dbconn);
        break;
    case 'POST':
        handlePost($dbconn, $iduser);
        break;
    case 'DELETE':
        handleDelete($dbconn, $iduser);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode tidak didukung']);
        break;
}

$dbconn->close();


// =================================================================================
// CRUD Handlers
// =================================================================================

function handleGet($dbconn) {
    $action = $_GET['action'] ?? null;
    $id = $_GET['id'] ?? null;

    if ($action === 'list_margins') {
        // Menggunakan V_MARGIN_AKTIF untuk memastikan hanya 1 margin aktif yang diambil
        try {
            // ORDER BY di backend
            $margin_result = $dbconn->query("SELECT idmargin_penjualan, persen FROM V_MARGIN_AKTIF LIMIT 1");
            $margins = $margin_result->fetch_all(MYSQLI_ASSOC);
            
            // Mengubah persen menjadi float untuk konsistensi data
            $margins = array_map(function($m) {
                $m['persen'] = (float)$m['persen'];
                return $m;
            }, $margins);
            
            echo json_encode(['success' => true, 'data' => $margins]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil margin aktif: ' . $e->getMessage()]);
        }
        return;
    }

    if ($action === 'list_barang') {
        // Menggunakan V_BARANG_AKTIF dan fungsi stok_terakhir()
        try {
            // Filter barang yang aktif dan stok > 0
            $barang_result = $dbconn->query(
                "SELECT b.idbarang, b.nama, stok_terakhir(b.idbarang) as stok, b.harga 
                 FROM barang b
                 WHERE b.status = 1 AND stok_terakhir(b.idbarang) > 0
                 ORDER BY b.nama ASC" // Order by di backend
            );
            $barangs = $barang_result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $barangs]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil daftar barang: ' . $e->getMessage()]);
        }
        return;
    }
    
    if ($action === 'list_penjualan') {
        // --- READ: Menampilkan Daftar Transaksi Penjualan ---
        try {
            // Menggunakan JOIN dan ORDER BY di backend
            $sql = "SELECT 
                        p.idpenjualan, 
                        p.created_at, 
                        u.username, 
                        mp.persen AS margin_persen,
                        p.total_nilai
                    FROM penjualan p
                    JOIN user u ON p.iduser = u.iduser
                    JOIN margin_penjualan mp ON p.idmargin_penjualan = mp.idmargin_penjualan
                    ORDER BY p.created_at DESC"; // Order by di backend
            $result = $dbconn->query($sql);
            $penjualan_list = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $penjualan_list]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil daftar penjualan: ' . $e->getMessage()]);
        }
        return;
    }

    if ($action === 'detail_penjualan' && $id) {
        // --- READ: Menampilkan Detail Transaksi Penjualan ---
        try {
            $sql_header = "SELECT 
                            p.idpenjualan, p.created_at, p.subtotal_nilai, p.ppn, p.total_nilai, 
                            u.username AS kasir, mp.persen AS margin_persen
                           FROM penjualan p
                           JOIN user u ON p.iduser = u.iduser
                           JOIN margin_penjualan mp ON p.idmargin_penjualan = mp.idmargin_penjualan
                           WHERE p.idpenjualan = ?";
            $stmt_header = $dbconn->prepare($sql_header);
            $stmt_header->bind_param("i", $id);
            $stmt_header->execute();
            $header = $stmt_header->get_result()->fetch_assoc();

            if (!$header) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan.']);
                return;
            }

            // Gunakan JOIN untuk menggabungkan data Detail Penjualan, Barang, dan Satuan
            $sql_detail = "SELECT 
                            dp.jumlah, dp.harga_satuan, dp.subtotal,
                            b.nama AS nama_barang, s.nama_satuan AS satuan
                           FROM detail_penjualan dp
                           JOIN barang b ON dp.idbarang = b.idbarang
                           JOIN satuan s ON b.idsatuan = s.idsatuan
                           WHERE dp.penjualan_idpenjualan = ?";
            $stmt_detail = $dbconn->prepare($sql_detail);
            $stmt_detail->bind_param("i", $id);
            $stmt_detail->execute();
            $items = $stmt_detail->get_result()->fetch_all(MYSQLI_ASSOC);

            echo json_encode(['success' => true, 'data' => ['header' => $header, 'items' => $items]]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil detail penjualan: ' . $e->getMessage()]);
        }
        return;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aksi tidak valid atau ID tidak diberikan.']);
}

function handlePost($dbconn, $iduser) {
    // --- CREATE: Menyimpan Transaksi Penjualan Baru ---
    $input = json_decode(file_get_contents('php://input'), true);

    $tanggal = $input['tanggal'] ?? null;
    $items = $input['items'] ?? [];

    $dbconn->begin_transaction();

    try {
        // 1. Ambil ID margin yang aktif langsung dari database
        $result_margin = $dbconn->query("SELECT idmargin_penjualan FROM margin_penjualan WHERE status = 1 LIMIT 1");
        if ($result_margin->num_rows === 0) {
            throw new Exception("Tidak ada margin penjualan yang aktif. Silakan atur satu margin aktif terlebih dahulu.");
        }
        $idmargin = $result_margin->fetch_assoc()['idmargin_penjualan'];

        // Validasi
        if (empty($tanggal) || empty($items)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap: Tanggal dan minimal satu barang harus diisi.']);
            $dbconn->rollback();
            return;
        }

        // Hitung total
        $subtotal_nilai = 0;
        foreach ($items as $item) {
            $subtotal_nilai += ($item['harga_jual'] * $item['jumlah']);
        }
        
        $ppn = 0; // Diasumsikan PPN 0, atau tambahkan logika PPN jika diperlukan
        $total_nilai = $subtotal_nilai + $ppn;

        // 2. Create the sales transaction header
        $stmt_penjualan = $dbconn->prepare("INSERT INTO penjualan (created_at, iduser, idmargin_penjualan, subtotal_nilai, ppn, total_nilai) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_penjualan->bind_param("siiddd", $tanggal, $iduser, $idmargin, $subtotal_nilai, $ppn, $total_nilai);
        $stmt_penjualan->execute();
        $idpenjualan = $dbconn->insert_id;

        // 3. Loop through each item and insert detail + update stock via SP
        $stmt_detail = $dbconn->prepare("INSERT INTO detail_penjualan (penjualan_idpenjualan, idbarang, harga_satuan, jumlah, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt_kartu_stok = $dbconn->prepare("CALL sp_proses_penjualan_transaksi(?, ?, ?)"); // SP mengurangi stok

        foreach ($items as $item) {
            $idbarang = $item['idbarang'];
            $jumlah = $item['jumlah'];
            $harga_jual = $item['harga_jual'];
            $subtotal = $jumlah * $harga_jual;

            // Panggil Stored Procedure untuk mengurangi stok
            $stmt_kartu_stok->bind_param("iii", $idpenjualan, $idbarang, $jumlah);
            $stmt_kartu_stok->execute();
            $stmt_kartu_stok->reset();

            // Insert Detail Penjualan
            $stmt_detail->bind_param("iiidd", $idpenjualan, $idbarang, $harga_jual, $jumlah, $subtotal);
            $stmt_detail->execute();
        }

        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => 'Transaksi penjualan berhasil disimpan dengan ID TX-' . $idpenjualan]);

    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan transaksi: ' . $e->getMessage()]);
    }
}

function handleDelete($dbconn, $iduser) {
    // --- DELETE: Membatalkan Transaksi Penjualan ---
    $idpenjualan = $_GET['id'] ?? null;

    if (!$idpenjualan || !is_numeric($idpenjualan)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Transaksi tidak valid.']);
        return;
    }

    $dbconn->begin_transaction();

    try {
        // 1. Ambil detail penjualan untuk mengembalikan stok
        $sql_details = "SELECT idbarang, jumlah FROM detail_penjualan WHERE penjualan_idpenjualan = ?";
        $stmt_details = $dbconn->prepare($sql_details);
        $stmt_details->bind_param("i", $idpenjualan);
        $stmt_details->execute();
        $details = $stmt_details->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($details)) {
             // Jika detail kosong, tetap hapus header dan lanjut
             $stmt_delete_header = $dbconn->prepare("DELETE FROM penjualan WHERE idpenjualan = ?");
             $stmt_delete_header->bind_param("i", $idpenjualan);
             $stmt_delete_header->execute();
             $dbconn->commit();
             echo json_encode(['success' => true, 'message' => "Transaksi TX-{$idpenjualan} berhasil dibatalkan (tanpa perubahan stok)."]);
             return;
        }

        // 2. Loop dan kembalikan stok menggunakan SP (reverse the sale)
        $stmt_kartu_stok = $dbconn->prepare("CALL sp_proses_pembatalan_penjualan(?, ?, ?)");

        foreach ($details as $detail) {
            $idbarang = $detail['idbarang'];
            $jumlah_kembali = $detail['jumlah'];

            $stmt_kartu_stok->bind_param("iii", $idpenjualan, $idbarang, $jumlah_kembali);
            $stmt_kartu_stok->execute();
            $stmt_kartu_stok->reset();
        }

        // 3. Hapus Detail Penjualan
        $sql_delete_detail = "DELETE FROM detail_penjualan WHERE penjualan_idpenjualan = ?";
        $stmt_delete_detail = $dbconn->prepare($sql_delete_detail);
        $stmt_delete_detail->bind_param("i", $idpenjualan);
        $stmt_delete_detail->execute();

        // 4. Hapus Header Penjualan
        $sql_delete_header = "DELETE FROM penjualan WHERE idpenjualan = ?";
        $stmt_delete_header = $dbconn->prepare($sql_delete_header);
        $stmt_delete_header->bind_param("i", $idpenjualan);
        $stmt_delete_header->execute();
        
        // 5. Hapus entri Kartu Stok terkait transaksi ini
        $sql_delete_kardus_keluar = "DELETE FROM kartu_stok WHERE idtransaksi = ? AND jenis_transaksi IN ('K', 'B')";
        $stmt_delete_kardus = $dbconn->prepare($sql_delete_kardus_keluar);
        $stmt_delete_kardus->bind_param("i", $idpenjualan);
        $stmt_delete_kardus->execute();

        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => "Transaksi TX-{$idpenjualan} berhasil dibatalkan dan stok telah dikembalikan."]);

    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal membatalkan transaksi: ' . $e->getMessage()]);
    }
}
?>