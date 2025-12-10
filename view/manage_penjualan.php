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
    <link rel="stylesheet" href="../css/dashboard_super_admin.css">
    <link rel="stylesheet" href="../css/penjualan.css">
    <style>
        /* Gaya Sederhana untuk Modal/Popup */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.4); 
            padding-top: 60px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; 
            padding: 20px;
            border: 1px solid #888;
            width: 80%; 
            max-width: 700px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close-button:hover,
        .close-button:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
        .modal-content h3 {
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .detail-info p {
            margin: 5px 0;
        }
        .detail-info strong {
            display: inline-block;
            width: 150px;
        }
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
                        <p>Transaksi Penjualan Barang</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem;">
                    <a href="../model/auth.php?action=logout" class="btn btn-danger"><span>🚪</span> Keluar</a>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="card transaction-form">
                <form id="formPenjualan">
                    <div class="form-header">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="tanggal">Tanggal Penjualan *</label>
                                <input type="date" id="tanggal" name="tanggal" required>
                            </div>
                            <div class="form-group">
                                <label for="select-margin">Margin Penjualan * (Saat Ini Aktif)</label>
                                <select id="select-margin" required disabled>
                                    <option value="">Memuat margin...</option>
                                </select>
                                <input type="hidden" id="hidden-margin-id" name="idmargin">
                                <input type="hidden" id="hidden-margin-persen" name="persen">
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
                                </tbody>
                        </table>
                    </div>

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

            <div class="card" style="margin-top: 32px;">
                <div class="card-header">
                    <h2>Daftar Transaksi Penjualan</h2>
                    <button id="btnRefreshList" class="btn btn-secondary btn-sm">Refresh</button>
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

<div id="saleDetailModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="document.getElementById('saleDetailModal').style.display='none'">&times;</span>
        <h3>Detail Transaksi Penjualan (<span id="detail-id"></span>)</h3>
        <div class="detail-info">
            <p><strong>Tanggal:</strong> <span id="detail-tanggal"></span></p>
            <p><strong>Kasir:</strong> <span id="detail-kasir"></span></p>
            <p><strong>Margin:</strong> <span id="detail-margin"></span></p>
            <p><strong>Total:</strong> <span id="detail-total"></span></p>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th>Jumlah</th>
                        <th>Harga Satuan</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody id="detail-item-list"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
const API_URL = '../model/penjualan.php';
let currentFilterStatus = 'aktif';

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('tanggal').valueAsDate = new Date();
    loadMarginAktif();
    loadBarangList('aktif');
    loadSalesList();

    // Event listener untuk refresh list
    document.getElementById('btnRefreshList').addEventListener('click', loadSalesList);
});

function formatRupiah(number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
}

async function loadMarginAktif() {
    try {
        const response = await fetch(`${API_URL}?action=list_margins`);
        const result = await response.json();
        const selectMargin = document.getElementById('select-margin');
        const hiddenId = document.getElementById('hidden-margin-id');
        const hiddenPersen = document.getElementById('hidden-margin-persen');
        
        if (result.success && result.data.length > 0) {
            const margin = result.data[0];
            selectMargin.innerHTML = `<option value="${margin.idmargin_penjualan}">${margin.persen}%</option>`;
            hiddenId.value = margin.idmargin_penjualan;
            hiddenPersen.value = margin.persen;
            selectMargin.disabled = true; // Tetap disable/read-only
        } else {
            selectMargin.innerHTML = '<option value="">⚠️ Tidak ada margin aktif</option>';
            hiddenId.value = ''; // Kosongkan agar validasi gagal
            alert('PERINGATAN: Tidak ada margin penjualan aktif yang ditemukan! Transaksi penjualan tidak dapat dilakukan.');
        }
    } catch (error) {
        alert('Gagal memuat margin: ' + error.message);
    }
}

async function loadBarangList(status) {
    currentFilterStatus = status;
    try {
        const response = await fetch(`${API_URL}?action=list_barang&status=${status}`);
        const result = await response.json();
        const selectBarang = document.getElementById('select-barang');

        selectBarang.innerHTML = '<option value="">Pilih barang...</option>';

        if (result.success) {
            result.data.forEach(item => {
                const optionValue = JSON.stringify(item);
                selectBarang.innerHTML += `<option value='${optionValue}'>${item.nama} (Stok: ${item.stok} | Harga: ${formatRupiah(item.harga)})</option>`;
            });
        } else {
            selectBarang.innerHTML += '<option value="">Gagal memuat: ' + result.message + '</option>';
        }
    } catch (error) {
        alert('Gagal memuat daftar barang: ' + error.message);
    }
}

async function loadSalesList() {
    try {
        const response = await fetch(`${API_URL}?action=list_penjualan`);
        const result = await response.json();
        const listBody = document.getElementById('penjualanListBody');

        if (result.success && result.data.length > 0) {
            listBody.innerHTML = result.data.map(sale => `
                <tr>
                    <td>TX-${sale.idpenjualan}</td>
                    <td>${new Date(sale.created_at).toLocaleDateString('id-ID')}</td>
                    <td>${sale.username}</td>
                    <td>${sale.margin_persen}%</td>
                    <td>${formatRupiah(sale.total_nilai)}</td>
                    <td class="action-buttons">
                        <button class="btn btn-secondary btn-sm" onclick="viewSaleDetails(${sale.idpenjualan})">Detail</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteSale(${sale.idpenjualan})">Hapus</button>
                    </td>
                </tr>
            `).join('');
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
    const marginPersenInput = document.getElementById('hidden-margin-persen');
    
    if (!selectedOption.value) {
        alert('Silakan pilih barang terlebih dahulu.');
        return;
    }
    if (!marginPersenInput.value) {
        alert('Margin penjualan belum dimuat atau tidak aktif.');
        return;
    }

    const jumlah = document.getElementById('jumlah-barang').value;
    const item = JSON.parse(selectedOption.value);
    const marginPersen = parseFloat(marginPersenInput.value);

    // Hitung harga jual: Harga Pokok + (Harga Pokok * Margin / 100)
    const harga_dasar = parseFloat(item.harga);
    const harga_jual = harga_dasar * (1 + (marginPersen / 100));
    item.harga_jual = harga_jual; 

    addItem(item, jumlah);
});

function addItem(item, jumlah = 1) { 
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

        // Validasi Stok
        if (qty > maxQty) {
            alert(`Stok tidak mencukupi. Stok maksimal untuk barang ini adalah ${maxQty}.`);
            qtyInput.value = maxQty;
            qty = maxQty;
        } else if (qty < 1) {
            qtyInput.value = 1;
            qty = 1;
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
    // Mengambil ID margin dari hidden input
    const idmargin = document.getElementById('hidden-margin-id').value; 
    const items = [];

    document.querySelectorAll('#item-list-body tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty').value);
        if (qty > 0) {
             items.push({
                idbarang: row.dataset.idbarang,
                jumlah: qty,
                harga_jual: parseFloat(row.querySelector('.item-price').dataset.price)
            });
        }
    });

    if (!tanggal || !idmargin || items.length === 0) {
        alert('Mohon lengkapi tanggal, pastikan margin aktif, dan tambahkan minimal satu barang dengan jumlah > 0.');
        submitButton.disabled = false;
        submitButton.textContent = 'Simpan Transaksi Penjualan';
        return;
    }

    const payload = { tanggal, idmargin, items };

    try {
        const response = await fetch('../model/penjualan.php', {
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
            // Muat ulang daftar barang (stok berkurang) dengan filter yang sedang aktif
            loadBarangList(currentFilterStatus); 
            loadSalesList(); // Muat ulang daftar penjualan
        }
    } catch (error) {
        alert('Terjadi kesalahan: ' + error.message);
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Simpan Transaksi Penjualan';
    }
});


// Fungsi untuk menampilkan detail (READ)
async function viewSaleDetails(id) {
    try {
        const response = await fetch(`../model/penjualan.php?action=detail_penjualan&id=${id}`);
        const result = await response.json();

        if (result.success) {
            const header = result.data.header;
            const items = result.data.items;
            const modal = document.getElementById('saleDetailModal');

            // Set Header Details
            document.getElementById('detail-id').textContent = `TX-${header.idpenjualan}`;
            document.getElementById('detail-tanggal').textContent = new Date(header.created_at).toLocaleDateString('id-ID');
            document.getElementById('detail-kasir').textContent = header.kasir;
            document.getElementById('detail-margin').textContent = `${header.margin_persen}%`;
            document.getElementById('detail-total').textContent = formatRupiah(header.total_nilai);

            // Set Item List
            const itemListBody = document.getElementById('detail-item-list');
            itemListBody.innerHTML = items.map(item => `
                <tr>
                    <td>${item.nama_barang} (${item.satuan})</td>
                    <td style="text-align: center;">${item.jumlah}</td>
                    <td>${formatRupiah(item.harga_satuan)}</td>
                    <td>${formatRupiah(item.subtotal)}</td>
                </tr>
            `).join('');

            modal.style.display = 'block';

        } else {
            alert('Gagal memuat detail: ' + result.message);
        }

    } catch (error) {
        alert('Terjadi kesalahan saat mengambil detail: ' + error.message);
    }
}


// Fungsi untuk menghapus/membatalkan transaksi (DELETE)
async function deleteSale(id) {
    if (!confirm(`Yakin ingin membatalkan transaksi TX-${id}? Aksi ini akan mengembalikan stok barang.`)) {
        return;
    }

    try {
        // Menggunakan method DELETE
        const response = await fetch(`../model/penjualan.php?id=${id}`, {
            method: 'DELETE'
        });

        const result = await response.json();
        alert(result.message);

        if (result.success) {
            loadSalesList(); // Muat ulang daftar penjualan
            // Muat ulang daftar barang (stok sudah bertambah) dengan filter yang sedang aktif
            loadBarangList(currentFilterStatus); 
        }
    } catch (error) {
        alert('Terjadi kesalahan saat membatalkan transaksi: ' + error.message);
    }
}

// Menutup modal jika user klik di luar modal
window.onclick = function(event) {
    const modal = document.getElementById('saleDetailModal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>

</body>
</html>