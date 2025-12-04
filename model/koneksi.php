<?php

$host = 'localhost';
$dbname = 'pbd';
$user = 'root';
$password = '';

// $dbconn = null;

$dbconn = new mysqli($host, $user, $password, $dbname);

// Cek koneksi
if ($dbconn->connect_error) {
    die("Koneksi database gagal: " . $dbconn->connect_error);
}


$dbconn->set_charset("utf8");

// echo "Koneksi database berhasil!";
