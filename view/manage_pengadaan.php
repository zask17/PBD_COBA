<?php
require_once '../model/koneksi.php';
require_once '../model/auth.php';
checkAuth();

// Ambil data user yang sedang login untuk ditampilkan di form
$loggedInUsername = htmlspecialchars($_SESSION['username'] ?? 'N/A');
$loggedInUserId = htmlspecialchars($_SESSION['user_id'] ?? '0');
$loggedInRoleId = $_SESSION['role_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengadaan Barang (PO) - Sistem Inventory</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link rel="stylesheet" href="../css/dashboard_super_admin.css"> 
    <link rel="stylesheet" href="../css/pengadaan.css">
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
                        <p>Manajemen Pengadaan Barang (Purchase Order)</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem;">
                    <?php if ($loggedInRoleId == 2): ?>
                        <a href="dashboard_user.php" class="btn btn-secondary"><span>⬅️</span> Kembali ke Dashboard</a>
                    <?php else: ?>
                        <!-- <a href="datamaster.php" class="btn btn-secondary"><span>⚙️</span> Menu Utama</a> -->
                    <?php endif; ?>
                    <a href="../model/auth.php?action=logout" class="btn btn-danger"><span>🚪</span> Keluar</a>
                </div>
            </div>
        </header>

        <div class="container">
            
            <div class="card transaction-form form-section">
                <form id="formPengadaan">
                    <input type="hidden" id="idpengadaan" name="idpengadaan">
                    <input type="hidden" id="formMethod" name="_method" value="POST"> <div class="form-header">
                        <h2 id="formTitle" style="margin-bottom: 20px;">Buat Pengadaan Baru</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="idvendor">Vendor *</label>
                                <select id="idvendor" name="idvendor" required>
                                    <option value="">Memuat vendor...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Dibuat Oleh</label>
                                <input type="text" id="username" name="username" value="<?php echo $loggedInUsername; ?>" readonly>
                                <input type="hidden" id="iduser" name="iduser" value="<?php echo $loggedInUserId; ?>">
                            </div>
                            <div class="form-group">
                                <label for="tanggal">Tanggal Pengadaan *</label>
                                <input type="date" id="tanggal" name="tanggal" required>
                            </div>
                        </div>
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
                            <div class="form-group" style="align-self: flex-end;">
                                <button type="button" id="btn-tambah-barang" class="btn btn-primary">Tambah ke Daftar</button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="item-list-table">
                            <thead>
                                <tr>
                                    <th>Nama Barang</th>
                                    <th width="15%">Jumlah</th>
                                    <th width="20%">Harga Beli</th>
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
                            <span>Total (Termasuk PPN 10%): </span>
                            <span id="grand-total">Rp 0</span>
                        </div>
                        <div style="padding: 28px 0 0 0; border-top: none; display:flex; gap:1rem;">
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">Batal/Reset</button>
                            <button type="button" id="btn-finalize" class="btn btn-success" style="display: none;" onclick="finalizePengadaan()">Tutup Pengadaan (Cancel)</button>
                            <button type="submit" class="btn btn-primary">Simpan Pengadaan</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Daftar Pengadaan</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tablePengadaan">
                            <thead>
                                <tr>
                                    <th>ID PO</th>
                                    <th>Tanggal</th>
                                    <th>Vendor</th>
                                    <th>Dibuat Oleh</th>
                                    <th>Total Nilai</th>
                                    <th>Sisa Penerimaan</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr><td colspan="8" style="text-align: center;">Memuat data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
const API_URL = '../model/pengadaan.php';
// Konstanta PPN
const PPN_RATE = 0.11;

document.addEventListener('DOMContentLoaded', () => {
    loadInitialData();
    // Set tanggal default hari ini
    document.getElementById('tanggal').valueAsDate = new Date(); 

    // Cek parameter URL untuk edit
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit_id');
    if (editId) {
        editPengadaan(editId);
    }
});

const formatRupiah = (number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number || 0);

async function fetchData(params = '') {
    try {
        const response = await fetch(`${API_URL}${params}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error('Fetch error:', error);
        alert('Gagal memuat data: ' + error.message);
        return { success: false, data: [] };
    }
}

async function loadInitialData() {
    loadMasterData();
    loadPengadaanList();
}

// Memuat Vendor dan Barang untuk Form
async function loadMasterData() {
    const result = await fetchData('?list_data=true');
    if (result.success) {
        // Populate Vendors
        const vendorSelect = document.getElementById('idvendor');
        vendorSelect.innerHTML = '<option value="">Pilih Vendor</option>' + 
            result.vendors.map(v => `<option value="${v.idvendor}">${v.nama_vendor}</option>`).join('');

        // Populate Barang
        const barangSelect = document.getElementById('select-barang');
        barangSelect.innerHTML = '<option value="">Pilih Barang</option>' + 
            result.barangs.map(item => {
                return `<option value='${JSON.stringify(item)}'>${item.nama} (${formatRupiah(item.harga)})</option>`;
            }).join('');
    }
}

// Memuat Daftar Pengadaan ke Tabel
async function loadPengadaanList() {
    const result = await fetchData();
    const tbody = document.getElementById('tableBody');
    if (result.success && result.data.length > 0) {
        tbody.innerHTML = result.data.map(po => {
            const sisaDipesan = po.total_dipesan - po.total_diterima;
            return `
                <tr>
                    <td>PO-${po.idpengadaan}</td>
                    <td>${new Date(po.tanggal).toLocaleDateString('id-ID')}</td>
                    <td>${po.nama_vendor}</td>
                    <td>${po.username}</td>
                    <td>${formatRupiah(po.total_nilai)}</td>
                    <td>${sisaDipesan} item</td>
                    <td>${getStatusBadgeList(po.display_status)}</td>
                    <td>
                        <button type="button" class="btn btn-primary btn-sm" onclick="editPengadaan(${po.idpengadaan})">Edit</button>
                        ${po.db_status === 'P' ? 
                            `<button type="button" class="btn btn-danger btn-sm" onclick="deletePengadaan(${po.idpengadaan})">Hapus</button>` 
                            : ''}
                    </td>
                </tr>
            `;
        }).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">Tidak ada data pengadaan.</td></tr>';
    }
}

function getStatusBadgeList(status) {
    switch (status) {
        case 'C':
            return `<span class="badge badge-danger">Dibatalkan</span>`;
        case 'F':
            return `<span class="badge badge-success">Diterima Penuh</span>`;
        case 'S':
            return `<span class="badge badge-warning">Sebagian Diterima</span>`;
        case 'P':
            return `<span class="badge badge-info">Proses</span>`; // Menggunakan badge-info untuk proses
        default:
            return `<span class="badge badge-secondary">${status}</span>`;
    }
}

document.getElementById('btn-tambah-barang').addEventListener('click', () => {
    const select = document.getElementById('select-barang');
    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption.value) {
        alert('Silakan pilih barang terlebih dahulu.');
        return;
    }
    const jumlah = document.getElementById('jumlah-barang').value;
    addItem(JSON.parse(selectedOption.value), jumlah);
});

function addItem(item, jumlah = 1) {
    const itemListBody = document.getElementById('item-list-body');
    if (document.querySelector(`tr[data-idbarang="${item.idbarang}"]`)) {
        alert('Barang sudah ada di dalam daftar.');
        return;
    }
    const row = document.createElement('tr');
    row.setAttribute('data-idbarang', item.idbarang);
    const subtotal = jumlah * item.harga;
    row.innerHTML = `
        <td>${item.nama}</td>
        <td><input type="number" class="item-qty" value="${jumlah}" min="1" oninput="updateTotals()" style="text-align: center;"></td>
        <td class="item-price" data-price="${item.harga}">${formatRupiah(item.harga)}</td>
        <td class="item-subtotal">${formatRupiah(subtotal)}</td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); updateTotals();">Hapus</button></td>
    `;
    itemListBody.appendChild(row);
    document.getElementById('select-barang').selectedIndex = 0;
    document.getElementById('jumlah-barang').value = 1;
    updateTotals();
}

function updateTotals() {
    let subTotal = 0;
    document.querySelectorAll('#item-list-body tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').dataset.price) || 0;
        const subtotal_item = qty * price;
        subTotal += subtotal_item;
        row.querySelector('.item-subtotal').textContent = formatRupiah(subtotal_item);
    });
    
    // Hitung Total Nilai (Subtotal + PPN)
    const ppn = subTotal * PPN_RATE;
    const grandTotal = subTotal + ppn;

    // Display Grand Total
    document.getElementById('grand-total').textContent = formatRupiah(grandTotal);
}

function resetForm() {
    document.getElementById('formPengadaan').reset();
    document.getElementById('idpengadaan').value = '';
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('tanggal').valueAsDate = new Date();
    document.getElementById('item-list-body').innerHTML = '';
    document.getElementById('formTitle').textContent = 'Buat Pengadaan Baru';
    document.querySelector('#formPengadaan button[type="submit"]').textContent = 'Simpan Pengadaan';
    document.getElementById('btn-finalize').style.display = 'none';
    updateTotals();
}

document.getElementById('formPengadaan').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const formMethod = document.getElementById('formMethod').value;

    const items = [];
    document.querySelectorAll('#item-list-body tr').forEach(row => {
        items.push({
            idbarang: row.dataset.idbarang,
            jumlah: parseFloat(row.querySelector('.item-qty').value),
            harga: parseFloat(row.querySelector('.item-price').dataset.price)
        });
    });

    if (items.length === 0) {
        alert('Mohon tambahkan minimal satu barang.');
        return;
    }

    const payload = {
        idpengadaan: formData.get('idpengadaan'),
        idvendor: formData.get('idvendor'),
        tanggal: formData.get('tanggal'),
        items: items,
        _method: formMethod // Tambahkan method untuk penanganan di backend
    };
    
    // Konfirmasi sebelum menyimpan/update
    const actionText = formMethod === 'PUT' ? 'memperbarui' : 'menyimpan';
    if (!confirm(`Yakin ingin ${actionText} Pengadaan ini?`)) return;

    try {
        const response = await fetch(API_URL, {
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        alert(result.message);
        if (result.success) {
            resetForm();
            loadPengadaanList();
        }
    } catch (error) {
        alert('Terjadi kesalahan: ' + error.message);
    }
});

async function editPengadaan(id) {
    const result = await fetchData(`?id=${id}`);
    if (result.success) {
        const po = result.data;
        resetForm();

        document.getElementById('formTitle').textContent = `Edit Pengadaan PO-${po.idpengadaan}`;
        document.getElementById('idpengadaan').value = po.idpengadaan;
        document.getElementById('tanggal').value = po.tanggal;
        document.getElementById('idvendor').value = po.idvendor;
        document.querySelector('#formPengadaan button[type="submit"]').textContent = 'Perbarui Pengadaan';

        // Jika status sudah C (Cancel), form hanya untuk view
        if (po.status === 'C') {
             alert(`Pengadaan PO-${id} sudah Dibatalkan dan tidak dapat diedit.`);
             document.getElementById('formPengadaan').querySelectorAll('input, select, button').forEach(el => el.disabled = true);
             document.getElementById('btn-finalize').style.display = 'none';
             return;
        }

        // Jika sudah ada penerimaan, tidak bisa diedit
        if (po.details.some(item => item.total_diterima > 0)) {
            alert(`Pengadaan PO-${id} sudah ada barang diterima (${po.details.filter(item => item.total_diterima > 0).length} item). Tidak dapat diubah/dihapus.`);
            document.getElementById('formPengadaan').querySelectorAll('input, select, button[type="submit"]').forEach(el => el.disabled = true);
            document.getElementById('btn-finalize').style.display = 'inline-flex';
            document.getElementById('formMethod').value = ''; // Non-editable, jadi method dikosongkan
            return;
        } else {
            document.getElementById('formMethod').value = 'PUT';
            document.getElementById('formPengadaan').querySelectorAll('input, select, button').forEach(el => el.disabled = false);
            document.getElementById('btn-finalize').style.display = 'inline-flex';
        }
        
        const itemListBody = document.getElementById('item-list-body');
        itemListBody.innerHTML = '';
        po.details.forEach(item => {
            const row = document.createElement('tr');
            row.setAttribute('data-idbarang', item.idbarang);
            row.innerHTML = `
                <td>${item.nama_barang}</td>
                <td><input type="number" class="item-qty" value="${item.jumlah}" min="1" oninput="updateTotals()" style="text-align: center;"></td>
                <td class="item-price" data-price="${item.harga_satuan}">${formatRupiah(item.harga_satuan)}</td>
                <td class="item-subtotal">${formatRupiah(item.sub_total)}</td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); updateTotals();" ${po.details.some(d => d.total_diterima > 0) ? 'disabled' : ''}>Hapus</button></td>
            `;
            itemListBody.appendChild(row);
        });

        updateTotals();
        window.scrollTo(0, 0);
    }
}

async function deletePengadaan(id) {
    if (!confirm(`Yakin ingin menghapus Pengadaan PO-${id}? Aksi ini tidak dapat dibatalkan.`)) return;

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ idpengadaan: id, _method: 'DELETE' })
        });
        const result = await response.json();
        alert(result.message);
        if (result.success) {
            resetForm();
            loadPengadaanList();
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function finalizePengadaan() {
    const idpengadaan = document.getElementById('idpengadaan').value;
    if (!idpengadaan) {
        alert('ID Pengadaan tidak ditemukan.');
        return;
    }

    if (!confirm(`Anda yakin ingin memfinalisasi (menutup) Pengadaan PO-${idpengadaan}? Status akan diubah menjadi 'Dibatalkan'.`)) return;

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ idpengadaan: idpengadaan, action: 'finalize' })
        });
        const result = await response.json();
        alert(result.message);
        if (result.success) {
            resetForm();
            loadPengadaanList();
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}
</script>
</body>
</html>