<?php

use const Dom\INDEX_SIZE_ERR;

require_once '../model/koneksi.php';
require_once '../model/auth.php';

checkAuth();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Role - Sistem Inventory PBD</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard_super_admin.css">
    <link rel="stylesheet" href="../css/role.css">
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
                        <p>Manajemen Role</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem; align-items: center;"></div>
                <a href="../model/auth.php?action=logout" class="btn btn-danger">
                    <span>Keluar</span>
                </a>
            </div>
    </div>
    </header>


    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>Daftar Role</h2>
                <button id="btnRefresh" class="btn btn-secondary btn-sm">Refresh</button>
                <button id="btnTambah" class="btn btn-primary">
                    <span>Tambah Role</span>
                </button>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table id="tableRoles">
                        <thead>
                            <tr>
                                <th>ID Role</th>
                                <th>Nama Role</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="3" style="text-align: center;">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>


    <div id="modalForm" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 id="modalTitle">Edit Role</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <form id="formRole">
                <input type="hidden" id="idrole" name="idrole">
                <input type="hidden" id="formMethod" name="_method">

                <div class="form-group">
                    <label for="nama_role">Nama Role *</label>
                    <input type="text" id="nama_role" name="nama_role" required>
                </div>

                <div class="form-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const API_URL = '../model/roles.php';

        document.addEventListener('DOMContentLoaded', () => {
            loadRoles();
        });

        async function loadRoles() {
            try {
                const response = await fetch(API_URL);
                if (response.status === 401) {
                    alert('Sesi Anda telah berakhir. Silakan login kembali.');
                    window.location.href = 'login.php';
                    return;
                }
                const result = await response.json();
                const tbody = document.getElementById('tableBody');

                if (result.success && result.data.length > 0) {
                    tbody.innerHTML = result.data.map(item => `
<tr>
<td>${item.idrole}</td>
<td>${item.nama_role}</td>
<td class="action-buttons">
<button class="btn btn-primary btn-sm" onclick="editRole('${item.idrole}')">Edit</button>
<button class="btn btn-danger btn-sm" onclick="deleteRole('${item.idrole}', '${item.nama_role}')">Hapus</button>
</td>
</tr>
`).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="3" style="text-align: center;">Tidak ada data role</td></tr>';
                }
            } catch (error) {
                console.error('Error loading roles:', error);
                document.getElementById('tableBody').innerHTML = '<tr><td colspan="3" style="text-align: center;">Gagal memuat data</td></tr>';
            }
        }

        // Fungsi Tambah Role
        document.getElementById('btnTambah').addEventListener('click', () => {
            document.getElementById('modalTitle').textContent = 'Tambah Role';
            document.getElementById('formRole').reset();
            document.getElementById('idrole').value = '';
            document.getElementById('formMethod').value = 'POST'; // Set method ke POST untuk menambah
            document.getElementById('modalForm').classList.add('show');
        });

        async function editRole(id) {
            try {
                const response = await fetch(`${API_URL}?id=${id}`);
                const result = await response.json();
                if (result.success) {
                    const data = result.data;
                    document.getElementById('modalTitle').textContent = 'Edit Role';
                    document.getElementById('idrole').value = data.idrole;
                    document.getElementById('formMethod').value = 'PUT'; // Set method ke PUT untuk mengedit
                    document.getElementById('nama_role').value = data.nama_role;
                    document.getElementById('modalForm').classList.add('show');
                } else {
                    alert(result.message);
                }
            } catch (error) {
                alert('Error memuat data untuk edit: ' + error.message);
            }
        }

        async function deleteRole(id, nama) {
            if (!confirm(`Yakin ingin menghapus role "${nama}"? Penghapusan akan gagal jika role ini masih digunakan oleh user.`)) return;

            const formData = new FormData();
            formData.append('_method', 'DELETE');
            formData.append('idrole', id);

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                alert(result.message);
                if (result.success) loadRoles();
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        document.getElementById('formRole').addEventListener('submit', async (e) => {
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
                    loadRoles();
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        function closeModal() {
            document.getElementById('modalForm').classList.remove('show');
        }

        document.getElementById('btnRefresh').addEventListener('click', loadRoles);

        window.onclick = function(event) {
            if (event.target === document.getElementById('modalForm')) {
                closeModal();
            }
        }
    </script>
    <?php include 'footer.php'; ?>
</body>

</html>