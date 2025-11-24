<?php
// model/auth.php

// Memulai sesi jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Memastikan pengguna sudah login. Jika tidak, redirect ke halaman login.
 * @param string $required_role Nama role yang diperlukan ('administrator' atau 'super administrator').
 */
function checkAuth($required_role = null) {
    // Cek apakah user sudah login
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        // Redirect ke login.php
        header("Location: login.php");
        exit;
    }

    // Cek role jika ada batasan
    if ($required_role && $_SESSION['role'] !== $required_role && $_SESSION['role'] !== 'super administrator') {
        header("Location: dashboard_admin.php");
        exit;
    }
}

// Logika Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Hapus semua variabel sesi
    $_SESSION = array();

    // Hapus sesi cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Hancurkan sesi
    session_destroy();

    // Redirect ke halaman awal
    header("Location: ../html/index.html");
    exit;
}
?>