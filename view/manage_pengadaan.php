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
                    <?php
                    // Tentukan tautan Dashboard berdasarkan role_id
                    $dashboard_link = '';
                    if (isset($_SESSION['role_id'])) {
                        if ($_SESSION['role_id'] == 1) {
                            $dashboard_link = 'dashboard_super_admin.php';
                        } elseif ($_SESSION['role_id'] == 2) {
                            $dashboard_link = 'dashboard_admin.php';
                        }
                    }

                    // Tombol Kembali ke Dashboard hanya ditampilkan jika ada link yang valid
                    if (!empty($dashboard_link)):
                    ?>
                        <a href="<?php echo $dashboard_link; ?>" class="btn btn-secondary">
                            <span>⬅️</span> Kembali ke Dashboard
                        </a>
                    <?php endif; ?>

                    <a href="../model/auth.php?action=logout" class="btn btn-danger">
                        <span>🚪</span> Keluar
                    </a>
                </div>
        </header>

        <div class="container">
            <div class="card transaction-form form-section">
                <form id="formPengadaan">
                    <input type="hidden" id="idpengadaan" name="idpengadaan">
                    <input type="hidden" id="formMethod" name="_method">

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
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_SESSION['username'] ?? 'N/A'); ?>" readonly>
                                <input type="hidden" id="iduser" name="iduser" value="<?php echo htmlspecialchars($_SESSION['user_id'] ?? ''); ?>">
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
                                <button type="button" id="btn-tambah-barang" class="btn btn-secondary">Tambah ke Daftar</button>
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
                            <span>Total: </span>
                            <span id="grand-total">Rp 0</span>
                        </div>
                        <div class="form-footer" style="padding: 28px 0 0 0; border-top: none; display:flex; gap:1rem;">
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">Batal</button>
                            <button type="button" id="btn-finalize" class="btn btn-success" style="display: none;" onclick="finalizePengadaan()">Finalisasi Pengadaan</button>
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
                                    <th width="10%">Aksi</th>
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

    <script>
        const API_URL = '../model/pengadaan.php';

        document.addEventListener('DOMContentLoaded', () => {
            loadInitialData();
            document.getElementById('tanggal').valueAsDate = new Date();

            const urlParams = new URLSearchParams(window.location.search);
            const editId = urlParams.get('edit_id');
            if (editId) {
                editPengadaan(editId);
            }
        });

        const formatRupiah = (number) => new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(number || 0);

        async function loadInitialData() {
            loadMasterData();
            loadPengadaanList();
        }

        async function fetchData(params = '') {
            try {
                const response = await fetch(`${API_URL}${params}`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
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
                const vendorSelect = document.getElementById('idvendor');
                vendorSelect.innerHTML = '<option value="">Pilih Vendor</option>' +
                    result.vendors.map(v => `<option value="${v.idvendor}">${v.nama_vendor}</option>`).join('');

                const barangSelect = document.getElementById('select-barang');
                barangSelect.innerHTML = '<option value="">Pilih Barang</option>' +
                    result.barangs.map(item => {
                        return `<option value='${JSON.stringify(item)}'>${item.nama} - ${formatRupiah(item.harga)}</option>`;
                    }).join('');
            }
        }

        async function loadPengadaanList() {
            const result = await fetchData();
            const tbody = document.getElementById('tableBody');
            if (result.success && result.data.length > 0) {
                tbody.innerHTML = result.data.map(po => `
            <tr>
                <td>PO-${po.idpengadaan}</td>
                <td>${new Date(po.tanggal).toLocaleDateString('id-ID')}</td>
                <td>${po.nama_vendor}</td>
                <td>${po.username}</td>
                <td>${formatRupiah(po.total_nilai)}</td>
                <td>${po.sisa_penerimaan} item</td>
                <td>${getStatusBadge(po.display_status)}</td>
                <td class="action-buttons">
                    <button class="btn btn-primary btn-sm" onclick="editPengadaan(${po.idpengadaan})">Edit</button>
                    ${po.sisa_penerimaan > 0 ? `<button class="btn btn-danger btn-sm" onclick="deletePengadaan(${po.idpengadaan})">Hapus</button>` : `<button class="btn btn-secondary btn-sm" disabled>Hapus</button>`}
                </td>
            </tr>
        `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">Tidak ada data pengadaan.</td></tr>';
            }
        }

        function getStatusBadge(status) {
            switch (status) {
                case 'Closed':
                    return `<span class="badge badge-danger">Closed</span>`;
                case 'Dipesan':
                    return `<span class="badge badge-warning">Dipesan</span>`;
                case 'Parsial':
                    return `<span class="badge badge-info">Parsial</span>`;
                case 'Diterima Penuh':
                    return `<span class="badge badge-success">Diterima Penuh</span>`;
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
        <td><input type="number" class="item-qty" value="${jumlah}" min="1" oninput="updateTotals()"></td>
        <td class="item-price" data-price="${item.harga}">${formatRupiah(item.harga)}</td>
        <td class="item-subtotal">${formatRupiah(subtotal)}</td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); updateTotals();">X</button></td>
    `;
            itemListBody.appendChild(row);
            document.getElementById('select-barang').selectedIndex = 0; // Reset dropdown
            document.getElementById('jumlah-barang').value = 1; // Reset input jumlah
            updateTotals();
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
            document.getElementById('formPengadaan').reset();
            document.getElementById('idpengadaan').value = '';
            document.getElementById('formMethod').value = '';
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
            const method = formData.get('_method') || 'POST';

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
                iduser: formData.get('iduser'),
                tanggal: formData.get('tanggal'),
                items: items
            };

            try {
                const response = await fetch(API_URL, {
                    method: 'POST', // Selalu POST, metode asli dihandle di backend
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
            }
        });

        async function editPengadaan(id) {
            const result = await fetchData(`?id=${id}`);
            if (result.success) {
                const po = result.data;
                resetForm();

                document.getElementById('formTitle').textContent = `Edit Pengadaan PO-${po.idpengadaan}`;
                document.getElementById('idpengadaan').value = po.idpengadaan;
                document.getElementById('formMethod').value = 'PUT';
                document.getElementById('tanggal').value = po.tanggal;
                document.getElementById('idvendor').value = po.idvendor;
                document.getElementById('iduser').value = po.iduser;

                // Show finalize button if applicable
                if (po.is_finalizable) {
                    document.getElementById('btn-finalize').style.display = 'inline-flex';
                }

                const itemListBody = document.getElementById('item-list-body');
                itemListBody.innerHTML = '';
                po.details.forEach(item => {
                    const row = document.createElement('tr');
                    row.setAttribute('data-idbarang', item.idbarang);
                    // Cek apakah barang sudah diterima penuh
                    const isReceived = item.jumlah == item.total_diterima;

                    // Nonaktifkan input jika sudah diterima penuh
                    const inputDisabled = isReceived ? 'readonly style="background-color: #3a4254;"' : '';
                    const deleteButton = isReceived ? `<button type="button" class="btn btn-secondary btn-sm" disabled>X</button>` : `<button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); updateTotals();">X</button>`;


                    row.innerHTML = `
                <td>${item.nama_barang} ${isReceived ? '<span class="badge badge-success" style="font-size: 10px;">Diterima Penuh</span>' : ''}</td>
                <td><input type="number" class="item-qty" value="${item.jumlah}" min="${item.total_diterima}" oninput="updateTotals()" ${inputDisabled}></td>
                <td class="item-price" data-price="${item.harga_satuan}">${formatRupiah(item.harga_satuan)}</td>
                <td class="item-subtotal">${formatRupiah(item.subtotal)}</td>
                <td>${deleteButton}</td>
            `;
                    itemListBody.appendChild(row);
                });

                updateTotals();
                document.querySelector('#formPengadaan button[type="submit"]').textContent = 'Perbarui Pengadaan';
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

            if (!confirm(`Anda yakin ingin memfinalisasi (menutup) Pengadaan PO-${idpengadaan}? Status akan diubah menjadi 'closed' dan tidak bisa diubah lagi.`)) return;

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
    </script>
</body>

</html>