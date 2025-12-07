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
    <title>Manajemen Margin - Sistem Inventory PBD</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard_super_admin.css">
    <link rel="stylesheet" href="../css/margin.css">
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
                        <p>Manajemen Margin Penjualan</p>
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
                    <h2>Daftar Margin Penjualan</h2>
                    <button id="btnFilterAktif" class="btn btn-success btn-sm" data-filter="aktif">Margin Aktif</button>
                    <button id="btnFilterSemua" class="btn btn-info btn-sm active" data-filter="semua">Semua Margin</button>
                    <button id="btnRefresh" class="btn btn-secondary btn-sm">Refresh</button>
                    <button id="btnTambah" class="btn btn-primary"><span>+</span> Tambah Margin</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tableMargin">
                            <thead>
                                <tr>
                                    <th>ID Margin</th>
                                    <th>Persentase (%)</th>
                                    <th>Status</th>
                                    <th>Dibuat Oleh</th>
                                    <th>Waktu Dibuat</th>
                                    <th>Waktu Diperbarui</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr>
                                    <td colspan="7" style="text-align: center;">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="modalForm" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah Margin</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <form id="formMargin">
                <input type="hidden" id="idmargin_penjualan" name="idmargin_penjualan">
                <input type="hidden" id="formMethod" name="_method">
                
                <div class="form-group">
                    <label for="persen">Persentase Margin (%) *</label>
                    <input type="number" id="persen" name="persen" required min="0" step="0.01">
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
        const API_URL = '../model/margin.php'; 

        document.addEventListener('DOMContentLoaded', () => {
            loadMargin();
        });

        function closeModal() {
            document.getElementById('modalForm').classList.remove('show');
            document.getElementById('formMargin').reset();
        }

        async function loadMargin(filter = 'semua') {
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Loading...</td></tr>';
            
            try {
                const response = await fetch(`${API_URL}?filter=${filter}`);
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    tbody.innerHTML = result.data.map(item => `
                        <tr>
                            <td>${item.idmargin_penjualan}</td>
                            <td>${item.persen}</td>
                            <td><span class="badge ${item.status_text === 'AKTIF' ? 'badge-success' : 'badge-danger'}">${item.status_text}</span></td>
                            <td>${item.username}</td>
                            <td>${item.created_at}</td>
                            <td>${item.updated_at || '-'}</td>
                            <td class="action-buttons">
                                <button class="btn btn-primary btn-sm" onclick="editMargin('${item.idmargin_penjualan}')">Edit</button>
                                ${item.status_text === 'AKTIF' ? 
                                    `<button class="btn btn-danger btn-sm" onclick="deleteMargin('${item.idmargin_penjualan}', '${item.persen}')">Nonaktifkan</button>` : 
                                    `<button class="btn btn-success btn-sm" onclick="aktifkanMargin('${item.idmargin_penjualan}')">Aktifkan</button>`}
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Tidak ada data margin</td></tr>';
                }
            } catch (error) {
                console.error('Error loading margin:', error);
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Gagal memuat data. Periksa koneksi API dan database.</td></tr>';
            }
        }

        document.getElementById('btnTambah').addEventListener('click', () => {
            document.getElementById('modalTitle').textContent = 'Tambah Margin';
            document.getElementById('formMargin').reset();
            document.getElementById('idmargin_penjualan').value = '';
            document.getElementById('formMethod').value = 'POST';
            document.getElementById('modalForm').classList.add('show');
        });

        async function editMargin(id) {
            try {
                const response = await fetch(`${API_URL}?id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    const data = result.data;
                    document.getElementById('modalTitle').textContent = 'Edit Margin';
                    document.getElementById('idmargin_penjualan').value = data.idmargin_penjualan;
                    document.getElementById('formMethod').value = 'PUT';
                    document.getElementById('persen').value = data.persen;
                    document.getElementById('status').value = data.status; // 'aktif'/'tidak_aktif'
                    document.getElementById('modalForm').classList.add('show');
                } else {
                    alert('Margin tidak ditemukan: ' + result.message);
                }
            } catch (error) {
                alert('Error memuat data untuk edit: ' + error.message);
            }
        }

        function aktifkanMargin(id) {
            editMargin(id); // Buka modal edit, user bisa set status=aktif
        }

        async function deleteMargin(id, persen) {
            if (!confirm(`Yakin ingin menonaktifkan margin "${persen}"?`)) return;
            
            const formData = new FormData();
            formData.append('_method', 'DELETE');
            formData.append('idmargin_penjualan', id);
            
            try {
                const response = await fetch(API_URL, { method: 'POST', body: formData });
                const result = await response.json();
                alert(result.message);
                if (result.success) loadMargin();
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        document.getElementById('formMargin').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            try {
                const response = await fetch(API_URL, { method: 'POST', body: formData });
                const result = await response.json();
                alert(result.message);
                if (result.success) {
                    closeModal();
                    loadMargin();
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        document.getElementById('btnRefresh').addEventListener('click', loadMargin);
        document.getElementById('btnFilterAktif').addEventListener('click', () => loadMargin('aktif'));
        document.getElementById('btnFilterSemua').addEventListener('click', () => loadMargin('semua'));

        window.onclick = function(event) {
            if (event.target === document.getElementById('modalForm')) {
                closeModal();
            }
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>