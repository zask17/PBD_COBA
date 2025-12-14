<?php
require_once '../model/koneksi.php';
require_once '../model/auth.php';

checkAuth();

// Menggunakan 'iduser' untuk konsistensi, tetapi menggunakan 'username' untuk tampilan
$loggedInUsername = htmlspecialchars($_SESSION['username'] ?? 'N/A'); 

// Tentukan URL kembali berdasarkan role
$user_role = $_SESSION['role'] ?? 'Guest';
$dashboard_url = '';
if ($user_role === 'super administrator') {
    $dashboard_url = 'dashboard_super_admin.php';
} else if ($user_role === 'administrator') {
    $dashboard_url = 'dashboard_admin.php';
}


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penerimaan Barang - Sistem Inventory</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard_super_admin.css">
    <link rel="stylesheet" href="../css/penerimaan.css">
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
                    <?php if (!empty($dashboard_url)): ?>
                        <a href="<?php echo $dashboard_url; ?>" class="btn btn-secondary"><span>Kembali ke Dashboard</span></a>
                    <?php endif; ?>
                    <a href="manage_pengadaan.php" class="btn btn-secondary"><span>Ke Pengadaan</span></a>
                    <a href="../model/auth.php?action=logout" class="btn btn-danger"><span>Keluar</span></a>
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
                        <h2 style="margin-bottom: 20px;">Input Penerimaan Barang</h2>
                        
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
                                    <th width="15%" style="text-align: center;">Jml. Dipesan</th>
                                    <th width="15%" style="text-align: center;">Total Sudah Terima</th>
                                    <th width="15%" style="text-align: center;">Sisa Belum Terima</th>
                                    <th width="15%" style="text-align: center;">Jml. Diterima Saat Ini</th>
                                    <th width="20%" style="text-align: right;">Harga Satuan PO</th>
                                    <th width="20%" style="text-align: right;">Subtotal Terima</th>
                                </tr>
                            </thead>
                            <tbody id="item-list-body">
                                <tr><td colspan="7" style="text-align: center;">Silakan pilih PO untuk melihat item.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-footer">
                        <div class="total-section">
                            <span>Total Nilai Diterima: </span>
                            <span id="grand-total">Rp 0</span>
                        </div>
                        <div style="padding: 28px 0 0 0; border-top: none; display:flex; gap:1rem;">
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">Batal/Reset</button>
                            <button type="submit" class="btn btn-primary">Simpan Penerimaan</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>Daftar Penerimaan</h2>
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
                                    <th style="text-align: center;">Status</th>
                                    <th style="text-align: center;">Aksi</th>
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

    <div id="modalDetailPenerimaan" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 id="detailModalTitle">Detail Penerimaan</h3>
                <button class="close" onclick="closeDetailModal()">&times;</button>
            </div>
            <div class="modal-body" style="padding: 28px;">
                <div class="form-row" style="margin-bottom: 20px; background: #f7f7f7; padding: 16px; border-radius: 8px;">
                    <div class="form-group"><label>ID Penerimaan:</label>
                        <p id="detailIdTerima" class="modal-info"></p>
                    </div>
                    <div class="form-group"><label>ID Pengadaan (PO):</label>
                        <p id="detailIdPO" class="modal-info"></p>
                    </div>
                    <div class="form-group"><label>Vendor:</label>
                        <p id="detailVendor" class="modal-info"></p>
                    </div>
                    <div class="form-group"><label>Tanggal Terima:</label>
                        <p id="detailTanggal" class="modal-info"></p>
                    </div>
                    <div class="form-group"><label>Status PO:</label>
                        <p id="detailStatusPO" class="modal-info"></p>
                    </div>
                </div>

                <div class="table-responsive">
                    <h4 style="margin-bottom: 10px; color: var(--text-color-dark);">Item yang Diterima</h4>
                    <table id="detail-item-list-table">
                        <thead>
                            <tr>
                                <th>Nama Barang</th>
                                <th width="15%" style="text-align: right;">Jml. Diterima</th>
                                <th width="20%" style="text-align: right;">Harga Satuan Terima</th>
                                <th width="20%" style="text-align: right;">Subtotal Terima</th>
                            </tr>
                        </thead>
                        <tbody id="detail-item-list-body"></tbody>
                    </table>
                </div>
                <div class="total-section" style="margin-top: 20px;">
                    </div>
            </div>
        </div>
    </div>


<script>
const API_URL = '../model/penerimaan.php'; 
// PPN RATE dari pengadaan.php
const PPN_RATE = 0.10; 

document.addEventListener('DOMContentLoaded', () => {
    loadOpenPOs();
    loadPenerimaan();
    document.getElementById('tanggal').valueAsDate = new Date();
});

const formatRupiah = (number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number || 0);

// --- UTILITY FUNCTIONS ---
function showLoading(state) {
    const loadingElement = document.getElementById('loading-indicator');
    if (loadingElement) {
        // Menggunakan ternary operator untuk display
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
            option.textContent = `PO# ${po.idpengadaan} (${po.status_teks}) - ${po.nama_vendor} (${tanggal})`;
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
            // Status PO dari pengadaan
            const statusMap = {
                'p': { text: 'Dipesan', class: 'badge-info' }, 
                's': { text: 'Sebagian', class: 'badge-warning' }, 
                'f': { text: 'Selesai', class: 'badge-success' },
                'c': { text: 'Batal', class: 'badge-secondary' }
            };
            const statusInfo = statusMap[p.po_status] || { text: p.po_status, class: 'badge-secondary' };
            const tanggal = new Date(p.created_at).toLocaleDateString('id-ID');

            return `
                <tr>
                    <td>${p.idpenerimaan}</td>
                    <td>PO-${p.idpengadaan}</td>
                    <td>${p.username}</td>
                    <td>${tanggal}</td>
                    <td><span class="badge ${statusInfo.class}">${statusInfo.text}</span></td>
                    <td class="action-buttons" style="text-align: center;">
                        <button class="btn btn-secondary btn-sm" onclick="event.stopPropagation(); viewPenerimaanDetails('${p.idpenerimaan}')">Lihat Detail</button>
                        <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); editPenerimaan('${p.idpenerimaan}')">Edit</button>
                    </td>
                </tr>
            `;
        }).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Belum ada data penerimaan.</td></tr>';
    }
}

// ============================================
// VIEW DETAIL PENERIMAAN (POP UP)
// ============================================
async function viewPenerimaanDetails(idpenerimaan) {
    // 1. Ambil detail penerimaan
    const result_terima = await fetchData(`${API_URL}?action=get_penerimaan_details&id=${idpenerimaan}`);
    if (!result_terima.success || !result_terima.data) {
        alert('Gagal memuat detail penerimaan.');
        return;
    }

    const terima = result_terima.data;
    const idpengadaan = terima.header.idpengadaan;
    
    // 2. Ambil detail PO untuk mendapatkan PPN dan total nilai PO
    const PO_API_URL = '../model/pengadaan.php';
    const result_po = await fetchData(`${PO_API_URL}?id=${idpengadaan}`);

    if (!result_po.success || !result_po.data) {
        alert('Gagal memuat detail Pengadaan (PO) terkait.');
        return;
    }
    
    const po = result_po.data;
    const detailBody = document.getElementById('detail-item-list-body');
    const totalSectionContainer = document.querySelector('#modalDetailPenerimaan .modal-body .total-section');
    let totalSubtotalTerima = 0;

    // --- Isi Header Modal ---
    document.getElementById('detailModalTitle').textContent = `Detail Penerimaan ID-${idpenerimaan}`;
    document.getElementById('detailIdTerima').textContent = idpenerimaan;
    document.getElementById('detailIdPO').textContent = `PO-${idpengadaan}`;
    document.getElementById('detailVendor').textContent = po.nama_vendor;
    document.getElementById('detailTanggal').textContent = new Date(terima.header.created_at).toLocaleDateString('id-ID');
    // NOTE: po.status adalah status DB ('p', 's', 'f', 'c'). Kita gunakan getStatusBadge untuk tampilan yang konsisten.
    document.getElementById('detailStatusPO').innerHTML = getStatusBadge(po.status); 

    // --- Isi Item Diterima ---
    detailBody.innerHTML = '';
    terima.details.forEach(item => {
        const subtotal = item.jumlah * item.harga_satuan;
        totalSubtotalTerima += subtotal; 
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${item.nama_barang}</td>
            <td style="text-align: right;">${item.jumlah}</td>
            <td style="text-align: right;">${formatRupiah(item.harga_satuan)}</td>
            <td style="text-align: right;">${formatRupiah(subtotal)}</td>
        `;
        detailBody.appendChild(row);
    });

    // --- Hitung dan Isi Total Section (Konsisten dengan PO) ---
    
    // 1. Subtotal PO (Nilai sebelum PPN, dari data PO)
    const poSubtotal = po.subtotal_nilai; // Ambil subtotal langsung dari data PO
    const poPPN = po.ppn; // Ambil PPN langsung dari data PO
    const poGrandTotal = po.total_nilai; // Ambil Total Akhir langsung dari data PO
    
    // Reset Total Section
    totalSectionContainer.innerHTML = ''; 

    // 1. Total Nilai Item Diterima (Subtotal Penerimaan)
    totalSectionContainer.innerHTML += `
        <div class="modal-total-line">
            <span>Subtotal Nilai Diterima:</span>
            <span style="color: var(--accent-green);">${formatRupiah(totalSubtotalTerima)}</span>
        </div>
    `;

    // 2. Garis Pemisah untuk PO Value
    totalSectionContainer.innerHTML += `<hr style="border-top: 1px dashed #ccc;">`;


    // 3. Subtotal Nilai PO
    totalSectionContainer.innerHTML += `
        <div class="modal-total-line">
            <span>Subtotal Nilai PO:</span>
            <span>${formatRupiah(poSubtotal)}</span>
        </div>
    `;

    // 4. PPN PO 10%
    totalSectionContainer.innerHTML += `
        <div class="modal-total-line">
            <span>PPN PO (${PPN_RATE * 100}%):</span>
            <span>${formatRupiah(poPPN)}</span>
        </div>
    `;

    // 5. Total Akhir PO (Grand Total)
    totalSectionContainer.innerHTML += `
        <div class="modal-total-line final" style="border-top: 1px solid #eee; padding-top: 5px;">
            <span>Total Akhir PO:</span>
            <span id="detail-grand-total">${formatRupiah(poGrandTotal)}</span>
        </div>
    `;
    
    // Show Modal
    document.getElementById('modalDetailPenerimaan').classList.add('show');
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
        itemListBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Silakan pilih PO untuk melihat item.</td></tr>';
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
            const sisaDipesan = item.jumlah_dipesan - item.total_diterima;
            const totalReceived = item.total_diterima; 

            if (sisaDipesan > 0) {
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
        itemListBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Gagal memuat detail PO.</td></tr>';
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
    
    const sisaBelumTerima = item.jumlah_dipesan - totalReceived;

    row.innerHTML = `
        <td>${item.nama_barang} (${item.idbarang})</td>
        <td style="text-align: center;">${item.jumlah_dipesan}</td>
        <td style="text-align: center;">${totalReceived}</td>
        <td style="text-align: center;">${sisaBelumTerima}</td>
        <td>
            <input type="number" class="item-qty form-control" value="${currentQty}" min="0" ${maxAttr} oninput="updateItemAndTotals(this)" 
            ${titleAttr}>
        </td>
        <td class="item-price" data-price="${item.harga_satuan}" style="text-align: right;">${formatRupiah(item.harga_satuan)}</td>
        <td class="item-subtotal" style="text-align: right;">${formatRupiah(subtotal)}</td>
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
        // Saat edit, Jml. Dipesan, Total Sudah Terima, Sisa Belum Terima tidak diketahui, diisi 0/placeholder
        addItemRow(item, qtyReceived, null, 0); 
    });

    // Perlu diisi placeholder untuk Jml. Dipesan, Total Sudah Terima, Sisa Belum Terima saat edit
    document.querySelectorAll('#item-list-body tr').forEach(row => {
        row.children[1].textContent = '-'; // Jml. Dipesan
        row.children[2].textContent = '-'; // Total Sudah Terima
        row.children[3].textContent = '-'; // Sisa Belum Terima
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
    document.getElementById('item-list-body').innerHTML = '<tr><td colspan="7" style="text-align: center;">Silakan pilih PO untuk melihat item.</td></tr>';
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
                    harga: price // Harga satuan PO digunakan sebagai harga satuan terima
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

function getStatusBadge(status) {
    // Menggunakan logika badge dari manage_pengadaan.php
    switch (status) {
        case 'Closed/Batal':
        case 'c':
            return `<span class="badge badge-danger">Closed/Batal</span>`;
        case 'Diterima Penuh':
        case 'f':
            return `<span class="badge badge-success">Diterima Penuh</span>`;
        case 'Parsial':
        case 's':
            return `<span class="badge badge-warning">Parsial</span>`;
        case 'Dipesan':
        case 'p':
            return `<span class="badge badge-info">Dipesan (Proses)</span>`;
        default:
            return `<span class="badge badge-secondary">${status}</span>`;
    }
}


function closeDetailModal() {
    document.getElementById('modalDetailPenerimaan').classList.remove('show');
}

// Close detail modal if clicked outside
window.addEventListener('click', (event) => {
    if (event.target == document.getElementById('modalDetailPenerimaan')) {
        closeDetailModal();
    }
});
</script>
    <?php include 'footer.php'; ?>

</body>
</html>