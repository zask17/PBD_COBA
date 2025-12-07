<?php

require_once '../model/koneksi.php';
require_once '../model/auth.php';

checkAuth();

// DISESUAIKAN: Variabel status aktif/non-aktif menggunakan kode 'A' dan 'N'/'T'
// Menggunakan 'T' sesuai skema DDL: WHEN status = 'T' THEN 'Non-Aktif'
$vendor_statuses = ['A' => 'Aktif', 'T' => 'Non-Aktif']; 
// DISESUAIKAN: Variabel badan hukum menggunakan kode 'A' dan 'T'
$badan_hukum_options = ['A' => 'Berbadan Hukum (PT)', 'T' => 'Tidak Berbadan Hukum (CV/UD)']; 
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Vendor - Sistem Inventory PBD</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard_super_admin.css">
    <link rel="stylesheet" href="../css/vendor.css">
    <style>
        /* CSS Tambahan untuk tombol filter aktif */
        .btn-filter-group .active {
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.5); 
            font-weight: bold;
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
                        <p>Database PBD - Manajemen Vendor</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem;">
                    <a href="../model/auth.php?action=logout" class="btn btn-danger">
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
                    <div style="display: flex; gap: 1rem; align-items: center; margin-left: auto;">
                        
                        <div class="btn-filter-group" style="display: flex; gap: 0.5rem;">
                            <button id="btnVendorAktif" class="btn btn-success btn-sm active" data-filter="aktif">✔ Vendor Aktif</button>
                            <button id="btnSemuaVendor" class="btn btn-info btn-sm" data-filter="semua">Semua Vendor</button>
                        </div>
                        <button id="btnTambah" class="btn btn-primary">
                            <span>+</span> Tambah Vendor
                        </button>
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
            let currentFilter = 'aktif'; // Filter default saat halaman dimuat

            document.addEventListener('DOMContentLoaded', () => {
                loadStats();
                loadVendor(currentFilter);
            });

            async function loadStats() {
                try {
                    const response = await fetch('../model/vendor.php?action=get_stats');
                    const result = await response.json();

                    if (result.success) {
                        document.getElementById('totalVendor').textContent = result.data.total_vendor || 0;
                        document.getElementById('totalVendorAktif').textContent = result.data.total_aktif || 0;
                    }
                } catch (error) {
                    console.error('Error loading vendor stats:', error);
                }
            }

            async function loadVendor(filter) {
                currentFilter = filter;
                const url = `../model/vendor.php?filter=${filter}`;
                const tbody = document.getElementById('tableBody');
                
                // Update tampilan tombol filter
                document.getElementById('btnVendorAktif').classList.remove('active');
                document.getElementById('btnSemuaVendor').classList.remove('active');
                if (filter === 'aktif') {
                    document.getElementById('btnVendorAktif').classList.add('active');
                } else {
                    document.getElementById('btnSemuaVendor').classList.add('active');
                }

                try {
                    const response = await fetch(url);
                    if (response.status === 401) {
                        alert('Sesi Anda telah berakhir. Anda akan diarahkan ke halaman login.');
                        window.location.href = '../login.html';
                        return;
                    }

                    const result = await response.json();

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
                    } else if (result.success && result.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Tidak ada data vendor yang sesuai dengan filter.</td></tr>';
                    } else {
                        console.error('API Error:', result.message);
                        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: #f5576c;">Gagal memuat data: ${result.message || 'Periksa konsol untuk detail.'}</td></tr>`;
                    }
                } catch (error) {
                    console.error('Network Error loading vendors:', error);
                    document.getElementById('tableBody').innerHTML = `<tr><td colspan="5" style="text-align: center; color: #f5576c;">Gagal memuat data. Periksa konsol untuk detail.</td></tr>`;
                }
            }
            
            // Event Listeners untuk tombol filter baru
            document.getElementById('btnVendorAktif').addEventListener('click', () => {
                loadVendor('aktif');
            });

            document.getElementById('btnSemuaVendor').addEventListener('click', () => {
                loadVendor('semua');
            });


            document.getElementById('btnTambah').addEventListener('click', () => {
                document.getElementById('modalTitle').textContent = 'Tambah Vendor';
                document.getElementById('formVendor').reset();
                document.getElementById('idvendor').value = '';
                // Set default status/badan hukum saat tambah baru 
                document.getElementById('badan_hukum').value = 'A'; 
                document.getElementById('status').value = 'A';
                document.getElementById('formMethod').value = 'POST';
                document.getElementById('modalForm').classList.add('show');
            });

            async function editVendor(id) {
                try {
                    const response = await fetch(`../model/vendor.php?id=${id}`);
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
                if (!confirm(`Nonaktifkan vendor "${nama}"? (Ini akan mengubah statusnya menjadi Non-Aktif/T)`)) return;

                try {
                    const formData = new FormData();
                    formData.append('_method', 'DELETE');
                    formData.append('idvendor', id);

                    const response = await fetch('../model/vendor.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    alert(result.message);

                    if (result.success) {
                        loadVendor(currentFilter); // Muat ulang dengan filter yang aktif
                        loadStats();
                    }
                } catch (error) {
                    alert('Error: ' + error.message);
                }
            }

            document.getElementById('formVendor').addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);

                try {
                    const response = await fetch('../model/vendor.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    alert(result.message);

                    if (result.success) {
                        closeModal();
                        loadVendor(currentFilter); // Muat ulang dengan filter yang aktif
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
    <?php include 'footer.php'; ?>

</html>