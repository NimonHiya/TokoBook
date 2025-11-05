<?php
session_start(); 
require_once __DIR__.'/../db.php';

// --- Login Check & Theme Detection ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role']!=='admin') { 
    header('Location: ../login.php'); 
    exit; 
}
$user_id = $_SESSION['user']['id'];

// --- Dark mode detection & Toggle Logic ---
$is_logged_in = true; 
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


// Path file data
$about_text_file = __DIR__ . '/../data/about.txt';
$about_title_file = __DIR__ . '/../data/about_title.txt';
$about_image_file = __DIR__ . '/../data/about_image.txt';
$upload_dir = __DIR__ . '/../assets/';

// Pesan notifikasi
$msg = '';
$msg_type = 'info';

// Ambil data awal
$content = file_exists($about_text_file) ? file_get_contents($about_text_file) : '';
$title = file_exists($about_title_file) ? file_get_contents($about_title_file) : 'Tentang TokoBook 📖';
$image_name = file_exists($about_image_file) ? trim(file_get_contents($about_image_file)) : 'toko.jpg';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simpan teks dan judul
    $content = $_POST['content'] ?? '';
    $title = $_POST['title'] ?? 'Tentang TokoBook 📖';
    file_put_contents($about_text_file, $content);
    file_put_contents($about_title_file, $title);
    $msg = 'Perubahan teks berhasil disimpan.';

    // --- Upload gambar baru jika ada ---
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_ext = ['jpg','jpeg','png','gif','webp'];
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_name = basename($_FILES['image']['name']);
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed_ext)) {
            $new_name = 'toko_' . time() . '.' . $ext;
            $target_path = $upload_dir . $new_name;

            if (move_uploaded_file($file_tmp, $target_path)) {
                // Simpan nama file ke about_image.txt
                file_put_contents($about_image_file, $new_name);
                $image_name = $new_name;
                $msg = 'Perubahan teks dan gambar berhasil disimpan.';
                $msg_type = 'success';
            } else {
                $msg = 'Error: Gagal mengunggah gambar.';
                $msg_type = 'danger';
            }
        } else {
            $msg = 'Error: Jenis file tidak valid. Hanya JPG, PNG, GIF, WEBP yang diizinkan.';
            $msg_type = 'danger';
        }
    } else {
        $msg_type = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../assets/logo.jpg" type="image/jpeg">
    <title>TokoBook - Edit Tentang Kami</title>
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
         body.dark-mode .form-select,
         body.dark-mode textarea { 
            background-color: #2b2b2b; 
            color: #f1f1f1; 
            border: 1px solid #444; 
         }
         body.dark-mode .form-control:focus,
         body.dark-mode textarea:focus { 
            background-color: #222; 
            border-color: #0d6efd; 
         }
         body.dark-mode .form-control[type="file"]::file-selector-button {
             background-color: #333;
             color: #f5f5f5;
             border-right: 1px solid #444;
         }
         .preview-img {
            width: 100%;
            max-width: 500px;
            height: 300px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid <?= $is_dark_mode ? '#333' : '#eee'; ?>;
        }
        /* Alert colors in dark mode */
        body.dark-mode .alert-success { background-color: #1a473b; color: #d1e7dd; border-color: #1a473b; }
        body.dark-mode .alert-danger { background-color: #491d1e; color: #f8d7da; border-color: #491d1e; }
        body.dark-mode .alert-info { background-color: #1f1f1f; color: #ccc; border-color: #333; }
        body.dark-mode .btn-close { filter: invert(1); }
        
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
        <h4 class="mb-3">Menu Admin</h4>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="book_add.php">Tambah Buku</a></li>
            <li class="nav-item"><a class="nav-link" href="categories.php">Kategori</a></li>
            <li class="nav-item"><a class="nav-link" href="users.php">Pengguna</a></li>
            <li class="nav-item"><a class="nav-link" href="orders.php">Pesanan</a></li>
            <li class="nav-item"><a class="nav-link" href="report.php">Buat Laporan (CSV)</a></li>
            <li class="nav-item"><a class="nav-link active" href="about_edit.php">Edit Tentang Kami</a></li>
            <li class="nav-item"><a class="nav-link" href="contacts.php">Pesan Kontak</a></li>
        </ul>
    </nav>

    <main class="content flex-grow-1">
        <div class="container">
            <h2 class="mb-4 text-primary">Edit Halaman Tentang Kami 📝</h2>
            
            <?php if($msg): ?>
                <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show border-0" role="alert">
                    <?php echo htmlspecialchars($msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-lg p-4">
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="title" class="form-label">Judul Halaman</label>
                        <input type="text" id="title" name="title" class="form-control" 
                               value="<?php echo htmlspecialchars($title); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="content" class="form-label">Konten Utama "Tentang Kami"</label>
                        <textarea class="form-control" id="content" name="content" rows="10" required><?php echo htmlspecialchars($content); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="image" class="form-label">Gambar Utama (Header)</label>
                        <br>
                        <img src="../assets/<?php echo htmlspecialchars($image_name); ?>" class="preview-img mb-3" alt="Current About Image">
                        
                        <input type="file" name="image" id="image" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp">
                        <small class="form-text text-muted">Biarkan kosong jika tidak ingin mengubah gambar.</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg">Simpan Perubahan</button>
                    <a href="../about.php" class="btn btn-outline-secondary btn-lg ms-2">Lihat Halaman</a>
                </form>
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