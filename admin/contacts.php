<?php
session_start();
require_once __DIR__.'/../db.php';

// --- Login Check & Theme Detection ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
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


// Ambil semua pesan
$contacts = $pdo->query('SELECT c.*, u.username FROM contacts c LEFT JOIN users u ON c.user_id=u.id ORDER BY c.sent_at DESC')->fetchAll(PDO::FETCH_ASSOC);

// Hitung pesan baru (sebelum update)
$newMessagesCount = $pdo->query('SELECT COUNT(*) FROM contacts WHERE is_read = 0')->fetchColumn();

// Tandai semua pesan sebagai dibaca setelah halaman dibuka
$pdo->query('UPDATE contacts SET is_read = 1 WHERE is_read = 0');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../assets/logo.jpg" type="image/jpeg">
    <title>TokoBook - Pesan Kontak</title>
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

        /* CARD & TABLE ADAPTATION */
        .card {
            background-color: <?= $card_color; ?>;
            color: <?= $is_dark_mode ? '#f5f5f5' : '#333'; ?>;
            border: 1px solid <?= $is_dark_mode ? '#333' : 'rgba(0,0,0,.125)'; ?>;
        }
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
        
        /* Message Badge */
        .badge-notif {
            background-color: #dc3545; /* Red danger */
            color: white;
            font-size: 0.75rem;
            padding: 0.25em 0.5em;
            border-radius: 10px;
            margin-left: 4px;
        }
        body.dark-mode .btn-outline-danger {
            color: #f8d7da;
            border-color: #f8d7da;
        }
        body.dark-mode .btn-outline-danger:hover {
            background-color: #f8d7da;
            color: #121212;
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
        <h4 class="mb-3">Menu Admin</h4>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="book_add.php">Tambah Buku</a></li>
            <li class="nav-item"><a class="nav-link" href="categories.php">Kategori</a></li>
            <li class="nav-item"><a class="nav-link" href="users.php">Pengguna</a></li>
            <li class="nav-item"><a class="nav-link" href="orders.php">Pesanan</a></li>
            <li class="nav-item"><a class="nav-link" href="report.php">Buat Laporan (CSV)</a></li>
            <li class="nav-item"><a class="nav-link" href="about_edit.php">Edit Tentang Kami</a></li>
            <li class="nav-item">
                <a class="nav-link active" href="contacts.php">
                    Pesan Kontak 
                    <span id="messageBadge" class="badge-notif" style="display:none;"></span>
                </a>
            </li>
        </ul>
    </nav>

    <main class="content flex-grow-1">
        <div class="container">
            <h2 class="mb-4 text-primary">Pesan Kontak Masuk 📧</h2>
            <div id="alertContainer">
                <?php if ($newMessagesCount > 0): ?>
                    <div class="alert alert-info border-0" role="alert">
                        🔔 Ada **<?php echo $newMessagesCount; ?> pesan baru** yang sudah ditandai sebagai dibaca.
                    </div>
                <?php endif; ?>
            </div>

            <div class="card shadow-lg">
                <div class="card-body p-4">
                    <?php if (empty($contacts)): ?>
                        <div class="alert alert-info border-0 text-center">Belum ada pesan kontak yang masuk.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover <?= $is_dark_mode ? 'table-dark' : '' ?>">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Pengirim</th>
                                        <th>Pesan</th>
                                        <th>Dikirim Pada</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contacts as $c): ?>
                                        <tr>
                                            <td><?php echo $c['id']; ?></td>
                                            <td><?php echo htmlspecialchars($c['username'] ?? 'Tamu'); ?></td>
                                            <td style="max-width: 400px; white-space: normal;"><?php echo nl2br(htmlspecialchars(substr($c['message'], 0, 100) . (strlen($c['message']) > 100 ? '...' : ''))); ?></td>
                                            <td><?php echo htmlspecialchars(date('d M Y H:i', strtotime($c['sent_at']))); ?></td>
                                            <td>
                                                <a href="contact_delete.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus pesan ini?')">Hapus</a>
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