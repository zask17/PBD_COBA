<?php
session_start();

require_once 'koneksi.php'; 

$error = '';

// 1. Pengecekan sesi: Jika user sudah login, arahkan langsung ke dashboard
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if ($_SESSION['role'] === 'super administrator') {
        header("Location: dashboard_super_admin.php");
    } else if ($_SESSION['role'] === 'administrator') {
        header("Location: dashboard_admin.php");
    }
    exit;
}

// 2. Logika Pemrosesan Form POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Username dan password wajib diisi.";
    } else {
        try {
            // Query untuk mengambil data user dan rolenya
            $stmt = $pdo->prepare("SELECT u.iduser, u.password, r.nama_role 
                                     FROM user u
                                     JOIN role r ON u.idrole = r.idrole
                                     WHERE u.username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // --- Verifikasi Password ---
                // PENTING: Ganti dengan password_verify() jika Anda menggunakan hashing.
                if ($password === $user['password']) {
                // ---------------------------
                    
                    // Login Berhasil
                    $_SESSION['loggedin'] = true;
                    $_SESSION['iduser'] = $user['iduser'];
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $user['nama_role'];

                    // Pengalihan berdasarkan Role
                    if ($_SESSION['role'] === 'super administrator') {
                        header("Location: dashboard_super_admin.php");
                    } else { // Asumsikan role lain adalah 'administrator'
                        header("Location: dashboard_admin.php");
                    }
                    exit();

                } else {
                    $error = "Password salah.";
                }
            } else {
                $error = "Username tidak ditemukan.";
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan database: " . $e->getMessage();
        }
    }
}
?>
