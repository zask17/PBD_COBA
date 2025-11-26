<?php
require_once '../model/koneksi.php';
require_once '../model/auth.php';

// Memastikan user sudah login
checkAuth();
$username = $_SESSION['username'] ?? 'Pengguna'; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Satuan - Sistem Inventory PBD</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard_super_admin.css">
    <link rel="stylesheet" href="../css/satuan.css">
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
                        <p>Manajemen Satuan</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem; align-items: center;">
                    <span>👋 Halo, <?php echo ucwords($username); ?>!</span>
                    <a href="../model/auth.php?action=logout" class="btn btn-danger">
                        <span>🚪</span> Keluar
                    </a>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2>Daftar Satuan</h2>
                    <button id="btnRefresh" class="btn btn-secondary btn-sm">🔄 Refresh</button>
                    <button id="btnTambah" class="btn btn-primary">
                        <span>+</span> Tambah Satuan
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tableSatuan">
                            <thead>
                                <tr>
                                    <th>ID Satuan</th>
                                    <th>Nama Satuan</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr>
                                    <td colspan="4" style="text-align: center;">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <footer>
            <p>Sistem Manajemen Inventory PBD © 2025</p>
        </footer>
    </div>

    <div id="modalForm" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah Satuan</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <form id="formSatuan">
                <input type="hidden" id="idsatuan" name="idsatuan">
                <input type="hidden" id="formMethod" name="_method">
                
                <div class="form-group">
                    <label for="nama_satuan">Nama Satuan *</label>
                    <input type="text" id="nama_satuan" name="nama_satuan" required>
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
        // Perbaikan: Pastikan path API ini sesuai dengan nama file PHP Anda.
        const API_URL = '../model/satuan.php'; 

        document.addEventListener('DOMContentLoaded', () => {
            loadSatuan();
        });

        function closeModal() {
            document.getElementById('modalForm').classList.remove('show');
            document.getElementById('formSatuan').reset();
        }

        async function loadSatuan() {
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">Loading...</td></tr>';
            
            try {
                // Fetch data dari API yang benar
                const response = await fetch(API_URL); 
                
                if (response.status === 401) {
                    alert('Sesi Anda telah berakhir. Silakan login kembali.');
                    window.location.href = '../view/login.php'; 
                    return;
                }
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    tbody.innerHTML = result.data.map(item => `
                        <tr>
                            <td>${item.idsatuan}</td>
                            <td>${item.nama_satuan}</td>
                            <td><span class="badge ${item.status_text === 'Aktif' ? 'badge-success' : 'badge-danger'}">${item.status_text}</span></td>
                            <td class="action-buttons">
                                <button class="btn btn-primary btn-sm" onclick="editSatuan('${item.idsatuan}')">Edit</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteSatuan('${item.idsatuan}', '${item.nama_satuan}')">Hapus</button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">Tidak ada data satuan</td></tr>';
                }
            } catch (error) {
                console.error('Error loading satuan:', error);
                // Menampilkan pesan error yang lebih jelas jika gagal koneksi/format
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">Gagal memuat data. Periksa koneksi API dan database.</td></tr>';
            }
        }

        document.getElementById('btnTambah').addEventListener('click', () => {
            document.getElementById('modalTitle').textContent = 'Tambah Satuan';
            document.getElementById('formSatuan').reset();
            document.getElementById('idsatuan').value = '';
            document.getElementById('formMethod').value = 'POST';
            document.getElementById('modalForm').classList.add('show');
        });

        async function editSatuan(id) {
            try {
                // Panggil API untuk GET single data
                const response = await fetch(`${API_URL}?id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    const data = result.data;
                    document.getElementById('modalTitle').textContent = 'Edit Satuan';
                    document.getElementById('idsatuan').value = data.idsatuan;
                    document.getElementById('formMethod').value = 'PUT';
                    document.getElementById('nama_satuan').value = data.nama_satuan;
                    document.getElementById('status').value = data.status; // status: 'aktif'/'tidak_aktif'
                    document.getElementById('modalForm').classList.add('show');
                } else {
                    alert('Satuan tidak ditemukan: ' + result.message);
                }
            } catch (error) {
                alert('Error memuat data untuk edit: ' + error.message);
            }
        }

        async function deleteSatuan(id, nama) {
            if (!confirm(`Yakin ingin menonaktifkan satuan "${nama}"?`)) return;
            
            const formData = new FormData();
            formData.append('_method', 'DELETE');
            formData.append('idsatuan', id);
            
            try {
                const response = await fetch(API_URL, { method: 'POST', body: formData });
                const result = await response.json();
                alert(result.message);
                if (result.success) loadSatuan();
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        document.getElementById('formSatuan').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            try {
                const response = await fetch(API_URL, { method: 'POST', body: formData });
                const result = await response.json();
                alert(result.message);
                if (result.success) {
                    closeModal();
                    loadSatuan();
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        document.getElementById('btnRefresh').addEventListener('click', loadSatuan);

        window.onclick = function(event) {
            if (event.target === document.getElementById('modalForm')) {
                closeModal();
            }
        }
    </script>
</body>
</html>