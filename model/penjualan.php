<?php
require_once 'koneksi.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$iduser = $_SESSION['iduser'] ?? 1;

switch ($method) {
    case 'GET':  handleGet($dbconn); break;
    case 'POST': handlePost($dbconn, $iduser); break;
    case 'DELETE': handleDelete($dbconn, $iduser); break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method tidak didukung']);
        exit;
}

$dbconn->close();

/* ============================================================= */
/*                          GET HANDLER                          */
/* ============================================================= */

function handleGet($dbconn) {
    $action = $_GET['action'] ?? '';
    $id     = $_GET['id'] ?? null;

    // 1. Daftar Margin Aktif (Menggunakan VIEW V_MARGIN_AKTIF)
    if ($action === 'list_margins') {
        try {
            // VIEW V_MARGIN_AKTIF sudah memfilter status = 1
            $res = $dbconn->query("SELECT idmargin_penjualan, persen, DIBUAT AS created_at 
                                   FROM V_MARGIN_AKTIF 
                                   ORDER BY idmargin_penjualan DESC LIMIT 1");
            $margin = $res->fetch_assoc();
            
            $data = $margin ? [$margin] : [];
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        return;
    }

    // 2. Daftar Barang (Menggunakan VIEW V_BARANG_AKTIF + Function stok_terakhir)
    if ($action === 'list_barang') {
        $statusFilter = $_GET['status'] ?? 'aktif';
        try {
            // Menggunakan VIEW V_BARANG_AKTIF untuk memastikan hanya barang status 1
            // Menggunakan Function stok_terakhir() yang sudah ada di DDL Anda
            $sql = "SELECT 
                        `KODE BARANG` AS idbarang, 
                        `NAMA BARANG` AS nama, 
                        `HARGA POKOK` AS harga,
                        stok_terakhir(`KODE BARANG`) AS stok
                    FROM V_BARANG_AKTIF";
            
            $res = $dbconn->query($sql);
            $barangs = $res->fetch_all(MYSQLI_ASSOC);

            // Filter yang menampilkan yang ada stoknya
            $filtered = array_filter($barangs, function($b) use ($statusFilter) {
                return $b['stok'] > 0;
            });

            // Urutkan berdasarkan ID barang
            usort($filtered, fn($a,$b) => $a['idbarang'] - $b['idbarang']);

            echo json_encode(['success' => true, 'data' => array_values($filtered)]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        return;
    }

    // 3. Daftar Penjualan (Menggunakan VIEW V_MARGIN_SEMUA untuk info margin)
    if ($action === 'list_penjualan') {
        try {
            // JOIN penjualan dengan user dan VIEW V_MARGIN_SEMUA
            $sql = "SELECT p.idpenjualan, p.created_at, u.username, 
                           vm.PERSEN_MARGIN AS margin_persen, p.total_nilai
                    FROM penjualan p
                    JOIN user u ON p.iduser = u.iduser
                    JOIN V_MARGIN_SEMUA vm ON p.idmargin_penjualan = vm.idmargin_penjualan
                    ORDER BY p.idpenjualan DESC";
            
            $res = $dbconn->query($sql);
            $list = $res->fetch_all(MYSQLI_ASSOC);

            echo json_encode(['success' => true, 'data' => $list]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        return;
    }

    // 4. Detail Penjualan (Menggunakan VIEW V_BARANG_SEMUA untuk info barang & satuan)
    if ($action === 'detail_penjualan' && $id) {
        try {
            // Header Penjualan
            $stmt = $dbconn->prepare("SELECT p.idpenjualan, p.created_at, p.subtotal_nilai, 
                                             p.ppn, p.total_nilai, u.username AS kasir, 
                                             vm.PERSEN_MARGIN AS margin_persen
                                      FROM penjualan p
                                      JOIN user u ON p.iduser = u.iduser
                                      JOIN V_MARGIN_SEMUA vm ON p.idmargin_penjualan = vm.idmargin_penjualan
                                      WHERE p.idpenjualan = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $header = $stmt->get_result()->fetch_assoc();

            if (!$header) {
                http_response_code(404);
                echo json_encode(['success'=>false, 'message'=>'Transaksi tidak ditemukan']);
                return;
            }

            // Item Detail menggunakan VIEW V_BARANG_SEMUA
            $stmt2 = $dbconn->prepare("SELECT dp.jumlah, dp.harga_satuan, dp.subtotal,
                                               vb.`NAMA BARANG` AS nama_barang, vb.SATUAN AS satuan
                                        FROM detail_penjualan dp
                                        JOIN V_BARANG_SEMUA vb ON dp.idbarang = vb.`KODE BARANG`
                                        WHERE dp.penjualan_idpenjualan = ?");
            $stmt2->bind_param("i", $id);
            $stmt2->execute();
            $items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

            echo json_encode(['success'=>true, 'data'=>['header'=>$header, 'items'=>$items]]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
        }
        return;
    }
}

/* ============================================================= */
/*                         POST HANDLER                          */
/* ============================================================= */
function handlePost($dbconn, $iduser) {
    $input = json_decode(file_get_contents('php://input'), true);
    $tanggal = $input['tanggal'] ?? null;
    $items   = $input['items']   ?? [];

    if (empty($tanggal) || empty($items)) {
        http_response_code(400);
        echo json_encode(['success'=>false, 'message'=>'Tanggal dan item wajib diisi']);
        return;
    }

    $dbconn->begin_transaction();

    try {
        // Ambil margin aktif yang paling baru (ORDER + LIMIT di PHP)
        $res = $dbconn->query("SELECT idmargin_penjualan, persen, created_at 
                               FROM margin_penjualan 
                               WHERE status = 1");
        $margins = $res->fetch_all(MYSQLI_ASSOC);

        if (empty($margins)) {
            throw new Exception("Tidak ada margin penjualan yang aktif!");
        }

        usort($margins, fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        $margin = $margins[0];
        $idmargin = $margin['idmargin_penjualan'];

        // Hitung subtotal
        $subtotal_nilai = 0;
        foreach ($items as $i) $subtotal_nilai += $i['harga_jual'] * $i['jumlah'];
        $ppn = 0;
        $total_nilai = $subtotal_nilai + $ppn;

        // Insert header
        $stmt = $dbconn->prepare("INSERT INTO penjualan 
            (created_at, iduser, idmargin_penjualan, subtotal_nilai, ppn, total_nilai)
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siiddd", $tanggal, $iduser, $idmargin, $subtotal_nilai, $ppn, $total_nilai);
        $stmt->execute();
        $idpenjualan = $dbconn->insert_id;

        // Insert detail + kurangi stok
        $stmtDetail = $dbconn->prepare("INSERT INTO detail_penjualan 
            (penjualan_idpenjualan, idbarang, harga_satuan, jumlah, subtotal)
            VALUES (?, ?, ?, ?, ?)");
        
        // Ambil stok terbaru untuk setiap barang (ORDER BY di PHP, bukan di SP)
        $stokCache = [];
        foreach ($items as $i) {
            if (!isset($stokCache[$i['idbarang']])) {
                $stokRes = $dbconn->query("SELECT stok FROM kartu_stok WHERE idbarang = {$i['idbarang']} ORDER BY created_at DESC, idkartu_stok DESC LIMIT 1");
                $stokRow = $stokRes->fetch_assoc();
                $stokCache[$i['idbarang']] = $stokRow['stok'] ?? 0;
            }
        }
        
        $stmtStok = $dbconn->prepare("CALL sp_proses_penjualan_transaksi(?, ?, ?, ?)");

        foreach ($items as $i) {
            $subtotal = $i['harga_jual'] * $i['jumlah'];
            
            // Ambil stok terbaru dari cache atau query
            $stokSebelumnya = $stokCache[$i['idbarang']] ?? 0;

            // Kurangi stok (dengan stok sebelumnya sebagai parameter)
            $stmtStok->bind_param("iiii", $idpenjualan, $i['idbarang'], $i['jumlah'], $stokSebelumnya);
            $stmtStok->execute();

            // Insert detail
            $stmtDetail->bind_param("iiidd", $idpenjualan, $i['idbarang'], $i['harga_jual'], $i['jumlah'], $subtotal);
            $stmtDetail->execute();
        }

        $dbconn->commit();
        echo json_encode([
            'success' => true,
            'message' => "Transaksi berhasil! ID: TX-$idpenjualan (Margin {$margin['persen']}%)"
        ]);

    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success'=>false, 'message'=>'Gagal menyimpan: '.$e->getMessage()]);
    }
}

/* ============================================================= */
/*                        DELETE HANDLER                         */
/* ============================================================= */
function handleDelete($dbconn, $iduser) {
    $id = $_GET['id'] ?? null;
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['success'=>false, 'message'=>'ID tidak valid']);
        return;
    }

    $dbconn->begin_transaction();
    try {
        // Ambil detail untuk kembalikan stok
        $stmt = $dbconn->prepare("SELECT idbarang, jumlah FROM detail_penjualan WHERE penjualan_idpenjualan = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Ambil stok terbaru untuk setiap barang pembatalan (ORDER BY di PHP, bukan di SP)
        $stokCacheBatal = [];
        foreach ($details as $d) {
            if (!isset($stokCacheBatal[$d['idbarang']])) {
                $stokRes = $dbconn->query("SELECT stok FROM kartu_stok WHERE idbarang = {$d['idbarang']} ORDER BY created_at DESC, idkartu_stok DESC LIMIT 1");
                $stokRow = $stokRes->fetch_assoc();
                $stokCacheBatal[$d['idbarang']] = $stokRow['stok'] ?? 0;
            }
        }

        $stmtStok = $dbconn->prepare("CALL sp_proses_pembatalan_penjualan(?, ?, ?, ?)");
        foreach ($details as $d) {
            // Ambil stok terbaru dari cache
            $stokSebelumnya = $stokCacheBatal[$d['idbarang']] ?? 0;
            
            // Kembalikan stok (dengan stok sebelumnya sebagai parameter)
            $stmtStok->bind_param("iiii", $id, $d['idbarang'], $d['jumlah'], $stokSebelumnya);
            $stmtStok->execute();
        }

        // Hapus detail & header
        $dbconn->query("DELETE FROM detail_penjualan WHERE penjualan_idpenjualan = $id");
        $dbconn->query("DELETE FROM penjualan WHERE idpenjualan = $id");
        $dbconn->query("DELETE FROM kartu_stok WHERE idtransaksi = $id AND jenis_transaksi IN ('K','B')");

        $dbconn->commit();
        echo json_encode(['success'=>true, 'message'=>"Transaksi TX-$id berhasil dibatalkan"]);
    } catch (Exception $e) {
        $dbconn->rollback();
        http_response_code(500);
        echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
    }
}
?>