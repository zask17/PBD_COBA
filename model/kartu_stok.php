<?php
// FILE: ../model/kartu_stok.php (API untuk Kartu Stok)

require_once 'koneksi.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

checkAuth(); 

$idbarang = $_GET['idbarang'] ?? null;

if (!$idbarang) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID Barang wajib diisi.']);
    exit;
}

// Logika untuk menampilkan nama jenis transaksi yang lebih user-friendly
function getDisplayJenisTransaksi($jenis) {
    switch ($jenis) {
        case 'M': return 'Masuk (Penerimaan)';
        case 'K': return 'Keluar (Penjualan)';
        case 'B': return 'Batal Jual (Reversal)';
        case 'A': return 'Penyesuaian (+)'; 
        case 'R': return 'Retur Vendor (-)'; 
        default: return 'Lainnya';
    }
}


try {
    // Mengambil riwayat kartu stok untuk idbarang tertentu
    // Diurutkan berdasarkan waktu pembuatan untuk mendapatkan aliran stok yang benar
    $stmt = $dbconn->prepare("SELECT idtransaksi, jenis_transaksi, masuk, keluar, stok, created_at FROM kartu_stok WHERE idbarang = ? ORDER BY created_at ASC, idkartu_stok ASC");
    $stmt->bind_param("i", $idbarang);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $row['jenis_transaksi_display'] = getDisplayJenisTransaksi($row['jenis_transaksi']);
        $data[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $data]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error database: ' . $e->getMessage()]);
}

?>