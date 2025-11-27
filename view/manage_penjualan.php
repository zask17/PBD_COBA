<?php
require_once '../model/koneksi.php';
require_once '../model/auth.php';

checkAuth();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjualan Barang - Sistem Inventory</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .transaction-form .card-body { padding: 0; }
        .form-header, .form-footer { padding: 28px; }
        .form-header { border-bottom: 1px solid #2a3142; }
        .form-footer { border-top: 1px solid #2a3142; }
        #item-list-table th, #item-list-table td { padding: 16px 28px; }
        #item-list-table input { background: #0f1419; border-color: #3a4254; padding: 8px; text-align: right; color: #e4e6eb; }
        .total-section { text-align: right; font-size: 1.5rem; font-weight: 700; }
    </style>
</head>
<body>
    <div class="dashboard-content">
        <!-- Header -->
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
                        <p>Transaksi Penjualan Barang</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem;">
                    <a href="../models/auth.php?action=logout" class="btn btn-danger"><span>🚪</span> Keluar</a>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="card transaction-form">
                <form id="formPenjualan">
                    <!-- Form Header -->
                    <div class="form-header">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="tanggal">Tanggal Penjualan *</label>
                                <input type="date" id="tanggal" name="tanggal" required>
                            </div>
                            <div class="form-group">
                                <label for="select-margin">Margin Penjualan *</label>
                                <select id="select-margin" required><option value="">Memuat margin...</option></select>
                            </div>
                        </div>
                    </div>
                    <div class="form-header" style="padding-top: 0;">
                        <div class="form-row" style="align-items: flex-end; gap: 16px;">
                             <div class="form-group" style="flex-grow: 3;">
                                <label for="select-barang">Pilih Barang untuk Ditambahkan</label>
                                <select id="select-barang">
                                    <option value="">Memuat barang...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="jumlah-barang">Jumlah</label>
                                <input type="number" id="jumlah-barang" value="1" min="1" style="text-align: center;">
                            </div>
                            <div class="form-group" style="align-self: flex-end;"><button type="button" id="btn-tambah-barang" class="btn btn-secondary">Tambah ke Daftar</button></div>
                        </div>
                    </div>

                    <!-- Item List Table -->
                    <div class="table-responsive">
                        <table id="item-list-table">
                            <thead>
                                <tr>
                                    <th>Nama Barang</th>
                                    <th width="15%">Jumlah</th>
                                    <th width="20%">Harga Jual</th>
                                    <th width="20%">Subtotal</th>
                                    <th width="5%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="item-list-body">
                                <!-- Items will be added here dynamically -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Form Footer -->
                    <div class="form-footer">
                        <div class="total-section">
                            <span>Total: </span>
                            <span id="grand-total">Rp 0</span>
                        </div>
                        <div class="form-footer" style="padding: 28px 0 0 0; border-top: none;">
                            <button type="submit" class="btn btn-primary">Simpan Transaksi Penjualan</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Sales List Table -->
            <div class="card" style="margin-top: 32px;">
                <div class="card-header">
                    <h2>Daftar Transaksi Penjualan</h2>
                    <button id="btnRefreshList" class="btn btn-secondary btn-sm">🔄 Refresh</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tablePenjualan">
                            <thead>
                                <tr>
                                    <th>ID Transaksi</th>
                                    <th>Tanggal</th>
                                    <th>Kasir</th>
                                    <th>Margin Penjualan</th>
                                    <th>Total Nilai</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="penjualanListBody">
                                <tr>
                                    <td colspan="6" style="text-align: center;">Memuat data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadMargins();
    loadBarangList();
    loadSalesList(); // Muat daftar penjualan saat halaman dibuka
    document.getElementById('tanggal').valueAsDate = new Date();

    document.getElementById('btnRefreshList').addEventListener('click', loadSalesList);
});

const formatRupiah = (number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);

async function loadBarangList() {
    try {
        const response = await fetch(`../models/penjualan.php?action=list_barang`);
        const result = await response.json();
        if (result.success) {
            const barangSelect = document.getElementById('select-barang');
            barangSelect.innerHTML = '<option value="">Pilih Barang</option>' + 
                result.data.map(item => {
                    // Tampilkan harga dasar di dropdown
                    return `<option value='${JSON.stringify(item)}'>${item.nama} (Stok: ${item.stok}) - Base: ${formatRupiah(item.harga)}</option>`;
                }).join('');
        }
    } catch (error) {
        console.error('Error loading barang list:', error);
    }
}

async function loadMargins() {
    try {
        const response = await fetch(`../models/penjualan.php?action=list_margins`);
        const result = await response.json();
        if (result.success) {
            const marginSelect = document.getElementById('select-margin');
            marginSelect.innerHTML = '<option value="">Pilih Margin</option>' + 
                result.data.map((margin, index) => `<option value="${margin.idmargin_penjualan}" data-persen="${margin.persen}" ${index === 0 ? 'selected' : ''}>${margin.persen}%</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading margin list:', error);
    }
}

async function loadSalesList() {
    try {
        const response = await fetch(`../models/penjualan.php?action=list_penjualan`);
        const result = await response.json();
        const listBody = document.getElementById('penjualanListBody');

        if (result.success && result.data.length > 0) {
            listBody.innerHTML = result.data.map(sale => {
                const tanggal = new Date(sale.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
                return `
                    <tr>
                        <td>TX-${sale.idpenjualan}</td>
                        <td>${tanggal}</td>
                        <td>${sale.username}</td>
                        <td>${sale.margin_persen}%</td>
                        <td>${formatRupiah(sale.total_nilai)}</td>
                        <td class="action-buttons">
                            <button class="btn btn-secondary btn-sm" onclick="viewSaleDetails(${sale.idpenjualan})">Detail</button>
                        </td>
                    </tr>
                `;
            }).join('');
        } else {
            listBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Belum ada transaksi penjualan.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading sales list:', error);
        document.getElementById('penjualanListBody').innerHTML = '<tr><td colspan="6" style="text-align: center; color: #f5576c;">Gagal memuat daftar penjualan.</td></tr>';
    }
}


document.getElementById('btn-tambah-barang').addEventListener('click', () => {
    const select = document.getElementById('select-barang');
    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption.value) {
        alert('Silakan pilih barang terlebih dahulu.');
        return;
    }
    const marginSelect = document.getElementById('select-margin');
    const selectedMarginOption = marginSelect.options[marginSelect.selectedIndex];
    if (!selectedMarginOption.value) {
        alert('Silakan pilih margin penjualan terlebih dahulu.');
        return;
    }

    const jumlah = document.getElementById('jumlah-barang').value;
    const item = JSON.parse(selectedOption.value);
    const marginPersen = parseFloat(selectedMarginOption.dataset.persen);

    // Hitung harga jual berdasarkan formula: harga_jual = harga_barang * (1 + margin_penjualan)
    const harga_dasar = parseFloat(item.harga);
    const harga_jual = harga_dasar * (1 + (marginPersen / 100));
    item.harga_jual = harga_jual; // Tambahkan harga jual yang sudah dihitung ke objek item

    addItem(item, jumlah);
});

function addItem(item, jumlah = 1) { // item sekarang sudah mengandung harga_jual
    const itemListBody = document.getElementById('item-list-body');
    
    if (document.querySelector(`tr[data-idbarang="${item.idbarang}"]`)) {
        alert('Barang sudah ada di dalam daftar.');
        return;
    }

    const row = document.createElement('tr');
    row.setAttribute('data-idbarang', item.idbarang);
    row.innerHTML = `
        <td>${item.nama}<br><small>Stok: ${item.stok} | Harga Dasar: ${formatRupiah(item.harga)}</small></td>
        <td><input type="number" class="item-qty" value="${jumlah}" min="1" max="${item.stok}" onchange="updateTotals()" onkeyup="updateTotals()"></td>
        <td class="item-price" data-price="${item.harga_jual}">${formatRupiah(item.harga_jual)}</td>
        <td class="item-subtotal">${formatRupiah(item.harga_jual * jumlah)}</td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); updateTotals();">X</button></td>
    `;
    itemListBody.appendChild(row);
    
    // Reset dropdown dan input jumlah
    document.getElementById('select-barang').selectedIndex = 0;
    document.getElementById('jumlah-barang').value = 1;
    updateTotals();
}

function updateTotals() {
    let grandTotal = 0;
    document.querySelectorAll('#item-list-body tr').forEach(row => {
        const qtyInput = row.querySelector('.item-qty');
        let qty = parseFloat(qtyInput.value) || 0;
        const maxQty = parseInt(qtyInput.max);

        // Validasi agar qty tidak melebihi stok
        if (qty > maxQty) {
            alert(`Stok tidak mencukupi. Stok maksimal untuk barang ini adalah ${maxQty}.`);
            qtyInput.value = maxQty;
            qty = maxQty;
        }

        const price = parseFloat(row.querySelector('.item-price').dataset.price) || 0;
        const subtotal = qty * price;
        grandTotal += subtotal;
        row.querySelector('.item-subtotal').textContent = formatRupiah(subtotal);
    });
    document.getElementById('grand-total').textContent = formatRupiah(grandTotal);
}

document.getElementById('formPenjualan').addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitButton = e.target.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    submitButton.textContent = 'Menyimpan...';

    const tanggal = document.getElementById('tanggal').value;
    const idmargin = document.getElementById('select-margin').value;
    const items = [];

    document.querySelectorAll('#item-list-body tr').forEach(row => {
        items.push({
            idbarang: row.dataset.idbarang,
            jumlah: parseFloat(row.querySelector('.item-qty').value),
            harga_jual: parseFloat(row.querySelector('.item-price').dataset.price)
        });
    });

    if (!tanggal || !idmargin || items.length === 0) {
        alert('Mohon lengkapi tanggal, pilih margin, dan tambahkan minimal satu barang.');
        submitButton.disabled = false;
        submitButton.textContent = 'Simpan Transaksi Penjualan';
        return;
    }

    const payload = { tanggal, idmargin, items };

    try {
        const response = await fetch('../models/penjualan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        alert(result.message);

        if (result.success) {
            // Reset form
            document.getElementById('formPenjualan').reset();
            document.getElementById('tanggal').valueAsDate = new Date();
            document.getElementById('item-list-body').innerHTML = '';
            updateTotals();
            loadMargins(); // Muat ulang margin
            loadBarangList(); // Muat ulang daftar barang
            loadSalesList(); // Muat ulang daftar penjualan
        }
    } catch (error) {
        alert('Terjadi kesalahan: ' + error.message);
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Simpan Transaksi Penjualan';
    }
});

function viewSaleDetails(id) {
    // Fungsi ini bisa dikembangkan lebih lanjut, misalnya membuka modal dengan detail item.
    alert(`Fungsi untuk melihat detail transaksi TX-${id} belum diimplementasikan.`);
}
</script>

</body>
</html>