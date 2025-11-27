<?php
require_once '../model/koneksi.php';
require_once '../model/auth.php';

checkAuth('super administrator');


$user_role = $_SESSION['role'] ?? 'Guest';
$username = $_SESSION['username'] ?? 'Pengguna';

// Super Admin memiliki akses penuh ke semua menu
$can_access_full_data_master = true;

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Super Admin - Sistem Inventory PBD</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard_super_admin.css">
</head>

<body>
    <div class="dashboard-content">
        <header>
            <div class="header-content">
                <div class="header-left">
                    <div class="logo">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                    </div>
                    <div class="header-title">
                        <h1>Sistem Manajemen Inventory</h1>
                        <p>Dashboard <strong><?php echo ucwords(htmlspecialchars($user_role)); ?></strong></p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem; align-items: center;">
                    <span>Halo, <?php echo ucwords($username); ?>!</span>
                    <a href="../model/auth.php?action=logout" class="btn btn-danger"><span>Keluar</span></a>
                </div>
            </div>
        </header>

        <div class="container">
            <h2 class="page-title">Menu Utama</h2>

            <h3 class="sub-menu-title">Menu Transaksi</h3>
            <div class="datamaster-grid">
                <a href="manage_pengadaan.php" class="dm-card">
                    <div class="dm-card-header"><span class="dm-card-icon">📝</span>
                        <h3 class="dm-card-title">Pengadaan (PO)</h3>
                    </div>
                    <p class="dm-card-description">Buat pesanan pembelian (Purchase Order) ke vendor.</p>
                </a>
                <a href="manage_penerimaan.php" class="dm-card">
                    <div class="dm-card-header"><span class="dm-card-icon">📥</span>
                        <h3 class="dm-card-title">Penerimaan Barang</h3>
                    </div>
                    <p class="dm-card-description">Catat barang yang masuk dari vendor untuk menambah stok.</p>
                </a>
                <a href="manage_penjualan.php" class="dm-card">
                    <div class="dm-card-header"><span class="dm-card-icon">📤</span>
                        <h3 class="dm-card-title">Penjualan Barang</h3>
                    </div>
                    <p class="dm-card-description">Catat transaksi penjualan barang ke pelanggan dan kurangi stok.</p>
                </a>
                <a href="report_kartu_stok.php" class="dm-card">
                    <div class="dm-card-header"><span class="dm-card-icon">📋</span>
                        <h3 class="dm-card-title">Kartu Stok</h3>
                    </div>
                    <p class="dm-card-description">Lacak pergerakan dan riwayat stok (masuk, keluar, saldo) per barang.</p>
                </a>
            </div>

            <h3 class="sub-menu-title">Menu Data Master</h3>
            <div class="datamaster-grid">
                <a href="manage_barang.php" class="dm-card">
                    <div class="dm-card-header"><span class="dm-card-icon">📦</span>
                        <h3 class="dm-card-title">Manajemen Barang</h3>
                    </div>
                    <p class="dm-card-description">Lihat, tambah, edit, dan hapus data barang. Filter barang berdasarkan status aktif.</p>
                </a>
                <a href="manage_satuan.php" class="dm-card">
                    <div class="dm-card-header"><span class="dm-card-icon">📐</span>
                        <h3 class="dm-card-title">Manajemen Satuan</h3>
                    </div>
                    <p class="dm-card-description">Kelola satuan unit untuk barang (e.g., Pcs, Box, Kg). Filter satuan aktif.</p>
                </a>
                <a href="manage_vendor.php" class="dm-card">
                    <div class="dm-card-header"><span class="dm-card-icon">🚚</span>
                        <h3 class="dm-card-title">Manajemen Vendor</h3>
                    </div>
                    <p class="dm-card-description">Kelola data pemasok atau vendor. Filter vendor berdasarkan status aktif.</p>
                </a>
                <a href="manage_margin.php" class="dm-card">
                    <div class="dm-card-header"><span class="dm-card-icon">📈</span>
                        <h3 class="dm-card-title">Manajemen Margin</h3>
                    </div>
                    <p class="dm-card-description">Atur persentase margin keuntungan untuk penjualan barang.</p>
                </a>
            </div>

            <h3 class="sub-menu-title">Menu Administrasi</h3>
            <div class="datamaster-grid">
                <a href="manage_users.php" class="dm-card">
                    <div class="dm-card-header"><span class="dm-card-icon">👥</span>
                        <h3 class="dm-card-title">Manajemen User</h3>
                    </div>
                    <p class="dm-card-description">Atur pengguna sistem dan tetapkan hak akses untuk setiap user.</p>
                </a>
                <a href="manage_roles.php" class="dm-card">
                    <div class="dm-card-header"><span class="dm-card-icon">🛡️</span>
                        <h3 class="dm-card-title">Manajemen Role</h3>
                    </div>
                    <p class="dm-card-description">Kelola jenis hak akses atau role yang tersedia dalam sistem.</p>
                </a>
            </div>
        </div>
    </div>
</body>

</html>