<?php
require_once '../model/koneksi.php';
require_once '../model/auth.php';

// Memastikan user sudah login
checkAuth();

// Ambil data user untuk ditampilkan di header
$user_role = $_SESSION['role'] ?? 'Guest';
$username = $_SESSION['username'] ?? 'Pengguna';

// Pilihan Jenis Barang (Sesuai skema DDL: J=Barang Jadi, B=Bahan Baku)
$jenis_barang_options = [
    'J' => 'Barang Jadi',
    'B' => 'Bahan Baku'
];
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
    <style>
        /* CSS Tambahan untuk tombol filter aktif */
        .btn-filter-group .active {
            box-shadow: 0 0 0 0.2rem rgba(38, 143, 255, 0.5); 
            font-weight: bold;
        }
        /* Penyesuaian layout header */
        .card-header > div.btn-filter-group { 
            margin-left: auto;
            margin-right: 1rem;
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
                        <p>Database PBD - Manajemen Barang</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem; align-items: center;">
                    <span>👋 Halo, <?php echo ucwords($username); ?>!</span>
                    <a href="../model/auth.php?action=logout" class="btn btn-danger"><span>🚪 Keluar</span></a>
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
                    
                    <button id="btnRefresh" class="btn btn-secondary btn-sm">🔄 Refresh</button>
                    
                    <div class="btn-filter-group" style="display: flex; gap: 0.5rem;">
                        <button id="btnBarangAktif" class="btn btn-success btn-sm active" data-filter="aktif">✔ Barang Aktif</button>
                        <button id="btnSemuaBarang" class="btn btn-info btn-sm" data-filter="semua">Semua Barang</button>
                    </div>
                    
                    <button id="btnTambah" class="btn btn-primary btn-sm"><span>+</span> Tambah Barang</button>
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
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h3 id="modalTitle">Tambah Barang</h3>
                    <button class="close" onclick="closeModal()">&times;</button>
                </div>
                <form id="formBarang" method="POST">
                    <input type="hidden" id="idbarang" name="idbarang">
                    <input type="hidden" id="formMethod" name="_method" value="POST">

                    <div id="kodeBarangDisplay" class="form-group" style="display: none;">
                        <label>Kode Barang</label>
                        <input type="text" id="kode_barang" name="kode_barang" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="nama_barang">Nama Barang *</label>
                        <input type="text" id="nama_barang" name="nama_barang" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="idsatuan">Satuan *</label>
                            <select id="idsatuan" name="idsatuan" required>
                                <option value="">Pilih Satuan</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="jenis_barang">Jenis Barang *</label>
                            <select id="jenis_barang" name="jenis_barang" required>
                                <option value="">Pilih Jenis</option>
                                <?php foreach ($jenis_barang_options as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="harga_pokok">Harga Pokok * (Cth: 120000)</label>
                            <input type="text" id="harga_pokok" name="harga_pokok" required inputmode="numeric">
                        </div>
                        <div class="form-group">
                            <label for="stok">Stok Awal * (Hanya saat tambah)</label>
                            <input type="number" id="stok" name="stok" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="aktif">Aktif</option>
                            <option value="tidak_aktif">Non-Aktif</option>
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
            const API_URL = '../model/barang.php'; 
            let currentFilter = 'aktif';

            document.addEventListener('DOMContentLoaded', () => {
                // Set initial active button
                document.getElementById('btnBarangAktif').classList.add('active'); 
                loadStats();
                loadBarang(currentFilter);
                loadSatuan();
            });

            const formatRupiah = (number) => {
                 if (typeof number !== 'number') {
                     // Bersihkan input string jika ada
                     number = parseFloat(String(number).replace(/[^0-9]/g, '')) || 0;
                 }
                 return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(number);
            };


            async function loadStats() {
                try {
                    const response = await fetch(API_URL + '?action=get_stats');
                    // Cek status HTTP sebelum mencoba json()
                    if (!response.ok) {
                         throw new Error(`Gagal fetch status: HTTP ${response.status}`);
                    }
                    const result = await response.json();

                    if (result.success) {
                        document.getElementById('totalBarang').textContent = result.data.total_barang;
                        document.getElementById('totalStok').textContent = formatRupiah(result.data.total_stok || 0);
                        document.getElementById('totalNilai').textContent = 'Rp ' + formatRupiah(result.data.total_nilai || 0);
                    }
                } catch (error) {
                    console.error('Error loading stats:', error);
                    // Mungkin API path salah atau koneksi bermasalah
                }
            }

            async function loadBarang(filter) {
                currentFilter = filter;
                const url = `${API_URL}?filter=${filter}`;
                const tbody = document.getElementById('tableBody');
                
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">Loading...</td></tr>';
                
                // Update tampilan tombol filter
                document.getElementById('btnBarangAktif').classList.remove('active');
                document.getElementById('btnSemuaBarang').classList.remove('active');
                if (filter === 'aktif') {
                    document.getElementById('btnBarangAktif').classList.add('active');
                } else {
                    document.getElementById('btnSemuaBarang').classList.add('active');
                }

                try {
                    const response = await fetch(url);
                    if (response.status === 401) {
                        alert('Sesi Anda telah berakhir. Anda akan diarahkan ke halaman login.');
                        window.location.href = '../view/login.php';
                        return;
                    }
                    if (!response.ok) {
                         throw new Error(`Gagal fetch data: HTTP ${response.status}`);
                    }

                    const result = await response.json();

                    if (result.success && result.data.length > 0) {
                        tbody.innerHTML = result.data.map(item => `
                        <tr>
                            <td>${item.kode_barang}</td>
                            <td>${item.nama_barang}</td>
                            <td>${item.nama_satuan || '-'}</td>
                            <td>${item.jenis_barang || '-'}</td>
                            <td>Rp ${formatRupiah(item.harga_pokok)}</td>
                            <td>${formatRupiah(item.stok)}</td>
                            <td><span class="badge ${item.status === 'aktif' ? 'badge-success' : 'badge-danger'}">${item.status}</span></td>
                            <td class="action-buttons">
                                <button class="btn btn-primary btn-sm" onclick="editBarang('${item.idbarang}')">Edit</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteBarang('${item.idbarang}', '${item.nama_barang}')">Hapus</button>
                            </td>
                        </tr>
                    `).join('');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">Tidak ada data barang yang sesuai dengan filter.</td></tr>';
                    }
                } catch (error) {
                    console.error('Error loading barang:', error);
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">Gagal memuat data. Periksa konsol browser.</td></tr>';
                }
            }

            // Event Listeners untuk tombol filter
            document.getElementById('btnBarangAktif').addEventListener('click', () => {
                loadBarang('aktif');
            });

            document.getElementById('btnSemuaBarang').addEventListener('click', () => {
                loadBarang('semua');
            });

            // Load satuan for dropdown
            async function loadSatuan() {
                try {
                    const response = await fetch(API_URL + '?action=get_satuan');
                    if (!response.ok) {
                         throw new Error(`Gagal fetch satuan: HTTP ${response.status}`);
                    }
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
                document.getElementById('formMethod').value = 'POST';
                document.getElementById('kodeBarangDisplay').style.display = 'none'; 
                document.getElementById('stok').readOnly = false; 
                document.getElementById('stok').required = true;
                document.getElementById('status').value = 'aktif'; 
                document.getElementById('modalForm').classList.add('show');
            });

            // Edit barang
            async function editBarang(id) {
                try {
                    const response = await fetch(`${API_URL}?id=${id}`);
                     if (!response.ok) {
                         throw new Error(`Gagal fetch detail barang: HTTP ${response.status}`);
                    }
                    const result = await response.json();

                    if (result.success) {
                        const data = result.data;
                        document.getElementById('modalTitle').textContent = 'Edit Barang';
                        document.getElementById('idbarang').value = data.idbarang;
                        document.getElementById('formMethod').value = 'PUT';
                        document.getElementById('kode_barang').value = data.idbarang; 
                        document.getElementById('nama_barang').value = data.nama_barang;
                        document.getElementById('idsatuan').value = data.idsatuan;
                        document.getElementById('jenis_barang').value = data.jenis_barang; 
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

            // Delete barang (Soft Delete)
            async function deleteBarang(id, nama) {
                if (!confirm(`Apakah Anda yakin ingin MENONAKTIFKAN barang "${nama}"? (Status akan diubah menjadi Non-Aktif)`)) return;

                try {
                    const formData = new FormData();
                    formData.append('_method', 'DELETE');
                    formData.append('idbarang', id);

                    const response = await fetch(API_URL, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    alert(result.message);

                    if (result.success) {
                        loadBarang(currentFilter);
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

                // Hilangkan formatting Rupiah dari harga pokok sebelum submit
                const hargaPokokInput = document.getElementById('harga_pokok');
                const cleanHarga = hargaPokokInput.value.replace(/[^0-9]/g, '');
                formData.set('harga_pokok', cleanHarga);

                try {
                    const response = await fetch(API_URL, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    alert(result.message);

                    if (result.success) {
                        closeModal();
                        loadBarang(currentFilter);
                        loadStats();
                    }
                } catch (error) {
                    alert('Error: ' + error.message);
                }
            });

            function closeModal() {
                document.getElementById('modalForm').classList.remove('show');
            }

            document.getElementById('btnRefresh').addEventListener('click', () => {
                loadBarang(currentFilter);
                loadStats();
            });
            
            // Format input harga pokok saat diisi
            document.getElementById('harga_pokok').addEventListener('input', function(e) {
                 let value = e.target.value.replace(/[^0-9]/g, '');
                 e.target.value = formatRupiah(value);
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