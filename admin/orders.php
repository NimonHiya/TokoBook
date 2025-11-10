<?php
session_start(); 
require_once __DIR__.'/../db.php';

// --- Global Feature Toggles ---
// **********************************************************
const FEATURE_THEME_TOGGLE = false;    // Menyembunyikan tombol ☀️/🌙
const FEATURE_SEARCH_FILTER = false;   // Menyembunyikan form pencarian/filter
const FEATURE_ORDER_TRACKING = false;  // Menyembunyikan modal pengiriman dan tampilan resi
// **********************************************************


// --- Login Check & Theme Detection ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role']!=='admin') { 
    header('Location: ../login.php'); 
    exit; 
}
$user_id = $_SESSION['user']['id'];
$is_logged_in = true; // Sudah pasti admin

// --- Dark mode detection & Toggle Logic (KONDISIONAL) ---
$current_db_mode = $_SESSION['user']['theme_mode'] ?? 0;

if (FEATURE_THEME_TOGGLE && isset($_GET['toggle_theme']) && $_GET['toggle_theme'] === '1') {
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


// ==== PROSES UPDATE STATUS / CANCEL ORDER (FLOW LOGIS) ====
// Catatan: Logika PHP ini tetap dijalankan di background, tetapi pemicunya (POST/GET) 
// akan dikontrol oleh FEATURE_ORDER_TRACKING.

// Mark Shipped (POST dengan Catatan)
if (isset($_POST['action']) && $_POST['action'] === 'mark_shipped_with_note') { 
    if (FEATURE_ORDER_TRACKING) { // Hanya proses jika fitur aktif
        $orderId = (int)$_POST['order_id'];
        $shippingNote = htmlspecialchars($_POST['tracking_number']) . " | " . htmlspecialchars($_POST['shipping_note'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE orders SET status = 'shipped', shipping_note = ? WHERE id = ?");
        $stmt->execute([$shippingNote, $orderId]);
        header("Location: orders.php?user_id=" . ($_POST['current_user_id'] ?? '') . "&success=shipped");
        exit;
    }
}

// Mark Complete: Dipanggil ketika statusnya DITERIMA
if (isset($_GET['mark_complete'])) { 
    $orderId = (int)$_GET['mark_complete'];
    $pdo->prepare("UPDATE orders SET status = 'selesai', completed_at = NOW() WHERE id = ?")->execute([$orderId]);
    header("Location: orders.php?user_id=" . ($_GET['user_id'] ?? '') . "&success=complete");
    exit;
}

// Mark Paid (Jika admin ingin memaksa status paid)
if (isset($_GET['mark_paid'])) { 
    $orderId = (int)$_GET['mark_paid'];
    $pdo->prepare("UPDATE orders SET status = 'paid', payment_date = NOW() WHERE id = ?")->execute([$orderId]);
    header("Location: orders.php?user_id=" . ($_GET['user_id'] ?? '') . "&success=paid");
    exit;
}

// Cancel Order (DELETE)
if (isset($_POST['cancel_order'])) {
    $orderId = (int)$_POST['cancel_order'];
    $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]); 
    header("Location: orders.php?user_id=" . ($_POST['current_user_id'] ?? '') . "&success=cancel");
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

// ==== INPUT FILTER & PAGINATION (KONDISIONAL) ====
$perPage = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$status_filter = '';
$search_query = '';
$where = ['o.user_id = ?'];
$params = [$selectedUser];

if (FEATURE_SEARCH_FILTER) { // Jika filtering aktif
    $status_filter = isset($_GET['status']) ? $_GET['status'] : ''; 
    $search_query = isset($_GET['q']) ? trim($_GET['q']) : ''; 
    
    if (!empty($status_filter)) {
        $where[] = 'o.status = ?';
        $params[] = $status_filter;
    }
    if (!empty($search_query)) {
        $where[] = 'b.title LIKE ?';
        $params[] = '%' . $search_query . '%';
    }
}

$whereSql = ' WHERE ' . implode(' AND ', $where);

// Hitung total pesanan user terpilih dengan filter
$totalOrdersSql = "SELECT COUNT(*) FROM orders o JOIN books b ON o.book_id = b.id" . $whereSql;
$totalOrdersStmt = $pdo->prepare($totalOrdersSql);
$totalOrdersStmt->execute($params);
$totalOrders = $totalOrdersStmt->fetchColumn();
$totalPages = ceil($totalOrders / $perPage);


// Ambil pesanan user terpilih dengan filter dan pagination
$ordersSql = "
    SELECT o.*, b.title, u.username 
    FROM orders o 
    JOIN books b ON o.book_id = b.id
    JOIN users u ON o.user_id = u.id
    " . $whereSql . "
    ORDER BY o.ordered_at DESC
    LIMIT $perPage OFFSET $offset
";
$ordersStmt = $pdo->prepare($ordersSql);
$ordersStmt->execute($params);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

// Mendapatkan Query String Dasar untuk Pagination
$currentUrlParams = array_filter($_GET, fn($key) => $key !== 'page' && $key !== 'toggle_theme', ARRAY_FILTER_USE_KEY);
$queryString = http_build_query($currentUrlParams);
$baseHref = strtok($_SERVER['PHP_SELF'], '?');
$separator = $queryString ? '&' : '?';

// Daftar status untuk dropdown
$statusList = [
    '' => 'Semua Status',
    'pending' => 'Pending (Menunggu Bayar)',
    'paid' => 'Paid (Siap Kirim)',
    'shipped' => 'Shipped (Dikirim)',
    'diterima' => 'Diterima Pelanggan',
    'selesai' => 'Selesai (Complete)',
    'cancelled' => 'Dibatalkan',
];
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
        background-color: <?= $sidebar_color; ?>; 
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
        display: block; 
        padding: 0.5rem 1rem;
        text-decoration: none;
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
    body.dark-mode .badge.bg-warning,
    body.dark-mode .badge.bg-info,
    body.dark-mode .badge.bg-primary {
        color: #121212 !important;
    }
    
    /* Modal Form Controls Dark Mode */
    body.dark-mode .modal-content .form-control {
        background-color: #383838;
        color: #f5f5f5;
        border-color: #444;
    }
    body.dark-mode .form-label {
        color: #f5f5f5;
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
                    
                    <?php if (FEATURE_THEME_TOGGLE): ?>
                    <li class="nav-item">
                        <a href="?toggle_theme=1" class="nav-link toggle-btn" title="Toggle Dark/Light Mode">
                            <?= $is_dark_mode ? '☀️' : '🌙' ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    
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

    <main class="content flex-grow-1 p-3">
        <div class="container-fluid">
            <h2 class="mb-4 text-primary">Kelola Pesanan 🧾</h2>

            <?php if (isset($_GET['success'])): ?>
                <?php if ($_GET['success'] === 'paid'): ?>
                    <div class="alert alert-primary border-0">Pesanan ditandai sebagai **PAID**. Siap dikirim!</div>
                <?php elseif ($_GET['success'] === 'shipped'): ?>
                    <div class="alert alert-info border-0">Pesanan ditandai sebagai **DIKIRIM** dengan catatan.</div>
                <?php elseif ($_GET['success'] === 'complete'): ?>
                    <div class="alert alert-success border-0">Pesanan ditandai sebagai **SELESAI**.</div>
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
                        <span class="text-primary">
                            <?php 
                                $currentUser = array_filter($users, fn($usr) => $usr['id'] == $selectedUser);
                                echo htmlspecialchars(array_values($currentUser)[0]['username'] ?? 'Pilih Pengguna');
                            ?>
                        </span> 
                        (Total: <?php echo $totalOrders; ?>)
                    </h5>
                    
                    <?php if (FEATURE_SEARCH_FILTER): ?>
                    <form method="get" class="row g-2 mb-4 align-items-center">
                        <input type="hidden" name="user_id" value="<?= $selectedUser; ?>">
                        
                        <div class="col-sm-5">
                            <input type="text" name="q" class="form-control" placeholder="Cari Judul Buku..." value="<?= htmlspecialchars($search_query); ?>">
                        </div>
                        
                        <div class="col-sm-4">
                            <select name="status" class="form-select">
                                <?php foreach ($statusList as $key => $name): ?>
                                    <option value="<?= $key; ?>" <?= $status_filter === $key ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-sm-3">
                            <button type="submit" class="btn btn-secondary w-100">Filter/Cari</button>
                        </div>
                        
                        <?php if ($status_filter || $search_query): ?>
                            <div class="col-12">
                                <a href="?user_id=<?= $selectedUser; ?>" class="btn btn-sm btn-outline-danger">Reset Filter</a>
                            </div>
                        <?php endif; ?>
                    </form>
                    <?php endif; // END FEATURE_SEARCH_FILTER ?>


                    <?php if (empty($orders)): ?>
                        <div class="alert alert-info mt-3 border-0">Tidak ada pesanan ditemukan untuk kriteria ini.</div>
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
                                        <th style="min-width: 260px;">Aksi</th>
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
                                                // LOGIKA BADGE DENGAN STATUS BARU: diterima
                                                $badge_class = 'bg-secondary';
                                                if ($o['status'] == 'pending') $badge_class = 'bg-warning text-dark';
                                                elseif ($o['status'] == 'paid') $badge_class = 'bg-primary'; 
                                                elseif ($o['status'] == 'shipped') $badge_class = 'bg-info text-dark'; 
                                                elseif ($o['status'] == 'diterima') $badge_class = 'bg-success'; // NEW STATUS
                                                elseif ($o['status'] == 'selesai') $badge_class = 'bg-success';
                                                elseif ($o['status'] == 'cancelled') $badge_class = 'bg-danger';
                                            ?>
                                            <span class="badge <?= $badge_class ?>"
                                                  title="<?= (FEATURE_ORDER_TRACKING && $o['status'] == 'shipped' && $o['shipping_note']) ? htmlspecialchars($o['shipping_note']) : ''; ?>"
                                                  data-bs-toggle="<?= (FEATURE_ORDER_TRACKING && $o['status'] == 'shipped' && $o['shipping_note']) ? 'tooltip' : ''; ?>"
                                                  data-bs-placement="top">
                                                <?php echo htmlspecialchars(ucfirst($o['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('d M Y H:i', strtotime($o['ordered_at']))); ?></td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                
                                                <?php if ($o['status'] == 'pending'): ?>
                                                    <span class="text-muted small">Menunggu Pembayaran User</span>
                                                    
                                                <?php elseif ($o['status'] == 'paid'): ?>
                                                    <?php if (FEATURE_ORDER_TRACKING): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-info text-dark"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#shippingModal"
                                                            data-order-id="<?= $o['id']; ?>"
                                                            data-user-id="<?= $selectedUser; ?>"
                                                            title="Masukkan nomor resi dan catatan">
                                                        Kirim
                                                    </button>
                                                    <?php else: ?>
                                                    <a href="?mark_shipped=<?php echo $o['id']; ?>&user_id=<?php echo $selectedUser; ?>" 
                                                       class="btn btn-sm btn-info text-dark"
                                                       onclick="return confirm('Tandai pesanan ini sebagai **DIKIRIM**?');">
                                                        Kirim
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                <?php elseif ($o['status'] == 'diterima'): ?>
                                                    <a href="?mark_complete=<?php echo $o['id']; ?>&user_id=<?php echo $selectedUser; ?>" 
                                                       class="btn btn-sm btn-success"
                                                       onclick="return confirm('Tandai pesanan ini sebagai **SELESAI** (Final)?');">
                                                        Selesai
                                                    </a>

                                                <?php elseif ($o['status'] == 'shipped'): ?>
                                                    <span class="text-info small">Menunggu Diterima Pelanggan</span>
                                                
                                                <?php endif; ?>
                                                
                                                <?php if ($o['status'] != 'selesai' && $o['status'] != 'cancelled'): ?>
                                                    <form method="post" action="" style="display:inline;">
                                                        <input type="hidden" name="cancel_order" value="<?php echo $o['id']; ?>">
                                                        <input type="hidden" name="current_user_id" value="<?php echo $selectedUser; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger"
                                                                onclick="return confirm('Hapus pesanan ini? Aksi ini tidak dapat dibatalkan.');">
                                                            Batal/Hapus
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($totalPages > 1): ?>
                            <nav>
                                <ul class="pagination mt-3">
                                    <?php 
                                    // Pagination links
                                    for ($p = 1; $p <= $totalPages; $p++): 
                                        $url = $baseHref . ($queryString ? '?' . $queryString : '?') . $separator . 'page=' . $p;
                                    ?>
                                        <li class="page-item <?php echo ($p == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?= $url; ?>"><?= $p; ?></a>
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

<?php if (FEATURE_ORDER_TRACKING): ?>
<div class="modal fade" id="shippingModal" tabindex="-1" aria-labelledby="shippingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content <?= $is_dark_mode ? 'bg-dark text-light' : 'bg-light text-dark' ?>">
            <div class="modal-header">
                <h5 class="modal-title" id="shippingModalLabel">Konfirmasi Pengiriman Pesanan</h5>
                <button type="button" class="btn-close btn-close-<?= $is_dark_mode ? 'white' : 'dark' ?>" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="shippingForm" method="POST" action="orders.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="mark_shipped_with_note">
                    <input type="hidden" name="order_id" id="modal-order-id">
                    <input type="hidden" name="current_user_id" value="<?= $selectedUser; ?>">

                    <div class="mb-3">
                        <label for="tracking_number" class="form-label">Nomor Resi / Kurir:</label>
                        <input type="text" class="form-control" id="tracking_number" name="tracking_number" placeholder="Contoh: JNE: JP12345678" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="shipping_note" class="form-label">Catatan Pengiriman (Opsional):</label>
                        <textarea class="form-control" id="shipping_note" name="shipping_note" rows="3" placeholder="Contoh: Barang sudah dipacking tebal."></textarea>
                    </div>
                    <div class="alert alert-warning small">
                        Pastikan data benar. Status pesanan akan diubah menjadi **DIKIRIM**.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info text-dark">Konfirmasi Kirim</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const shippingModal = document.getElementById('shippingModal');
        shippingModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const orderId = button.getAttribute('data-order-id');
            const userId = button.getAttribute('data-user-id');
            
            const modalOrderIdInput = shippingModal.querySelector('#modal-order-id');
            const modalCurrentUserIdInput = shippingModal.querySelector('input[name="current_user_id"]'); 
            
            modalOrderIdInput.value = orderId;
            modalCurrentUserIdInput.value = userId; 
        });
        
        // Inisialisasi Tooltips 
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
</script>
<?php endif; // END FEATURE_ORDER_TRACKING ?>

<footer class="py-3 mt-5 <?= $is_dark_mode ? 'bg-dark text-light' : 'bg-light text-dark' ?>">
    <div class="container text-center">
        <p>&copy; <?php echo date('Y'); ?> TokoBook</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>