<?php
session_start();
require_once __DIR__ . '/db.php';

// Pastikan user sudah login
if (!isset($_SESSION['user'])) {
    // Redirect ke login jika mencoba menambahkan tanpa login
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user']['id'];
$book_id = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;
$action = $_GET['action'] ?? ''; 

if ($book_id <= 0) {
    // Jika ID buku tidak valid
    header('Location: index.php');
    exit;
}

if ($action === 'add') {
    try {
        // Coba tambahkan. IGNORE memastikan tidak terjadi error jika item sudah ada
        $stmt = $pdo->prepare('INSERT IGNORE INTO wishlist (user_id, book_id) VALUES (?, ?)');
        $stmt->execute([$user_id, $book_id]);
        $_SESSION['flash'] = "Buku berhasil ditambahkan ke Wishlist!";
    } catch (PDOException $e) {
        $_SESSION['flash'] = "Gagal menambahkan ke Wishlist.";
    }
} elseif ($action === 'remove') {
    try {
        // Hapus dari Wishlist
        $stmt = $pdo->prepare('DELETE FROM wishlist WHERE user_id = ? AND book_id = ?');
        $stmt->execute([$user_id, $book_id]);
        $_SESSION['flash'] = "Buku dihapus dari Wishlist.";
    } catch (PDOException $e) {
        $_SESSION['flash'] = "Gagal menghapus dari Wishlist.";
    }
}

// Redirect kembali ke halaman sebelumnya (book_detail)
header('Location: book_detail.php?id=' . $book_id);
exit;
?>