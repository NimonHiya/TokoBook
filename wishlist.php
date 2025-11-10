<?php
session_start(); 
require_once __DIR__.'/db.php';

// --- Global Feature Toggles ---
// **********************************************************
const FEATURE_THEME_TOGGLE = false;    // Menyembunyikan tombol ☀️/🌙
const FEATURE_WISHLIST = false;        // Menyembunyikan link Wishlist (di Navbar)
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


// --- Wishlist Handler Logic (Jika ada aksi penghapusan langsung dari halaman ini) ---
if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['book_id'])) {
    $book_id_to_remove = (int)$_GET['book_id'];
    $stmt = $pdo->prepare('DELETE FROM wishlist WHERE user_id = ? AND book_id = ?');
    $stmt->execute([$user_id, $book_id_to_remove]);
    
    $_SESSION['flash'] = "Buku berhasil dihapus dari Wishlist.";
    
    // Redirect untuk menghilangkan parameter GET
    header("Location: wishlist.php");
    exit;
}

// --- Ambil data Wishlist ---
$wishlist_stmt = $pdo->prepare('
    SELECT b.*, w.id as wishlist_entry_id 
    FROM wishlist w
    JOIN books b ON w.book_id = b.id
    WHERE w.user_id = ?
    ORDER BY w.added_at DESC
');
$wishlist_stmt->execute([$user_id]);
$wishlist_items = $wishlist_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/logo.jpg" type="image/jpeg">
    <title>TokoBook - Wishlist</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* CSS Konsisten Site-Wide */
        body { transition: background-color 0.3s, color 0.3s; }
        body.dark-mode { background-color: #121212; color: #f5f5f5; }
        body.dark-mode .card, 
        body.dark-mode .list-group-item { background-color: #1e1e1e; color: #f5f5f5; border-color: #333; }
        body.dark-mode .list-group-item { border-left-color: #1e1e1e !important; }
        .navbar-brand { font-weight: bold; }
        .book-img { height: 150px; width: 100px; object-fit: cover; }
        .toggle-btn { border: none; background-color: transparent; color: inherit; font-weight: bold; cursor: pointer; padding: .5rem 1rem; text-decoration: none; }
        .list-group-item { border-left: 5px solid #0d6efd; }
        
        body.dark-mode .alert-warning { background-color: #2a2a2a; color: #ffc107; border-color: #ffc107; }
        body.dark-mode .alert-info { background-color: #1f1f1f; color: #ccc; border-color: #333; }

    </style>
</head>
<body class="<?= $theme === 'dark' ? 'dark-mode' : '' ?>">
<header>
    <nav class="navbar navbar-expand-lg <?= $nav_class ?>">
        <div class="container">
            <a class="navbar-brand" href="index.php">TokoBook</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                    
                    <?php if (FEATURE_WISHLIST): ?>
                    <li class="nav-item"><a class="nav-link active" href="wishlist.php">Wishlist</a></li>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="admin/dashboard.php">Admin</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout (<?= htmlspecialchars($_SESSION['user']['username']); ?>)</a></li>
                    
                    <?php if (FEATURE_THEME_TOGGLE): ?>
                    <li class="nav-item">
                        <a href="?toggle_theme=1" class="nav-link toggle-btn" title="Toggle Dark/Light Mode">
                            <?= $is_dark_mode ? '☀️' : '🌙' ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main class="container my-5">
    <div class="row">
        <div class="col-md-10 mx-auto">
            <h2 class="mb-4 text-primary">Wishlist Saya ❤️</h2>
            
            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="alert alert-info alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['flash']); 
                    unset($_SESSION['flash']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($wishlist_items)): ?>
                <div class="alert alert-warning text-center">
                    Wishlist Anda masih kosong. Ayo jelajahi buku dan simpan favorit Anda!
                </div>
            <?php else: ?>
                <div class="list-group shadow-lg">
                    <?php foreach ($wishlist_items as $item): ?>
                        <div class="list-group-item d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <?php 
                                    $imagePath = 'assets/images/' . htmlspecialchars($item['image']);
                                    if (!file_exists($imagePath)) {
                                        $imagePath = 'https://via.placeholder.com/100x150?text=Cover';
                                    }
                                ?>
                                <img src="<?= $imagePath; ?>" class="me-3 book-img rounded" alt="<?= htmlspecialchars($item['title']); ?>">
                                
                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($item['title']); ?></h5>
                                    <p class="mb-1 text-muted">Oleh: <?= htmlspecialchars($item['author']); ?></p>
                                    <p class="mb-0 fw-bold text-success">Rp <?= number_format($item['price'], 2, ',', '.'); ?></p>
                                    <?php if ($item['stock'] == 0): ?>
                                            <span class="badge bg-danger">Stok Habis</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-flex flex-column gap-2 text-end">
                                <a href="book_detail.php?id=<?= $item['id']; ?>" class="btn btn-sm btn-outline-primary">Lihat Detail</a>
                                
                                <?php if ($item['stock'] > 0): ?>
                                    <a href="cart_handler.php?book_id=<?= $item['id']; ?>&qty=1" class="btn btn-sm btn-success">
                                        <i class="fas fa-cart-plus"></i> Beli Sekarang
                                    </a>
                                <?php endif; ?>

                                <a href="wishlist.php?book_id=<?= $item['id']; ?>&action=remove" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus buku ini dari wishlist?')">Hapus</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<footer class="py-3 mt-5 <?= $is_dark_mode ? 'bg-dark text-light' : 'bg-light text-dark' ?>">
    <div class="container text-center">
        <p>&copy; <?= date('Y'); ?> TokoBook</p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>