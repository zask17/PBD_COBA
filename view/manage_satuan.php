<?php
require_once '../model/koneksi.php';
require_once '../model/auth.php';

// Memastikan user sudah login
checkAuth();
$username = $_SESSION['username'] ?? 'Pengguna';

// Tentukan URL kembali berdasarkan role
$user_role = $_SESSION['role'] ?? 'Guest';
$dashboard_url = '';
if ($user_role === 'super administrator') {
    $dashboard_url = 'dashboard_super_admin.php';
} else if ($user_role === 'administrator') {
    $dashboard_url = 'dashboard_admin.php';
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Satuan - Sistem Inventory PBD</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard_super_admin.css">
    <link rel="stylesheet" href="../css/satuan.css">
    <style>
        .btn.active {
            box-shadow: 0 0 0 0.2rem rgba(38, 143, 255, 0.5);
            /* Contoh: bayangan biru */
            opacity: 0.8;
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
                        <p>Manajemen Satuan</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem; align-items: center;">
                    <span>👋 Halo, <?php echo ucwords($username); ?>!</span>
                    <?php if (!empty($dashboard_url)): ?>
                        <a href="<?php echo $dashboard_url; ?>" class="btn btn-secondary"><span> Kembali ke Dashboard</span></a>
                    <?php endif; ?>
                    <a href="../model/auth.php?action=logout" class="btn btn-danger">
                        <span>Keluar</span>
                    </a>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2>Daftar Satuan</h2>
                    <div style="display: flex; gap: 0.5rem; margin-left: auto; margin-right: 1rem;">
                        <button id="btnSatuanAktif" class="btn btn-info btn-sm active" data-filter="aktif">✔ Satuan Aktif</button>
                        <button id="btnSemuaSatuan" class="btn btn-warning btn-sm" data-filter="semua">Semua Satuan</button>
                    </div>
                    <button id="btnRefresh" class="btn btn-secondary btn-sm">Refresh</button>
                    <button id="btnTambah" class="btn btn-primary"><span>Tambah Satuan</span></button>
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
        const API_URL = '../model/satuan.php';
        // Ubah default view menjadi 'aktif'
        let currentView = 'aktif'; 

        document.addEventListener('DOMContentLoaded', () => {
            loadSatuan(currentView);
            // Hapus baris ini karena sudah diatur di markup dan akan diatur di loadSatuan
            // document.getElementById('btnSemuaSatuan').classList.add('active'); 
        });

        function closeModal() {
            document.getElementById('modalForm').classList.remove('show');
            document.getElementById('formSatuan').reset();
        }

        // Fungsi utama untuk memuat data dengan filter
        async function loadSatuan(view = 'aktif') { // Ubah default argument di fungsi
            currentView = view;
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">Loading...</td></tr>';

            // Atur status active pada tombol
            document.getElementById('btnSatuanAktif').classList.remove('active');
            document.getElementById('btnSemuaSatuan').classList.remove('active');
            if (view === 'aktif') {
                document.getElementById('btnSatuanAktif').classList.add('active');
            } else {
                document.getElementById('btnSemuaSatuan').classList.add('active');
            }

            try {
                // Tambahkan parameter 'view' ke URL API
                const response = await fetch(`${API_URL}?view=${view}`);

                if (response.status === 401) {
                    alert('Sesi Anda telah berakhir. Silakan login kembali.');
                    window.location.href = '../view/login.php';
                    return;
                }
                const result = await response.json();

                if (result.success && result.data.length > 0) {
                    tbody.innerHTML = result.data.map(item => {
                        // Di V_SATUAN_AKTIF, status_text tidak ada, jadi defaultnya 'Aktif'
                        const statusText = item.status_text || 'Aktif'; 
                        const badgeClass = statusText === 'Aktif' ? 'badge-success' : 'badge-danger';
                        
                        // --- LOGIKA PERUBAHAN TOMBOL AFEKSI START ---
                        let actionButton;
                        if (statusText === 'Aktif') {
                            // Jika Aktif, tombolnya adalah Nonaktifkan
                            actionButton = `<button class="btn btn-danger btn-sm" onclick="deleteSatuan('${item.idsatuan}', '${item.nama_satuan}')">Nonaktifkan</button>`;
                        } else {
                            // Jika Non-Aktif, tombolnya adalah Aktifkan
                            actionButton = `<button class="btn btn-success btn-sm" onclick="reactivateSatuan('${item.idsatuan}', '${item.nama_satuan}')">Aktifkan</button>`;
                        }
                        // --- LOGIKA PERUBAHAN TOMBOL AFEKSI END ---

                        return `
                            <tr>
                                <td>${item.idsatuan}</td>
                                <td>${item.nama_satuan}</td>
                                <td><span class="badge ${badgeClass}">${statusText}</span></td>
                                <td class="action-buttons">
                                    <button class="btn btn-primary btn-sm" onclick="editSatuan('${item.idsatuan}')">Edit</button>
                                    ${actionButton}
                                </td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">Tidak ada data satuan</td></tr>';
                }
            } catch (error) {
                console.error('Error loading satuan:', error);
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">Gagal memuat data. Periksa koneksi API dan database.</td></tr>';
            }
        }

        // --- FUNGSI BARU UNTUK RE-AKTIVASI (SOFT DELETE REVERSAL) ---
        async function reactivateSatuan(id, nama) {
            if (!confirm(`Yakin ingin mengaktifkan kembali satuan "${nama}"?`)) return;

            try {
                // 1. Ambil detail data (perlu nama satuan untuk PUT)
                const responseDetail = await fetch(`${API_URL}?id=${id}`);
                const detailResult = await responseDetail.json();
                
                if (!detailResult.success) {
                    alert('Gagal mengambil detail satuan untuk re-aktivasi.');
                    return;
                }

                const data = detailResult.data;
                
                // 2. Siapkan data untuk PUT (Update status menjadi aktif)
                const formData = new FormData();
                formData.append('_method', 'PUT'); 
                formData.append('idsatuan', id);
                formData.append('nama_satuan', data.nama_satuan); 
                formData.append('status', 'aktif'); // Mengubah status menjadi aktif (1)

                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                alert(result.message);
                if (result.success) loadSatuan(currentView); // Muat ulang dengan filter yang aktif
            } catch (error) {
                alert('Error: Gagal mengaktifkan satuan ' + error.message);
            }
        }


        // Event Listeners untuk tombol filter
        document.getElementById('btnSatuanAktif').addEventListener('click', () => {
            loadSatuan('aktif');
        });

        document.getElementById('btnSemuaSatuan').addEventListener('click', () => {
            loadSatuan('semua');
        });

        document.getElementById('btnTambah').addEventListener('click', () => {
            document.getElementById('modalTitle').textContent = 'Tambah Satuan';
            document.getElementById('formSatuan').reset();
            document.getElementById('idsatuan').value = '';
            document.getElementById('formMethod').value = 'POST';
            document.getElementById('status').value = 'aktif'; // Default aktif
            document.getElementById('modalForm').classList.add('show');
        });

        async function editSatuan(id) {
            try {
                const response = await fetch(`${API_URL}?id=${id}`);
                const result = await response.json();

                if (result.success) {
                    const data = result.data;
                    document.getElementById('modalTitle').textContent = 'Edit Satuan';
                    document.getElementById('idsatuan').value = data.idsatuan;
                    document.getElementById('formMethod').value = 'PUT';
                    document.getElementById('nama_satuan').value = data.nama_satuan;
                    document.getElementById('status').value = data.status;
                    document.getElementById('modalForm').classList.add('show');
                } else {
                    alert('Satuan tidak ditemukan: ' + result.message);
                }
            } catch (error) {
                alert('Error memuat data untuk edit: ' + error.message);
            }
        }

        // Soft Delete / Menonaktifkan
        async function deleteSatuan(id, nama) {
            if (!confirm(`Yakin ingin menonaktifkan satuan "${nama}"? (Status akan diubah menjadi Non-Aktif)`)) return;

            const formData = new FormData();
            formData.append('_method', 'DELETE');
            formData.append('idsatuan', id);

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                alert(result.message);
                if (result.success) loadSatuan(currentView); // Muat ulang dengan filter yang aktif
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        document.getElementById('formSatuan').addEventListener('submit', async (e) => {
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
                    loadSatuan(currentView); // Muat ulang dengan filter yang aktif
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        document.getElementById('btnRefresh').addEventListener('click', () => loadSatuan(currentView));

        window.onclick = function(event) {
            if (event.target === document.getElementById('modalForm')) {
                closeModal();
            }
        }
    </script>
</body>
<?php include 'footer.php'; ?>

</html>