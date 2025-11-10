<?php
session_start();
require_once __DIR__ . '/db.php';

// --- Global Feature Toggles ---
// **********************************************************
const FEATURE_THEME_TOGGLE = false; 
const FEATURE_SEARCH_FILTER = false; 
const FEATURE_RATING_REVIEW = false;
const FEATURE_WISHLIST = false; 
// **********************************************************


// --- DARK MODE LOGIC START ---

$is_logged_in = isset($_SESSION['user']);
$theme_source = ''; 

// LOGIKA PHP UNTUK TOGGLE DIHAPUS JIKA FITUR DIMATIKAN
if (FEATURE_THEME_TOGGLE && isset($_GET['toggle_theme']) && $_GET['toggle_theme'] === '1') {
    if ($is_logged_in) {
        // Logika Database
        $user_id = $_SESSION['user']['id']; 
        $current_db_mode = $_SESSION['user']['theme_mode'] ?? 0;
        $new_db_mode = ($current_db_mode == 0) ? 1 : 0; 

        if (isset($pdo)) {
            $update_stmt = $pdo->prepare("UPDATE users SET theme_mode = ? WHERE id = ?");
            $update_stmt->execute([$new_db_mode, $user_id]);
            $_SESSION['user']['theme_mode'] = $new_db_mode;
        }
    } else {
        // Logika Cookie
        if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
            setcookie('theme', 'light', time() + (86400 * 30), '/');
        } else {
            setcookie('theme', 'dark', time() + (86400 * 30), '/');
        }
    }
    
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// 1. Tentukan Tema yang akan digunakan
if ($is_logged_in) {
    $is_dark_mode = ($_SESSION['user']['theme_mode'] ?? 0) == 1;
    $theme_source = 'DB';
} else {
    $is_dark_mode = ($_COOKIE['theme'] ?? 'light') === 'dark';
    $theme_source = 'Cookie';
}

$theme = $is_dark_mode ? 'dark' : 'light';

// --- DARK MODE LOGIC END ---

// --- Helper function untuk bintang (DIBUAT KONDISIONAL) ---
if (FEATURE_RATING_REVIEW) {
    function display_stars($rating) {
        $stars = '';
        $full_stars = floor($rating);
        
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $full_stars) {
                $stars .= '★'; 
            } else {
                $stars .= '☆'; 
            }
        }
        return $stars;
    }
}


// --- Input pencarian, filter, dan sorting (Dipertahankan di PHP agar SQL tidak error) ---
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$category_id = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? intval($_GET['category_id']) : null;
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? floatval($_GET['min_price']) : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : null;
$sort_by = $_GET['sort_by'] ?? 'date_desc'; // Default sorting

// --- Pagination ---
$perPage = 6;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// --- Ambil kategori ---
$categories = [];
if (isset($pdo)) {
    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
}


// --- Filter dinamis ---
$where = [];
$params = [];

if (FEATURE_SEARCH_FILTER) { // Bungkus filter logic
    if ($q) {
        $where[] = 'b.title LIKE :q';
        $params[':q'] = '%' . $q . '%';
    }
    if ($category_id) {
        $where[] = 'b.category_id = :cid';
        $params[':cid'] = $category_id;
    }
    if ($min_price !== null) {
        $where[] = 'b.price >= :minp';
        $params[':minp'] = $min_price;
    }
    if ($max_price !== null) {
        $where[] = 'b.price <= :maxp';
        $params[':maxp'] = $max_price;
    }
}

$whereSql = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';

// --- Logika Sorting SQL ---
$orderBySql = 'ORDER BY b.id DESC'; // Default (date_desc)

if (FEATURE_RATING_REVIEW) { // Hanya izinkan sorting rating jika fitur rating aktif
    switch ($sort_by) {
        case 'rating_desc':
            $orderBySql = 'ORDER BY avg_rating DESC, b.id DESC';
            break;
        case 'price_asc':
            $orderBySql = 'ORDER BY b.price ASC, b.id DESC';
            break;
        case 'price_desc':
            $orderBySql = 'ORDER BY b.price DESC, b.id DESC';
            break;
        case 'title_asc':
            $orderBySql = 'ORDER BY b.title ASC, b.id DESC';
            break;
        case 'date_desc':
        default:
            $orderBySql = 'ORDER BY b.id DESC';
            break;
    }
} else {
    $orderBySql = 'ORDER BY b.id DESC'; // Default jika sorting rating dimatikan
}


// --- Hitung total dan Ambil data buku ---
$books = [];
$totalBooks = 0;
$totalPages = 1;

if (isset($pdo)) {
    // Hitung total
    $countSql = 'SELECT COUNT(b.id) FROM books b' . $whereSql;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalBooks = $countStmt->fetchColumn();
    $totalPages = ceil($totalBooks / $perPage);

    // Ambil data buku DENGAN AVG RATING (Hanya JOIN jika rating aktif)
    $sql = '
        SELECT 
            b.*
            ' . (FEATURE_RATING_REVIEW ? ', COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.id) AS total_reviews' : '') . '
        FROM books b
        ' . (FEATURE_RATING_REVIEW ? 'LEFT JOIN reviews r ON b.id = r.book_id' : '') . '
        ' . $whereSql . '
        ' . (FEATURE_RATING_REVIEW ? 'GROUP BY b.id' : '') . '
        ' . $orderBySql . '
        LIMIT :limit OFFSET :offset
    ';
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        if ($key === ':limit' || $key === ':offset') continue; 
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function untuk mendapatkan nama yang sedang disortir
function get_sort_name($sort_by) {
    switch ($sort_by) {
        case 'rating_desc': return 'Rating Tertinggi';
        case 'price_asc': return 'Harga Terendah';
        case 'price_desc': return 'Harga Tertinggi';
        case 'title_asc': return 'Judul (A-Z)';
        case 'date_desc':
        default: return 'Terbaru';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/logo.jpg" type="image/jpeg">
<title>TokoBook - Home</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* ======== Tema umum ======== */
body { transition: background-color 0.3s, color 0.3s; }
.navbar-brand { font-weight: bold; }
.book-card { transition: transform 0.2s; border-radius: 10px; overflow: hidden; height: 100%; }
.book-card:hover { transform: scale(1.02); }
.book-image { height: 300px; width: 100%; object-fit: cover; background-color: #f8f9fa; }
.card-body { padding: 1.5rem; display: flex; flex-direction: column; }
.card-title { font-size: 1.25rem; margin-bottom: 0.5rem; } 
.pagination { justify-content: center; margin-top: 30px; }

/* ======== DARK MODE Enhancements (KONSISTENSI & FIX INPUT TEXT) ======== */
body.dark-mode { 
    background-color: #121212; 
    color: #f5f5f5; 
}
/* Navbar dan Footer konsisten */
.navbar-dark.bg-dark, 
body.dark-mode footer.bg-dark { 
    background-color: #1f1f1f !important; 
    color: #f5f5f5; 
}
/* Card konsisten */
body.dark-mode .card { 
    background-color: #1e1e1e; 
    color: #f5f5f5; 
    border-color: #333; 
}

/* FIX INPUT TEXT COLOR IN DARK MODE */
body.dark-mode .form-label, 
body.dark-mode .dropdown-menu {
    color: #f5f5f5; 
}
body.dark-mode .form-control, 
body.dark-mode .form-select,
body.dark-mode .dropdown-menu {
    background-color: #2b2b2b; 
    color: #f5f5f5; /* FIX: Teks input/select jadi putih */
    border-color: #444;
}
body.dark-mode .form-control::placeholder {
    color: #aaa;
}
/* Dropdown menu items */
body.dark-mode .dropdown-item {
    color: #f5f5f5;
}
body.dark-mode .dropdown-item:hover,
body.dark-mode .dropdown-item:focus {
    background-color: #383838;
}

/* Tombol Sekunder di Dark Mode */
body.dark-mode .btn-secondary {
    background-color: #333;
    border-color: #333;
    color: #f5f5f5;
}
body.dark-mode .btn-secondary:hover {
    background-color: #444;
    border-color: #444;
}
body.dark-mode .btn-outline-primary { 
    color: #0d6efd; 
    border-color: #0d6efd; 
}
body.dark-mode .btn-outline-primary:hover { 
    background-color: #0d6efd; 
    color: #fff; 
}
/* Tombol toggle tema */
.theme-btn { 
    border: none; 
    background: transparent; 
    color: inherit; 
    font-size: 1.2rem; 
    cursor: pointer; 
    margin-left: 10px; 
    line-height: 1.8; 
    text-decoration: none;
}
body.dark-mode .alert-info {
    background-color: #1f1f1f;
    border-color: #333;
    color: #6c757d;
}

/* RATING STAR STYLING */
.rating-stars-display {
    color: gold; 
    letter-spacing: 2px;
}
.rating-stars-display small {
    color: #bbb;
}

/* New Search/Filter Bar Styling */
.filter-container {
    background-color: var(--bs-light);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 25px;
}
body.dark-mode .filter-container {
    background-color: #1f1f1f;
    border: 1px solid #333;
}

/* Fix for dropdown menus inside dropdowns */
.dropdown-menu-end {
    right: 0;
    left: auto;
}
</style>
</head>

<body class="<?= $theme === 'dark' ? 'dark-mode' : '' ?>">
<header>
    <nav class="navbar navbar-expand-lg <?= $is_dark_mode ? 'navbar-dark bg-dark' : 'navbar-dark bg-primary' ?>">
        <div class="container">
            <a class="navbar-brand" href="index.php">TokoBook</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <?php if (isset($_SESSION['user'])): ?>
                        <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                        <li class="nav-item"><a class="nav-link" href="my_orders.php">Pesanan Saya</a></li>
                        <?php if (FEATURE_WISHLIST): ?>
                        <li class="nav-item"><a class="nav-link" href="wishlist.php">Wishlist</a></li>
                        <?php endif; ?>
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
                        <a href="?toggle_theme=1" class="theme-btn" title="Toggle dark mode">
                            <?= $theme === 'dark' ? '☀️' : '🌙' ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main class="container my-5">
    <div class="row mb-4">
        <div class="col-md-12">
            <?php if (FEATURE_SEARCH_FILTER): ?>
            <form method="get" class="row g-3 align-items-center filter-container">

                <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by); ?>">
                <input type="hidden" name="category_id" value="<?= htmlspecialchars($category_id ?? ''); ?>">
                <input type="hidden" name="min_price" value="<?= htmlspecialchars($min_price ?? ''); ?>">
                <input type="hidden" name="max_price" value="<?= htmlspecialchars($max_price ?? ''); ?>">
                
                <div class="col-lg-5 col-md-6">
                    <div class="position-relative">
                        <input type="text" name="q" id="search-box" class="form-control form-control-lg" placeholder="Cari Judul, Penulis, atau ISBN..." autocomplete="off"
                                value="<?= htmlspecialchars($q); ?>">
                        <div id="suggestions" class="list-group position-absolute w-100 shadow-lg"
                                style="z-index:1000; display:none;"></div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-3">
                    <button type="submit" class="btn btn-primary btn-lg w-100">Cari</button>
                </div>

                <div class="col-lg-2 col-md-3 dropdown">
                    <button class="btn btn-secondary w-100 dropdown-toggle" type="button" id="sortByDropdown"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        Urutkan: <?= get_sort_name($sort_by); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item <?= $sort_by == 'date_desc' ? 'active' : ''; ?>" href="#" onclick="document.querySelector('input[name=sort_by]').value='date_desc'; this.closest('form').submit();">Terbaru</a></li>
                        <li><a class="dropdown-item <?= $sort_by == 'rating_desc' ? 'active' : ''; ?>" href="#" onclick="document.querySelector('input[name=sort_by]').value='rating_desc'; this.closest('form').submit();">Rating Tertinggi</a></li>
                        <li><a class="dropdown-item <?= $sort_by == 'price_asc' ? 'active' : ''; ?>" href="#" onclick="document.querySelector('input[name=sort_by]').value='price_asc'; this.closest('form').submit();">Harga Terendah</a></li>
                        <li><a class="dropdown-item <?= $sort_by == 'price_desc' ? 'active' : ''; ?>" href="#" onclick="document.querySelector('input[name=sort_by]').value='price_desc'; this.closest('form').submit();">Harga Tertinggi</a></li>
                        <li><a class="dropdown-item <?= $sort_by == 'title_asc' ? 'active' : ''; ?>" href="#" onclick="document.querySelector('input[name=sort_by]').value='title_asc'; this.closest('form').submit();">Judul (A-Z)</a></li>
                    </ul>
                </div>


                <div class="col-lg-3 col-md-12 dropdown">
                    <button class="btn btn-secondary w-100 dropdown-toggle" type="button" id="filterDropdown"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        Filter Lanjut
                    </button>
                    <div class="dropdown-menu p-3 dropdown-menu-end" style="min-width:300px;">
                        
                        <h6 class="dropdown-header">Filter Aktif: (<?= count(array_filter([$category_id, $min_price, $max_price])) ?>)</h6>
                        <hr class="dropdown-divider">
                        
                        <div class="mb-3">
                            <label for="category_id_filter" class="form-label small mb-1">Kategori</label>
                            <select id="category_id_filter" class="form-select form-select-sm" onchange="document.querySelector('input[name=category_id]').value=this.value;">
                                <option value="">Semua Kategori</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id']; ?>" <?= ($category_id == $c['id']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row g-2 mb-3">
                            <div class="col">
                                <label for="min_price_filter" class="form-label small mb-1">Min Harga</label>
                                <input type="number" step="0.01" id="min_price_filter"
                                                 class="form-control form-control-sm"
                                                 placeholder="Min"
                                                 value="<?= htmlspecialchars($min_price ?? ''); ?>"
                                                 onchange="document.querySelector('input[name=min_price]').value=this.value;">
                            </div>
                            <div class="col">
                                <label for="max_price_filter" class="form-label small mb-1">Max Harga</label>
                                <input type="number" step="0.01" id="max_price_filter"
                                                 class="form-control form-control-sm"
                                                 placeholder="Max"
                                                 value="<?= htmlspecialchars($max_price ?? ''); ?>"
                                                 onchange="document.querySelector('input[name=max_price]').value=this.value;">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm w-100">Terapkan Filter</button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <section class="books">
        <?php if ($totalBooks === 0): ?>
             <div class="alert alert-info text-center border-0">Tidak ada buku ditemukan dengan kriteria tersebut.</div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php foreach ($books as $b): ?>
                    <div class="col">
                        <article class="book-card card h-100 shadow-sm">
                            <?php 
                                $imagePath = !empty($b['image']) && file_exists(__DIR__ . '/assets/images/' . $b['image']) 
                                    ? 'assets/images/' . htmlspecialchars($b['image']) 
                                    : 'https://via.placeholder.com/400x600?text=No+Cover';
                            ?>
                            <img src="<?= $imagePath; ?>" class="book-image" alt="<?= htmlspecialchars($b['title']); ?>">
                            
                            <div class="card-body">
                                <h3 class="card-title"><?= htmlspecialchars($b['title']); ?></h3>
                                
                                <?php if (FEATURE_RATING_REVIEW): ?>
                                    <?php if ($b['total_reviews'] > 0): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="text-warning rating-stars-display me-2 h6 mb-0">
                                                <?= display_stars($b['avg_rating']); ?>
                                            </span>
                                            <small class="text-muted">(<?= $b['total_reviews']; ?> ulasan)</small>
                                        </div>
                                    <?php else: ?>
                                        <small class="text-muted mb-2">Belum ada ulasan.</small>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <p class="mb-1">Penulis: <?= htmlspecialchars($b['author']); ?></p>
                                <p class="fw-bold text-success">Rp <?= number_format($b['price'], 2, ',', '.'); ?></p>
                                
                                <div class="btn-group d-flex gap-2 mt-auto">
                                    <a href="book_detail.php?id=<?= $b['id']; ?>" class="btn btn-outline-primary btn-sm">Detail</a>
                                    <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
                                        <a href="admin/book_edit.php?id=<?= $b['id']; ?>" class="btn btn-outline-secondary btn-sm">Edit</a>
                                        <a href="admin/book_delete.php?id=<?= $b['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Yakin ingin menghapus buku ini?')">Hapus</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center mt-4">
                        <?php 
                        $queryString = http_build_query(array_filter($_GET, fn($key) => $key !== 'page' && $key !== 'toggle_theme', ARRAY_FILTER_USE_KEY));
                        $baseHref = strtok($_SERVER['PHP_SELF'], '?');
                        $separator = $queryString ? '&' : '?';
                        $fullBaseUrl = $baseHref . ($queryString ? '?' . $queryString : '');
                        ?>

                        <?php if ($page > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= $fullBaseUrl . ($queryString ? $separator : '?') . 'page=' . ($page - 1); ?>">Previous</a></li>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= ($p == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= $fullBaseUrl . ($queryString ? $separator : '?') . 'page=' . $p; ?>"><?= $p; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item"><a class="page-link" href="<?= $fullBaseUrl . ($queryString ? $separator : '?') . 'page=' . ($page + 1); ?>">Next</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
        <div class="text-center mt-4">
            <a href="admin/book_add.php" class="btn btn-success">Tambah Buku Baru</a>
        </div>
    <?php endif; ?>
</main>

<footer class="py-3 mt-5 <?= $is_dark_mode ? 'bg-dark text-light' : 'bg-light text-dark' ?>">
    <div class="container text-center">
        <p class="mb-0">&copy; <?= date('Y'); ?> TokoBook</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchBox = document.getElementById('search-box');
    const suggestions = document.getElementById('suggestions');
    
    // Sinkronkan nilai filter yang tersembunyi dengan dropdown filter lanjutan
    document.getElementById('filterDropdown').addEventListener('click', function() {
        // Menggunakan ID filter yang benar di dalam dropdown
        const categoryFilter = document.getElementById('category_id_filter');
        const minPriceFilter = document.getElementById('min_price_filter');
        const maxPriceFilter = document.getElementById('max_price_filter');

        // Mengambil nilai dari input hidden (yang merepresentasikan filter yang saat ini aktif di URL)
        categoryFilter.value = document.querySelector('input[name=category_id]').value;
        minPriceFilter.value = document.querySelector('input[name=min_price]').value;
        maxPriceFilter.value = document.querySelector('input[name=max_price]').value;
    });

    // Menambahkan event listener untuk menyinkronkan perubahan di dropdown ke input hidden
    document.getElementById('category_id_filter').addEventListener('change', function() {
        document.querySelector('input[name=category_id]').value = this.value;
    });
    document.getElementById('min_price_filter').addEventListener('change', function() {
        document.querySelector('input[name=min_price]').value = this.value;
    });
    document.getElementById('max_price_filter').addEventListener('change', function() {
        document.querySelector('input[name=max_price]').value = this.value;
    });
    
    // Search Suggestions Logic (Hanya berjalan jika fitur aktif)
    if (<?php echo FEATURE_SEARCH_FILTER ? 'true' : 'false'; ?>) {
        searchBox.addEventListener('input', function () {
            const query = this.value.trim();
            if (query.length < 2) { suggestions.style.display = 'none'; return; }
            // Note: Pastikan search_suggest.php ada dan berfungsi
            fetch('search_suggest.php?q=' + encodeURIComponent(query))
                .then(res => res.text())
                .then(html => {
                    suggestions.innerHTML = html;
                    suggestions.style.display = html.trim() ? 'block' : 'none';
                });
        });
        suggestions.addEventListener('click', e => {
            const item = e.target.closest('.list-group-item'); 
            if (item) {
                const title = item.getAttribute('data-title');
                if (title) {
                    searchBox.value = title;
                    suggestions.style.display = 'none';
                    searchBox.closest('form').submit(); // Langsung submit setelah memilih
                }
            }
        });
        document.addEventListener('click', e => {
            if (!suggestions.contains(e.target) && e.target !== searchBox) suggestions.style.display = 'none';
        });
    }
});
</script>
</body>
</html>