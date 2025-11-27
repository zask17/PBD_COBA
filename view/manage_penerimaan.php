<?php
require_once '../model/koneksi.php';
require_once '../model/auth.php';

checkAuth();

// Menggunakan 'iduser' untuk konsistensi, tetapi menggunakan 'username' untuk tampilan
$loggedInUsername = htmlspecialchars($_SESSION['username'] ?? 'N/A'); 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penerimaan Barang - Sistem Inventory</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard_super_admin.css">
    <link rel="stylesheet" href="../css/pengadaan.css">
    
    <style>
        /* Variabel diambil dari style.css/pengadaan.css */
        :root {
            --primary-green: #1A5319;
            --secondary-color: #689F38; /* Ditambahkan untuk konsistensi */
            --accent-green: #689F38;
            --background-section: #E8F5E9;
            --text-color-dark: #333333;
        }

        /* Layout Umum Form */
        .card.transaction-form {
            padding: 0;
            margin-top: 30px;
            margin-bottom: 30px;
        }
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .form-group { flex: 1; display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 5px; font-weight: 600; font-size: 0.9em; color: var(--primary-green); }
        .form-group input, .form-group select { padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 1em; }
        
        /* Header/Footer Form */
        .form-header, .form-footer { padding: 20px; }
        .form-header { border-bottom: 1px solid #ddd; }
        .form-footer { border-top: 1px solid #ddd; }
        .form-footer > div:last-child { display: flex; gap: 1rem; padding-top: 20px; border-top: none; }

        /* Tabel Detail Item */
        #item-list-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        #item-list-table th, #item-list-table td { padding: 12px 20px; border-bottom: 1px solid #eee; }
        #item-list-table th { background-color: var(--background-section); color: var(--text-color-dark); font-size: 0.9em; text-align: center; }
        #item-list-table td { text-align: center; }
        #item-list-table td:first-child { text-align: left; } /* Nama barang di kiri */
        #item-list-table input { text-align: center; }

        /* Total Section */
        .total-section { text-align: right; font-size: 1.3rem; font-weight: 700; color: var(--primary-green); padding: 10px 0; }
        
        /* Button Styles */
        .btn-sm { padding: 5px 8px; font-size: 0.8em; }
        .btn-danger { background-color: #dc3545; color: white; border: none; }
        .btn-secondary { background-color: #6c757d; color: white; border: none; }
        .btn-primary { background-color: var(--primary-green); color: white; border: none; }
        .btn-success { background-color: var(--accent-green); color: white; border: none; }
        
        /* Badge Styles */
        .badge { padding: 5px 10px; border-radius: 4px; font-weight: 700; font-size: 0.8em; display: inline-block; }
        .badge-warning { background: rgba(255, 193, 7, 0.15); color: #ffc107; } 
        .badge-info { background: rgba(0, 123, 255, 0.15); color: #007bff; } 
        .badge-success { background: rgba(40, 167, 69, 0.15); color: #28a745; }
        .badge-secondary { background: rgba(108, 117, 125, 0.15); color: #6c757d; }
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
                        <p>Transaksi Penerimaan Barang</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem;">
                    <a href="manage_pengadaan.php" class="btn btn-secondary"><span>⬅️</span> Ke Pengadaan</a>
                    <a href="../model/auth.php?action=logout" class="btn btn-danger"><span>🚪</span> Keluar</a>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="card transaction-form">
                <form id="formPenerimaan">
                    <input type="hidden" id="idpenerimaan_edit" name="idpenerimaan">
                    <input type="hidden" id="formMethod" name="_method" value="POST">
                    <input type="hidden" id="idpengadaan" name="idpengadaan">
                    
                    <div class="form-header">
                        <h2 style="margin-bottom: 10px; color: var(--primary-green);">Input Penerimaan Barang</h2>
                        
                        <div class="form-group" style="flex: 1; margin-bottom: 10px;">
                            <label for="select-po">Pilih Pengadaan (PO) untuk Diterima</label>
                            <select id="select-po" onchange="handlePOSelection(event)"><option value="">Pilih PO...</option></select>
                        </div>
                        <div id="loading-indicator" style="display: none; text-align: center; color: var(--primary-green); font-weight: bold;">Memuat detail PO...</div>
                    </div>
                    
                    <div class="form-header">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nama_vendor">Vendor</label>
                                <input type="text" id="nama_vendor" name="nama_vendor" readonly required placeholder="Pilih PO untuk menampilkan vendor">
                            </div>
                            <div class="form-group">
                                <label for="tanggal">Tanggal Penerimaan *</label>
                                <input type="date" id="tanggal" name="tanggal" required>
                            </div>
                            <div class="form-group">
                                <label>Dibuat Oleh</label>
                                <input type="text" value="<?php echo $loggedInUsername; ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="item-list-table">
                            <thead>
                                <tr>
                                    <th>Nama Barang (ID)</th>
                                    <th width="15%">Jml. Dipesan</th>
                                    <th width="15%">Total Sudah Terima</th>
                                    <th width="15%">Sisa Belum Terima</th>
                                    <th width="15%">Jml. Diterima Saat Ini</th>
                                    <th width="20%">Harga Satuan PO</th>
                                    <th width="20%">Subtotal Terima</th>
                                </tr>
                            </thead>
                            <tbody id="item-list-body">
                                <tr><td colspan="7" class="text-center">Silakan pilih PO untuk melihat item.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-footer">
                        <div class="total-section">
                            <span>Total Nilai Diterima: </span>
                            <span id="grand-total">Rp 0</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">Batal/Reset</button>
                            <button type="submit" class="btn btn-primary">Simpan Penerimaan</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 style="color: var(--primary-green);">Daftar Penerimaan</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tablePenerimaan">
                            <thead>
                                <tr>
                                    <th>ID Terima</th>
                                    <th>ID PO</th>
                                    <th>Penerima</th>
                                    <th>Tanggal Terima</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
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
const API_URL = '../model/penerimaan.php'; 

document.addEventListener('DOMContentLoaded', () => {
    loadOpenPOs();
    loadPenerimaan();
    document.getElementById('tanggal').valueAsDate = new Date();
});

const formatRupiah = (number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);

// --- UTILITY FUNCTIONS ---
function showLoading(state) {
    const loadingElement = document.getElementById('loading-indicator');
    if (loadingElement) {
        loadingElement.style.display = state ? 'block' : 'none';
    }
}

async function fetchData(url, options = {}) {
    showLoading(true);
    try {
        const response = await fetch(url, options);
        showLoading(false);
        if (!response.ok) {
             const errorData = await response.json().catch(() => ({ message: `HTTP error! status: ${response.status}` }));
             throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        showLoading(false);
        console.error('Fetch error:', error);
        alert('Gagal memuat data: ' + error.message);
        return { success: false, data: [] };
    }
}

// --- PO & PENERIMAAN DATA HANDLING ---

async function loadOpenPOs() {
    const result = await fetchData(`${API_URL}?action=get_open_pos`);
    
    if (result.success) {
        const select = document.getElementById('select-po');
        const currentVal = document.getElementById('idpengadaan').value; 
        
        select.innerHTML = '<option value="">Pilih PO...</option>';

        result.data.forEach(po => {
            const tanggal = new Date(po.timestamp).toLocaleDateString('id-ID');
            const option = document.createElement('option');
            option.value = po.idpengadaan;
            option.textContent = `PO# ${po.idpengadaan} - ${po.nama_vendor} (${tanggal})`;
            option.setAttribute('data-vendor-name', po.nama_vendor);
            select.appendChild(option);
        });
 

        if (currentVal && document.getElementById('formMethod').value === 'PUT') {
            select.value = currentVal;
            select.disabled = true;
        } else {
             select.disabled = false;
        }

    }
}

async function loadPenerimaan() {
    const result = await fetchData(`${API_URL}?action=get_penerimaan`);
    const tbody = document.getElementById('tableBody');

    if (result.success && result.data.length > 0) {
        tbody.innerHTML = result.data.map(p => {
            const statusMap = {
                'P': { text: 'Proses', class: 'badge-info' }, 
                'S': { text: 'Sebagian', class: 'badge-warning' }, 
                'F': { text: 'Selesai', class: 'badge-success' },
                'C': { text: 'Batal', class: 'badge-secondary' } // Ditambahkan untuk konsistensi
            };
            const statusInfo = statusMap[p.status] || { text: p.status, class: 'badge-secondary' };
            const tanggal = new Date(p.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

            return `
                <tr>
                    <td>${p.idpenerimaan}</td>
                    <td>PO-${p.idpengadaan}</td>
                    <td>${p.username}</td>
                    <td>${tanggal}</td>
                    <td><span class="badge ${statusInfo.class}">${statusInfo.text}</span></td>
                    <td class="action-buttons">
                        <button class="btn btn-primary btn-sm" onclick="editPenerimaan('${p.idpenerimaan}')">Detail/Edit</button>
                    </td>
                </tr>
            `;
        }).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Belum ada data penerimaan.</td></tr>';
    }
}


/**
 * Handler saat PO dipilih dari dropdown.
 * Memuat detail item PO yang terkait dari backend.
 */
async function handlePOSelection(e) {
    const idpengadaan = e.target.value;
    const itemListBody = document.getElementById('item-list-body');
    const vendorInput = document.getElementById('nama_vendor');
    const poSelect = document.getElementById('select-po');
    const selectedOption = poSelect.options[poSelect.selectedIndex];
    
    // Reset jika tidak ada PO yang dipilih
    if (!idpengadaan) {
        vendorInput.value = '';
        vendorInput.placeholder = 'Pilih PO untuk menampilkan vendor';
        document.getElementById('idpengadaan').value = ''; 
        itemListBody.innerHTML = '<tr><td colspan="7" class="text-center">Silakan pilih PO untuk melihat item.</td></tr>';
        updateTotals();
        return;
    }

    const vendorName = selectedOption.getAttribute('data-vendor-name');

    const result = await fetchData(`${API_URL}?action=get_po_details&id=${idpengadaan}`);

    if (result.success && result.data) {
        const data = result.data;
        
        vendorInput.value = vendorName || 'N/A';
        document.getElementById('idpengadaan').value = idpengadaan;
        
        itemListBody.innerHTML = '';
        let itemsAvailable = false;

        data.forEach(item => {
            // Hitung Sisa Dipesan dari data yang dikirim backend
            const sisaDipesan = item.jumlah_dipesan - item.total_diterima;
            const totalReceived = item.total_diterima; // Sudah ada dari backend

            if (sisaDipesan > 0) {
                // Gunakan sisaDipesan sebagai nilai default dan maks input
                // Item kini harus memiliki item.jumlah_dipesan, item.total_diterima, item.harga_satuan
                addItemRow(item, sisaDipesan, sisaDipesan, totalReceived);
                itemsAvailable = true;
            }
        });

        if (!itemsAvailable) {
            alert('Semua item pada PO ini sudah diterima sepenuhnya atau PO sudah ditutup! Silakan pilih PO lain.');
            resetForm();
            return;
        }

        updateTotals();
    } else {
        alert('Gagal memuat detail PO atau PO tidak memiliki item.');
        itemListBody.innerHTML = '<tr><td colspan="7" class="text-center">Gagal memuat detail PO.</td></tr>';
    }
}

/**
 * Menambahkan baris item ke tabel.
 */
function addItemRow(item, currentQty, maxQty = null, totalReceived = 0) {
    const itemListBody = document.getElementById('item-list-body');
    
    if (document.querySelector(`tr[data-idbarang="${item.idbarang}"]`)) return; 

    const row = document.createElement('tr');
    row.setAttribute('data-idbarang', item.idbarang);
    
    const subtotal = currentQty * item.harga_satuan; 
    
    const maxAttr = maxQty !== null ? `max="${maxQty}"` : ''; 
    const titleAttr = maxQty !== null ? `title="Maksimal ${maxQty} item yang bisa diterima"` : `title="Jumlah diterima"`;

    row.innerHTML = `
        <td>${item.nama_barang} (${item.idbarang})</td>
        <td>${item.jumlah_dipesan}</td>
        <td>${totalReceived}</td>
        <td>${item.jumlah_dipesan - totalReceived}</td>
        <td>
            <input type="number" class="item-qty form-control" value="${currentQty}" min="0" ${maxAttr} oninput="updateItemAndTotals(this)" 
            ${titleAttr} style="width: 100px;">
        </td>
        <td class="item-price" data-price="${item.harga_satuan}">${formatRupiah(item.harga_satuan)}</td>
        <td class="item-subtotal">${formatRupiah(subtotal)}</td>
    `;
    itemListBody.appendChild(row);
}

function updateItemAndTotals(inputElement) {
    let qty = parseFloat(inputElement.value) || 0;
    const maxVal = inputElement.getAttribute('max');
    
    if (maxVal !== null) {
        const maxLimit = parseFloat(maxVal);
        if (qty > maxLimit) {
            alert(`Jumlah yang diterima tidak boleh melebihi sisa yang dipesan (${maxLimit}).`);
            qty = maxLimit;
            inputElement.value = qty;
        }
    }
    
    if (qty < 0) {
        qty = 0;
        inputElement.value = 0;
    }
    
    const row = inputElement.closest('tr');
    const price = parseFloat(row.querySelector('.item-price').dataset.price) || 0;
    const subtotal = qty * price;
    
    row.querySelector('.item-subtotal').textContent = formatRupiah(subtotal);
    
    updateTotals();
}

function updateTotals() {
    let grandTotal = 0;
    document.querySelectorAll('#item-list-body tr').forEach(row => {
        const qtyElement = row.querySelector('.item-qty');
        if (qtyElement) {
            const qty = parseFloat(qtyElement.value) || 0;
            const price = parseFloat(row.querySelector('.item-price').dataset.price) || 0;
            const subtotal = qty * price;
            grandTotal += subtotal;
        }
    });
    document.getElementById('grand-total').textContent = formatRupiah(grandTotal);
}

// --- EDIT HANDLER ---

async function editPenerimaan(idpenerimaan) {
    const result = await fetchData(`${API_URL}?action=get_penerimaan_details&id=${idpenerimaan}`);
    if (!result.success || !result.data) {
        alert('Gagal memuat data penerimaan untuk diedit.');
        return;
    }

    resetForm(); 

    const data = result.data;
    const itemListBody = document.getElementById('item-list-body');
    const poSelect = document.getElementById('select-po');

    // 1. Isi Header Form
    document.getElementById('idpengadaan').value = data.header.idpengadaan;
    document.getElementById('idpenerimaan_edit').value = idpenerimaan;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('tanggal').value = data.header.created_at; 
    document.querySelector('#formPenerimaan button[type="submit"]').textContent = 'Perbarui Penerimaan';

    // 2. Isi Vendor dan Nonaktifkan PO Selection
    await loadOpenPOs(); 
    poSelect.value = data.header.idpengadaan;
    poSelect.disabled = true;
    document.getElementById('nama_vendor').value = data.header.nama_vendor;
    
    // 3. Muat Item (tanpa batasan max)
    itemListBody.innerHTML = '';
    data.details.forEach(item => {
        const qtyReceived = parseFloat(item.jumlah); 
        addItemRow(item, qtyReceived, null, 0); 
    });

    updateTotals();
    window.scrollTo(0, 0); 
}

// --- FORM SUBMISSION ---

function resetForm() {
    document.getElementById('formPenerimaan').reset();
    document.getElementById('idpengadaan').value = '';
    document.getElementById('idpenerimaan_edit').value = '';
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('tanggal').valueAsDate = new Date();
    document.getElementById('item-list-body').innerHTML = '<tr><td colspan="7" class="text-center">Silakan pilih PO untuk melihat item.</td></tr>';
    document.getElementById('select-po').disabled = false;
    document.getElementById('nama_vendor').value = '';
    document.getElementById('nama_vendor').placeholder = 'Pilih PO untuk menampilkan vendor';
    document.querySelector('#formPenerimaan button[type="submit"]').textContent = 'Simpan Penerimaan';
    updateTotals();
    loadOpenPOs();
}

document.getElementById('formPenerimaan').addEventListener('submit', async (e) => {
    e.preventDefault();

    const tanggal = document.getElementById('tanggal').value;
    const idpengadaan = document.getElementById('idpengadaan').value;
    const idpenerimaan = document.getElementById('idpenerimaan_edit').value;
    const formMethod = document.getElementById('formMethod').value || 'POST';
    
    const items = [];
    let grandTotal = 0;

    document.querySelectorAll('#item-list-body tr').forEach(row => {
        const qtyElement = row.querySelector('.item-qty');
        if (qtyElement) {
            const qty = parseFloat(qtyElement.value);
            const price = parseFloat(row.querySelector('.item-price').dataset.price);
            
            if (qty > 0) {
                items.push({
                    idbarang: row.dataset.idbarang,
                    jumlah: qty,
                    harga: price 
                });
                grandTotal += qty * price;
            }
        }
    });

    if (!idpengadaan || !tanggal) {
        alert('Mohon pilih PO dan lengkapi tanggal.');
        return;
    }
    
    if (items.length === 0) {
        alert('Pastikan minimal ada satu barang yang diterima (jumlah > 0).');
        return;
    }

    const payload = { 
        idpenerimaan: formMethod === 'PUT' ? idpenerimaan : undefined, 
        idpengadaan, 
        tanggal, 
        items 
    };
    
    const actionText = formMethod === 'PUT' ? 'memperbarui' : 'menyimpan';
    if (!confirm(`Yakin ingin ${actionText} Penerimaan senilai ${formatRupiah(grandTotal)}?`)) return;

    try {
        const response = await fetch(API_URL, {
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...payload, _method: formMethod })
        });

        const result = await response.json();
        alert(result.message);

        if (result.success) {
            resetForm();
            loadPenerimaan();
        }
    } catch (error) {
        alert('Terjadi kesalahan saat berkomunikasi dengan server: ' + error.message);
    }
});
</script>

</body>
</html>