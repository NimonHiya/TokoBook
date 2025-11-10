<?php
session_start();
require_once __DIR__ . '/db.php'; // Asumsi $pdo tersedia dari file ini

// --- Global Feature Toggles ---
// **********************************************************
const FEATURE_THEME_TOGGLE = false;    // Menyembunyikan tombol ☀️/🌙
// **********************************************************


// 1. Pengecekan Otentikasi
if (!isset($_SESSION['user'])){ 
    header('Location: login.php'); 
    exit; 
}
$user_id = $_SESSION['user']['id']; // Ambil user ID di sini

// --- Theme Logic (Tanpa Toggle) ---
$is_dark_mode = ($_SESSION['user']['theme_mode'] ?? 0) == 1;
$nav_class = $is_dark_mode ? 'navbar-dark bg-dark' : 'navbar-dark bg-primary'; 


$cart = $_SESSION['cart'] ?? [];
$message = '';
$message_html = '';
$alert_type = 'alert-info';
$pdfCreated = false;

if (empty($cart)) { 
    $message = 'Your cart is currently empty.';
    $alert_type = 'alert-info';
    $message_html = '<div class="text-center"><h4 class="text-secondary">Your cart is currently empty.</h4><p>Please add some books to proceed to checkout.</p></div>';

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $address = trim($_POST['address']);
    $payment = $_POST['payment'];

    $ids = implode(',', array_map('intval', array_keys($cart)));
    
    // Ambil detail buku
    $stmt = $pdo->query("SELECT * FROM books WHERE id IN ($ids)");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;
    foreach($rows as $r){
        $qty = $cart[$r['id']];
        $total += $r['price'] * $qty;
    }

    // Cek keberadaan kolom 'stock' sekali saja (Optimasi!)
    $s = $pdo->prepare('SHOW COLUMNS FROM books LIKE "stock"');
    $s->execute();
    $has_stock_column = $s->fetch(PDO::FETCH_ASSOC);
    
    // Mulai Transaksi Database
    $pdo->beginTransaction();
    try{
        // 1. VALIDASI STOK (Jika kolom 'stock' ada)
        if ($has_stock_column) {
            $stockStmt = $pdo->prepare('SELECT id, stock, title FROM books WHERE id = :id');
            foreach ($rows as $r) {
                $qty = $cart[$r['id']];
                $stockStmt->execute([':id' => $r['id']]);
                $bk = $stockStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($bk && isset($bk['stock']) && intval($bk['stock']) < $qty) {
                    throw new Exception('Insufficient stock for "' . htmlspecialchars($r['title']) . '". Available: ' . intval($bk['stock']) . ', requested: ' . $qty);
                }
            }
        }
        
        // 2. INSERT ORDER dan UPDATE STOCK
        $ins = $pdo->prepare('INSERT INTO orders (user_id,book_id,quantity,total_price,payment_method,shipping_address,status) VALUES (:uid,:bid,:q,:tp,:pm,:sa,:st)');
        $updateStock = $pdo->prepare('UPDATE books SET stock = stock - :qty WHERE id = :id');
        $insertedIds = [];
        
        foreach($rows as $r){
            $qty = $cart[$r['id']];
            
            // Insert Order
            $ins->execute([':uid'=>$user_id,':bid'=>$r['id'],':q'=>$qty,':tp'=>$r['price']*$qty,':pm'=>$payment,':sa'=>$address,':st'=>'pending']);
            $insertedIds[] = $pdo->lastInsertId();

            // Decrement Stock (Jika kolom 'stock' ada)
            if ($has_stock_column) {
                $updateStock->execute([':qty' => $qty, ':id' => $r['id']]);
            }
        }

        $pdo->commit();
        unset($_SESSION['cart']);

        // 3. GENERASI INVOICE HTML
        $invoiceDir = __DIR__ . '/data/invoices';
        if (!is_dir($invoiceDir)) mkdir($invoiceDir, 0755, true);
        $invoiceId = time() . '_' . $user_id;
        
        // Content Invoice (dipertahankan)
        $invoiceHtml = '<!doctype html><html><head><meta charset="utf-8"><title>Invoice ' . $invoiceId . '</title><style>body{font-family:Arial,Helvetica,sans-serif}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ccc;padding:8px}</style></head><body>';
        $invoiceHtml .= '<h2>Invoice #' . $invoiceId . '</h2>';
        $invoiceHtml .= '<p>User: ' . htmlspecialchars($_SESSION['user']['username']) . ' (' . htmlspecialchars($_SESSION['user']['email'] ?? '') . ')</p>';
        $invoiceHtml .= '<p>Shipping address: ' . nl2br(htmlspecialchars($address)) . '</p>';
        $invoiceHtml .= '<p>Payment method: ' . htmlspecialchars($payment) . '</p>';
        $invoiceHtml .= '<table><thead><tr><th>Book</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead><tbody>';
        foreach($rows as $r){
            $qty = $cart[$r['id']];
            $subtotal = $r['price'] * $qty;
            $invoiceHtml .= '<tr><td>' . htmlspecialchars($r['title']) . '</td><td>' . $qty . '</td><td>Rp ' . number_format($r['price'],2) . '</td><td>Rp ' . number_format($subtotal,2) . '</td></tr>';
        }
        $invoiceHtml .= '</tbody></table>';
        $invoiceHtml .= '<p>Total: <strong>Rp ' . number_format($total,2) . '</strong></p>';
        $invoiceHtml .= '<p>Order IDs: ' . implode(', ', $insertedIds) . '</p>';
        $invoiceHtml .= '<p>Generated at: ' . date('Y-m-d H:i:s') . '</p>';
        $invoiceHtml .= '</body></html>';

        $invoiceHtmlPath = $invoiceDir . '/invoice_' . $invoiceId . '.html';
        file_put_contents($invoiceHtmlPath, $invoiceHtml);

        // 4. GENERASI INVOICE PDF (Dompdf)
        $pdfPath = $invoiceDir . '/invoice_' . $invoiceId . '.pdf';
        if (file_exists(__DIR__ . '/vendor/autoload.php')){
            require_once __DIR__ . '/vendor/autoload.php';
            $domClass = '\\Dompdf\\Dompdf';
            if (class_exists($domClass)){
                $dompdf = new $domClass();
                $dompdf->loadHtml($invoiceHtml);
                $dompdf->setPaper('A4','portrait');
                $dompdf->render();
                file_put_contents($pdfPath, $dompdf->output());
                $pdfCreated = true;
            }
        }

        // 5. PENYESUAIAN UI UNTUK SUKSES
        $message = 'Order placed successfully.';
        $alert_type = 'alert-success';

        // prepare user message with download buttons (UI IMPROVEMENT)
        $btnHtml = '<div class="d-flex gap-2 justify-content-center mt-4">';
        $btnHtml .= '<a class="btn btn-outline-secondary btn-sm" href="data/invoices/invoice_' . $invoiceId . '.html" target="_blank">Download HTML</a>';
        if ($pdfCreated) {
            // Tombol PDF dibuat menonjol
            $btnHtml .= '<a class="btn btn-success btn-lg" href="data/invoices/invoice_' . $invoiceId . '.pdf" target="_blank">Download PDF Invoice</a>';
        } else {
            $btnHtml .= '<button class="btn btn-secondary btn-lg" disabled>PDF not generated</button>';
        }
        $btnHtml .= '</div>';
        
        // Ikon Sukses
        $success_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-check-circle-fill text-success mb-3" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.92 10.92a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>';

        $message_html = '<div class="text-center">' . $success_icon . '<h3 class="mb-2">Order Success!</h3><p class="text-muted">' . htmlspecialchars($message) . '</p>' . $btnHtml . '</div>';

    } catch (Exception $e){ 
        $pdo->rollBack(); 
        $message = 'Error: ' . htmlspecialchars($e->getMessage());
        $alert_type = 'alert-danger';
        
        // PENYESUAIAN UI UNTUK ERROR
        $error_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-x-octagon-fill me-2" viewBox="0 0 16 16"><path d="M11.46.146A.5.5 0 0 0 11.107 0H4.893a.5.5 0 0 0-.353.146L.146 4.54A.5.5 0 0 0 0 4.893v6.214a.5.5 0 0 0 .146.353l4.394 4.394a.5.5 0 0 0 .353.146h6.214a.5.5 0 0 0 .353-.146l4.394-4.394a.5.5 0 0 0 .146-.353V4.893a.5.5 0 0 0-.146-.353L11.46.146zm-6.106 4.5a.5.5 0 1 1 .708.708L7.99 7.99l2.427-2.427a.5.5 0 1 1 .708.708L8.706 8.7a.5.5 0 0 1-.708 0L5.354 5.207a.5.5 0 0 1 0-.708z"/></svg>';
        $message_html = '<div class="d-flex align-items-center mb-3"><h4 class="m-0 text-danger">' . $error_icon . 'Transaction Failed!</h4></div><p class="text-danger">' . $message . '</p>';
    }
} else {
    // Redirect ke cart.php jika tidak ada POST
    header('Location: cart.php'); 
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/logo.jpg" type="image/jpeg">
    <link rel="shortcut icon" href="assets/logo.jpg" type="image/jpeg">
    <title>TokoBook - Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-brand { font-weight: bold; }
        /* CSS Tambahan untuk Tampilan Sukses */
        .success-card {
            border: 2px solid var(--bs-success);
            background-color: #f6fff6; /* Warna latar belakang sangat lembut */
        }
    </style>
</head>
<body>
<header>
    <nav class="navbar navbar-expand-lg <?= $nav_class ?> shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="/TokoBook/index.php">TokoBook</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                    <?php if (isset($_SESSION['user']) && $_SESSION['user']['role']==='admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="admin/dashboard.php">Admin</a></li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-light btn-sm ms-2" href="logout.php">
                            Logout (<?php echo htmlspecialchars($_SESSION['user']['username']); ?>)
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main class="container my-5">
    <div class="row">
        <div class="col-md-9 col-lg-7 mx-auto">
            <h2 class="mb-4 text-center">Checkout Status</h2>
            
            <?php if ($alert_type === 'alert-success'): ?>
                <div class="card shadow-lg p-5 success-card border-success">
                    <?php echo $message_html; // Sudah termasuk ikon, pesan, dan tombol ?>
                    
                    <div class="text-center mt-5 pt-4 border-top">
                        <a href="index.php" class="btn btn-primary">Continue Shopping</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm p-4">
                    <div class="alert <?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message_html ?? htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    
                    <div class="text-center mt-4">
                        <?php if ($alert_type === 'alert-danger'): ?>
                            <a href="cart.php" class="btn btn-secondary">Go Back to Cart</a>
                        <?php else: ?>
                            <a href="index.php" class="btn btn-primary">Back to Shop</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</main>

<footer class="bg-dark text-white py-3 text-center mt-5">
    <div class="container">
        <p class="m-0">&copy; <?php echo date('Y'); ?> TokoBook. All Rights Reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>