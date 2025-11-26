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
    <title>Manajemen Satuan - Sistem Inventory PBD</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard-content">
        <!-- Header -->
        <header>
            <div class="header-content">
                <div class="header-left">
                    <div class="logo">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                            <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                            <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                        </svg>
                    </div>
                    <div class="header-title">
                        <h1>Sistem Manajemen Inventory</h1>
                        <p>Manajemen Satuan</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem;">
                    <a href="datamaster.php" class="btn btn-secondary">
                        <span>⚙️</span> Data Master
                    </a>
                    <button id="btnTambah" class="btn btn-primary">
                        <span>+</span> Tambah Satuan
                    </button>
                    <a href="../models/auth.php?action=logout" class="btn btn-danger">
                        <span>🚪</span> Keluar
                    </a>
                </div>
            </div>
        </header>

        <div class="container">
            <!-- Satuan Table -->
            <div class="card">
                <div class="card-header">
                    <h2>Daftar Satuan</h2>
                    <button id="btnRefresh" class="btn btn-secondary btn-sm">🔄 Refresh</button>
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

        <!-- Footer -->
        <footer>
            <p>Sistem Manajemen Inventory PBD © 2025</p>
        </footer>
    </div>

    <!-- Modal Form -->
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
        document.addEventListener('DOMContentLoaded', () => {
            loadSatuan();
        });

        async function loadSatuan() {
            try {
                const response = await fetch('../models/satuan.php');
                if (response.status === 401) {
                    alert('Sesi Anda telah berakhir. Silakan login kembali.');
                    window.location.href = '../login.html';
                    return;
                }
                const result = await response.json();
                const tbody = document.getElementById('tableBody');
                
                if (result.success && result.data.length > 0) {
                    tbody.innerHTML = result.data.map(item => `
                        <tr>
                            <td>${item.idsatuan}</td>
                            <td>${item.nama_satuan}</td>
                            <td><span class="badge ${item.status == 1 ? 'badge-success' : 'badge-danger'}">${item.status_text}</span></td>
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
                document.getElementById('tableBody').innerHTML = '<tr><td colspan="4" style="text-align: center;">Gagal memuat data</td></tr>';
            }
        }

        document.getElementById('btnTambah').addEventListener('click', () => {
            document.getElementById('modalTitle').textContent = 'Tambah Satuan';
            document.getElementById('formSatuan').reset();
            document.getElementById('idsatuan').value = '';
            document.getElementById('formMethod').value = '';
            document.getElementById('modalForm').classList.add('show');
        });

        async function editSatuan(id) {
            try {
                const response = await fetch(`../models/satuan.php?id=${id}`);
                const result = await response.json();
                if (result.success) {
                    const data = result.data;
                    document.getElementById('modalTitle').textContent = 'Edit Satuan';
                    document.getElementById('idsatuan').value = data.idsatuan;
                    document.getElementById('formMethod').value = 'PUT';
                    document.getElementById('nama_satuan').value = data.nama_satuan;
                    document.getElementById('status').value = data.status;
                    document.getElementById('modalForm').classList.add('show');
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
                const response = await fetch('../models/satuan.php', { method: 'POST', body: formData });
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
                const response = await fetch('../models/satuan.php', { method: 'POST', body: formData });
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

        function closeModal() {
            document.getElementById('modalForm').classList.remove('show');
        }

        document.getElementById('btnRefresh').addEventListener('click', loadSatuan);

        window.onclick = function(event) {
            if (event.target === document.getElementById('modalForm')) {
                closeModal();
            }
        }
    </script>
</body>
</html>