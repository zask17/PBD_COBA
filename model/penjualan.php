<?php
require_once 'koneksi.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$iduser = $_SESSION['iduser'] ?? 1; // sesuaikan dengan session login kamu

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

    // 1. Daftar Margin Aktif (hanya 1 yang paling baru)
    if ($action === 'list_margins') {
        try {
            $res = $dbconn->query("SELECT idmargin_penjualan, persen, created_at 
                                   FROM margin_penjualan 
                                   WHERE status = 1");
            $margins = $res->fetch_all(MYSQLI_ASSOC);

            // ORDER BY created_at DESC di PHP
            usort($margins, fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']));
            $latest = !empty($margins) ? [$margins[0]] : [];

            echo json_encode(['success' => true, 'data' => $latest]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        return;
    }

    // 2. Daftar Barang (dengan stok terbaru dihitung di PHP)
    if ($action === 'list_barang') {
        $statusFilter = $_GET['status'] ?? 'aktif';
        try {
            // Ambil semua barang
            $sql = "SELECT b.idbarang, b.nama, b.harga, b.status 
                    FROM barang b 
                    JOIN satuan s ON b.idsatuan = s.idsatuan";
            $res = $dbconn->query($sql);
            $barangs = $res->fetch_all(MYSQLI_ASSOC);

            // Hitung stok terbaru untuk setiap barang (di PHP)
            foreach ($barangs as &$b) {
                $stokRes = $dbconn->query("SELECT stok, created_at, idkartu_stok 
                                          FROM kartu_stok 
                                          WHERE idbarang = {$b['idbarang']} 
                                          ORDER BY created_at DESC, idkartu_stok DESC 
                                          LIMIT 1");
                $row = $stokRes->fetch_assoc();
                $b['stok'] = $row['stok'] ?? 0;
            }
            unset($b);

            // Filter sesuai permintaan
            $filtered = array_filter($barangs, function($b) use ($statusFilter) {
                $aktif = $b['status'] == 1;
                $adaStok = $b['stok'] > 0;
                if ($statusFilter === 'aktif') return $aktif && $adaStok;
                if ($statusFilter === 'semua') return $adaStok;
                return false;
            });

            // Urutkan berdasarkan nama (di PHP)
            usort($filtered, fn($a,$b) => strcmp($a['nama'], $b['nama']));

            echo json_encode(['success' => true, 'data' => array_values($filtered)]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        return;
    }

    // 3. Daftar Penjualan (diurutkan di PHP)
    if ($action === 'list_penjualan') {
        try {
            $sql = "SELECT p.idpenjualan, p.created_at, u.username, 
                           mp.persen AS margin_persen, p.total_nilai
                    FROM penjualan p
                    JOIN user u ON p.iduser = u.iduser
                    JOIN margin_penjualan mp ON p.idmargin_penjualan = mp.idmargin_penjualan";
            $res = $dbconn->query($sql);
            $list = $res->fetch_all(MYSQLI_ASSOC);

            // Urutkan DESC idpenjualan di PHP
            usort($list, fn($a,$b) => $b['idpenjualan'] - $a['idpenjualan']);

            echo json_encode(['success' => true, 'data' => $list]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        return;
    }

    // 4. Detail Penjualan
    if ($action === 'detail_penjualan' && $id) {
        // (kode detail tetap sama seperti sebelumnya, sudah aman)
        // ... (saya sisipkan kode lengkap di bawah agar 100% copy-paste)
        try {
            $stmt = $dbconn->prepare("SELECT p.idpenjualan, p.created_at, p.subtotal_nilai, 
                                             p.ppn, p.total_nilai, u.username AS kasir, 
                                             mp.persen AS margin_persen
                                      FROM penjualan p
                                      JOIN user u ON p.iduser = u.iduser
                                      JOIN margin_penjualan mp ON p.idmargin_penjualan = mp.idmargin_penjualan
                                      WHERE p.idpenjualan = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $header = $stmt->get_result()->fetch_assoc();

            if (!$header) {
                http_response_code(404);
                echo json_encode(['success'=>false, 'message'=>'Transaksi tidak ditemukan']);
                return;
            }

            $stmt2 = $dbconn->prepare("SELECT dp.jumlah, dp.harga_satuan, dp.subtotal,
                                              b.nama AS nama_barang, s.nama_satuan AS satuan
                                       FROM detail_penjualan dp
                                       JOIN barang b ON dp.idbarang = b.idbarang
                                       JOIN satuan s ON b.idsatuan = s.idsatuan
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
        $stmtStok = $dbconn->prepare("CALL sp_proses_penjualan_transaksi(?, ?, ?)");

        foreach ($items as $i) {
            $subtotal = $i['harga_jual'] * $i['jumlah'];

            // Kurangi stok
            $stmtStok->bind_param("iii", $idpenjualan, $i['idbarang'], $i['jumlah']);
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

        $stmtStok = $dbconn->prepare("CALL sp_proses_pembatalan_penjualan(?, ?, ?)");
        foreach ($details as $d) {
            $stmtStok->bind_param("iii", $id, $d['idbarang'], $d['jumlah']);
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