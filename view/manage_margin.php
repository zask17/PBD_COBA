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
    <title>Manajemen Margin Penjualan - Sistem Inventory</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard-content">
        <!-- Header -->
        <header>
            <div class="header-content">
                <div class="header-left">
                    <div class="logo">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
                    </div>
                    <div class="header-title">
                        <h1>Sistem Manajemen Inventory</h1>
                        <p>Manajemen Margin Penjualan</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem;">
                    <a href="datamaster.php" class="btn btn-secondary"><span>⚙️</span> Menu Utama</a>
                    <button id="btnTambah" class="btn btn-primary"><span>+</span> Tambah Margin</button>
                    <a href="../models/auth.php?action=logout" class="btn btn-danger"><span>🚪</span> Keluar</a>
                </div>
            </div>
        </header>

        <div class="container">
            <!-- Margin Table -->
            <div class="card">
                <div class="card-header">
                    <h2>Daftar Margin Penjualan</h2>
                    <button id="btnRefresh" class="btn btn-secondary btn-sm">🔄 Refresh</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tableMargin">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Persentase (%)</th>
                                    <th>Status</th>
                                    <th>Dibuat/Diubah Oleh</th>
                                    <th>Waktu Dibuat</th>
                                    <th>Waktu Diperbarui</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr><td colspan="7" style="text-align: center;">Memuat data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer>
            <p>Sistem Manajemen Inventory PBD © 2025</p>
        </footer>
    </div>

    <!-- Modal Form -->
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
                    <input type="number" id="persen" name="persen" required step="0.01" min="0">
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="aktif">Aktif</option>
                        <option value="tidak_aktif">Tidak Aktif</option>
                    </select>
                     <small style="color: #8b92a7; font-size: 12px;">Hanya boleh ada satu margin yang aktif.</small>
                </div>
                
                <div class="form-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const API_URL = '../models/margin.php';

        document.addEventListener('DOMContentLoaded', () => {
            loadMargins();
        });

        async function loadMargins() {
            try {
                const response = await fetch(API_URL);
                const result = await response.json();
                const tbody = document.getElementById('tableBody');
                
                if (result.success && result.data.length > 0) {
                    tbody.innerHTML = result.data.map(item => {
                        const statusText = item.status == 1 ? 'Aktif' : 'Tidak Aktif';
                        const statusClass = item.status == 1 ? 'badge-success' : 'badge-danger';
                        const createdAt = item.created_at ? new Date(item.created_at).toLocaleString('id-ID') : '-';
                        const updatedAt = item.updated_at ? new Date(item.updated_at).toLocaleString('id-ID') : '-';

                        return `
                            <tr>
                                <td>${item.idmargin_penjualan}</td>
                                <td>${item.persen}</td>
                                <td><span class="badge ${statusClass}">${statusText}</span></td>
                                <td>${item.username}</td>
                                <td>${createdAt}</td>
                                <td>${updatedAt}</td>
                                <td class="action-buttons">
                                    <button class="btn btn-primary btn-sm" onclick="editMargin('${item.idmargin_penjualan}')">Edit</button>
                                    ${item.status == 1 ? `<button class="btn btn-danger btn-sm" onclick="deleteMargin('${item.idmargin_penjualan}', '${item.persen}')">Nonaktifkan</button>` : ''}
                                </td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Tidak ada data margin</td></tr>';
                }
            } catch (error) {
               console.error('Error loading margins:', error);
                document.getElementById('tableBody').innerHTML = '<tr><td colspan="7" style="text-align: center;">Gagal memuat data</td></tr>';
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
                    document.getElementById('status').value = data.status;
                    document.getElementById('modalForm').classList.add('show');
                }
            } catch (error) {
                alert('Error memuat data untuk edit: ' + error.message);
            }
        }

        async function deleteMargin(id, persen) {
            if (!confirm(`Yakin ingin menonaktifkan margin "${persen}%"? Margin yang aktif saat ini akan dinonaktifkan.`)) return;
            
            const formData = new FormData();
            formData.append('_method', 'DELETE');
            formData.append('idmargin_penjualan', id);
            
            try {
                const response = await fetch(API_URL, { method: 'POST', body: formData });
                const result = await response.json();
                alert(result.message);
                if (result.success) loadMargins();
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
                    loadMargins();
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        function closeModal() {
            document.getElementById('modalForm').classList.remove('show');
        }

        document.getElementById('btnRefresh').addEventListener('click', loadMargins);

        window.onclick = function(event) {
            if (event.target === document.getElementById('modalForm')) {
                closeModal();
            }
        }
    </script>
</body>

</html>