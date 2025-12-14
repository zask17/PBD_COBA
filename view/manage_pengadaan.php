<?php
// manage_pengadaan.php
// Pastikan path ke auth.php dan dbconnect.php sesuai dengan struktur file Anda
require_once '../model/koneksi.php';
require_once '../model/auth.php';

checkAuth();

// Ambil data user yang sedang login untuk ditampilkan di form
$loggedInUsername = htmlspecialchars($_SESSION['username'] ?? 'N/A');
$loggedInUserId = htmlspecialchars($_SESSION['iduser'] ?? '0');
$loggedInRoleId = $_SESSION['role_id'] ?? 0;


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
    <title>Pengadaan Barang (PO) - Sistem Inventory</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard_super_admin.css">
    <link rel="stylesheet" href="../css/pengadaan.css">
    <style>
        /* Tambahkan CSS untuk badge status dan modal jika belum ada di file CSS Anda */
        .badge {
            display: inline-block;
            padding: 0.25em 0.4em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            color: #fff;
        }

        .badge-danger {
            background-color: #dc3545;
        }

        .badge-success {
            background-color: #28a745;
        }

        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }

        .badge-info {
            background-color: #17a2b8;
        }

        .badge-secondary {
            background-color: #6c757d;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal.show {
            display: block;
        }

        .modal-content {
            background-color: #ffffff;
            margin: 10% auto;
            padding: 0;
            border: 1px solid #3a4254;
            width: 90%;
            max-width: 800px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.5);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 28px;
            border-bottom: 1px solid #3a4254;
        }

        .modal-header h3 {
            margin: 0;
            color: #333333;
        }

        .modal-header .close {
            color: #8b92a7;
            font-size: 28px;
            font-weight: bold;
            background: none;
            border: none;
            cursor: pointer;
        }

        .modal-info {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333; /* Warna diubah agar terlihat di latar putih */
            margin-top: 5px;
        }

        .modal-body label {
            color: #8b92a7;
            font-size: 0.9rem;
            margin-bottom: 5px;
            display: block;
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
                        <p>Manajemen Pengadaan Barang (Purchase Order)</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem;">
                    <?php if (!empty($dashboard_url)): ?>
                        <a href="<?php echo $dashboard_url; ?>" class="btn btn-secondary"><span> Kembali ke Dashboard</span></a>
                    <?php endif; ?>
                    <a href="../model/auth.php?action=logout" class="btn btn-danger"><span>Keluar</span></a>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="card transaction-form form-section">
                <form id="formPengadaan">
                    <input type="hidden" id="idpengadaan" name="idpengadaan">
                    <input type="hidden" id="formMethod" name="_method" value="POST">

                    <div class="form-header">
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
                            <tbody id="item-list-body"></tbody>
                        </table>
                    </div>

                    <div class="form-footer">
                        <div class="total-section">
                            <span>Total Nilai: </span>
                            <span id="grand-total">Rp 0</span>
                            <small style="display: block; color: #8b92a7;">(Sudah termasuk PPN 10%)</small>
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
                                <tr>
                                    <td colspan="8" style="text-align: center;">Memuat data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="modalDetail" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 id="detailModalTitle">Detail Pengadaan</h3>
                <button class="close" onclick="closeDetailModal()">&times;</button>
            </div>
            <div class="modal-body" style="padding: 28px;">
                <div class="form-row" style="margin-bottom: 20px; background: #f7f7f7; padding: 16px; border-radius: 8px;">
                    <div class="form-group"><label>ID Pengadaan:</label>
                        <p id="detailIdPO" class="modal-info"></p>
                    </div>
                    <div class="form-group"><label>Vendor:</label>
                        <p id="detailVendor" class="modal-info"></p>
                    </div>
                    <div class="form-group"><label>Tanggal:</label>
                        <p id="detailTanggal" class="modal-info"></p>
                    </div>
                    <div class="form-group"><label>Dibuat Oleh:</label>
                        <p id="detailUser" class="modal-info"></p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="detail-item-list-table">
                        <thead>
                            <tr>
                                <th>Nama Barang</th>
                                <th width="15%">Jumlah</th>
                                <th width="20%">Harga Beli</th>
                                <th width="20%">Subtotal</th>
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
        const API_URL = '../model/pengadaan.php';
        // PPN 10% sesuai SP: sp_hitung_dan_finalisasi_pengadaan
        const PPN_RATE = 0.10;
        let masterBarang = []; // Global variable untuk menyimpan daftar barang

        document.addEventListener('DOMContentLoaded', () => {
            loadInitialData();
            document.getElementById('tanggal').valueAsDate = new Date();
        });

        const formatRupiah = (number) => new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(number || 0);

        async function loadInitialData() {
            await loadMasterData();
            loadPengadaanList();
        }

        async function fetchData(params = '') {
            try {
                const response = await fetch(`${API_URL}${params}`);
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! status: ${response.status} - ${errorText}`);
                }
                return await response.json();
            } catch (error) {
                console.error('Fetch error:', error);
                alert('Gagal memuat data: ' + error.message);
                return {
                    success: false,
                    data: []
                };
            }
        }

        async function loadMasterData() {
            const result = await fetchData('?list_data=true');
            if (result.success) {
                // masterBarang hanya berisi barang tanpa info vendor dari tabel barang_vendor
                masterBarang = result.barangs;

                // Populate Vendors
                const vendorSelect = document.getElementById('idvendor');
                vendorSelect.innerHTML = '<option value="">Pilih Vendor</option>' +
                    result.vendors.map(v => `<option value="${v.idvendor}">${v.nama_vendor}</option>`).join('');

                // Populate Barang
                const barangSelect = document.getElementById('select-barang');
                barangSelect.innerHTML = '<option value="">Pilih Barang</option>' +
                    masterBarang.map(item => {
                        // Item value hanya berisi data pokok barang
                        const itemData = {
                            idbarang: item.idbarang,
                            nama: item.nama,
                            harga: item.harga
                        };
                        return `<option value='${JSON.stringify(itemData)}'>${item.nama} - ${formatRupiah(item.harga)}</option>`;
                    }).join('');
            }
        }

        async function loadPengadaanList() {
            const result = await fetchData();
            const tbody = document.getElementById('tableBody');
            if (result.success && result.data.length > 0) {
                tbody.innerHTML = result.data.map(po => {
                    const sisaDipesan = po.total_dipesan - po.total_diterima;
                    // Tentukan apakah tombol edit/hapus bisa ditampilkan (hanya jika Dipesan dan belum ada penerimaan)
                    const isDeletable = po.display_status === 'Dipesan' && po.total_diterima == 0; // PO hanya bisa dihapus jika status 'Dipesan' dan belum ada penerimaan

                    return `
            <tr onclick="editPengadaan(${po.idpengadaan})" style="cursor: pointer;">
                <td>PO-${po.idpengadaan}</td>
                <td>${new Date(po.tanggal).toLocaleDateString('id-ID')}</td>
                <td>${po.nama_vendor}</td>
                <td>${po.username}</td>
                <td>${formatRupiah(po.total_nilai)}</td>
                <td>${sisaDipesan} item</td>
                <td>${getStatusBadge(po.display_status)}</td>
                <td class="action-buttons">
                    <button class="btn btn-secondary btn-sm" onclick="event.stopPropagation(); viewPengadaanDetails(${po.idpengadaan})">Lihat Detail</button>
                </td>
            </tr>
        `;
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">Tidak ada data pengadaan.</td></tr>';
            }
        }

        // async function loadPengadaanList() {
        //     const result = await fetchData();
        //     const tbody = document.getElementById('tableBody');
        //     if (result.success && result.data.length > 0) {
        //         tbody.innerHTML = result.data.map(po => {
        //             const sisaDipesan = po.total_dipesan - po.total_diterima;
        //             // Tentukan apakah tombol edit/hapus bisa ditampilkan (hanya jika Dipesan dan belum ada penerimaan)
        //             const isDeletable = po.display_status === 'Dipesan' && po.total_diterima == 0; // PO hanya bisa dihapus jika status 'Dipesan' dan belum ada penerimaan

        //             return `
        //     <tr onclick="editPengadaan(${po.idpengadaan})" style="cursor: pointer;">
        //         <td>PO-${po.idpengadaan}</td>
        //         <td>${new Date(po.tanggal).toLocaleDateString('id-ID')}</td>
        //         <td>${po.nama_vendor}</td>
        //         <td>${po.username}</td>
        //         <td>${formatRupiah(po.total_nilai)}</td>
        //         <td>${sisaDipesan} item</td>
        //         <td>${getStatusBadge(po.display_status)}</td>
        //         <td class="action-buttons">
        //             <button class="btn btn-secondary btn-sm" onclick="event.stopPropagation(); viewPengadaanDetails(${po.idpengadaan})">Lihat Detail</button>
        //             ${isDeletable ? 
        //                 `<button class="btn btn-danger btn-sm" onclick="event.stopPropagation(); deletePengadaan(${po.idpengadaan})">Hapus</button>` 
        //                 : ''}
        //         </td>
        //     </tr>
        // `;
        //         }).join('');
        //     } else {
        //         tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">Tidak ada data pengadaan.</td></tr>';
        //     }
        // }

        function getStatusBadge(status) {
            switch (status) {
                case 'Closed/Batal':
                    return `<span class="badge badge-danger">Closed/Batal</span>`;
                case 'Diterima Penuh':
                    return `<span class="badge badge-success">Diterima Penuh</span>`;
                case 'Parsial':
                    return `<span class="badge badge-warning">Sebagian</span>`;
                case 'Dipesan':
                    return `<span class="badge badge-info">Dipesan (Proses)</span>`;
                default:
                    return `<span class="badge badge-secondary">${status}</span>`;
            }
        }

        document.getElementById('btn-tambah-barang').addEventListener('click', () => {
            const select = document.getElementById('select-barang');
            const selectedOption = select.options[select.selectedIndex];
            const selectedVendorId = document.getElementById('idvendor').value;
            const itemListBody = document.getElementById('item-list-body');

            if (!selectedVendorId) {
                alert('Mohon pilih Vendor di header terlebih dahulu.');
                return;
            }

            if (!selectedOption.value) {
                alert('Silakan pilih barang terlebih dahulu.');
                return;
            }

            const itemData = JSON.parse(selectedOption.value);

            // --- Validasi Vendor Tunggal (Sederhana di Frontend) ---
            // Jika ada item, vendor di header harus dikunci.
            if (itemListBody.rows.length > 0) {
                // Vendor sudah dikunci, kita hanya lanjutkan. 
            }


            const jumlah = document.getElementById('jumlah-barang').value;
            if (jumlah < 1) {
                alert('Jumlah harus minimal 1.');
                return;
            }
            addItem(itemData, jumlah);
        });

        function addItem(item, jumlah = 1) {
            const itemListBody = document.getElementById('item-list-body');
            if (document.querySelector(`tr[data-idbarang="${item.idbarang}"]`)) {
                alert('Barang sudah ada di dalam daftar.');
                return;
            }

            // Kunci dropdown vendor setelah item pertama ditambahkan
            document.getElementById('idvendor').disabled = true;

            const row = document.createElement('tr');
            row.setAttribute('data-idbarang', item.idbarang);
            // Harga satuan akan dihitung subtotalnya
            row.setAttribute('data-harga-satuan', item.harga);

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
            const itemRows = document.querySelectorAll('#item-list-body tr');
            const vendorSelect = document.getElementById('idvendor');

            itemRows.forEach(row => {
                const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                const price = parseFloat(row.dataset.hargaSatuan) || 0;
                const subtotal_item = qty * price;
                subTotal += subtotal_item;
                row.querySelector('.item-subtotal').textContent = formatRupiah(subtotal_item);
            });

            // Hitung PPN 10%
            const ppn = subTotal * PPN_RATE;
            const grandTotal = subTotal + ppn;

            document.getElementById('grand-total').textContent = formatRupiah(grandTotal);

            // Re-enable the vendor dropdown only if all items have been removed
            if (itemRows.length === 0) {
                vendorSelect.disabled = false;
            }
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

            // Pastikan semua input form pengadaan bisa diakses kembali
            document.getElementById('idvendor').disabled = false;
            document.getElementById('tanggal').disabled = false; // Tambahkan ini
            document.getElementById('btn-tambah-barang').disabled = false;
            document.getElementById('formPengadaan').querySelectorAll('input, select, button[type="submit"]').forEach(el => el.disabled = false);

            document.querySelector('#formPengadaan button[type="submit"]').style.display = 'inline-flex';

            updateTotals();
        }

        document.getElementById('formPengadaan').addEventListener('submit', async (e) => {
            e.preventDefault();

            // --- START MODIFIKASI: Aktifkan Vendor sementara agar nilainya terkirim ---
            const vendorSelect = document.getElementById('idvendor');
            const tanggalInput = document.getElementById('tanggal');
            
            const isVendorDisabled = vendorSelect.disabled;
            const isTanggalDisabled = tanggalInput.disabled;

            if (isVendorDisabled) {
                vendorSelect.disabled = false;
            }
             if (isTanggalDisabled) {
                tanggalInput.disabled = false;
            }
            // --- END MODIFIKASI ---

            const formData = new FormData(e.target);
            const method = document.getElementById('formMethod').value;

            const items = [];
            document.querySelectorAll('#item-list-body tr').forEach(row => {
                items.push({
                    idbarang: row.dataset.idbarang,
                    jumlah: parseFloat(row.querySelector('.item-qty').value),
                    harga: parseFloat(row.dataset.hargaSatuan) // Ambil harga satuan
                });
            });

            if (items.length === 0) {
                alert('Mohon tambahkan minimal satu barang.');
                // --- MODIFIKASI: Disable kembali jika submit dibatalkan ---
                if (isVendorDisabled) { vendorSelect.disabled = true; }
                if (isTanggalDisabled) { tanggalInput.disabled = true; }
                // --- END MODIFIKASI ---
                return;
            }

            const payload = {
                idpengadaan: formData.get('idpengadaan'),
                idvendor: formData.get('idvendor'),
                iduser: document.getElementById('iduser').value,
                tanggal: formData.get('tanggal'),
                items: items
            };

            const actionText = method === 'PUT' ? 'memperbarui' : 'menyimpan';
            if (!confirm(`Yakin ingin ${actionText} Pengadaan ini?`)) {
                // --- MODIFIKASI: Disable kembali jika submit dibatalkan ---
                if (isVendorDisabled) { vendorSelect.disabled = true; }
                if (isTanggalDisabled) { tanggalInput.disabled = true; }
                // --- END MODIFIKASI ---
                return;
            }

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        ...payload,
                        _method: method
                    })
                });
                const result = await response.json();
                alert(result.message);
                if (result.success) {
                    resetForm();
                    loadPengadaanList();
                }
            } catch (error) {
                alert('Terjadi kesalahan: ' + error.message);
            } finally {
                // --- MODIFIKASI: Pastikan vendor dan tanggal didisable kembali, terlepas dari sukses/gagal ---
                if (isVendorDisabled && document.getElementById('formMethod').value === 'PUT') {
                    vendorSelect.disabled = true;
                    tanggalInput.disabled = true;
                }
                // --- END MODIFIKASI ---
            }
        });

        async function editPengadaan(id) {
            const result = await fetchData(`?id=${id}`);
            if (result.success) {
                const po = result.data;
                resetForm();

                document.getElementById('formTitle').textContent = `Ubah Pengadaan PO-${po.idpengadaan} (${po.nama_vendor})`;
                document.getElementById('idpengadaan').value = po.idpengadaan;
                document.getElementById('tanggal').value = po.tanggal.substring(0, 10);
                document.getElementById('idvendor').value = po.vendor_idvendor;
                document.querySelector('#formPengadaan button[type="submit"]').textContent = 'Perbarui Pengadaan';

                // Tentukan kemampuan edit/hapus
                const isModifiable = po.can_be_modified;
                const isCanceled = po.status === 'c';

                // Handle Item List
                const itemListBody = document.getElementById('item-list-body');
                itemListBody.innerHTML = '';
                po.details.forEach(item => {
                    const row = document.createElement('tr');
                    row.setAttribute('data-idbarang', item.idbarang);
                    row.setAttribute('data-harga-satuan', item.harga_satuan);

                    row.innerHTML = `
            <td>${item.nama_barang}</td>
            <td><input type="number" class="item-qty" value="${item.jumlah}" min="1" oninput="updateTotals()" style="text-align: center;" ${isModifiable ? '' : 'disabled'}></td>
            <td class="item-price" data-price="${item.harga_satuan}">${formatRupiah(item.harga_satuan)}</td>
            <td class="item-subtotal">${formatRupiah(item.sub_total)}</td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); updateTotals();" ${isModifiable ? '' : 'disabled'}>Hapus</button></td>
        `;
                    itemListBody.appendChild(row);
                });
                updateTotals();

                if (isModifiable) {
                    document.getElementById('formMethod').value = 'PUT';
                    document.getElementById('idvendor').disabled = true; // Kunci vendor setelah item dimuat
                    document.getElementById('tanggal').disabled = false; // Tanggal bisa diubah saat PUT
                    document.getElementById('btn-tambah-barang').disabled = false;
                    document.querySelector('#formPengadaan button[type="submit"]').style.display = 'inline-flex';
                    document.getElementById('btn-finalize').style.display = 'none';
                } else {
                    // Jika tidak bisa diubah (sudah ada penerimaan atau status 'c')
                    document.getElementById('idvendor').disabled = true;
                    document.getElementById('tanggal').disabled = true;
                    document.getElementById('btn-tambah-barang').disabled = true;
                    document.querySelector('#formPengadaan button[type="submit"]').style.display = 'none';
                    document.getElementById('btn-finalize').style.display = isCanceled ? 'none' : 'inline-flex';
                    document.getElementById('btn-finalize').disabled = isCanceled;

                    alert(`Pengadaan PO-${id} tidak dapat diubah karena status ${isCanceled ? 'sudah ditutup/dibatalkan' : 'sudah ada penerimaan tercatat'}.`);
                }

                window.scrollTo(0, 0);
            }
        }

        async function deletePengadaan(id) {
            if (!confirm(`Yakin ingin menghapus Pengadaan PO-${id}? Aksi ini tidak dapat dibatalkan.`)) return;

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        idpengadaan: id,
                        _method: 'DELETE'
                    })
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

            if (!confirm(`Anda yakin ingin memfinalisasi (menutup) Pengadaan PO-${idpengadaan}? Status akan diubah menjadi 'Closed/Batal'.`)) return;

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        idpengadaan: idpengadaan,
                        action: 'finalize'
                    })
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

        // manage_pengadaan.php (dalam <script> - fungsi viewPengadaanDetails)

        async function viewPengadaanDetails(id) {
            const result = await fetchData(`?id=${id}`);
            if (result.success) {
                const po = result.data;
                // Populate Modal Header
                document.getElementById('detailModalTitle').textContent = `Detail Pengadaan PO-${po.idpengadaan}`;
                document.getElementById('detailIdPO').textContent = `PO-${po.idpengadaan}`;
                document.getElementById('detailVendor').textContent = po.nama_vendor;
                document.getElementById('detailTanggal').textContent = new Date(po.tanggal).toLocaleDateString('id-ID');
                document.getElementById('detailUser').textContent = po.username;

                // Populate Item List
                const detailBody = document.getElementById('detail-item-list-body');
                detailBody.innerHTML = '';
                let totalSubtotal = 0;
                po.details.forEach(item => {
                    totalSubtotal += parseFloat(item.sub_total); // Menggunakan item.sub_total
                    const row = document.createElement('tr');
                    row.innerHTML = `
            <td>${item.nama_barang}</td>
            <td style="text-align: right;">${item.jumlah}</td>
            <td style="text-align: right;">${formatRupiah(item.harga_satuan)}</td>
            <td style="text-align: right;">${formatRupiah(item.sub_total)}</td>
        `;
                    detailBody.appendChild(row);
                });

                // Hitung total dengan PPN 10%
                const ppn = Math.floor(totalSubtotal * PPN_RATE);
                const grandTotal = totalSubtotal + ppn;

                // --- PERUBAHAN UTAMA: Tambahkan baris untuk Subtotal Nilai dan PPN ---
                const totalSectionContainer = document.querySelector('#modalDetail .modal-body .total-section');

                // Hapus konten total sebelumnya
                totalSectionContainer.innerHTML = '';

                // 1. Tambahkan Subtotal Nilai (sebelum PPN)
                totalSectionContainer.innerHTML += `
                <div style="text-align: right; margin-top: 10px;">
                    <span style="font-weight: 400; color: #8b92a7;">Subtotal Nilai:</span>
                    <span style="font-weight: 600; display: inline-block; width: 120px; color: #333;">${formatRupiah(totalSubtotal)}</span>
                </div>
            `;

                // 2. Tambahkan PPN 10%
                totalSectionContainer.innerHTML += `
                <div style="text-align: right;">
                    <span style="font-weight: 400; color: #8b92a7;">PPN (${PPN_RATE * 100}%):</span>
                    <span style="font-weight: 600; display: inline-block; width: 120px; color: #333;">${formatRupiah(ppn)}</span>
                </div>
            `;

                // 3. Tambahkan Total Akhir (Grand Total)
                totalSectionContainer.innerHTML += `
                <div style="text-align: right; border-top: 1px solid #ccc; padding-top: 5px;">
                    <span style="font-weight: 600; color: #333;">Total Akhir:</span>
                    <span id="detail-grand-total" style="font-weight: 700; color: #28a745; display: inline-block; width: 120px;">${formatRupiah(grandTotal)}</span>
                </div>
            `;
                // --- AKHIR PERUBAHAN UTAMA ---

                // Show Modal
                document.getElementById('modalDetail').classList.add('show');
            } else {
                alert('Gagal memuat detail pengadaan.');
            }
        }

        function closeDetailModal() {
            document.getElementById('modalDetail').classList.remove('show');
        }

        // Close detail modal if clicked outside
        window.addEventListener('click', (event) => {
            if (event.target == document.getElementById('modalDetail')) {
                closeDetailModal();
            }
        });
    </script>
    <?php include 'footer.php'; ?>
</body>

</html>