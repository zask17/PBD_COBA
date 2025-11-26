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
    <title>Penerimaan Barang - Sistem Inventory</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .transaction-form .card-body { padding: 0; }
        .form-header, .form-footer { padding: 28px; }
        .form-header { border-bottom: 1px solid #2a3142; }
        .form-footer { border-top: 1px solid #2a3142; }
        #item-list-table th, #item-list-table td { padding: 16px 28px; }
        #item-list-table input { 
            background: #0f1419; border-color: #3a4254; 
            padding: 8px; text-align: right; 
            color: #e4e6eb; /* Menambahkan warna teks terang */
        }
        .search-container { position: relative; }
        #search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #2a3142;
            border: 1px solid #3a4254;
            border-radius: 0 0 10px 10px;
            z-index: 10;
            max-height: 250px;
            overflow-y: auto;
        }
        .search-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #3a4254;
        }
        .search-item:last-child { border-bottom: none; }
        .search-item:hover { background: #323948; }
        .search-item small { color: #8b92a7; }
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
                        <p>Transaksi Penerimaan Barang</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem;">
                    <a href="../models/auth.php?action=logout" class="btn btn-danger"><span>🚪</span> Keluar</a>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="card transaction-form">
                <form id="formPenerimaan">
                    <input type="hidden" id="idpenerimaan_edit" name="idpenerimaan">
                    <input type="hidden" id="formMethod" name="_method">
                    <input type="hidden" id="idpengadaan" name="idpengadaan">
                    <!-- Form Header -->
                    <div class="form-header" style="background: rgba(102, 126, 234, 0.05);">
                        <div class="form-group">
                            <label for="select-po">Pilih Pengadaan (Purchase Order) untuk Diterima</label>
                            <select id="select-po"><option value="">Pilih PO...</option></select>
                        </div>
                    </div>
                    <div class="form-header">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nama_vendor">Vendor *</label>
                                <input type="text" id="nama_vendor" name="nama_vendor" readonly required placeholder="Pilih PO untuk menampilkan vendor">
                            </div>
                            <div class="form-group">
                                <label for="tanggal">Tanggal Penerimaan *</label>
                                <input type="date" id="tanggal" name="tanggal" required>
                            </div>
                        </div>
                    </div>

                    <!-- Item List Table -->
                    <div class="table-responsive">
                        <table id="item-list-table">
                            <thead>
                                <tr>
                                    <th>Nama Barang</th>
                                    <th width="15%">Jumlah</th>
                                    <th width="20%">Harga Beli</th>
                                    <th width="20%">Subtotal</th>
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
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan Penerimaan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
         <!-- Area Tampilan Data -->
            <div class="card">
                <div class="card-header">
                    <h2>Daftar Penerimaan</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tablePenerimaan">
                            <thead>
                                <tr>
                                    <th>ID Penerimaan</th>
                                    <th>ID Pengadaan</th>
                                    <th>ID User</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr><td colspan="6" style="text-align: center;">Memuat data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

<script>
const API_URL = '../models/penerimaan.php';

document.addEventListener('DOMContentLoaded', () => {
    loadOpenPOs();
    loadPenerimaan(); // Panggil fungsi untuk memuat tabel penerimaan
    document.getElementById('tanggal').valueAsDate = new Date();

    document.getElementById('select-po').addEventListener('change', handlePOSelection);
});

const formatRupiah = (number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);

async function loadPenerimaan() {
    try {
        const response = await fetch(`${API_URL}?action=get_penerimaan`);
        const result = await response.json();
        const tbody = document.getElementById('tableBody');

        if (result.success && result.data.length > 0) {
            tbody.innerHTML = result.data.map(p => {
                // Mapping untuk status agar lebih mudah dibaca
                const statusMap = {
                    'P': { text: 'Pending', class: 'badge-warning' },
                    'C': { text: 'Cicilan', class: 'badge-info' },
                    'F': { text: 'Final', class: 'badge-success' }
                };
                const statusInfo = statusMap[p.status] || { text: p.status, class: 'badge-secondary' };
                const tanggal = new Date(p.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

                return `
                    <tr>
                        <td>${p.idpenerimaan}</td>
                        <td>PO-${p.idpengadaan}</td>
                        <td>${p.iduser}</td>
                        <td><span class="badge ${statusInfo.class}">${statusInfo.text}</span></td>
                        <td class="action-buttons">
                            <button class="btn btn-primary btn-sm" onclick="editPenerimaan('${p.idpenerimaan}')">Edit</button>
                        </td>
                        <td>${tanggal}</td>
                    </tr>
                `;
            }).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Belum ada data penerimaan.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading penerimaan:', error);
        document.getElementById('tableBody').innerHTML = '<tr><td colspan="6" style="text-align: center;">Gagal memuat data.</td></tr>';
    }
}

async function loadOpenPOs() {
    try {
        const response = await fetch(`${API_URL}?action=get_open_pos`);
        const result = await response.json();
        if (result.success) {
            const select = document.getElementById('select-po');
            const currentVal = select.value; // Simpan nilai saat ini jika ada
            select.innerHTML = '<option value="">Pilih PO...</option>' + 
                result.data.map(po => {
                    const tanggal = new Date(po.timestamp).toLocaleDateString('id-ID');
                    return `<option value="${po.idpengadaan}">PO-${po.idpengadaan} - ${po.nama_vendor} (${tanggal})</option>`;
                }).join('');
            if (document.getElementById('formMethod').value === 'PUT') {
                select.value = currentVal; // Set kembali nilainya jika sedang mode edit
            }
        }
    } catch (error) {
        console.error('Error loading open POs:', error);
    }
}

async function handlePOSelection(e) {
    const idpengadaan = e.target.value;
    const itemListBody = document.getElementById('item-list-body');
    const vendorInput = document.getElementById('nama_vendor');
    
    // Reset form jika tidak ada PO yang dipilih
    if (!idpengadaan) {
        vendorInput.value = '';
        vendorInput.placeholder = 'Pilih PO untuk menampilkan vendor';
        document.getElementById('idpengadaan').value = ''; // Reset hidden input
        itemListBody.innerHTML = '';
        updateTotals();
        return;
    }

    try {
        const response = await fetch(`${API_URL}?action=get_po_details&id=${idpengadaan}`);
        const result = await response.json();

        if (result.success && result.data.length > 0) {
            // Set vendor dari PO yang dipilih
            const selectedPOText = e.target.options[e.target.selectedIndex].text;
            vendorInput.value = selectedPOText.split(' - ')[1].split(' (')[0]; // Ekstrak nama vendor dari teks option
            document.getElementById('idpengadaan').value = idpengadaan; // Simpan ID PO di hidden input
            
            // Kosongkan daftar item sebelum mengisi yang baru
            itemListBody.innerHTML = '';

            // Tambahkan setiap item dari PO ke tabel
            result.data.forEach(item => {
                // Menggunakan data dari PO untuk harga dan jumlah
                addItemFromPO(item);
            });
            updateTotals();
        } else {
            alert('Gagal memuat detail PO atau PO tidak memiliki item.');
        }
    } catch (error) {
        console.error('Error fetching PO details:', error);
        alert('Terjadi kesalahan saat mengambil detail PO.');
    }
}

async function editPenerimaan(idpenerimaan) {
    try {
        const response = await fetch(`${API_URL}?action=get_penerimaan_details&id=${idpenerimaan}`);
        const result = await response.json();

        if (result.success && result.data) {
            resetForm(); // Bersihkan form sebelum diisi
            const data = result.data;
            const itemListBody = document.getElementById('item-list-body');
            const vendorInput = document.getElementById('nama_vendor');
            const poSelect = document.getElementById('select-po');

            // Isi form dengan data yang ada
            document.getElementById('idpengadaan').value = data.header.idpengadaan;
            document.getElementById('idpenerimaan_edit').value = idpenerimaan;
            document.getElementById('formMethod').value = 'PUT';
            document.getElementById('tanggal').value = data.header.created_at.split(' ')[0];
            document.querySelector('#formPenerimaan button[type="submit"]').textContent = 'Perbarui Penerimaan';

            // Tampilkan PO yang sedang diedit dan nonaktifkan pilihan
            poSelect.innerHTML = `<option value="${data.header.idpengadaan}">PO-${data.header.idpengadaan} (Editing)</option>`;
            poSelect.value = data.header.idpengadaan;
            poSelect.disabled = true;

            // Isi nama vendor
            vendorInput.value = data.header.nama_vendor;

            // Isi daftar barang
            itemListBody.innerHTML = '';
            data.details.forEach(item => {
                // Gunakan nama_barang jika ada (dari edit), jika tidak, gunakan nama (dari pilih PO baru)
                const itemData = {
                    idbarang: item.idbarang,
                    nama: item.nama_barang,
                    jumlah: item.jumlah,
                    harga_satuan: item.harga_satuan
                };
                addItemFromPO(itemData);
            });

            updateTotals();
            window.scrollTo(0, 0); // Scroll ke atas untuk fokus ke form
        } else {
            alert('Gagal memuat data penerimaan untuk diedit.');
        }
    } catch (error) {
        console.error('Error fetching penerimaan details for edit:', error);
        alert('Terjadi kesalahan saat mengambil detail penerimaan.');
    }
}

function addItemFromPO(item) {
    const itemListBody = document.getElementById('item-list-body');
    if (document.querySelector(`tr[data-idbarang="${item.idbarang}"]`)) return; // Hindari duplikat

    const row = document.createElement('tr');
    row.setAttribute('data-idbarang', item.idbarang);
    const namaBarang = item.nama_barang || item.nama;
    const subtotal = item.jumlah * item.harga_satuan;
    row.innerHTML = `
        <td>${namaBarang}</td>
        <td><input type="number" class="item-qty" value="${item.jumlah}" min="0" max="${item.jumlah}" onchange="updateTotals()" title="Jumlah di PO: ${item.jumlah}"></td>
        <td class="item-price" data-price="${item.harga_satuan}">${formatRupiah(item.harga_satuan)}</td>
        <td class="item-subtotal">${formatRupiah(subtotal)}</td>
    `;
    itemListBody.appendChild(row);
}

function updateTotals() {
    let grandTotal = 0;
    document.querySelectorAll('#item-list-body tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').dataset.price) || 0;
        const subtotal = qty * price;
        grandTotal += subtotal;
        row.querySelector('.item-subtotal').textContent = formatRupiah(subtotal);
    });
    document.getElementById('grand-total').textContent = formatRupiah(grandTotal);
}

function resetForm() {
    document.getElementById('formPenerimaan').reset();
    document.getElementById('idpengadaan').value = '';
    document.getElementById('idpenerimaan_edit').value = '';
    document.getElementById('formMethod').value = '';
    document.getElementById('tanggal').valueAsDate = new Date();
    document.getElementById('item-list-body').innerHTML = '';
    document.getElementById('select-po').disabled = false;
    document.getElementById('nama_vendor').value = '';
    document.querySelector('#formPenerimaan button[type="submit"]').textContent = 'Simpan Penerimaan';
    updateTotals();
    loadOpenPOs(); // Muat ulang PO yang terbuka
}

document.getElementById('formPenerimaan').addEventListener('submit', async (e) => {
    e.preventDefault();

    const tanggal = document.getElementById('tanggal').value;
    const idpengadaan = document.getElementById('idpengadaan').value; // Ambil ID PO dari hidden input
    const idpenerimaan = document.getElementById('idpenerimaan_edit').value;
    const items = [];

    document.querySelectorAll('#item-list-body tr').forEach(row => {
        items.push({
            idbarang: row.dataset.idbarang,
            jumlah: parseFloat(row.querySelector('.item-qty').value),
            harga: parseFloat(row.querySelector('.item-price').dataset.price)
        });
    });

    if (!idpengadaan || !tanggal || items.length === 0) {
        alert('Mohon pilih PO, lengkapi tanggal, dan pastikan ada barang yang diterima.');
        return;
    }

    const method = document.getElementById('formMethod').value || 'POST';
    const payload = { idpenerimaan, idpengadaan, tanggal, items };

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...payload, _method: method })
        });

        const result = await response.json();
        alert(result.message);

        if (result.success) {
            resetForm();
            loadPenerimaan(); // Muat ulang tabel penerimaan setelah berhasil menyimpan
        }
    } catch (error) {
        alert('Terjadi kesalahan: ' + error.message);
    }
});
</script>

</body>
</html>