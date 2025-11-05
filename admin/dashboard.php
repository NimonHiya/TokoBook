<?php
session_start(); 
require_once __DIR__.'/../db.php';

// --- Login Check & Theme Detection ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role']!=='admin') { 
    header('Location: ../login.php'); 
    exit; 
}
$user_id = $_SESSION['user']['id'];
$is_logged_in = true; // Sudah pasti login

// --- Dark mode detection & Toggle Logic ---
$current_db_mode = $_SESSION['user']['theme_mode'] ?? 0;

if (isset($_GET['toggle_theme']) && $_GET['toggle_theme'] === '1') {
    // Logika Database: Toggle theme_mode di tabel users
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
$nav_class = $is_dark_mode ? 'navbar-dark bg-dark' : 'navbar-dark bg-primary'; // Konsisten dengan index.php
$sidebar_color = $is_dark_mode ? '#1e1e1e' : '#f8f9fa';
$card_color = $is_dark_mode ? '#1e1e1e' : '#ffffff';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../assets/logo.jpg" type="image/jpeg">
    <title>TokoBook - Admin Dashboard</title>
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

        /* CONTENT ADAPTATION */
        .content { padding: 2rem; }
        .card {
            background-color: <?= $card_color; ?>;
            color: <?= $is_dark_mode ? '#f5f5f5' : '#333'; ?>;
            border: 1px solid <?= $is_dark_mode ? '#333' : 'rgba(0,0,0,.125)'; ?>;
        }
        .toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 1055; }
        
        /* Alert and Toast */
        body.dark-mode .alert-info {
            background-color: #1f1f1f;
            border-color: #333;
            color: #ccc;
        }
        .toast.text-bg-primary { /* Primary toast */
            background-color: #0d6efd !important; 
        }
        
        @media (max-width: 991.98px) { .sidebar { min-height: auto; } }
    </style>
</head>
<body class="<?= $theme === 'dark' ? 'dark-mode' : '' ?>">
<header>
    <nav class="navbar navbar-expand-lg <?= $nav_class ?>">
        <div class="container">
            <a class="navbar-brand" href="../index.php">TokoBook</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="../contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="../cart.php">Cart</a></li>
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">Admin</a></li>
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
            <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="book_add.php">Tambah Buku</a></li>
            <li class="nav-item"><a class="nav-link" href="categories.php">Kategori</a></li>
            <li class="nav-item"><a class="nav-link" href="users.php">Pengguna</a></li>
            <li class="nav-item"><a class="nav-link" href="orders.php">Pesanan</a></li>
            <li class="nav-item"><a class="nav-link" href="report.php">Buat Laporan (CSV)</a></li>
            <li class="nav-item"><a class="nav-link" href="about_edit.php">Edit Tentang Kami</a></li>
            <li class="nav-item">
                <a class="nav-link" href="contacts.php">
                    Pesan Kontak 
                    <span id="contact-badge" class="badge bg-danger ms-1" style="display:none;"></span>
                </a>
            </li>
        </ul>
    </nav>

    <main class="content flex-grow-1">
        <div class="container">
            <h2 class="mb-4 text-primary">Admin Dashboard 📊</h2>
            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="alert alert-info border-0"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
            <?php endif; ?>
            <div class="card shadow-sm p-4">
                <p>Selamat datang, <?php echo htmlspecialchars($_SESSION['user']['username']); ?>. Gunakan sidebar untuk mengelola inventaris, pengguna, pesanan, dan konten situs.</p>
            </div>
            
            <div class="row mt-4 g-4">
                 <div class="col-md-4">
                     <div class="card p-3 text-center">
                         <h5 class="card-title">Manajemen Buku</h5>
                         <p class="card-text">Kelola daftar buku yang tersedia di toko.</p>
                         <a href="book_add.php" class="btn btn-sm btn-primary">Tambah Buku</a>
                     </div>
                 </div>
                 <div class="col-md-4">
                     <div class="card p-3 text-center">
                         <h5 class="card-title">Lihat Pesanan</h5>
                         <p class="card-text">Tinjau dan proses pesanan yang masuk.</p>
                         <a href="orders.php" class="btn btn-sm btn-primary">Lihat Pesanan</a>
                     </div>
                 </div>
                 <div class="col-md-4">
                     <div class="card p-3 text-center">
                         <h5 class="card-title">Pengaturan Pengguna</h5>
                         <p class="card-text">Kelola akun pengguna dan peran mereka.</p>
                         <a href="users.php" class="btn btn-sm btn-primary">Kelola Pengguna</a>
                     </div>
                 </div>
             </div>
        </div>
    </main>
</div>

<div class="toast-container" id="toast-container"></div>

<footer class="py-3 mt-5 <?= $is_dark_mode ? 'bg-dark text-light' : 'bg-light text-dark' ?>">
    <div class="container text-center">
        <p>&copy; <?php echo date('Y'); ?> TokoBook</p>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let lastMessageId = 0;

function showToast(message) {
    // Toast BG disesuaikan untuk dark mode di CSS
    let toastClass = 'text-bg-primary';
    let btnCloseClass = 'btn-close-white';

    let toastHTML = `
    <div class="toast align-items-center ${toastClass} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close ${btnCloseClass} me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>`;
    let toastElement = $(toastHTML);
    $('#toast-container').append(toastElement);
    let toast = new bootstrap.Toast(toastElement[0], { delay: 5000 });
    toast.show();
}

// Fungsi cek pesan baru
function checkMessages() {
    // Pastikan path ke check_messages.php sudah benar, relatif terhadap dashboard.php
    $.getJSON('check_messages.php', function(data) {
        let count = data.unread;
        let badge = $('#contact-badge');
        if(count > 0) {
            badge.text(count).show();
        } else {
            badge.hide();
        }

        // Tampilkan popup untuk pesan terbaru jika ada pesan baru
        if(data.latest_id > lastMessageId) {
            lastMessageId = data.latest_id;
            if(data.latest_message) {
                showToast(data.latest_message);
            }
        }
    });
}

// Cek setiap 10 detik
setInterval(checkMessages, 10000);
checkMessages();
</script>
</body>
</html>