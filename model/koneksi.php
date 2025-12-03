<?php

$host = 'localhost';
$dbname = 'pbd';
$user = 'root';
$password = '';

$dbconn = null;

$dbconn = new mysqli($host, $user, $password, $dbname);

// Cek koneksi
if ($dbconn->connect_error) {
    die("Koneksi database gagal: " . $dbconn->connect_error);
}


$dbconn->set_charset("utf8");

// echo "Koneksi database berhasil!";


// PAKAI PDO 
// $host = 'localhost';
// $dbname = 'uts_pbd';
// $user = 'root';
// $password = '';

// try {
//     // Membuat objek koneksi PDO
//     $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    
//     // Set mode error PDO ke exception
//     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
//     // echo "Koneksi database berhasil!"; // Hapus ini setelah pengujian
// } catch (PDOException $e) {
//     // Tangani error koneksi
//     die("Koneksi database gagal: " . $e->getMessage());
// }
?>