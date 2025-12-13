<?php
// FILE: ../view/manage_kartu_stok.php

require_once '../model/koneksi.php';
require_once '../model/auth.php';

checkAuth();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Stok - Sistem Inventory PBD</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard_super_admin.css">
    <link rel="stylesheet" href="../css/kartu_stok.css"> 
    <style>
        .text-success { color: #28a745; font-weight: bold; }
        .text-danger { color: #dc3545; font-weight: bold; }
    </style>
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
                        <p>Kartu Stok</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem; align-items: center;"></div>
                <a href="../model/auth.php?action=logout" class="btn btn-danger">
                    <span>Keluar</span>
                </a>
            </div>
        </header>


        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2>Daftar Barang Aktif</h2>
                    <button id="btnRefresh" class="btn btn-secondary btn-sm">Refresh</button>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tableBarang">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama Barang</th>
                                    <th>Jenis</th>
                                    <th>Satuan</th>
                                    <th>Harga Pokok</th>
                                    <th>Stok Terakhir</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tableBodyBarang">
                                <tr>
                                    <td colspan="7" style="text-align: center;">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>


        <footer>
            </footer>
    </div>

    <div id="modalRiwayat" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h3 id="modalTitle">Riwayat Kartu Stok: <span id="namaBarangTitle"></span></h3>
                <button class="close" onclick="closeModalRiwayat()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="tableKartuStok">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jenis Transaksi</th>
                                <th>Ref. ID</th>
                                <th>Masuk (IN)</th>
                                <th>Keluar (OUT)</th>
                                <th>Stok Akhir</th>
                            </tr>
                        </thead>
                        <tbody id="tableBodyKartuStok">
                            <tr>
                                <td colspan="6" style="text-align: center;">Memuat riwayat...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BARANG_URL = '../model/barang.php?action=list_active_stock'; 
        const API_KARTU_STOK_URL = '../model/kartu_stok.php'; 

        document.addEventListener('DOMContentLoaded', () => {
            loadBarang();
            document.getElementById('btnRefresh').addEventListener('click', loadBarang);
        });
        
        const formatRupiah = (number) => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(number);

        async function loadBarang() {
            try {
                const response = await fetch(API_BARANG_URL);
                if (response.status === 401) {
                    alert('Sesi Anda telah berakhir. Silakan login kembali.');
                    window.location.href = 'login.php';
                    return;
                }
                const result = await response.json();
                const tbody = document.getElementById('tableBodyBarang');

                if (result.success && result.data.length > 0) {
                    tbody.innerHTML = result.data.map(item => `
<tr>
    <td>${item.idbarang}</td>
    <td>${item.nama}</td>
    <td>${item.jenis === 'J' ? 'Barang Jadi' : 'Bahan Baku'}</td>
    <td>${item.satuan}</td>
    <td>Rp ${formatRupiah(item.harga)}</td>
    <td>${formatRupiah(item.stok_terakhir)}</td>
    <td class="action-buttons">
        <button class="btn btn-secondary btn-sm" onclick="showStockCard('${item.idbarang}', '${item.nama}')">Riwayat</button>
    </td>
</tr>
`).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Tidak ada data barang aktif</td></tr>';
                }
            } catch (error) {
                console.error('Error loading barang:', error);
                document.getElementById('tableBodyBarang').innerHTML = '<tr><td colspan="7" style="text-align: center;">Gagal memuat data barang</td></tr>';
            }
        }

        async function showStockCard(idbarang, namaBarang) {
            document.getElementById('namaBarangTitle').textContent = namaBarang;
            document.getElementById('tableBodyKartuStok').innerHTML = '<tr><td colspan="6" style="text-align: center;">Memuat riwayat...</td></tr>';
            document.getElementById('modalRiwayat').classList.add('show');

            try {
                const response = await fetch(`${API_KARTU_STOK_URL}?idbarang=${idbarang}`);
                const result = await response.json();
                const tbody = document.getElementById('tableBodyKartuStok');

                if (result.success && result.data.length > 0) {
                    tbody.innerHTML = result.data.map(item => `
<tr>
    <td>${new Date(item.created_at).toLocaleString('id-ID')}</td>
    <td>${item.jenis_transaksi_display}</td>
    <td>${item.idtransaksi}</td>
    <td class="${item.masuk > 0 ? 'text-success' : ''}">${formatRupiah(item.masuk)}</td>
    <td class="${item.keluar > 0 ? 'text-danger' : ''}">${formatRupiah(item.keluar)}</td>
    <td>${formatRupiah(item.stok)}</td>
</tr>
`).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Tidak ada riwayat transaksi untuk barang ini.</td></tr>';
                }
            } catch (error) {
                console.error('Error loading stock card:', error);
                document.getElementById('tableBodyKartuStok').innerHTML = '<tr><td colspan="6" style="text-align: center;">Gagal memuat riwayat kartu stok.</td></tr>';
            }
        }

        function closeModalRiwayat() {
            document.getElementById('modalRiwayat').classList.remove('show');
        }

        window.onclick = function(event) {
            if (event.target === document.getElementById('modalRiwayat')) {
                closeModalRiwayat();
            }
        }
    </script>
    <?php include 'footer.php'; ?>
</body>

</html>