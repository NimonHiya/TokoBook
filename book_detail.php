<?php
session_start();
require_once __DIR__ . '/db.php';

// --- THEME & LOGIN CHECK ---
$is_logged_in = isset($_SESSION['user']);
// Logika Dark Mode Hybrid: prioritaskan preferensi user login, fallback ke cookie
$is_dark_mode = $is_logged_in ? ($_SESSION['user']['theme_mode'] ?? 0) == 1 : ($_COOKIE['theme'] ?? 'light') === 'dark';
$theme = $is_dark_mode ? 'dark' : 'light';
$nav_class = $is_dark_mode ? 'navbar-dark bg-dark' : 'navbar-dark bg-primary'; 
$card_color = $is_dark_mode ? '#1e1e1e' : '#ffffff';

// --- DATA FETCH ---
$id = isset($_GET['id'])? intval($_GET['id']):0;
$stmt = $pdo->prepare('SELECT * FROM books WHERE id=:id');
$stmt->execute([':id'=>$id]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book){
    // Pesan jika buku tidak ditemukan
    die('Book not found');
}

$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;
$is_in_wishlist = false;

// --- CHECK WISHLIST STATUS ---
if ($user_id) {
    $wish_stmt = $pdo->prepare('SELECT 1 FROM wishlist WHERE user_id = ? AND book_id = ?');
    $wish_stmt->execute([$user_id, $book['id']]);
    $is_in_wishlist = $wish_stmt->fetchColumn();
}

// --- FETCH REVIEWS AND AVERAGE RATING ---
$reviews_stmt = $pdo->prepare('
    SELECT r.rating, r.review_text, r.created_at, u.username 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.book_id = :bid 
    ORDER BY r.created_at DESC
');
$reviews_stmt->execute([':bid' => $book['id']]);
$reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_reviews = count($reviews);
$average_rating = 0;

if ($total_reviews > 0) {
    $sum_ratings = array_sum(array_column($reviews, 'rating'));
    $average_rating = round($sum_ratings / $total_reviews, 1);
}

// Helper function to display stars
function display_stars($rating) {
    $stars = '';
    // Memastikan rating dibulatkan ke bawah untuk tampilan bintang penuh
    $full_stars = floor($rating);
    
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full_stars) {
            $stars .= '★'; // Bintang terisi
        } else {
            $stars .= '☆'; // Bintang kosong
        }
    }
    return $stars;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/logo.jpg" type="image/jpeg">
    <title>TokoBook - <?php echo htmlspecialchars($book['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* CSS Konsisten Site-Wide */
        body { transition: background-color 0.3s, color 0.3s; }
        body.dark-mode { 
            background-color: #121212; 
            color: #f5f5f5; /* Teks default Body */
        }
        .navbar-brand { font-weight: bold; }
        .book-detail { line-height: 1.6; }
        .book-card { transition: transform 0.2s; background-color: <?= $card_color; ?>; border-color: <?= $is_dark_mode ? '#333' : 'rgba(0,0,0,.125)'; ?>; }
        .book-card:hover { transform: scale(1.01); }
        
        /* Dark Mode General Text & Content */
        body.dark-mode .card-body,
        body.dark-mode .card-text strong,
        body.dark-mode .form-label,
        body.dark-mode .book-detail,
        body.dark-mode h2,
        body.dark-mode h4,
        body.dark-mode h5 {
            color: #f5f5f5 !important; /* Memastikan teks di dalam card jadi putih */
        }
        body.dark-mode .text-secondary {
            color: #bbb !important; /* Teks sekunder untuk dark mode */
        }
        body.dark-mode .border,
        body.dark-mode .border-top {
            border-color: #444 !important; /* Garis pembatas deskripsi */
        }
        
        /* Dark Mode Inputs/Alerts */
        body.dark-mode .form-control { background-color: #2b2b2b; color: #f1f1f1; border-color: #444; }
        body.dark-mode .form-control:focus { background-color: #222; border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); }
        body.dark-mode .alert-warning { background-color: #2a2a2a; color: #ffc107; border-color: #ffc107; }
        body.dark-mode .alert-info { background-color: #1f1f1f; color: #ccc; border-color: #333; }
        .toggle-btn { border: none; background-color: transparent; color: inherit; font-weight: bold; cursor: pointer; padding: .5rem 1rem; text-decoration: none; }
        
        /* Wishlist specific */
        .btn-wishlist { margin-left: 10px; }
        
        /* Star Rating Specific */
        .rating-stars-display { letter-spacing: 2px; }
        .review-item { border-left: 3px solid #0d6efd; padding-left: 15px !important; }
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
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                        <li class="nav-item"><a class="nav-link" href="wishlist.php">Wishlist</a></li>
                        <?php if ($_SESSION['user']['role']==='admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="admin/dashboard.php">Admin</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['user']['username']); ?>)</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main class="container my-5">
    <div class="row">
        <div class="col-md-8 col-lg-6 mx-auto">
            <div class="book-card card shadow-lg">
                <?php 
                    $imagePath = !empty($book['image']) && file_exists(__DIR__ . '/assets/images/' . $book['image']) 
                        ? 'assets/images/' . htmlspecialchars($book['image']) 
                        : 'https://via.placeholder.com/400x600?text=No+Cover';
                ?>
                <img src="<?= $imagePath; ?>" class="img-fluid mb-3 rounded-top" alt="<?php echo htmlspecialchars($book['title']); ?>">
                
                <div class="card-body">
                    <h2 class="card-title mb-4 text-primary"><?php echo htmlspecialchars($book['title']); ?></h2>
                    
                    <?php if ($total_reviews > 0): ?>
                        <div class="d-flex align-items-center mb-3">
                            <h4 class="me-2 mb-0 text-warning fw-bold"><?= $average_rating; ?></h4>
                            <span class="text-warning rating-stars-display me-3 h5 mb-0">
                                <?= display_stars($average_rating); ?>
                            </span>
                            <small class="text-muted">(<?= $total_reviews; ?> Ulasan)</small>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Belum ada ulasan.</p>
                    <?php endif; ?>
                    
                    <p class="card-text"><strong>Penulis:</strong> <?php echo htmlspecialchars($book['author']); ?></p>
                    <p class="card-text"><strong>Harga:</strong> <span class="text-success fw-bold">Rp <?php echo number_format($book['price'], 2, ',', '.'); ?></span></p>
                    
                    <?php if (!empty($book['category_id'])): 
                        $cat = $pdo->prepare('SELECT name FROM categories WHERE id=:id'); 
                        $cat->execute([':id'=>$book['category_id']]); 
                        $catname = $cat->fetchColumn(); 
                    ?>
                        <p class="card-text"><strong>Kategori:</strong> <span class="badge bg-info text-dark"><?php echo htmlspecialchars($catname); ?></span></p>
                    <?php endif; ?>
                    
                    <p class="card-text"><strong>Stok:</strong> 
                        <span class="fw-bold text-<?= (isset($book['stock']) && intval($book['stock']) > 0) ? 'success' : 'danger'; ?>">
                            <?php echo isset($book['stock'])?intval($book['stock']):0; ?>
                        </span>
                    </p>
                    
                    <h4 class="mt-4 mb-2 text-secondary">Deskripsi</h4>
                    <div class="book-detail card-text border p-3 rounded"><?php echo nl2br(htmlspecialchars($book['description'])); ?></div>
                    
                    <?php if ($is_logged_in): ?>
                        <?php $available = isset($book['stock'])?intval($book['stock']):0; ?>
                        <div class="d-flex align-items-center mt-4">
                            <?php if ($available <= 0): ?>
                                <div class="alert alert-warning mb-0 me-3">Stok Habis</div>
                            <?php else: ?>
                                <form method="post" action="cart.php" class="d-flex align-items-center me-3">
                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                    <label for="qty" class="form-label me-2 mb-0">Qty:</label>
                                    <input type="number" class="form-control me-3" id="qty" name="qty" value="1" min="1" max="<?php echo $available; ?>" style="width: 80px;">
                                    <button type="submit" class="btn btn-primary">Beli</button>
                                </form>
                            <?php endif; ?>
                            
                            <a href="wishlist_handler.php?book_id=<?= $book['id']; ?>&action=<?= $is_in_wishlist ? 'remove' : 'add'; ?>" 
                               class="btn btn-outline-danger btn-wishlist <?= $is_in_wishlist ? 'active' : ''; ?>"
                               title="<?= $is_in_wishlist ? 'Hapus dari Wishlist' : 'Tambah ke Wishlist'; ?>">
                                
                                <?= $is_in_wishlist ? '❤️ Di Wishlist' : '🤍 Tambah ke Wishlist'; ?>
                            </a>
                        </div>
                        
                        <?php if (!empty($_SESSION['flash'])): ?>
                            <div class="alert alert-success mt-3"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
                        <?php endif; ?>

                    <?php else: ?>
                        <p class="mt-4">
                            <a href="login.php" class="btn btn-primary">Login untuk Beli</a>
                            <a href="login.php" class="btn btn-outline-danger btn-wishlist" title="Login untuk Wishlist">
                                🤍 Wishlist
                            </a>
                        </p>
                    <?php endif; ?>
                    
                    <div class="mt-5 border-top pt-4">
                        <h4 class="mb-3 text-primary">Daftar Ulasan (<?= $total_reviews; ?>)</h4>
                        
                        <?php if ($total_reviews > 0): ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="border p-3 mb-3 rounded review-item book-detail">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="fw-bold me-2"><?= htmlspecialchars($review['username']); ?></span>
                                            <span class="text-warning fw-bold me-3">
                                                <?= display_stars($review['rating']); ?>
                                            </span>
                                        </div>
                                        <small class="text-muted"><?= date('d M Y', strtotime($review['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info mt-3">
                                Belum ada ulasan untuk buku ini.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="index.php" class="btn btn-outline-secondary">Kembali ke Beranda</a>
                    </div>
                </div>
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
</body>
</html>