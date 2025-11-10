<?php
session_start();
require_once __DIR__ . '/db.php';

// --- Global Feature Toggles ---
// **********************************************************
const FEATURE_THEME_TOGGLE = false;    // Menyembunyikan tombol ☀️/🌙
const FEATURE_ABOUT_CONTENT = false;   // Menyembunyikan fitur Read More/Less (konten panjang)
const FEATURE_PRODUCT_LISTING = false; // Menyembunyikan daftar buku Rekomendasi/Pagination
// **********************************************************


// -------------------------
// CEK LOGIN DAN STATUS THEME
// -------------------------
$is_logged_in = isset($_SESSION['user']);
if ($is_logged_in) {
    $is_dark_mode = ($_SESSION['user']['theme_mode'] ?? 0) == 1;
} else {
    $is_dark_mode = ($_COOKIE['theme'] ?? 'light') === 'dark';
}

// -------------------------
// TOGGLE THEME MODE (Hanya jika FEATURE_THEME_TOGGLE = true)
// -------------------------
if (FEATURE_THEME_TOGGLE && isset($_GET['toggle_theme']) && $_GET['toggle_theme'] === '1') {
    if ($is_logged_in) {
        $user_id = $_SESSION['user']['id'];
        $current_db_mode = $_SESSION['user']['theme_mode'] ?? 0;
        $new_db_mode = $current_db_mode == 0 ? 1 : 0;

        // Update DB
        $update_stmt = $pdo->prepare("UPDATE users SET theme_mode = ? WHERE id = ?");
        $update_stmt->execute([$new_db_mode, $user_id]);

        // Update sesi agar langsung berubah
        $_SESSION['user']['theme_mode'] = $new_db_mode;

    } else {
        // Toggle cookie
        if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
            setcookie('theme', 'light', time() + (86400 * 30), '/');
        } else {
            setcookie('theme', 'dark', time() + (86400 * 30), '/');
        }
    }

    // Redirect agar tema langsung diterapkan
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// -------------------------
// BACA FILE ABOUT
// -------------------------
$about_file = __DIR__ . '/data/about.txt';
$about_title_file = __DIR__ . '/data/about_title.txt';
$about_image_file = __DIR__ . '/data/about_image.txt';

$content = file_exists($about_file) ? file_get_contents($about_file) : 'About content not found.';
$about_title = file_exists($about_title_file) ? file_get_contents($about_title_file) : 'Tentang TokoBook 📖';
$about_image = file_exists($about_image_file) ? trim(file_get_contents($about_image_file)) : 'toko.jpg';

// -------------------------
// POTONG KONTEN (Hanya dijalankan jika fitur About aktif)
// -------------------------
if (FEATURE_ABOUT_CONTENT) {
    $cut_length = 300;
    $long_narration_available = strlen($content) > $cut_length;
    $short_content = substr($content, 0, $cut_length);
    $remaining_content = $long_narration_available ? substr($content, $cut_length) : '';
} else {
    // Jika fitur dinonaktifkan, gunakan konten penuh/standar
    $short_content = $content;
    $long_narration_available = false;
    $remaining_content = '';
}


// -------------------------
// PAGINATION & FETCH BOOKS (Hanya jika fitur aktif)
// -------------------------
if (FEATURE_PRODUCT_LISTING) {
    $items_per_page = 4;
    $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

    $sql_count = 'SELECT COUNT(id) FROM books';
    $total_items = $pdo->query($sql_count)->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = max(1, min($current_page, $total_pages > 0 ? $total_pages : 1));
    $offset = ($current_page - 1) * $items_per_page;

    $sql_books = 'SELECT b.*, c.name as category_name 
                FROM books b 
                LEFT JOIN categories c ON b.category_id = c.id
                ORDER BY b.id DESC 
                LIMIT :limit OFFSET :offset';
    $stmt_books = $pdo->prepare($sql_books);
    $stmt_books->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt_books->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt_books->execute();
    $books_on_page = $stmt_books->fetchAll(PDO::FETCH_ASSOC);
} else {
    $books_on_page = [];
    $total_pages = 0;
    $current_page = 1;
    $total_items = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/logo.jpg" type="image/jpeg">
    <title>TokoBook - About Us</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            transition: background-color 0.3s, color 0.3s;
        }
        body.light-mode {
            background-color: white;
            color: black;
        }
        body.dark-mode {
            background-color: #121212;
            color: white;
        }
        .navbar-brand { font-weight: bold; }
        .about-content { line-height: 1.6; }
        .book-card { height: 100%; 
            /* Tambahkan styling card untuk dark mode */
            <?php if ($is_dark_mode): ?>
            background-color: #1e1e1e;
            color: white;
            border-color: #333;
            <?php endif; ?>
        }
        .book-img { height: 250px; width: 100%; object-fit: cover; }
        .about-img {
            width: 100%;
            height: 350px;
            object-fit: cover;
            object-position: center;
        }
        .toggle-btn {
            border: none;
            background-color: transparent;
            color: inherit;
            font-weight: bold;
            cursor: pointer;
            padding: .5rem 1rem; /* Sesuaikan padding agar terlihat seperti nav-link */
            text-decoration: none;
        }
        .card.dark-mode-card {
            background-color: #1e1e1e;
            color: white;
            border-color: #333;
        }
        .card.dark-mode-card .card-text.text-muted {
            color: #ccc !important; /* Memastikan teks muted terlihat di dark mode */
        }
    </style>
</head>
<body class="<?= $is_dark_mode ? 'dark-mode' : 'light-mode' ?>">
<header>
    <nav class="navbar navbar-expand-lg <?= $is_dark_mode ? 'navbar-dark bg-dark' : 'navbar-dark bg-primary' ?>">
        <div class="container">
            <a class="navbar-brand" href="/TokoBook/index.php">TokoBook</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="admin/dashboard.php">Admin</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout (<?= htmlspecialchars($_SESSION['user']['username']); ?>)</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <?php endif; ?>
                    
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

            <h1 class="text-center mb-5 text-primary"><?php echo htmlspecialchars($about_title); ?></h1>

            <div class="text-center mb-5">
                <img src="assets/<?php echo htmlspecialchars($about_image); ?>" 
                     class="img-fluid rounded shadow-lg about-img" 
                     alt="Gedung TokoBook">
            </div>

            <h2 class="mb-3 text-secondary">Kisah Kami</h2>
            <div class="about-content card p-4 shadow-sm mb-5 <?= $is_dark_mode ? 'dark-mode-card' : '' ?>">
                <p class="lead">
                    <?php 
                    // FITUR DISEMBUYIKAN: KONTEN DINAMIS (READ MORE/LESS)
                    if (FEATURE_ABOUT_CONTENT):
                        echo nl2br(htmlspecialchars($short_content)); 
                        if ($long_narration_available): ?>
                            <span id="dots">...</span>
                            <span id="more-text" style="display: none;"><?php echo nl2br(htmlspecialchars($remaining_content)); ?></span>
                        <?php endif;
                    else: 
                        // Konten Penuh/Standar jika fitur Read More dimatikan
                        echo nl2br(htmlspecialchars($content));
                    endif;
                    ?>
                </p>
                
                <?php if (FEATURE_ABOUT_CONTENT && $long_narration_available): ?>
                    <button onclick="readMoreLess()" id="read-more-btn" class="btn btn-link p-0 text-start text-primary fw-bold">
                        Baca Selengkapnya
                    </button>
                <?php endif; ?>
            </div>

            <!-- <h2 class="mb-4 text-success">Rekomendasi Buku</h2> -->
            
            <?php if (FEATURE_PRODUCT_LISTING): ?>
                <?php if ($total_items > 0): ?>
                    <div class="row row-cols-1 row-cols-md-4 g-4 mb-4">
                        <?php foreach ($books_on_page as $book): ?>
                            <div class="col">
                                <div class="card book-card shadow-sm <?= $is_dark_mode ? 'dark-mode-card' : '' ?>">
                                    <?php 
                                        $imagePath = !empty($book['image']) && file_exists(__DIR__ . '/assets/images/' . $book['image']) 
                                            ? 'assets/images/' . htmlspecialchars($book['image']) 
                                            : 'https://via.placeholder.com/300x400?text=' . urlencode(htmlspecialchars($book['title']));
                                    ?>
                                    <img src="<?php echo $imagePath; ?>" class="card-img-top book-img" alt="<?php echo htmlspecialchars($book['title']); ?>">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h5>
                                        <p class="card-text text-muted small">Oleh: <?php echo htmlspecialchars($book['author']); ?></p>
                                        <?php if (!empty($book['category_name'])): ?>
                                            <p class="card-text"><span class="badge bg-info text-dark"><?php echo htmlspecialchars($book['category_name']); ?></span></p>
                                        <?php endif; ?>
                                        <a href="book_detail.php?id=<?php echo $book['id']; ?>" class="btn btn-sm btn-outline-success">Lihat Detail</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Book page navigation">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo; Sebelumnya</span>
                                    </a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" aria-label="Next">
                                        <span aria-hidden="true">Berikutnya &raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert <?= $is_dark_mode ? 'alert-dark text-white' : 'alert-warning' ?> text-center border-0">Belum ada buku yang tersedia di database.</div>
                <?php endif; ?>
            <?php endif; // END FEATURE_PRODUCT_LISTING ?>

        </div>
    </div>
</main>

<footer class="py-3 mt-5 <?= $is_dark_mode ? 'bg-dark text-light' : 'bg-light text-dark' ?>">
    <div class="container text-center">
        <p>&copy; <?= date('Y'); ?> TokoBook</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function readMoreLess() {
    const dots = document.getElementById("dots");
    const moreText = document.getElementById("more-text");
    const btnText = document.getElementById("read-more-btn");

    if (moreText.style.display === "none") {
        dots.style.display = "none";
        moreText.style.display = "inline";
        btnText.innerHTML = "Baca Sedikit"; 
    } else {
        dots.style.display = "inline";
        moreText.style.display = "none";
        btnText.innerHTML = "Baca Selengkapnya"; 
    }
}
</script>
</body>
</html>