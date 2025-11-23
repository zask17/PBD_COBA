<?php

$host = 'localhost';
$dbname = 'uts_pbd';
$user = 'root';
$password = '';

try {
    // Membuat objek koneksi PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    
    // Set mode error PDO ke exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // echo "Koneksi database berhasil!"; // Hapus ini setelah pengujian
} catch (PDOException $e) {
    // Tangani error koneksi
    die("Koneksi database gagal: " . $e->getMessage());
}
?>