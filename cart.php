<?php
session_start();
require_once __DIR__ . '/db.php';

// --- Global Feature Toggles ---
// **********************************************************
const FEATURE_THEME_TOGGLE = false;    // Menyembunyikan tombol ☀️/🌙
// **********************************************************


// --- Dark mode detection & Toggle Logic ---
$is_logged_in = isset($_SESSION['user']); // Sudah pasti login di halaman ini, tapi kita jaga
$user_id = $_SESSION['user']['id'];
$current_db_mode = $_SESSION['user']['theme_mode'] ?? 0;

// LOGIKA PHP UNTUK TOGGLE DIHAPUS JIKA FITUR DIMATIKAN
if (FEATURE_THEME_TOGGLE && isset($_GET['toggle_theme']) && $_GET['toggle_theme'] === '1') {
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


// --- Cart Processing Logic ---
if ($_SERVER['REQUEST_METHOD']==='POST'){
    $book_id = intval($_POST['book_id']);
    $requested = max(1,intval($_POST['qty']));
    
    // check stock
    $sstmt = $pdo->prepare('SELECT stock FROM books WHERE id=:id');
    $sstmt->execute([':id'=>$book_id]);
    $avail = (int)$sstmt->fetchColumn();
    $qty = min($requested, max(0, $avail));
    
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    
    if ($qty<=0) {
        // nothing to add
    } else {
        if (isset($_SESSION['cart'][$book_id])) $_SESSION['cart'][$book_id] += $qty; else $_SESSION['cart'][$book_id] = $qty;
        // ensure we don't exceed stock overall
        if ($_SESSION['cart'][$book_id] > $avail) $_SESSION['cart'][$book_id] = $avail;
    }
    header('Location: cart.php'); 
    exit;
}

$cart = $_SESSION['cart'] ?? [];
$items = [];
$total = 0;
if ($cart){
    $ids = implode(',', array_map('intval', array_keys($cart)));
    $stmt = $pdo->query("SELECT * FROM books WHERE id IN ($ids)");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($rows as $r){
        $requestedQty = $cart[$r['id']];
        $avail = (int)$r['stock'];
        
        // clamp quantity to available stock
        if ($requestedQty > $avail){
            $r['qty'] = $avail;
            // update session to reflect available stock
            $_SESSION['cart'][$r['id']] = $avail;
            $r['stock_exceeded'] = true;
        } else {
            $r['qty'] = $requestedQty;
            $r['stock_exceeded'] = false;
        }
        $r['subtotal'] = $r['qty'] * $r['price'];
        $total += $r['subtotal'];
        $items[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/logo.jpg" type="image/jpeg">
    <title>TokoBook - Keranjang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* CSS Konsisten */
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
        
        /* Form & Input Konsisten */
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
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="cart.php">Cart</a></li>
                    <li class="nav-item"><a class="nav-link" href="my_orders.php">Pesanan Saya</a></li>
                    <?php if (isset($_SESSION['user']) && $_SESSION['user']['role']==='admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="admin/dashboard.php">Admin</a></li>
                    <?php endif; ?>
                    
                    <?php if (FEATURE_THEME_TOGGLE): ?>
                    <li class="nav-item">
                        <a href="?toggle_theme=1" class="nav-link toggle-btn" title="Toggle Dark/Light Mode">
                            <?= $is_dark_mode ? '☀️' : '🌙' ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['user']['username']); ?>)</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main class="container my-5">
    <div class="row">
        <div class="col-md-10 mx-auto">
            <h2 class="mb-4 text-primary">Keranjang Belanja Anda 🛒</h2>
            
            <?php if(empty($items)): ?>
                <div class="alert alert-info text-center" role="alert">
                    Keranjang masih kosong. <a href="index.php" class="alert-link">Mulai belanja sekarang</a>!
                </div>
            <?php else: ?>
                <div class="card shadow-lg">
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover <?= $is_dark_mode ? 'table-dark' : '' ?>">
                                <thead>
                                    <tr>
                                        <th scope="col">Judul Buku</th>
                                        <th scope="col" class="text-center">Kuantitas</th>
                                        <th scope="col" class="text-end">Harga Satuan</th>
                                        <th scope="col" class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($items as $i): ?>
                                        <tr class="<?= $i['stock_exceeded'] ? 'table-warning' : '' ?>">
                                            <td>
                                                <a href="book_detail.php?id=<?= $i['id']; ?>" class="text-decoration-none <?= $is_dark_mode ? 'text-light' : 'text-dark' ?>">
                                                    <?php echo htmlspecialchars($i['title']); ?>
                                                </a>
                                                <?php if ($i['stock_exceeded']): ?>
                                                    <span class="badge bg-danger ms-2">Stock Habis! (Hanya <?= $i['qty']; ?> tersedia)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><?php echo $i['qty']; ?></td>
                                            <td class="text-end">Rp <?php echo number_format($i['price'], 2, ',', '.'); ?></td>
                                            <td class="text-end">Rp **<?php echo number_format($i['subtotal'], 2, ',', '.'); ?>**</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-end mt-4">
                            <a href="cart_clear.php" onclick="return confirm('Apakah Anda yakin ingin mengosongkan keranjang?')" class="btn btn-sm btn-outline-danger me-3">
                                Kosongkan Keranjang
                            </a>
                            <h5 class="d-inline-block">Total Keseluruhan: <span class="text-success">Rp **<?php echo number_format($total, 2, ',', '.'); ?>**</span></h5>
                        </div>

                        <form method="post" action="checkout.php" class="mt-4">
                            <h4 class="mb-3 text-secondary">Detail Pengiriman</h4>
                            <div class="mb-3">
                                <label for="address" class="form-label">Alamat Pengiriman Lengkap</label>
                                <textarea class="form-control" id="address" name="address" rows="4" required></textarea>
                            </div>
                            <div class="mb-4">
                                <label for="payment" class="form-label">Metode Pembayaran</label>
                                <select class="form-select" id="payment" name="payment">
                                    <option value="Bank Transfer">Transfer Bank</option>
                                    <option value="COD">Bayar di Tempat (COD)</option>
                                </select>
                            </div>
                            
                            <?php 
                            // Asumsi Anda akan memasukkan file csrf.php atau memiliki fungsi di tempat lain
                            if (function_exists('csrf_input_field')) { echo csrf_input_field(); } 
                            ?>
                            
                            <button type="submit" class="btn btn-primary w-100 btn-lg">PROSES CHECKOUT</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-outline-secondary">Lanjut Belanja</a>
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