<?php
session_start();
require_once __DIR__ . '/db.php';

// --- Dark mode detection & Toggle Logic ---
$is_logged_in_before_login = isset($_SESSION['user']); // Status login saat ini

if (isset($_GET['toggle_theme']) && $_GET['toggle_theme'] === '1') {
    if ($is_logged_in_before_login) {
        // Logic ini tidak akan terjadi di halaman login, tapi kita jaga
        $user_id = $_SESSION['user']['id'];
        $current_db_mode = $_SESSION['user']['theme_mode'] ?? 0;
        $new_db_mode = ($current_db_mode == 0) ? 1 : 0; 
        
        if (isset($pdo)) {
            $update_stmt = $pdo->prepare("UPDATE users SET theme_mode = ? WHERE id = ?");
            $update_stmt->execute([$new_db_mode, $user_id]);
            $_SESSION['user']['theme_mode'] = $new_db_mode;
        }
    } else {
        // Logic untuk pengguna anonim (cookie)
        if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
            setcookie('theme', 'light', time() + (86400 * 30), '/');
        } else {
            setcookie('theme', 'dark', time() + (86400 * 30), '/');
        }
    }
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Re-check theme after potential toggle
if (isset($_SESSION['user'])) {
    $is_dark_mode = ($_SESSION['user']['theme_mode'] ?? 0) == 1;
} else {
    $is_dark_mode = ($_COOKIE['theme'] ?? 'light') === 'dark';
}
$theme = $is_dark_mode ? 'dark' : 'light';


// --- Login Logic ---
$err = '';
$msg = '';
$email_input = ''; // Untuk menjaga email tetap di kolom input
if (isset($_GET['msg'])) {
    $msg = htmlspecialchars($_GET['msg']); // Untuk pesan dari register.php
}

if ($_SERVER['REQUEST_METHOD']==='POST'){
    $email = trim($_POST['email']);
    $email_input = $email; 
    $password = $_POST['password'];
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email=:e');
    $stmt->execute([':e'=>$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])){
        // Amankan sesi
        unset($user['password']);
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        
        // Redirect setelah login berhasil
        header('Location: index.php'); exit;
    } else {
        $err = 'Kredensial tidak valid. Email atau kata sandi salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/logo.jpg" type="image/jpeg">
    <title>TokoBook - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* CSS Konsisten */
        body { transition: background-color 0.3s, color 0.3s; }
        .navbar-brand { font-weight: bold; }
        .card { border: none; border-radius: 10px; transition: all 0.3s ease; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.2); }
        .form-control { border-radius: 8px; padding: 10px; }
        .form-control:focus { box-shadow: 0 0 5px rgba(13,110,253,0.4); border-color: #0d6efd; }
        .btn-primary { border-radius: 8px; padding: 10px; font-weight: 600; }
        a.text-primary { text-decoration: none; font-weight: 500; }
        a.text-primary:hover { text-decoration: underline; }
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
        
        /* Input Konsisten */
        body.dark-mode .form-control { 
            background-color: #2b2b2b; 
            color: #f1f1f1; 
            border: 1px solid #444; 
        }
        body.dark-mode .form-control:focus { 
            background-color: #222; 
            border-color: #0d6efd; 
        }

        /* Teks Link di dark mode */
        body.dark-mode a.text-primary { color: #66b3ff !important; }

        /* Alert di dark mode */
        body.dark-mode .alert-danger {
            background-color: #491d1e;
            color: #f8d7da;
            border-color: #58151c;
        }
        body.dark-mode .alert-danger .btn-close {
            filter: invert(1);
        }
        body.dark-mode .alert-success {
            background-color: #1c3d3a;
            color: #d1e7dd;
            border-color: #1a473b;
        }
        body.dark-mode .alert-success .btn-close {
            filter: invert(1);
        }

    </style>
</head>
<body class="<?= $theme === 'dark' ? 'dark-mode' : '' ?>">
<header>
    <nav class="navbar navbar-expand-lg <?= $is_dark_mode ? 'navbar-dark bg-dark' : 'navbar-dark bg-primary' ?>">
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
                    <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="login.php">Login</a></li>
                    <li class="nav-item">
                        <a href="?toggle_theme=1" class="nav-link toggle-btn" title="Toggle Dark/Light Mode">
                            <?= $is_dark_mode ? '☀️' : '🌙' ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main class="container my-5">
    <div class="row">
        <div class="col-md-6 col-lg-4 mx-auto">
            <div class="card shadow-lg">
                <div class="card-body p-4">
                    <h2 class="mb-4 text-center text-primary">Masuk ke Akun Anda</h2>
                    
                    <?php if($msg): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($msg); ?>
                            <button type="button" class="btn-close <?= $is_dark_mode ? 'btn-close-white' : '' ?>" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if($err): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($err); ?>
                            <button type="button" class="btn-close <?= $is_dark_mode ? 'btn-close-white' : '' ?>" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email_input); ?>" placeholder="Alamat email terdaftar" required>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Kata sandi Anda" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                    <div class="text-center mt-3">
                        <p>Belum punya akun? <a href="register.php" class="text-primary">Daftar sekarang</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="py-3 mt-5 <?= $is_dark_mode ? 'bg-dark text-light' : 'bg-light text-dark' ?>">
    <div class="container text-center">
        <p>&copy; <?= date('Y') ?> TokoBook</p>
    </div>
</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>