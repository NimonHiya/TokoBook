<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';

// --- Validasi Keamanan dan POST ---

// 1. Cek Token CSRF
if (empty($_POST['csrf_token']) || !csrf_validate($_POST['csrf_token'])) {
    $_SESSION['flash'] = 'Token keamanan tidak valid. Coba lagi.';
    $_SESSION['alert_type'] = 'danger';
    header('Location: my_orders.php'); 
    exit;
}

// 2. Cek Login
if (!isset($_SESSION['user'])) {
    header('Location: login.php'); 
    exit;
}

$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$user_id = $_SESSION['user']['id'];

if (!$order_id) {
    $_SESSION['flash'] = 'Error: ID Pesanan tidak valid.';
    $_SESSION['alert_type'] = 'danger';
    header('Location: my_orders.php'); 
    exit;
}

// --- Proses Konfirmasi Penerimaan ---

$pdo->beginTransaction();
try {
    // 1. Verifikasi kepemilikan dan status order
    $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $order_id, ':uid' => $user_id]);
    $ord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ord) {
        throw new Exception('Pesanan tidak ditemukan atau Anda tidak memiliki izin.');
    }
    // Hanya bisa diterima jika statusnya 'shipped'
    if ($ord['status'] !== 'shipped') {
        throw new Exception('Pesanan belum dikirim, atau sudah berstatus ' . htmlspecialchars($ord['status']) . '.');
    }

    // 2. Tandai sebagai DITERIMA
    // Asumsi: Anda telah menambahkan kolom `received_at` di database Anda.
    $u = $pdo->prepare('UPDATE orders SET status = :s, received_at = NOW() WHERE id = :id'); 
    $u->execute([':s' => 'diterima', ':id' => $order_id]); 

    $pdo->commit();
    $_SESSION['flash'] = 'Pesanan ID **' . $order_id . '** berhasil Anda konfirmasi diterima. Terima kasih atas pembelian Anda!';
    $_SESSION['alert_type'] = 'success';
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = 'Gagal mengkonfirmasi penerimaan: ' . htmlspecialchars($e->getMessage());
    $_SESSION['alert_type'] = 'danger';
}

header('Location: my_orders.php'); 
exit;