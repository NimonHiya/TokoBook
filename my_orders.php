<?php
session_start(); 
require_once __DIR__.'/db.php';
require_once __DIR__.'/csrf.php'; 

// --- Global Feature Toggles ---
// **********************************************************
const FEATURE_THEME_TOGGLE = false;    // Menyembunyikan tombol ☀️/🌙
const FEATURE_RATING_REVIEW = false;   // Menyembunyikan tombol "Beri Ulasan"
const FEATURE_ORDER_TRACKING = false;  // Menyembunyikan tampilan resi/catatan pengiriman
// **********************************************************


// --- Login Check ---
if (!isset($_SESSION['user'])) { 
    header('Location: login.php'); 
    exit; 
}
$user_id = $_SESSION['user']['id'];

// --- Dark mode detection & Toggle Logic (KONDISIONAL) ---
$current_db_mode = $_SESSION['user']['theme_mode'] ?? 0;

if (FEATURE_THEME_TOGGLE && isset($_GET['toggle_theme']) && $_GET['toggle_theme'] === '1') {
    $new_db_mode = ($current_db_mode == 0) ? 1 : 0; 

    if (isset($pdo)) {
        $update_stmt = $pdo->prepare("UPDATE users SET theme_mode = ? WHERE id = ?");
        $update_stmt->execute([$new_db_mode, $user_id]);
        $_SESSION['user']['theme_mode'] = $new_db_mode;
    }
    
    // Redirect tanpa query string
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Re-check theme after potential toggle
$is_dark_mode = ($_SESSION['user']['theme_mode'] ?? 0) == 1;
$theme = $is_dark_mode ? 'dark' : 'light';
$nav_class = $is_dark_mode ? 'navbar-dark bg-dark' : 'navbar-dark bg-primary';


// --- Fetch Orders (MODIFIED: INCLUDE shipping_note) ---
$orders = $pdo->prepare('SELECT o.*, o.shipping_note, b.title, b.id as book_id FROM orders o JOIN books b ON o.book_id=b.id WHERE o.user_id=:uid ORDER BY o.ordered_at DESC');
$orders->execute([':uid'=>$user_id]);
$rows = $orders->fetchAll(PDO::FETCH_ASSOC);

// Ambil pesan flash dari sesi
$flash_message = $_SESSION['flash'] ?? null;
if ($flash_message) {
    // Ambil tipe alert, default ke 'info'
    $alert_type = $_SESSION['alert_type'] ?? 'info';
    unset($_SESSION['flash']);
    unset($_SESSION['alert_type']);
}

// LOGIKA BANTUAN UNTUK REVIEW (Untuk menentukan apakah tombol ulasan muncul)
// Dibuat kondisional, tetapi fungsi harus tetap didefinisikan jika fitur dihidupkan
if (FEATURE_RATING_REVIEW) {
    function check_review_status($pdo, $order_id, $book_id, $user_id) {
        $review_exists_stmt = $pdo->prepare('SELECT 1 FROM reviews WHERE order_id = :oid AND book_id = :bid AND user_id = :uid');
        $review_exists_stmt->execute([':oid' => $order_id, ':bid' => $book_id, ':uid' => $user_id]);
        return $review_exists_stmt->fetch(PDO::FETCH_COLUMN);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/logo.jpg" type="image/jpeg">
    <title>TokoBook - Pesanan Saya</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* CSS Konsisten dari file lain */
        body { transition: background-color 0.3s, color 0.3s; }
        .navbar-brand { font-weight: bold; }
        .card { border: none; border-radius: 10px; transition: all 0.3s ease; }
        .toggle-btn {
            border: none;
            background-color: transparent;
            color: inherit;
            font-weight: bold;
            cursor: pointer;
            padding: .5rem 1rem;
            text-decoration: none;
        }

        /* DARK MODE - KONSISTEN */
        body.dark-mode { background-color: #121212; color: #f5f5f5; }
        
        /* Navbar & Footer Konsisten */
        .navbar-dark.bg-dark,
        body.dark-mode footer.bg-dark { 
             background-color: #1f1f1f !important; 
        }

        /* Card Konsisten */
        body.dark-mode .card { 
            background-color: #1e1e1e !important; 
            color: #f5f5f5; 
            border: 1px solid #333; 
        }
        
        /* Tooltip Dark Mode */
        .tooltip-inner {
            background-color: #1f1f1f;
            color: #f5f5f5;
            border: 1px solid #444;
        }
        .tooltip.bs-tooltip-auto[data-popper-placement^=top] .tooltip-arrow::before {
            border-top-color: #1f1f1f;
        }
        .tooltip.bs-tooltip-auto[data-popper-placement^=bottom] .tooltip-arrow::before {
            border-bottom-color: #1f1f1f;
        }
        
        /* === UI KHUSUS ORDER CARD === */
        .order-card {
            margin-bottom: 25px;
            padding: 20px;
            border-left: 5px solid var(--bs-primary); /* Garis biru penanda */
        }
        .order-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        body.dark-mode .order-header {
            border-bottom: 1px solid #444;
        }
        .order-item-detail {
            border-left: 3px solid var(--bs-light);
            padding-left: 10px;
        }
        body.dark-mode .order-item-detail {
            border-left: 3px solid #333;
        }
        .shipping-note-box {
            background-color: var(--bs-light);
            padding: 10px;
            border-radius: 5px;
        }
        body.dark-mode .shipping-note-box {
            background-color: #2a2a2a;
        }

        /* === PENTING: FORCE TEXT COLOR TO WHITE IN DARK MODE === */
        body.dark-mode .order-card h5,
        body.dark-mode .order-card strong,
        body.dark-mode .order-card .text-muted,
        body.dark-mode .order-card .small {
             color: #f5f5f5 !important; /* Memaksa semua teks di dalam kartu menjadi putih/terang */
        }
        body.dark-mode .text-success {
             color: #198754 !important; /* Mempertahankan warna Success */
        }
        
    </style>
</head>
<body class="<?= $theme === 'dark' ? 'dark-mode' : '' ?>">
<header>
    <nav class="navbar navbar-expand-lg <?= $nav_class ?>">
        <div class="container">
            <a class="navbar-brand" href="index.php">TokoBook</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="my_orders.php">Pesanan Saya</a></li>
                    <?php if (isset($_SESSION['user']) && $_SESSION['user']['role']==='admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="admin/dashboard.php">Admin</a></li>
                    <?php endif; ?>
                    
                    <?php if (FEATURE_THEME_TOGGLE): ?>
                    <li class="nav-item">
                        <a href="?toggle_theme=1" class="nav-link toggle-btn" title="Toggle Dark/Light Mode">
                            <?= $is_dark_mode ? '☀️' : '🌙' ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['user']['username']); ?>)</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main class="container my-5">
    <div class="row">
        <div class="col-md-10 mx-auto">
            <h2 class="mb-4 text-primary">Daftar Pesanan Saya 📦</h2>
            
            <?php 
            // MENAMPILKAN PESAN FLASH DARI SESI 
            if ($flash_message): 
                $alert_class = $alert_type === 'success' ? 'alert-success' : ($alert_type === 'danger' ? 'alert-danger' : ($alert_type === 'warning' ? 'alert-warning' : 'alert-info'));
            ?>
                <div class="alert <?= $alert_class ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (empty($rows)): ?>
                <div class="alert alert-info text-center" role="alert">
                    Anda belum memiliki pesanan. <a href="index.php" class="alert-link">Mulai belanja sekarang!</a>
                </div>
            <?php else: ?>
                <div class="order-list">
                    <?php foreach($rows as $r): 
                        // Pengecekan Review hanya jika fitur aktif
                        $has_reviewed = FEATURE_RATING_REVIEW ? check_review_status($pdo, $r['id'], $r['book_id'], $user_id) : false;
                        
                        // Detail Pengiriman hanya jika fitur aktif
                        $shipping_details = FEATURE_ORDER_TRACKING && $r['shipping_note'] ? htmlspecialchars($r['shipping_note']) : 'Catatan pengiriman belum ada.';
                        
                        // Logika Badge
                        $badge_class = 'bg-secondary';
                        if ($r['status'] === 'pending') $badge_class = 'bg-warning text-dark';
                        elseif ($r['status'] === 'paid') $badge_class = 'bg-primary';
                        elseif ($r['status'] === 'shipped') $badge_class = 'bg-info text-dark';
                        elseif ($r['status'] === 'diterima') $badge_class = 'bg-success'; 
                        elseif ($r['status'] === 'selesai') $badge_class = 'bg-success'; 
                        elseif ($r['status'] === 'cancelled') $badge_class = 'bg-danger';
                    ?>
                        <div class="order-card card shadow-sm">
                            <div class="order-header d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small me-3">Order ID: <strong>#<?php echo $r['id']; ?></strong></span>
                                    <span class="text-muted small">
                                        Tanggal: <?php echo htmlspecialchars(date('d M Y H:i', strtotime($r['ordered_at']))); ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="badge <?= $badge_class ?>" 
                                          title="<?= (FEATURE_ORDER_TRACKING && $r['status'] === 'shipped' && $r['shipping_note']) ? $shipping_details : ''; ?>"
                                          data-bs-toggle="<?= (FEATURE_ORDER_TRACKING && $r['status'] === 'shipped' && $r['shipping_note']) ? 'tooltip' : ''; ?>"
                                          data-bs-placement="top">
                                        <?php echo htmlspecialchars(ucfirst($r['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="row align-items-center">
                                <div class="col-sm-6 order-item-detail">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($r['title']); ?></h5>
                                    <p class="mb-1 small">Kuantitas: **<?php echo $r['quantity']; ?>**</p>
                                    <p class="fw-bold text-success">Total: Rp <?php echo number_format($r['total_price'], 2, ',', '.'); ?></p>
                                </div>
                                
                                <div class="col-sm-6 text-end">
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <div class="d-flex gap-2 justify-content-end">
                                            <form method="post" action="order_pay.php" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $r['id']; ?>">
                                                <?php echo csrf_input_field(); ?>
                                                <button type="submit" class="btn btn-sm btn-success">Bayar</button>
                                            </form>
                                            <form method="post" action="order_cancel.php" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini?');" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $r['id']; ?>">
                                                <?php echo csrf_input_field(); ?>
                                                <button type="submit" class="btn btn-sm btn-danger">Batal</button>
                                            </form>
                                        </div>
                                    
                                    <?php elseif ($r['status'] === 'shipped'): ?>
                                        <form method="post" action="order_receive.php" onsubmit="return confirm('Konfirmasi bahwa barang sudah Anda terima?');" class="d-inline">
                                            <input type="hidden" name="order_id" value="<?php echo $r['id']; ?>">
                                            <?php echo csrf_input_field(); ?> 
                                            <button type="submit" class="btn btn-sm btn-primary">Barang Diterima</button>
                                        </form>

                                    <?php elseif ($r['status'] === 'diterima'): ?>
                                        <span class="text-success small">Menunggu Admin Selesaikan</span>

                                    <?php elseif ($r['status'] === 'selesai'): ?>
                                        <?php if (FEATURE_RATING_REVIEW): ?>
                                            <div class="d-flex gap-2 justify-content-end">
                                                <?php if ($has_reviewed): ?>
                                                    <span class="badge bg-secondary">Sudah Diulas</span>
                                                <?php else: ?>
                                                    <a href="review_form.php?order_id=<?php echo $r['id']; ?>&book_id=<?php echo $r['book_id']; ?>" 
                                                       class="btn btn-sm btn-warning text-dark">
                                                        Beri Ulasan
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (FEATURE_ORDER_TRACKING && $r['status'] === 'shipped' && !empty($r['shipping_note'])): ?>
                                <div class="shipping-note-box mt-3 small text-start">
                                    <strong class="text-primary">Catatan Pengiriman:</strong>
                                    <span class="text-muted"><?= $shipping_details; ?></span>
                                    <span class="d-block text-danger small">
                                        *Detail resi lengkap dapat dilihat dengan mengarahkan kursor ke Status di atas.
                                    </span>
                                </div>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-outline-secondary">Kembali ke Beranda</a>
            </div>
        </div>
    </div>
</main>

<footer class="py-3 mt-5 <?= $is_dark_mode ? 'bg-dark text-light' : 'bg-light text-dark' ?>">
    <div class="container text-center">
        <p>&copy; <?php echo date('Y'); ?> TokoBook</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Inisialisasi Tooltips (hanya jika fitur aktif)
        <?php if (FEATURE_ORDER_TRACKING): ?>
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl)
        })
        <?php endif; ?>
    });
</script>
</body>
</html>