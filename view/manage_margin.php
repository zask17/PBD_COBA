<?php
require_once '../model/koneksi.php';
require_once '../model/auth.php';

// Memastikan user sudah login dan mengambil data user
checkAuth();

// Ambil data user untuk ditampilkan di header
$username = $_SESSION['username'] ?? 'Pengguna';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Margin Penjualan - Sistem Inventory</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard_super_admin.css">
    <style>
        /* CSS Umum dari Dashboard */
        .dashboard-content header {
            padding: 15px 0;
        }

        .dashboard-content .header-title p {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
        }

        .dashboard-content .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 15px;
        }

        .header-actions span {
            color: white;
            font-weight: 500;
        }

        .btn-danger {
            background-color: #D32F2F;
            color: white;
            border: 2px solid #D32F2F;
        }

        .btn-danger:hover {
            background-color: #B71C1C;
            border-color: #B71C1C;
        }

        /* Styles untuk Card dan Table */
        .card {
            background: #FFFFFF;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .card-header h2 {
            font-size: 1.5rem;
            color: var(--primary-green, #4CAF50);
            margin: 0;
        }

        .card-body {
            padding: 0;
        }

        .table-responsive {
            overflow-x: auto;
        }

        #tableMargin {
            width: 100%;
            border-collapse: collapse;
        }

        #tableMargin th,
        #tableMargin td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        #tableMargin thead th {
            background-color: var(--background-section, #f7f7f7);
            color: var(--text-color-dark, #333);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        #tableMargin tbody tr:hover {
            background-color: #f9f9f9;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .badge-success {
            background-color: #E8F5E9;
            color: #4CAF50;
        }

        .badge-danger {
            background-color: #FFEBEE;
            color: #F44336;
        }

        /* Modal Styles (Sama seperti halaman Barang) */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            justify-content: center;
            align-items: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            /* Diatur ulang dari 500px di inline HTML Anda */
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease-out;
        }

        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--primary-green, #4CAF50);
        }

        .modal-header .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            border: none;
            background: none;
            cursor: pointer;
        }

        #formMargin {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #444;
            font-size: 0.9rem;
        }

        input[type="number"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1rem;
        }

        .form-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            margin-top: 20px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                        <p>Database PBD - Manajemen Margin Penjualan</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem; align-items: center;">
                    <span>👋 Halo, <?php echo ucwords($username); ?>!</span>
                    <a href="datamaster.php" class="btn btn-secondary"><span>⚙️</span> Menu Utama</a>
                    <a href="../models/auth.php?action=logout" class="btn btn-danger"><span>🚪</span> Keluar</a>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2>Daftar Margin Penjualan</h2>
                    <button id="btnRefresh" class="btn btn-secondary btn-sm">🔄 Refresh</button>
                    <button id="btnTambah" class="btn btn-primary"><span>+</span> Tambah Margin</button>
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
                                <tr>
                                    <td colspan="7" style="text-align: center;">Memuat data...</td>
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
        const API_URL = '../models/margin.php'; // Pastikan path ini benar

        document.addEventListener('DOMContentLoaded', () => {
            loadMargins();
        });

        async function loadMargins() {
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Memuat data...</td></tr>';

            try {
                const response = await fetch(API_URL);

                // Cek status otentikasi
                if (response.status === 401) {
                    alert('Sesi Anda telah berakhir. Anda akan diarahkan ke halaman login.');
                    window.location.href = '../view/login.php';
                    return;
                }

                const result = await response.json();

                if (result.success && result.data.length > 0) {
                    tbody.innerHTML = result.data.map(item => {
                        // Pastikan item.status adalah integer 1/0 atau string 'aktif'/'tidak_aktif'
                        const statusText = item.status == 1 || item.status === 'AKTIF' ? 'Aktif' : 'Tidak Aktif';
                        const statusClass = item.status == 1 || item.status === 'AKTIF' ? 'badge-success' : 'badge-danger';
                        const createdAt = item.created_at ? new Date(item.created_at).toLocaleString('id-ID') : '-';
                        const updatedAt = item.updated_at && item.updated_at !== item.created_at ? new Date(item.updated_at).toLocaleString('id-ID') : '-';
                        // Menyesuaikan nilai status untuk form edit
                        const statusValue = item.status == 1 || item.status === 'AKTIF' ? 'aktif' : 'tidak_aktif';


                        // Tombol Nonaktifkan hanya muncul jika status aktif
                        const deleteButton = statusValue === 'aktif' ?
                            `<button class="btn btn-danger btn-sm" onclick="deleteMargin('${item.idmargin_penjualan}', '${item.persen}')">Nonaktifkan</button>` : '';

                        return `
                            <tr>
                                <td>${item.idmargin_penjualan}</td>
                                <td>${item.persen}%</td>
                                <td><span class="badge ${statusClass}">${statusText}</span></td>
                                <td>${item.username || '-'}</td>
                                <td>${createdAt}</td>
                                <td>${updatedAt}</td>
                                <td class="action-buttons">
                                    <button class="btn btn-primary btn-sm" onclick="editMargin('${item.idmargin_penjualan}', '${statusValue}')">Edit</button>
                                    ${deleteButton}
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
            document.getElementById('status').value = 'aktif'; // Default aktif saat tambah
            document.getElementById('modalForm').classList.add('show');
        });

        // Menerima parameter statusValue untuk form
        async function editMargin(id, statusValue) {
            try {
                const response = await fetch(`${API_URL}?id=${id}`);
                const result = await response.json();
                if (result.success) {
                    const data = result.data;
                    document.getElementById('modalTitle').textContent = 'Edit Margin';
                    document.getElementById('idmargin_penjualan').value = data.idmargin_penjualan;
                    document.getElementById('formMethod').value = 'PUT';
                    document.getElementById('persen').value = data.persen;
                    // Menggunakan statusValue yang sudah di mapping (aktif/tidak_aktif)
                    document.getElementById('status').value = statusValue;
                    document.getElementById('modalForm').classList.add('show');
                } else {
                    alert('Gagal memuat data untuk edit: ' + result.message);
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
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
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
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
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
            document.getElementById('formMargin').reset(); // Reset form saat ditutup
        }

        // Tombol Refresh memanggil loadMargins
        document.getElementById('btnRefresh').addEventListener('click', loadMargins);

        window.onclick = function(event) {
            if (event.target === document.getElementById('modalForm')) {
                closeModal();
            }
        }
    </script>
</body>

</html>