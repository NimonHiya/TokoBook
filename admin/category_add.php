<?php
session_start(); 
require_once __DIR__.'/../db.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role']!=='admin') { 
    header('Location: ../login.php'); 
    exit; 
}
$user_id = $_SESSION['user']['id'];

// --- Dark mode detection & Toggle Logic ---
$is_logged_in = true; // Sudah pasti admin
$current_db_mode = $_SESSION['user']['theme_mode'] ?? 0;

if (isset($_GET['toggle_theme']) && $_GET['toggle_theme'] === '1') {
    $new_db_mode = ($current_db_mode == 0) ? 1 : 0; 
    if (isset($pdo)) {
        $update_stmt = $pdo->prepare("UPDATE users SET theme_mode = ? WHERE id = ?");
        $update_stmt->execute([$new_db_mode, $user_id]);
        $_SESSION['user']['theme_mode'] = $new_db_mode;
    }
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Re-check theme after potential toggle
$is_dark_mode = ($_SESSION['user']['theme_mode'] ?? 0) == 1;
$theme = $is_dark_mode ? 'dark' : 'light';
$nav_class = $is_dark_mode ? 'navbar-dark bg-dark' : 'navbar-dark bg-primary'; 
$sidebar_color = $is_dark_mode ? '#1e1e1e' : '#f8f9fa';
$card_color = $is_dark_mode ? '#1e1e1e' : '#ffffff';


// --- Delete Logic ---
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $delete_id = intval($_GET['delete']);
        // Pastikan tidak ada buku yang menggunakan kategori ini (walau harusnya DB menangani SET NULL)
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$delete_id]);
        $_SESSION['flash'] = "Kategori berhasil dihapus.";
    } catch (PDOException $e) {
        $_SESSION['flash'] = "Error: Kategori mungkin sedang digunakan oleh beberapa buku.";
    }
    header('Location: categories.php'); 
    exit;
}

// --- Add Logic ---
if ($_SERVER['REQUEST_METHOD']==='POST'){
    $name = trim($_POST['name']);
    try {
        $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (:n)');
        $stmt->execute([':n'=>$name]);
        $_SESSION['flash'] = "Kategori '$name' berhasil ditambahkan.";
    } catch (PDOException $e) {
        $_SESSION['flash'] = "Gagal: Kategori mungkin sudah ada.";
    }
    header('Location: categories.php'); 
    exit;
}

// --- Fetch Categories ---
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../assets/logo.jpg" type="image/jpeg">
    <title>TokoBook - Kelola Kategori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* CSS Konsisten */
        body { transition: background-color 0.3s, color 0.3s; }
        .navbar-brand { font-weight: bold; }
        .toggle-btn {
            border: none;
            background-color: transparent;
            color: inherit;
            font-weight: bold;
            cursor: pointer;
            padding: .5rem 1rem;
            text-decoration: none;
        }

        /* DARK MODE - KONSISTEN SITE-WIDE */
        body.dark-mode { 
            background-color: #121212; 
            color: #f5f5f5; 
        }
        
        /* Navbar & Footer Konsisten */
        .navbar-dark.bg-dark,
        body.dark-mode footer.bg-dark { 
             background-color: #1f1f1f !important; 
        }

        /* ADMIN SIDEBAR ADAPTATION */
        .sidebar { 
            min-height: 100vh; 
            background-color: <?= $sidebar_color; ?>; 
            border-right: 1px solid <?= $is_dark_mode ? '#333' : '#dee2e6'; ?>;
            transition: background-color 0.3s, border-color 0.3s;
        }
        .sidebar .nav-link { 
            color: <?= $is_dark_mode ? '#f5f5f5' : '#333'; ?>; 
            padding: 0.75rem 1rem; 
            transition: background-color 0.2s, color 0.2s;
        }
        .sidebar .nav-link:hover { 
            background-color: <?= $is_dark_mode ? '#2a2a2a' : '#e9ecef'; ?>; 
        }
        .sidebar .nav-link.active { 
            background-color: #0d6efd; /* Primary */
            color: white !important; 
        }
        .sidebar h4 {
             color: <?= $is_dark_mode ? '#0d6efd' : '#333'; ?>;
        }

        /* CARD & FORM ADAPTATION */
        .card {
            background-color: <?= $card_color; ?>;
            color: <?= $is_dark_mode ? '#f5f5f5' : '#333'; ?>;
            border: 1px solid <?= $is_dark_mode ? '#333' : 'rgba(0,0,0,.125)'; ?>;
        }
         body.dark-mode .form-control,
         body.dark-mode .form-select { 
            background-color: #2b2b2b; 
            color: #f1f1f1; 
            border: 1px solid #444; 
         }
         body.dark-mode .form-control:focus { 
            background-color: #222; 
            border-color: #0d6efd; 
         }

        /* TABLE ADAPTATION */
        body.dark-mode .table {
            color: #f5f5f5;
        }
        body.dark-mode .table-hover>tbody>tr:hover {
            --bs-table-bg-hover: #2b2b2b;
        }
        body.dark-mode .table-responsive {
            border: 1px solid #333; 
            border-radius: 8px;
            overflow: hidden;
        }
        body.dark-mode .alert-info {
            background-color: #1f1f1f;
            border-color: #333;
            color: #ccc;
        }
        
        @media (max-width: 991.98px) { .sidebar { min-height: auto; } }
    </style>
</head>
<body class="<?= $theme === 'dark' ? 'dark-mode' : '' ?>">
<header>
    <nav class="navbar navbar-expand-lg <?= $nav_class ?>">
        <div class="container">
            <a class="navbar-brand" href="../index.php">TokoBook</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="../contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="../cart.php">Cart</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="dashboard.php">Admin</a></li>
                    <li class="nav-item">
                        <a href="?toggle_theme=1" class="nav-link toggle-btn" title="Toggle Dark/Light Mode">
                            <?= $is_dark_mode ? '☀️' : '🌙' ?>
                        </a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="../logout.php">Logout (<?php echo htmlspecialchars($_SESSION['user']['username']); ?>)</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<div class="d-flex">
    <nav class="sidebar d-flex flex-column p-3">
        <h4 class="mb-3">Admin Menu</h4>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="book_add.php">Tambah Buku</a></li>
            <li class="nav-item"><a class="nav-link active" href="categories.php">Kategori</a></li>
            <li class="nav-item"><a class="nav-link" href="users.php">Pengguna</a></li>
            <li class="nav-item"><a class="nav-link" href="orders.php">Pesanan</a></li>
            <li class="nav-item"><a class="nav-link" href="report.php">Buat Laporan (CSV)</a></li>
            <li class="nav-item"><a class="nav-link" href="about_edit.php">Edit Tentang Kami</a></li>
            <li class="nav-item"><a class="nav-link" href="contacts.php">Pesan Kontak</a></li>
        </ul>
    </nav>

    <main class="content flex-grow-1">
        <div class="container">
            <h2 class="mb-4 text-primary">Kelola Kategori Buku</h2>

            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="alert alert-info border-0"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card shadow-lg p-4 h-100">
                        <h4 class="mb-3">Tambah Kategori Baru</h4>
                        <form method="post">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nama Kategori</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Tambah Kategori</button>
                        </form>
                    </div>
                </div>

                <div class="col-md-8 mb-4">
                    <div class="card shadow-lg p-4 h-100">
                        <h4 class="mb-3">Daftar Kategori (Total: <?php echo count($categories); ?>)</h4>
                        
                        <?php if (empty($categories)): ?>
                             <div class="alert alert-info border-0">Belum ada kategori.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover <?= $is_dark_mode ? 'table-dark' : '' ?>">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($categories as $c): ?>
                                            <tr>
                                                <td><?php echo $c['id']; ?></td>
                                                <td><?php echo htmlspecialchars($c['name']); ?></td>
                                                <td>
                                                    <a href="categories.php?delete=<?php echo $c['id']; ?>" 
                                                       onclick="return confirm('Menghapus kategori akan mengatur category_id pada buku terkait menjadi NULL. Lanjutkan?')" 
                                                       class="btn btn-sm btn-danger">Hapus</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<footer class="py-3 mt-5 <?= $is_dark_mode ? 'bg-dark text-light' : 'bg-light text-dark' ?>">
    <div class="container text-center">
        <p>&copy; <?php echo date('Y'); ?> TokoBook</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>