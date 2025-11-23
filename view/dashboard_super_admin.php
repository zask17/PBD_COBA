<?php
// dashboard_super_admin.php

session_start();

// Periksa apakah pengguna sudah login dan role-nya benar
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'super administrator') {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Super Administrator</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .dashboard-content {
            padding: 40px;
            text-align: center;
        }

        .welcome-box {
            background-color: #FFFDE7;
            padding: 30px;
            border-radius: 10px;
            border: 1px solid #FFC107;
        }
    </style>
</head>

<body>
    <div class="dashboard-content">
        <div class="welcome-box">
            <h1 style="color: #FF9800;">
                Selamat Datang, <?= ucwords(strtolower(htmlspecialchars($_SESSION['username']))); ?>!
            </h1>

            <p style="font-size: 1.2em; margin-top: 15px;">
                Anda berhasil login sebagai <?= ucwords(strtolower(htmlspecialchars($_SESSION['role']))); ?>.
            </p>

            <p style="margin-top: 25px;">Ini adalah dashboard kontrol penuh.</p>
            <a href="logout.php" class="btn btn-primary" style="margin-top: 30px;">Logout</a>
        </div>

        <!-- Di sini akan diletakkan menu navigasi dan konten utama dashboard -->
    </div>
</body>

</html>