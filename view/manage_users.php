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
                        <p>Manajemen User & Role</p>
                    </div>
                </div>
                <div class="header-actions" style="display: flex; gap: 1rem;">
                    <a href="datamaster.php" class="btn btn-secondary">
                        <span>⚙️</span> Data Master
                    </a>
                    <button id="btnTambah" class="btn btn-primary">
                        <span>+</span> Tambah User
                    </button>
                    <a href="../models/auth.php?action=logout" class="btn btn-danger">
                        <span>🚪</span> Keluar
                    </a>
                </div>
            </div>
        </header>

        <div class="container">
            <!-- User Table -->
            <div class="card">
                <div class="card-header">
                    <h2>Daftar Pengguna</h2>
                    <button id="btnRefresh" class="btn btn-secondary btn-sm">🔄 Refresh</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tableUsers">
                            <thead>
                                <tr>
                                    <th>ID User</th>
                                    <th>Username</th>
                                    <th>Role</th>
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
                <h3 id="modalTitle">Tambah User</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <form id="formUser">
                <input type="hidden" id="iduser" name="iduser">
                <input type="hidden" id="formMethod" name="_method">
                
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Isi untuk mengubah/menambah">
                    <small style="color: #8b92a7; font-size: 12px;">Kosongkan jika tidak ingin mengubah password saat edit.</small>
                </div>
                
                <div class="form-group">
                    <label for="idrole">Role *</label>
                    <select id="idrole" name="idrole" required>
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
        document.addEventListener('DOMContentLoaded', () => {
            loadUsers();
            loadRoles();
        });

        async function loadUsers() {
            try {
                const response = await fetch('../models/users.php');
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
                const response = await fetch('../models/users.php?action=get_roles');
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
            document.getElementById('formMethod').value = '';
            document.getElementById('password').required = true; // Password wajib saat tambah
            document.getElementById('modalForm').classList.add('show');
        });

        async function editUser(id) {
            try {
                const response = await fetch(`../models/users.php?id=${id}`);
                const result = await response.json();
                if (result.success) {
                    const data = result.data;
                    document.getElementById('modalTitle').textContent = 'Edit User';
                    document.getElementById('iduser').value = data.iduser;
                    document.getElementById('formMethod').value = 'PUT';
                    document.getElementById('username').value = data.username;
                    document.getElementById('idrole').value = data.idrole;
                    document.getElementById('password').value = ''; // Kosongkan password
                    document.getElementById('password').required = false; // Password tidak wajib saat edit
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
                const response = await fetch('../models/users.php', { method: 'POST', body: formData });
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

            // Validasi password saat tambah user
            if (!formData.get('iduser') && !formData.get('password')) {
                alert('Password wajib diisi untuk user baru.');
                return;
            }

            try {
                const response = await fetch('../models/users.php', { method: 'POST', body: formData });
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
</body>
</html>