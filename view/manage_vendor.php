<?php

require_once '../model/koneksi.php';
require_once '../model/auth.php';

checkAuth(); 

// Variabel status aktif/non-aktif untuk select option
$vendor_statuses = [1 => 'Aktif', 0 => 'Non-Aktif'];
$badan_hukum_options = ['c' => 'Perusahaan (C)', 'p' => 'Perorangan (P)'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Vendor - Sistem Inventory PBD</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard-content">
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
                        <p>Database PBD - Manajemen Vendor</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem;">
                    <a href="datamaster.php" class="btn btn-secondary">
                        <span>⚙️</span> Data Master
                    </a>
                    <button id="btnTambah" class="btn btn-primary">
                        <span>+</span> Tambah Vendor
                    </button>
                    <a href="../models/auth.php?action=logout" class="btn btn-danger">
                        <span>🚪</span> Keluar
                    </a>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Vendor</h3>
                    <div class="value" id="totalVendor">0</div>
                </div>
                <div class="stat-card">
                    <h3>Total Vendor Aktif</h3>
                    <div class="value" id="totalVendorAktif">0</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Daftar Vendor</h2>
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <button id="btnFilterAktif" class="btn btn-secondary btn-sm" data-filter="aktif">Tampilkan Semua</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tableVendor">
                            <thead>
                                <tr>
                                    <th>ID Vendor</th>
                                    <th>Nama Vendor</th>
                                    <th>Badan Hukum</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr>
                                    <td colspan="5" style="text-align: center;">Loading...</td>
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
                <h3 id="modalTitle">Tambah Vendor</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <form id="formVendor">
                <input type="hidden" id="idvendor" name="idvendor">
                <input type="hidden" id="formMethod" name="_method" value="POST">
                
                <div class="form-group">
                    <label for="nama_vendor">Nama Vendor *</label>
                    <input type="text" id="nama_vendor" name="nama_vendor" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="badan_hukum">Badan Hukum *</label>
                        <select id="badan_hukum" name="badan_hukum" required>
                            <option value="">Pilih Jenis</option>
                            <?php foreach ($badan_hukum_options as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select id="status" name="status" required>
                            <?php foreach ($vendor_statuses as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
            loadStats();
            loadVendor();
        });

        async function loadStats() {
            try {
                const response = await fetch('../models/vendor.php?action=get_stats');
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('totalVendor').textContent = result.data.total_vendor || 0;
                    document.getElementById('totalVendorAktif').textContent = result.data.total_aktif || 0;
                }
            } catch (error) {
                console.error('Error loading vendor stats:', error);
            }
        }

        async function loadVendor() {
            const filter = document.getElementById('btnFilterAktif').dataset.filter;
            const url = `../models/vendor.php?filter=${filter}`;

            try {
                const response = await fetch(url); // Hanya butuh satu fetch
                if (response.status === 401) {
                    alert('Sesi Anda telah berakhir. Anda akan diarahkan ke halaman login.');
                    window.location.href = '../login.html';
                    return;
                }

                const result = await response.json();
                const tbody = document.getElementById('tableBody');
                
                if (result.success && result.data.length > 0) {
                    tbody.innerHTML = result.data.map(item => `
                        <tr>
                            <td>${item.idvendor}</td>
                            <td>${item.nama_vendor}</td>
                            <td>${item.jenis_badan_hukum}</td>
                            <td><span class="badge ${item.status_aktif === 'Aktif' ? 'badge-success' : 'badge-danger'}">${item.status_aktif}</span></td>
                            <td class="action-buttons">
                                <button class="btn btn-primary btn-sm" onclick="editVendor('${item.idvendor}')">Edit</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteVendor('${item.idvendor}', '${item.nama_vendor}')">Hapus</button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Tidak ada data vendor</td></tr>';
                }
            } catch (error) {
                console.error('Error loading vendors:', error);
                // Tampilkan pesan error yang lebih ramah di tabel
                document.getElementById('tableBody').innerHTML = `<tr><td colspan="5" style="text-align: center; color: #f5576c;">Gagal memuat data. Periksa konsol untuk detail.</td></tr>`;
            }
        }

        document.getElementById('btnFilterAktif').addEventListener('click', function() {
            const currentFilter = this.dataset.filter;
            if (currentFilter === 'aktif') {
                this.dataset.filter = 'semua';
                this.textContent = 'Tampilkan Semua';
            } else {
                this.dataset.filter = 'aktif';
                this.textContent = 'Tampilkan Aktif Saja';
            }
            loadVendor();
        });

        document.getElementById('btnTambah').addEventListener('click', () => {
            document.getElementById('modalTitle').textContent = 'Tambah Vendor';
            document.getElementById('formVendor').reset();
            document.getElementById('idvendor').value = '';
            document.getElementById('formMethod').value = 'POST';
            document.getElementById('modalForm').classList.add('show');
        });

        async function editVendor(id) {
            try {
                const response = await fetch(`../models/vendor.php?id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    const data = result.data;
                    document.getElementById('modalTitle').textContent = 'Edit Vendor';
                    document.getElementById('idvendor').value = data.idvendor;
                    document.getElementById('formMethod').value = 'PUT';
                    document.getElementById('nama_vendor').value = data.nama_vendor;
                    document.getElementById('badan_hukum').value = data.badan_hukum;
                    document.getElementById('status').value = data.status;
                    
                    document.getElementById('modalForm').classList.add('show');
                }
            } catch (error) {
                alert('Error loading data: ' + error.message);
            }
        }

        async function deleteVendor(id, nama) {
            if (!confirm(`Hapus vendor "${nama}"?`)) return;
            
            try {
                const formData = new FormData();
                formData.append('_method', 'DELETE');
                formData.append('idvendor', id);
                
                const response = await fetch('../models/vendor.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                alert(result.message);
                
                if (result.success) {
                    loadVendor();
                    loadStats();
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        document.getElementById('formVendor').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const method = formData.get('_method');
            
           try {
                const response = await fetch('../models/vendor.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                alert(result.message);
                
                if (result.success) {
                    closeModal();
                    loadVendor();
                    loadStats();
                }
           } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        function closeModal() {
            document.getElementById('modalForm').classList.remove('show');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalForm');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>

</body>
</html>