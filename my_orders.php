<?php
session_start(); 
require_once __DIR__.'/db.php';

// --- Login Check ---
if (!isset($_SESSION['user'])) { 
    header('Location: login.php'); 
    exit; 
}
$user_id = $_SESSION['user']['id'];

// --- Dark mode detection & Toggle Logic (Consistent Hybrid Logic) ---
$is_logged_in = true; // Sudah pasti login
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
$nav_class = $is_dark_mode ? 'navbar-dark bg-dark' : 'navbar-dark bg-primary';


// --- Fetch Orders ---
$orders = $pdo->prepare('SELECT o.*, b.title FROM orders o JOIN books b ON o.book_id=b.id WHERE o.user_id=:uid ORDER BY o.ordered_at DESC');
$orders->execute([':uid'=>$user_id]);
$rows = $orders->fetchAll(PDO::FETCH_ASSOC);
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
        
        /* Tabel Dark Mode */
        body.dark-mode .table {
            color: #f5f5f5;
        }
        body.dark-mode .table-hover>tbody>tr:hover {
            --bs-table-bg-hover: #2b2b2b;
        }
        body.dark-mode .table-responsive {
            border: 1px solid #333; /* Border for table container */
            border-radius: 8px;
            overflow: hidden;
        }
        body.dark-mode .alert-info {
            background-color: #1f1f1f;
            border-color: #333;
            color: #ccc;
        }
        body.dark-mode .btn-outline-secondary {
            color: #ccc;
            border-color: #ccc;
        }
        body.dark-mode .btn-outline-secondary:hover {
            background-color: #ccc;
            color: #121212;
        }
        /* Badge colors in dark mode need contrast */
        body.dark-mode .badge.bg-warning {
            color: #121212 !important;
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
                    <li class="nav-item">
                        <a href="?toggle_theme=1" class="nav-link toggle-btn" title="Toggle Dark/Light Mode">
                            <?= $is_dark_mode ? '☀️' : '🌙' ?>
                        </a>
                    </li>
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
            
            <?php if (empty($rows)): ?>
                <div class="alert alert-info text-center" role="alert">
                    Anda belum memiliki pesanan. <a href="index.php" class="alert-link">Mulai belanja sekarang!</a>
                </div>
            <?php else: ?>
                <div class="card shadow-lg">
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover <?= $is_dark_mode ? 'table-dark' : '' ?>">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Buku</th>
                                        <th>Kuantitas</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                        <th>Tanggal Pesan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($rows as $r): ?>
                                        <tr>
                                            <td><?php echo $r['id']; ?></td>
                                            <td><?php echo htmlspecialchars($r['title']); ?></td>
                                            <td><?php echo $r['quantity']; ?></td>
                                            <td>Rp **<?php echo number_format($r['total_price'], 2, ',', '.'); ?>**</td>
                                            <td>
                                                <?php 
                                                    $badge_class = 'bg-secondary';
                                                    if ($r['status'] === 'pending') $badge_class = 'bg-warning text-dark';
                                                    elseif ($r['status'] === 'paid') $badge_class = 'bg-success';
                                                    elseif ($r['status'] === 'cancelled') $badge_class = 'bg-danger';
                                                ?>
                                                <span class="badge <?= $badge_class ?>"><?php echo htmlspecialchars(ucfirst($r['status'])); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($r['status'] === 'pending'): ?>
                                                    <div class="d-flex gap-2">
                                                        <form method="post" action="order_pay.php" class="d-inline">
                                                            <input type="hidden" name="order_id" value="<?php echo $r['id']; ?>">
                                                            <?php // echo csrf_input_field(); // Asumsi CSRF field ada ?>
                                                            <button type="submit" class="btn btn-sm btn-success">Bayar</button>
                                                        </form>
                                                        
                                                        <form method="post" action="order_cancel.php" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini?');" class="d-inline">
                                                            <input type="hidden" name="order_id" value="<?php echo $r['id']; ?>">
                                                            <?php // echo csrf_input_field(); // Asumsi CSRF field ada ?>
                                                            <button type="submit" class="btn btn-sm btn-danger">Batal</button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('d M Y H:i', strtotime($r['ordered_at']))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
</body>
</html>