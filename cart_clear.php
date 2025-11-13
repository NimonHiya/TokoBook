<?php
session_start();
require_once __DIR__ . '/db.php'; // Pastikan koneksi DB tersedia jika diperlukan, meskipun tidak digunakan di sini.

// Cek autentikasi: Pastikan hanya pengguna yang login yang bisa mengosongkan keranjangnya.
if (!isset($_SESSION['user'])) { 
    header('Location: login.php'); 
    exit; 
}

// --- Logika Pengosongan Keranjang ---

// 1. Hapus variabel sesi 'cart'
if (isset($_SESSION['cart'])) {
    unset($_SESSION['cart']);
    
    // 2. Set pesan flash untuk notifikasi di halaman cart.php
    $_SESSION['flash'] = 'Keranjang belanja Anda berhasil dikosongkan.';
    $_SESSION['alert_type'] = 'info';
} else {
    // Pesan jika keranjang sudah kosong
    $_SESSION['flash'] = 'Keranjang Anda memang sudah kosong.';
    $_SESSION['alert_type'] = 'warning';
}

// 3. Redirect kembali ke halaman keranjang
header('Location: cart.php');
exit;