<?php
require_once '../model/koneksi.php';
require_once '../model/auth.php';

// Memastikan user sudah login
checkAuth();

// Ambil data user untuk ditampilkan di header
$user_role = $_SESSION['role'] ?? 'Guest';
$username = $_SESSION['username'] ?? 'Pengguna';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Barang - Sistem Inventory PBD</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard_super_admin.css">
    <link rel="stylesheet" href="../css/barang.css">
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
                        <p>Database PBD - Manajemen Barang</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem; align-items: center;">
                    <span>👋 Halo, <?php echo ucwords($username); ?>!</span>
                    <a href="../model/auth.php?action=logout" class="btn btn-danger"><span>Keluar</span></a>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Barang (Aktif)</h3>
                    <div class="value" id="totalBarang">0</div>
                </div>
                <div class="stat-card">
                    <h3>Total Stok</h3>
                    <div class="value" id="totalStok">0</div>
                </div>
                <div class="stat-card">
                    <h3>Nilai Inventory (Harga Pokok)</h3>
                    <div class="value" id="totalNilai">Rp 0</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Daftar Barang</h2>
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <button id="btnFilterAktif" class="btn btn-secondary btn-sm" data-filter="aktif">Tampilkan Semua</button>
                        <button id="btnRefresh" class="btn btn-secondary btn-sm">🔄 Refresh</button>
                        <button id="btnTambah" class="btn btn-primary btn-sm">
                            <span>+</span> Tambah Barang
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tableBarang">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama Barang</th>
                                    <th>Satuan</th>
                                    <th>Jenis</th>
                                    <th>Harga Pokok</th>
                                    <th>Stok</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr>
                                    <td colspan="8" style="text-align: center;">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <footer>
                <p>Sistem Manajemen Inventory PBD © 2025</p>
            </footer>
        </div>

        <div id="modalForm" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modalTitle">Tambah Barang</h3>
                    <button class="close" onclick="closeModal()">&times;</button>
                </div>
                <form id="formBarang">
                    <input type="hidden" id="idbarang" name="idbarang">
                    <input type="hidden" id="formMethod" name="_method">

                    <div id="kodeBarangDisplay" class="form-group" style="display: none;">
                        <label>Kode Barang</label>
                        <input type="text" id="kode_barang" name="kode_barang" readonly>
                    </div>
                    <div class="form-group">
                        <div class="form-group">
                            <label for="nama_barang">Nama Barang *</label>
                            <input type="text" id="nama_barang" name="nama_barang" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="idsatuan">Satuan *</label>
                            <select id="idsatuan" name="idsatuan" required>
                                <option value="">Pilih Satuan</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="jenis_barang">Jenis Barang</label>
                            <select id="jenis_barang" name="jenis_barang">
                                <option value="">Pilih Jenis</option>
                                <option value="F">Finished Good (Barang Jadi)</option>
                                <option value="B">Bahan Baku (Raw Material)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="harga_pokok">Harga Pokok *</label>
                            <input type="number" id="harga_pokok" name="harga_pokok" required step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="stok">Stok Awal *</label>
                            <input type="number" id="stok" name="stok" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="aktif">Aktif</option>
                            <option value="tidak_aktif">Tidak Aktif</option>
                        </select>
                    </div>

                    <div class="form-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            // Load data on page load
            document.addEventListener('DOMContentLoaded', () => {
                loadStats();
                loadBarang();
                loadSatuan();
            });

            // Load statistics
            async function loadStats() {
                try {
                    // Panggil API di model/barang.php
                    const response = await fetch('../model/barang.php?action=get_stats');
                    const result = await response.json();

                    if (result.success) {
                        document.getElementById('totalBarang').textContent = result.data.total_barang;
                        document.getElementById('totalStok').textContent = result.data.total_stok || 0;
                        document.getElementById('totalNilai').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(result.data.total_nilai || 0);
                    }
                } catch (error) {
                    console.error('Error loading stats:', error);
                }
            }

            // Load barang list
            async function loadBarang() {
                const filter = document.getElementById('btnFilterAktif').dataset.filter;
                const url = `../model/barang.php?filter=${filter}`;

                try {
                    const response = await fetch(url);

                    // Menangani error otentikasi (sesi berakhir)
                    if (response.status === 401) {
                        alert('Sesi Anda telah berakhir. Anda akan diarahkan ke halaman login.');
                        window.location.href = '../view/login.php';
                        return;
                    }

                    const result = await response.json();

                    const tbody = document.getElementById('tableBody');

                    if (result.success && result.data.length > 0) {
                        tbody.innerHTML = result.data.map(item => `
                        <tr>
                            <td>${item.kode_barang}</td>
                            <td>${item.nama_barang}</td>
                            <td>${item.nama_satuan || '-'}</td>
                            <td>${item.jenis_barang || '-'}</td>
                            <td>Rp ${new Intl.NumberFormat('id-ID').format(item.harga_pokok)}</td>
                            <td>${item.stok}</td>
                            <td><span class="badge ${item.status === 'aktif' ? 'badge-success' : 'badge-danger'}">${item.status}</span></td>
                            <td class="action-buttons">
                                <button class="btn btn-primary btn-sm" onclick="editBarang('${item.idbarang}')">Edit</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteBarang('${item.idbarang}', '${item.nama_barang}')">Hapus</button>
                            </td>
                        </tr>
                    `).join('');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">Tidak ada data</td></tr>';
                    }
                } catch (error) {
                    console.error('Error loading barang:', error);
                }
            }

            // Toggle filter button
            document.getElementById('btnFilterAktif').addEventListener('click', function() {
                const currentFilter = this.dataset.filter;
                if (currentFilter === 'semua') {
                    this.dataset.filter = 'aktif';
                    this.textContent = 'Tampilkan Semua';
                } else {
                    this.dataset.filter = 'semua';
                    this.textContent = 'Tampilkan Aktif Saja';
                }
                loadBarang();
            });

            // Load satuan for dropdown
            async function loadSatuan() {
                try {
                    const response = await fetch('../model/barang.php?action=get_satuan');
                    const result = await response.json();

                    if (result.success) {
                        const select = document.getElementById('idsatuan');
                        select.innerHTML = '<option value="">Pilih Satuan</option>' +
                            result.data.map(item => `<option value="${item.idsatuan}">${item.nama_satuan}</option>`).join('');
                    }
                } catch (error) {
                    console.error('Error loading satuan:', error);
                }
            }

            // Show modal for add
            document.getElementById('btnTambah').addEventListener('click', () => {
                document.getElementById('modalTitle').textContent = 'Tambah Barang';
                document.getElementById('formBarang').reset();
                document.getElementById('idbarang').value = '';
                document.getElementById('formMethod').value = '';
                document.getElementById('kodeBarangDisplay').style.display = 'none'; // Sembunyikan kode barang saat tambah
                document.getElementById('stok').readOnly = false; // Stok bisa diisi saat tambah
                document.getElementById('modalForm').classList.add('show');
            });

            // Edit barang
            async function editBarang(id) {
                try {
                    const response = await fetch(`../model/barang.php?id=${id}`);
                    const result = await response.json();

                    if (result.success) {
                        const data = result.data;
                        document.getElementById('modalTitle').textContent = 'Edit Barang';
                        document.getElementById('idbarang').value = data.idbarang;
                        document.getElementById('formMethod').value = 'PUT';
                        document.getElementById('kode_barang').value = data.kode_barang;
                        document.getElementById('nama_barang').value = data.nama_barang;
                        document.getElementById('idsatuan').value = data.idsatuan;
                        document.getElementById('jenis_barang').value = data.jenis_barang;
                        document.getElementById('harga_pokok').value = data.harga_pokok;
                        document.getElementById('stok').value = data.stok || 0;
                        document.getElementById('stok').readOnly = true; // Stok tidak bisa diedit di master data
                        document.getElementById('status').value = data.status;

                        document.getElementById('kodeBarangDisplay').style.display = 'block'; // Tampilkan kode barang saat edit
                        document.getElementById('modalForm').classList.add('show');
                    }
                } catch (error) {
                    alert('Error loading data: ' + error.message);
                }
            }

            // Delete barang (Soft Delete)
            async function deleteBarang(id, nama) {
                if (!confirm(`Apakah Anda yakin ingin MENONAKTIFKAN barang "${nama}"? (Soft Delete)`)) return;

                try {
                    const formData = new FormData();
                    formData.append('_method', 'DELETE');
                    formData.append('idbarang', id);

                    const response = await fetch('../model/barang.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    alert(result.message);

                    if (result.success) {
                        loadBarang();
                        loadStats();
                    }
                } catch (error) {
                    alert('Error: ' + error.message);
                }
            }

            // Submit form (Add or Edit)
            document.getElementById('formBarang').addEventListener('submit', async (e) => {
                e.preventDefault();

                const formData = new FormData(e.target);

                try {
                    const response = await fetch('../model/barang.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    alert(result.message);

                    if (result.success) {
                        closeModal();
                        loadBarang();
                        loadStats();
                    }
                } catch (error) {
                    alert('Error: ' + error.message);
                }
            });

            // Close modal
            function closeModal() {
                document.getElementById('modalForm').classList.remove('show');
            }

            // Refresh button
            document.getElementById('btnRefresh').addEventListener('click', () => {
                // Memanggil kedua fungsi untuk memuat ulang data statistik dan tabel barang
                loadBarang();
                loadStats();
            });

            // Close modal when clicking outside
            window.onclick = function(event) {
                const modal = document.getElementById('modalForm');
                if (event.target === modal) {
                    closeModal();
                }
            }
        </script>

</body>

</html>