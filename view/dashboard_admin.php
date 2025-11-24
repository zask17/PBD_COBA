<?php
// File: view/dashboard_admin.php

require_once '../model/koneksi.php';
require_once '../model/auth.php';
checkAuth();

$user_role = $_SESSION['role'] ?? 'Guest';
$username = $_SESSION['username'] ?? 'Pengguna';

// Tentukan hak akses untuk role Admin
$is_admin = ($user_role === 'administrator');
$is_super_admin = ($user_role === 'super administrator');


$can_access_transaction = ($is_admin || $is_super_admin);
$can_read_barang = ($is_admin || $is_super_admin); // Admin bisa Read

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Inventory PBD</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<style>
    .dashboard-content header {
        padding: 15px 0;
    }

    .dashboard-content .header-title p {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.8);
        margin: 0;
    }

    .dashboard-content .container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 15px;
    }

    .page-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-green);
        margin-bottom: 1rem;
        border-bottom: 2px solid var(--accent-green);
        padding-bottom: 1rem;
    }

    .sub-menu-title {
        color: #8b92a7;
        font-weight: 600;
        margin-top: 40px;
        margin-bottom: -10px;
        font-size: 1.2rem;
    }

    .datamaster-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 24px;
        margin-top: 32px;
    }

    .dm-card {
        background: #FFFFFF;
        border-radius: 12px;
        padding: 24px;
        text-decoration: none;
        color: var(--text-color-dark);
        border-left: 5px solid var(--primary-green);
        box-shadow: var(--shadow-light);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .dm-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        border-left-color: var(--accent-green);
    }

    .dm-card-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 10px;
    }

    .dm-card-icon {
        font-size: 28px;
        background: var(--background-section);
        color: var(--primary-green);
        padding: 12px;
        border-radius: 10px;
        flex-shrink: 0;
    }

    .dm-card-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary-green);
    }

    .dm-card-description {
        font-size: 0.9rem;
        color: #555;
        line-height: 1.5;
    }

    .header-actions span {
        color: white;
        font-weight: 500;
    }

    .btn-danger {
        background-color: #D32F2F;
        color: white;
        border: 2px solid #D32F2F;
    }

    .btn-danger:hover {
        background-color: #B71C1C;
        border-color: #B71C1C;
    }
</style>

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
                    <span>👋 Halo, <?php echo ucwords($username); ?>!</span>
                    <a href="../model/auth.php?action=logout" class="btn btn-danger"><span>🚪</span> Keluar</a>
                </div>
            </div>
        </header>

        <div class="container">
            <h2 class="page-title">Menu Utama</h2>

            <?php if ($can_access_transaction): ?>
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
            <?php endif; ?>

            <?php if ($can_read_barang): ?>
                <h3 class="sub-menu-title">Data Master Dasar</h3>
                <div class="datamaster-grid">
                    <a href="manage_barang.php" class="dm-card">
                        <div class="dm-card-header"><span class="dm-card-icon">📦</span>
                            <h3 class="dm-card-title">Manajemen Barang</h3>
                        </div>
                        <p class="dm-card-description">Lihat daftar, stok, dan informasi dasar barang.</p>
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</body>

</html>