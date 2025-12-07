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
    <title>Manajemen User & Role - Sistem Inventory PBD</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard_super_admin.css">
    <link rel="stylesheet" href="../css/user.css">
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
                        <p>Manajemen User & Role</p>
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
            <div class="card">
                <div class="card-header">
                    <h2>Daftar Pengguna</h2>
                    <button id="btnRefresh" class="btn btn-secondary btn-sm">🔄 Refresh</button>
                    <button id="btnTambah" class="btn btn-primary"><span>+</span> Tambah User</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tableUsers" class="data-table">
                            <thead>
                                <tr>
                                    <th>ID User</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th style="width: 150px;">Aksi</th>
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

    <div id="modalForm" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah User</h3>
                <button class="close-button" onclick="closeModal()">&times;</button>
            </div>
            <form id="formUser">
                <input type="hidden" id="iduser" name="iduser">
                <input type="hidden" id="formMethod" name="_method">
                
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Isi untuk mengubah/menambah" class="form-control">
                    <small style="color: #8b92a7; font-size: 12px;">Kosongkan jika tidak ingin mengubah password saat edit.</small>
                </div>
                
                <div class="form-group">
                    <label for="idrole">Role *</label>
                    <select id="idrole" name="idrole" required class="form-control">
                        <option value="">Pilih Role...</option>
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
        const API_URL = '../model/users.php'; 

        document.addEventListener('DOMContentLoaded', () => {
            loadUsers();
            loadRoles();
        });

        async function loadUsers() {
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
                            <td>${item.iduser}</td>
                            <td>${item.username}</td>
                            <td>${item.nama_role || 'N/A'}</td> 
                            <td class="action-buttons">
                                <button class="btn btn-primary btn-sm" onclick="editUser('${item.iduser}')">Edit</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteUser('${item.iduser}', '${item.username}')">Hapus</button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">Tidak ada data pengguna</td></tr>';
                }
            } catch (error) {
                console.error('Error loading users:', error);
                document.getElementById('tableBody').innerHTML = '<tr><td colspan="4" style="text-align: center;">Gagal memuat data</td></tr>';
            }
        }

        async function loadRoles() {
            try {
                const response = await fetch(`${API_URL}?action=get_roles`); 
                const result = await response.json();
                if (result.success) {
                    const select = document.getElementById('idrole');
                    select.innerHTML = '<option value="">Pilih Role...</option>' + 
                        result.data.map(role => `<option value="${role.idrole}">${role.nama_role}</option>`).join('');
                }
            } catch (error) {
                console.error('Error loading roles:', error);
            }
        }

        document.getElementById('btnTambah').addEventListener('click', () => {
            document.getElementById('modalTitle').textContent = 'Tambah User';
            document.getElementById('formUser').reset();
            document.getElementById('iduser').value = '';
            document.getElementById('formMethod').value = 'POST'; 
            document.getElementById('password').required = true; 
            document.getElementById('modalForm').classList.add('show');
        });

        async function editUser(id) {
            try {
                const response = await fetch(`${API_URL}?id=${id}`);
                const result = await response.json();
                if (result.success) {
                    const data = result.data;
                    document.getElementById('modalTitle').textContent = 'Edit User';
                    document.getElementById('iduser').value = data.iduser;
                    document.getElementById('formMethod').value = 'PUT'; 
                    document.getElementById('username').value = data.username;
                    document.getElementById('idrole').value = data.idrole;
                    document.getElementById('password').value = ''; 
                    document.getElementById('password').required = false; 
                    document.getElementById('modalForm').classList.add('show');
                }
            } catch (error) {
                alert('Error memuat data user: ' + error.message);
            }
        }

        async function deleteUser(id, nama) {
            if (!confirm(`Yakin ingin menghapus user "${nama}"? Aksi ini tidak dapat dibatalkan.`)) return;
            
            const formData = new FormData();
            formData.append('_method', 'DELETE'); 
            formData.append('iduser', id);
            
            try {
                const response = await fetch(API_URL, { method: 'POST', body: formData });
                const result = await response.json();
                alert(result.message);
                if (result.success) loadUsers();
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        document.getElementById('formUser').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const method = formData.get('_method');

            if (method === 'POST' && !formData.get('password')) {
                alert('Password wajib diisi untuk user baru.');
                document.getElementById('password').focus();
                return;
            }

            try {
                const response = await fetch(API_URL, { method: 'POST', body: formData });
                const result = await response.json();
                alert(result.message);
                if (result.success) {
                    closeModal();
                    loadUsers();
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        function closeModal() {
            document.getElementById('modalForm').classList.remove('show');
        }

        document.getElementById('btnRefresh').addEventListener('click', loadUsers);

        window.onclick = function(event) {
            if (event.target === document.getElementById('modalForm')) {
                closeModal();
            }
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>