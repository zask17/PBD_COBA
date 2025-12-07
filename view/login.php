<?php
// login.php (Halaman yang Menampilkan Form dan Memproses Login)

session_start();
// PATH: Naik satu folder (dari view/) ke model/koneksi.php
// Asumsi: file koneksi.php mendefinisikan variabel koneksi MySQLi $dbconn
require_once '../model/koneksi.php'; 

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

    // Pastikan $dbconn (MySQLi object) tersedia
    if (!isset($dbconn) || !$dbconn) {
        $error = "Kesalahan koneksi database. Variabel \$dbconn tidak ditemukan.";
    } elseif (empty($username) || empty($password)) {
        $error = "Username dan password wajib diisi.";
    } else {
        // Menggunakan try-catch untuk menangani kegagalan database/prepared statement
        try {
            // Query untuk mengambil data user dan rolenya
            // Menggunakan positional placeholder (?) untuk MySQLi
            $stmt = $dbconn->prepare("SELECT u.iduser, u.password, r.nama_role 
                                     FROM user u
                                     JOIN role r ON u.idrole = r.idrole
                                     WHERE u.username = ?"); 
            
            // Cek jika prepare gagal
            if (!$stmt) {
                 throw new Exception('Gagal mempersiapkan statement: ' . $dbconn->error);
            }

            // Binding parameter: 's' untuk string (username)
            $stmt->bind_param("s", $username);
            
            // Eksekusi statement
            $stmt->execute();
            
            // Ambil hasil
            $result = $stmt->get_result();
            $user = $result->fetch_assoc(); // Ambil baris sebagai array asosiatif
            
            // Tutup statement
            $stmt->close();

            if ($user) {
                // PENTING: Untuk project PBD awal, kita pakai perbandingan langsung.
                // DI LINGKUNGAN PRODUKSI HARUS MENGGUNAKAN password_verify($password, $user['password'])
                if ($password === $user['password']) {
                    
                    // Login Berhasil
                    $_SESSION['loggedin'] = true;
                    $_SESSION['iduser'] = $user['iduser'];
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $user['nama_role'];

                    // Pengalihan berdasarkan Role
                    if ($_SESSION['role'] === 'super administrator') {
                        header("Location: dashboard_super_admin.php");
                    } else { // 'administrator'
                        header("Location: dashboard_admin.php");
                    }
                    exit();

                } else {
                    $error = "Password salah.";
                }
            } else {
                $error = "Username tidak ditemukan.";
            }
        } catch (Exception $e) { // Menangkap Exception umum untuk error MySQLi
            $error = "Terjadi kesalahan database: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenStock - Login</title>
    <link rel="stylesheet" href="../css/style.css"> 
</head>
<body class="login-body">
    <div class="login-container">
        <h2>👋 Selamat Datang</h2>
        <p style="color: #555; margin-bottom: 20px;">Silakan masukkan data diri Anda.</p>
        
        <?php if (!empty($error)): ?>
            <p class="error-message"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        
        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Masuk ke Sistem</button>
        </form>
        <p style="margin-top: 20px; font-size: 14px;"><a href="../html/index.html" style="color: #555;">Kembali ke Halaman Awal</a></p>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>