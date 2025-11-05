<?php
session_start();
require_once __DIR__ . '/../db.php';

// --- Login Check & Theme Detection ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
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


// ==== PROSES UPDATE STATUS / CANCEL ORDER ====
if (isset($_GET['mark_paid'])) {
    $orderId = (int)$_GET['mark_paid'];
    $pdo->prepare("UPDATE orders SET status = 'selesai' WHERE id = ?")->execute([$orderId]);
    header("Location: orders.php?success=paid");
    exit;
}

if (isset($_POST['cancel_order'])) {
    $orderId = (int)$_POST['cancel_order'];
    $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);
    header("Location: orders.php?success=cancel");
    exit;
}

// ==== AMBIL DATA USERS YANG MEMILIKI PESANAN ====
$users = $pdo->query("
    SELECT DISTINCT u.id, u.username 
    FROM users u 
    JOIN orders o ON u.id = o.user_id 
    ORDER BY u.username ASC
")->fetchAll(PDO::FETCH_ASSOC);

$selectedUser = isset($_GET['user_id']) ? (int)$_GET['user_id'] : ($users[0]['id'] ?? 0);

// ==== PAGINATION ====
$perPage = 5; // Ubah ke 5 atau lebih untuk tampilan lebih baik
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// Hitung total pesanan user terpilih
$totalOrdersStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$totalOrdersStmt->execute([$selectedUser]);
$totalOrders = $totalOrdersStmt->fetchColumn();
$totalPages = ceil($totalOrders / $perPage);

// Ambil pesanan user terpilih
$ordersStmt = $pdo->prepare("
    SELECT o.*, b.title, u.username 
    FROM orders o 
    JOIN books b ON o.book_id = b.id
    JOIN users u ON o.user_id = u.id
    WHERE o.user_id = ?
    ORDER BY o.ordered_at DESC
    LIMIT $perPage OFFSET $offset
");
$ordersStmt->execute([$selectedUser]);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="../assets/logo.jpg" type="image/jpeg">
<title>TokoBook - Kelola Pesanan</title>
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
    body.dark-mode .alert {
        background-color: #1f1f1f;
        border-color: #333;
        color: #ccc;
    }
    
    /* USERS LIST STYLING */
    .users-list {
        background-color: <?= $sidebar_color; ?>; /* Konsisten dengan sidebar */
        border-right: 1px solid <?= $is_dark_mode ? '#333' : '#dee2e6'; ?>;
        max-height: 75vh;
        overflow-y: auto;
    }
    .users-list h5 {
        background-color: <?= $is_dark_mode ? '#1f1f1f' : '#e9ecef'; ?> !important;
        color: <?= $is_dark_mode ? '#f5f5f5' : '#333'; ?>;
        border-bottom: 1px solid <?= $is_dark_mode ? '#333' : '#dee2e6'; ?>;
    }
    .user-item {
        color: <?= $is_dark_mode ? '#ccc' : '#333'; ?>;
    }
    .user-item:hover {
        background-color: <?= $is_dark_mode ? '#2a2a2a' : '#f1f1f1'; ?>;
        color: <?= $is_dark_mode ? '#f5f5f5' : '#333'; ?>;
    }
    .user-item.active {
        background-color: #0d6efd;
        color: #fff;
    }
    .pagination { justify-content: center; }

    /* Badge colors in dark mode */
    body.dark-mode .badge.bg-warning {
        color: #121212 !important;
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
            <li class="nav-item"><a class="nav-link active" href="orders.php">Pesanan</a></li>
            <li class="nav-item"><a class="nav-link" href="report.php">Buat Laporan (CSV)</a></li>
            <li class="nav-item"><a class="nav-link" href="about_edit.php">Edit Tentang Kami</a></li>
            <li class="nav-item"><a class="nav-link" href="contacts.php">Pesan Kontak</a></li>
        </ul>
    </nav>

    <main class="content flex-grow-1">
        <div class="container-fluid">
            <h2 class="mb-4 text-primary">Kelola Pesanan 🧾</h2>

            <?php if (isset($_GET['success'])): ?>
                <?php if ($_GET['success'] === 'paid'): ?>
                    <div class="alert alert-success border-0">Pesanan ditandai sebagai **selesai**.</div>
                <?php elseif ($_GET['success'] === 'cancel'): ?>
                    <div class="alert alert-danger border-0">Pesanan telah **dibatalkan** dan dihapus.</div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="row g-0 border rounded-3 overflow-hidden shadow-lg">
                <div class="col-md-3 users-list">
                    <h5 class="text-center py-2 mb-0">Pengguna</h5>
                    <?php if (empty($users)): ?>
                         <p class="p-3 text-muted">Belum ada pengguna yang memesan.</p>
                    <?php endif; ?>
                    <?php foreach ($users as $u): ?>
                        <a href="?user_id=<?php echo $u['id']; ?>" 
                           class="user-item <?php echo ($selectedUser == $u['id']) ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($u['username']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="col-md-9 p-4 <?= $is_dark_mode ? 'bg-dark' : 'bg-white' ?>">
                    <h5 class="mb-3">Pesanan dari: 
                        <span class="text-success">
                            <?php 
                                $currentUser = array_filter($users, fn($usr) => $usr['id'] == $selectedUser);
                                echo htmlspecialchars(array_values($currentUser)[0]['username'] ?? 'Pilih Pengguna');
                            ?>
                        </span> 
                        (Total: <?php echo $totalOrders; ?>)
                    </h5>

                    <?php if (empty($orders)): ?>
                        <div class="alert alert-info mt-3 border-0">Tidak ada pesanan ditemukan untuk pengguna ini.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle <?= $is_dark_mode ? 'table-dark' : '' ?>">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Buku</th>
                                        <th>Kuantitas</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Tanggal Pesan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($orders as $o): ?>
                                    <tr>
                                        <td><?php echo $o['id']; ?></td>
                                        <td><?php echo htmlspecialchars($o['title']); ?></td>
                                        <td><?php echo $o['quantity']; ?></td>
                                        <td>Rp **<?php echo number_format($o['total_price'], 2, ',', '.'); ?>**</td>
                                        <td>
                                            <?php 
                                                $badge_class = 'bg-secondary';
                                                if ($o['status'] == 'pending') $badge_class = 'bg-warning text-dark';
                                                elseif ($o['status'] == 'selesai') $badge_class = 'bg-success';
                                            ?>
                                            <span class="badge <?= $badge_class ?>"><?php echo htmlspecialchars(ucfirst($o['status'])); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('d M Y H:i', strtotime($o['ordered_at']))); ?></td>
                                        <td>
                                            <?php if ($o['status'] == 'pending'): ?>
                                                <a href="?mark_paid=<?php echo $o['id']; ?>&user_id=<?php echo $selectedUser; ?>" 
                                                   class="btn btn-sm btn-success"
                                                   onclick="return confirm('Tandai pesanan ini sebagai selesai?');">
                                                   Selesai
                                                </a>
                                                <form method="post" action="" style="display:inline;">
                                                    <input type="hidden" name="cancel_order" value="<?php echo $o['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger mt-1 mt-sm-0"
                                                        onclick="return confirm('Hapus pesanan ini? Aksi ini tidak dapat dibatalkan.');">
                                                        Batal
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <nav>
                                <ul class="pagination mt-3">
                                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                        <li class="page-item <?php echo ($p == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?user_id=<?php echo $selectedUser; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
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