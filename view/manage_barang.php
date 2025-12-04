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
                    <a href="../model/auth.php?action=logout" class="btn btn-danger"><span>🚪</span> Keluar</a>
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
                        <button id="btnFilterAktif" class="btn btn-success btn-sm active-filter" data-filter="aktif">Barang Aktif</button>
                        <button id="btnFilterSemua" class="btn btn-info btn-sm" data-filter="semua">Semua Barang</button>
                        
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
                <p>Sistem Manajemen Inventory PBD &copy; 2025</p>
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
                                <option value="J">Finished Good (Barang Jadi)</option>
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
                            <label for="stok">Stok Awal (Hanya untuk Tambah) *</label>
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
            // Variabel untuk menyimpan filter aktif saat ini
            let currentFilter = 'aktif';

            document.addEventListener('DOMContentLoaded', () => {
                loadStats();
                loadBarang();
                loadSatuan();
                // Set kelas aktif default
                document.getElementById('btnFilterAktif').classList.add('active');
            });

            // --- FUNGSI UTAMA ---

            async function loadStats() {
                try {
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

            async function loadBarang() {
                const url = `../model/barang.php?filter=${currentFilter}`;

                try {
                    const response = await fetch(url);
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
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">Gagal memuat data barang.</td></tr>';
                }
            }
            
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

            // --- FILTER LOGIC ---
            function setFilter(filter) {
                currentFilter = filter;
                document.getElementById('btnFilterAktif').classList.remove('active');
                document.getElementById('btnFilterSemua').classList.remove('active');
                
                if (filter === 'aktif') {
                    document.getElementById('btnFilterAktif').classList.add('active');
                } else {
                    document.getElementById('btnFilterSemua').classList.add('active');
                }
                loadBarang();
            }

            document.getElementById('btnFilterAktif').addEventListener('click', () => setFilter('aktif'));
            document.getElementById('btnFilterSemua').addEventListener('click', () => setFilter('semua'));


            // --- CRUD LOGIC ---

            document.getElementById('btnTambah').addEventListener('click', () => {
                document.getElementById('modalTitle').textContent = 'Tambah Barang';
                document.getElementById('formBarang').reset();
                document.getElementById('idbarang').value = '';
                document.getElementById('formMethod').value = '';
                document.getElementById('kodeBarangDisplay').style.display = 'none'; 
                document.getElementById('stok').readOnly = false; 
                document.getElementById('stok').required = true;
                document.getElementById('modalForm').classList.add('show');
            });

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
                        document.getElementById('jenis_barang').value = data.jenis_barang; // Menggunakan kode J/B
                        document.getElementById('harga_pokok').value = data.harga_pokok;
                        document.getElementById('stok').value = data.stok || 0;
                        document.getElementById('stok').readOnly = true; 
                        document.getElementById('stok').required = false;
                        document.getElementById('status').value = data.status;

                        document.getElementById('kodeBarangDisplay').style.display = 'block'; 
                        document.getElementById('modalForm').classList.add('show');
                    }
                } catch (error) {
                    alert('Error loading data: ' + error.message);
                }
            }

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

            document.getElementById('formBarang').addEventListener('submit', async (e) => {
                e.preventDefault();

                const formData = new FormData(e.target);

                // Hapus stok dari formData jika sedang PUT (Edit)
                if (document.getElementById('formMethod').value === 'PUT') {
                    formData.delete('stok'); 
                }

                // FIX: Pastikan jenis_barang dikirim sebagai kode J/B
                const selectedJenis = document.getElementById('jenis_barang').value;
                if (selectedJenis === 'F') { // Handle typo "F" menjadi "J" jika frontend menggunakan F
                    formData.set('jenis_barang', 'J');
                } else if (selectedJenis === 'B') {
                    formData.set('jenis_barang', 'B');
                }

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

            // --- MODAL & REFRESH ---

            function closeModal() {
                document.getElementById('modalForm').classList.remove('show');
            }

            document.getElementById('btnRefresh').addEventListener('click', () => {
                loadBarang();
                loadStats();
            });

            window.onclick = function(event) {
                const modal = document.getElementById('modalForm');
                if (event.target === modal) {
                    closeModal();
                }
            }
        </script>

</body>

</html>