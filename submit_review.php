<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user'])) {
    header('Location: my_orders.php');
    exit;
}

// 1. Validasi Keamanan & Input
if (empty($_POST['csrf_token']) || !csrf_validate($_POST['csrf_token'])) {
    $_SESSION['flash'] = 'Token keamanan tidak valid. Ulasan gagal dikirim.';
    $_SESSION['alert_type'] = 'danger';
    header('Location: my_orders.php');
    exit;
}

$user_id = $_SESSION['user']['id'];
$order_id = (int)($_POST['order_id'] ?? 0);
$book_id = (int)($_POST['book_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$review_text = trim($_POST['review_text'] ?? '');

// 2. Validasi Data
if ($order_id <= 0 || $book_id <= 0 || $rating < 1 || $rating > 5 || empty($review_text) || strlen($review_text) < 10) {
    $_SESSION['flash'] = 'Data ulasan tidak lengkap atau tidak valid.';
    $_SESSION['alert_type'] = 'danger';
    header('Location: review_form.php?order_id=' . $order_id . '&book_id=' . $book_id);
    exit;
}

// 3. Cek Status dan Cek Ulasan Ganda (Hanya 1x dan harus status Selesai)
try {
    $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? AND book_id = ? AND user_id = ?');
    $stmt->execute([$order_id, $book_id, $user_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    // Cek item dan status
    if (!$item || $item['status'] !== 'selesai') {
        $_SESSION['flash'] = 'Pesanan tidak memenuhi syarat untuk diulas (Status: ' . htmlspecialchars($item['status'] ?? 'N/A') . ').';
        $_SESSION['alert_type'] = 'danger';
        header('Location: my_orders.php');
        exit;
    }

    // Cek duplikasi (melindungi dari pengiriman ganda via POST, meskipun database sudah punya UNIQUE KEY)
    $check_review = $pdo->prepare('SELECT id FROM reviews WHERE order_id = ? AND book_id = ? AND user_id = ?');
    $check_review->execute([$order_id, $book_id, $user_id]);
    if ($check_review->fetch()) {
        $_SESSION['flash'] = 'Anda sudah mengulas item ini. Ulasan tidak dapat ditambahkan lagi.';
        $_SESSION['alert_type'] = 'warning';
        header('Location: my_orders.php');
        exit;
    }

    // 4. Masukkan Ulasan ke Database
    $ins_stmt = $pdo->prepare('INSERT INTO reviews (user_id, book_id, order_id, rating, review_text) VALUES (?, ?, ?, ?, ?)');
    $ins_stmt->execute([$user_id, $book_id, $order_id, $rating, $review_text]);

    $_SESSION['flash'] = 'Terima kasih! Ulasan Anda telah berhasil disimpan dan tidak dapat diubah.';
    $_SESSION['alert_type'] = 'success';

} catch (PDOException $e) {
    // Tangani error database (misal: jika UNIQUE KEY violation terjadi)
    if ($e->getCode() == '23000' || $e->getCode() == '23505') {
        $_SESSION['flash'] = 'Anda sudah mengulas item ini (Database Error: Duplicate Entry).';
        $_SESSION['alert_type'] = 'warning';
    } else {
        $_SESSION['flash'] = 'Terjadi kesalahan sistem saat menyimpan ulasan: ' . $e->getMessage();
        $_SESSION['alert_type'] = 'danger';
    }
} catch (Exception $e) {
    $_SESSION['flash'] = $e->getMessage();
    $_SESSION['alert_type'] = 'danger';
}

header('Location: my_orders.php');
exit;