<?php

require_once 'koneksi.php'; // Diubah: Menggunakan koneksi.php dari folder model/
require_once 'auth.php';   // Diubah: Menggunakan auth.php dari folder model/

header('Content-Type: application/json; charset=utf-8');
checkAuth(true); 

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($dbconn);
        break;
    case 'POST':
        handlePost($dbconn);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode tidak didukung']);
        break;
}

$dbconn->close();

// c:\laragon\www\proyek_pbd\models\penjualan.php

// ...
function handleGet($dbconn) {
    $action = $_GET['action'] ?? null;
    
    if ($action === 'list_margins') {
        try {
            // Ambil hanya margin yang aktif (status = 1). Diasumsikan hanya ada satu.
            $margin_result = $dbconn->query("SELECT idmargin_penjualan, persen FROM margin_penjualan WHERE status = 1 LIMIT 1");
            // FIX: Always return an array to be compatible with frontend's .map() function.
            $margin = $margin_result->fetch_all(MYSQLI_ASSOC); // Fetch as an array
            echo json_encode(['success' => true, 'data' => $margin]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil margin aktif: ' . $e->getMessage()]);
        }
        return;
    }

    if ($action === 'list_barang') {
        try {
            // Modifikasi: Ambil harga dasar (harga pokok) dari tabel barang.
            // Harga jual akan dihitung di frontend.
            $barang_result = $dbconn->query(
                "SELECT idbarang, nama, stok_terakhir(idbarang) as stok, harga 
                 FROM barang
                 WHERE status = 1 AND stok_terakhir(idbarang) > 0"
            );
            $barangs = $barang_result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $barangs]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil daftar barang: ' . $e->getMessage()]);
        }
        return; // Hentikan eksekusi setelah ini
    }
    if ($action === 'list_penjualan') {
        try {
            // Query untuk mengambil daftar penjualan beserta detailnya
            $sql = "SELECT 
                        p.idpenjualan, 
                        p.created_at, 
                        u.username, 
                        mp.persen AS margin_persen,
                        (SELECT SUM(dp.subtotal) FROM detail_penjualan dp WHERE dp.penjualan_idpenjualan = p.idpenjualan) AS total_nilai
                    FROM penjualan p
                    JOIN user u ON p.iduser = u.iduser
                    JOIN margin_penjualan mp ON p.idmargin_penjualan = mp.idmargin_penjualan
                    ORDER BY p.created_at DESC";
            $result = $dbconn->query($sql);
            $penjualan_list = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $penjualan_list]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil daftar penjualan: ' . $e->getMessage()]);
        }
        return;
    }
    else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
    }
}

function handlePost($dbconn) {
    $input = json_decode(file_get_contents('php://input'), true);

    $tanggal = $input['tanggal'] ?? null;
    $items = $input['items'] ?? [];
    $iduser = $_SESSION['user_id'] ?? null;

    // Memulai transaksi database
    $dbconn->begin_transaction();

    try {
        // 1. Ambil ID margin yang aktif langsung dari database
        $result_margin = $dbconn->query("SELECT idmargin_penjualan FROM margin_penjualan WHERE status = 1 LIMIT 1");
        if ($result_margin->num_rows === 0) {
            throw new Exception("Tidak ada margin penjualan yang aktif. Silakan atur satu margin aktif terlebih dahulu.");
        }
        $idmargin = $result_margin->fetch_assoc()['idmargin_penjualan'];

        // Validasi input setelah mendapatkan margin
        if (empty($tanggal) || empty($items)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap: Tanggal dan minimal satu barang harus diisi.']);
            $dbconn->rollback(); // Batalkan transaksi sebelum keluar
            return;
        }

        // Hitung total sebelum memasukkan header
        $subtotal_nilai = 0;
        foreach ($items as $item) {
            $subtotal_nilai += ($item['harga_jual'] * $item['jumlah']);
        }
        
        // Assuming PPN is 0 for now as it's not in the form.
        $ppn = 0; 
        $total_nilai = $subtotal_nilai + $ppn;

        // 2. Create the sales transaction header, including the active margin ID
        // FIX: Add subtotal_nilai, ppn, and total_nilai to the insert query
        $stmt_penjualan = $dbconn->prepare("INSERT INTO penjualan (created_at, iduser, idmargin_penjualan, subtotal_nilai, ppn, total_nilai) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_penjualan->bind_param("siiddd", $tanggal, $iduser, $idmargin, $subtotal_nilai, $ppn, $total_nilai);
        $stmt_penjualan->execute();
        $idpenjualan = $dbconn->insert_id;

        // 3. Loop through each item and insert it into the detail_penjualan table
        // FIX: Reverting column name from 'jumlah_terima' back to 'jumlah' as per the new error.
        $stmt_detail = $dbconn->prepare("INSERT INTO detail_penjualan (penjualan_idpenjualan, idbarang, harga_satuan, jumlah, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt_kartu_stok = $dbconn->prepare("CALL proses_penjualan_transaksi(?, ?, ?)");

        foreach ($items as $item) {
            $idbarang = $item['idbarang'];
            $jumlah = $item['jumlah'];
            $harga_jual = $item['harga_jual'];
            $subtotal = $jumlah * $harga_jual;

            // Call the Stored Procedure to reduce stock and record it in the stock card.
            // SP: proses_penjualan_transaksi(IN p_idpenjualan INT, IN p_idbarang INT, IN p_jumlah INT)
            $stmt_kartu_stok->bind_param("iii", $idpenjualan, $idbarang, $jumlah);
            $stmt_kartu_stok->execute();

            $stmt_detail->bind_param("iiidd", $idpenjualan, $idbarang, $harga_jual, $jumlah, $subtotal);
            $stmt_detail->execute();
        }

        // Commit the transaction if successful
        $dbconn->commit();
        echo json_encode(['success' => true, 'message' => 'Transaksi penjualan berhasil disimpan.']);

    } catch (Exception $e) {
        // Rollback jika terjadi error
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan transaksi: ' . $e->getMessage()]);
    }
}
?>
