<?php
session_start();
require_once __DIR__ . '/db.php';

// --- Global Feature Toggles (AKTIFKAN FITUR DI SINI) ---
// **********************************************************
const FEATURE_THEME_TOGGLE = FALSE;     // <-- UBAH FALSE MENJADI TRUE
// **********************************************************


// -------------------------
// CEK LOGIN DAN STATUS THEME
// -------------------------
$is_logged_in = isset($_SESSION['user']);
if ($is_logged_in) {
    $is_dark_mode = ($_SESSION['user']['theme_mode'] ?? 0) == 1;
} else {
    $is_dark_mode = ($_COOKIE['theme'] ?? 'light') === 'dark';
}

// -------------------------
// TOGGLE THEME MODE (Hanya jika FEATURE_THEME_TOGGLE = true)
// -------------------------
if (FEATURE_THEME_TOGGLE && isset($_GET['toggle_theme']) && $_GET['toggle_theme'] === '1') {
    if ($is_logged_in) {
        $user_id = $_SESSION['user']['id'];
        $current_db_mode = $_SESSION['user']['theme_mode'] ?? 0;
        $new_db_mode = $current_db_mode == 0 ? 1 : 0;

        // Update DB
        $update_stmt = $pdo->prepare("UPDATE users SET theme_mode = ? WHERE id = ?");
        $update_stmt->execute([$new_db_mode, $user_id]);

        // Update sesi agar langsung berubah
        $_SESSION['user']['theme_mode'] = $new_db_mode;

    } else {
        // Toggle cookie
        if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
            setcookie('theme', 'light', time() + (86400 * 30), '/');
        } else {
            setcookie('theme', 'dark', time() + (86400 * 30), '/');
        }
    }

    // Redirect agar tema langsung diterapkan
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ==========================
// CONTACT FORM LOGIC
// ==========================
$msg = '';
$msg_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message']);
    $user_id = isset($_SESSION['user']) ? $_SESSION['user']['id'] : null;
    
    if ($message) {
        try {
            $stmt = $pdo->prepare('INSERT INTO contacts (user_id, message) VALUES (:uid, :m)');
            $stmt->execute([':uid' => $user_id, ':m' => $message]);
            
            $msg = 'Pesan Anda berhasil dikirim ke Admin. Terima kasih atas masukan Anda!';
            $msg_type = 'success';
        } catch (PDOException $e) {
            $msg = 'Terjadi kesalahan saat mengirim pesan. Mohon coba lagi.';
            $msg_type = 'danger';
        }
    } else {
        $msg = 'Pesan tidak boleh kosong.';
        $msg_type = 'warning';
    }
}

// ==========================
// FETCH MESSAGE HISTORY (TERMASUK BALASAN)
// ==========================
$history = [];
if ($is_logged_in) {
    $user_id = $_SESSION['user']['id'];
    $historyStmt = $pdo->prepare("SELECT id, message, sent_at, reply_message, replied_at FROM contacts WHERE user_id = ? ORDER BY sent_at DESC");
    $historyStmt->execute([$user_id]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/logo.jpg" type="image/jpeg">
    <title>TokoBook - Hubungi Kami</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* CSS umum untuk transisi smooth */
        body { transition: background-color 0.3s, color 0.3s; }
        body { background-color: white; color: black; }
        
        /* Dark Mode Specific Styles */
        body.dark-mode { background-color: #121212; color: white; }
        body.dark-mode .card,
        body.dark-mode .form-control,
        body.dark-mode .alert {
            background-color: #1e1e1e; color: white; border-color: #333;
        }
        
        /* Memastikan INPUT/TEXTAREA terlihat jelas di Dark Mode */
        body.dark-mode .form-control {
            background-color: #1e1e1e; 
            color: white; /* Teks yang diketik */
            border-color: #333;
        }
        body.dark-mode .form-control:focus {
            background-color: #1e1e1e; 
            color: white; 
            border-color: #0d6efd; 
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        body.dark-mode .form-control::placeholder {
            color: #bbb; /* Teks Placeholder */
            opacity: 1; 
        }
        /* End Input Fix */
        
        .toggle-btn { border: none; background-color: transparent; color: inherit; font-weight: bold; cursor: pointer; padding: .5rem 1rem; text-decoration: none; }

        /* Reply/History Styles */
        .ticket-box {
            border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 20px; background-color: #fcfcfc;
        }
        body.dark-mode .ticket-box { border-color: #333; background-color: #2a2a2a; }
        .user-message { padding: 10px; border-radius: 5px; background-color: #e9ecef; color: #333; }
        
        /* DARK MODE TICKET TEXT FIX */
        body.dark-mode .user-message { background-color: #1e1e1e; color: #f5f5f5; }
        body.dark-mode .ticket-box .text-muted { color: #bbb !important; }
        body.dark-mode .user-message strong { color: #f5f5f5 !important; }
        
        body.dark-mode .admin-reply { background-color: #1c3d3a; color: #d1e7dd; border-color: #198754; }
        body.dark-mode .admin-reply strong { color: #d1e7dd !important; }
        body.dark-mode .alert-warning { background-color: #2a2a2a; color: #ffc107; border-color: #ffc107; }
        
        /* Admin Reply Colors (Light Mode) */
        .admin-reply { margin-top: 15px; padding: 10px; border-left: 4px solid #198754; background-color: #d4edda; color: #155724; border-radius: 0 5px 5px 0; }
    </style>
</head>
<body class="<?= $is_dark_mode ? 'dark-mode' : '' ?>">

<header>
    <nav class="navbar navbar-expand-lg <?= $is_dark_mode ? 'navbar-dark bg-dark' : 'navbar-dark bg-primary' ?>">
        <div class="container">
            <a class="navbar-brand" href="/TokoBook/index.php">TokoBook</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link active" href="contact.php">Contact</a></li>
                    <?php if (isset($_SESSION['user'])): ?>
                        <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
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
                        <a href="?toggle_theme=1" class="nav-link toggle-btn" title="Toggle Dark/Light Mode">
                            <?= $is_dark_mode ? '☀️' : '🌙' ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main class="container my-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <h2 class="mb-4 text-center text-primary">Hubungi Kami 👋</h2>
            
            <?php if ($msg): ?>
                <div class="alert alert-<?= $msg_type ?> <?= $is_dark_mode && $msg_type !== 'success' ? 'text-white' : '' ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($msg); ?>
                    <button type="button" class="btn-close <?= $is_dark_mode ? 'btn-close-white' : '' ?>" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!$is_logged_in): ?>
                <div class="alert alert-warning text-center">
                    Anda tidak login. Pesan akan dikirim sebagai Tamu.
                    <a href="login.php" class="alert-link">Login untuk melihat riwayat tiket.</a>
                </div>
            <?php endif; ?>
            
            <?php if ($is_logged_in): ?>
                <h4 class="mb-3 text-secondary">Riwayat Tiket Anda</h4>
                <?php if (empty($history)): ?>
                    <div class="alert alert-info text-center">Anda belum pernah mengirim tiket.</div>
                <?php else: ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($history as $ticket): ?>
                            <div class="ticket-box">
                                <small class="text-muted d-block mb-2">Tiket #<?= $ticket['id']; ?> - Dikirim: <?= date('d M Y H:i', strtotime($ticket['sent_at'])); ?></small>
                                
                                <div class="user-message">
                                    <strong class="text-dark">Pesan Anda:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
                                </div>
                                
                                <?php if (isset($ticket['reply_message']) && $ticket['reply_message']): ?>
                                    <div class="admin-reply">
                                        <strong class="text-dark">Balasan Admin:</strong> <span class="float-end small text-muted">(Dibalas: <?= date('d M Y H:i', strtotime($ticket['replied_at'])); ?>)</span><br>
                                        <?php echo nl2br(htmlspecialchars($ticket['reply_message'])); ?>
                                    </div>
                                <? else: ?>
                                    <div class="alert alert-warning small mt-2 mb-0">Menunggu balasan dari tim support...</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <hr>
            <?php endif; ?>

            <div class="card p-4 shadow-sm">
                <h4 class="mb-3 text-primary">Kirim Tiket Baru</h4>
                <form method="post">
                    <div class="mb-3">
                        <label for="message" class="form-label">Tulis Pesan Anda</label>
                        <textarea class="form-control" id="message" name="message" rows="5" placeholder="Sampaikan saran, kritik, atau pertanyaan Anda di sini..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Kirim Pesan</button>
                </form>
            </div>

            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-outline-secondary">Kembali ke Beranda</a>
            </div>
        </div>
    </div>
</main>

<footer class="py-3 mt-5 <?= $is_dark_mode ? 'bg-dark text-light' : 'bg-light text-dark' ?>">
    <div class="container text-center">
        <p class="mb-0">&copy; <?= date('Y') ?> TokoBook</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>