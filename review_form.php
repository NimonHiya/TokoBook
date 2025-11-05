<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';

if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user']['id'];
$order_id = (int)($_GET['order_id'] ?? 0);
$book_id = (int)($_GET['book_id'] ?? 0);

if (!$order_id || !$book_id) { header('Location: my_orders.php'); exit; }

// 1. Ambil detail item pesanan
$stmt = $pdo->prepare('SELECT o.status, b.title FROM orders o JOIN books b ON o.book_id = b.id WHERE o.id = ? AND o.book_id = ? AND o.user_id = ?');
$stmt->execute([$order_id, $book_id, $user_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Cek validasi: Item harus ada DAN status harus 'selesai'
if (!$item || $item['status'] !== 'selesai') {
    $_SESSION['flash'] = 'Pesanan tidak memenuhi syarat untuk diulas (Status harus "selesai").';
    $_SESSION['alert_type'] = 'danger';
    header('Location: my_orders.php'); 
    exit;
}

// 3. Cek validasi: Sudah diulas? (Pencegahan ganda)
// Perlu cek user_id di sini, meskipun UNIQUE KEY di DB sudah cukup, ini double check
$check_review = $pdo->prepare('SELECT id FROM reviews WHERE order_id = ? AND book_id = ? AND user_id = ?');
$check_review->execute([$order_id, $book_id, $user_id]);
if ($check_review->fetch()) {
    $_SESSION['flash'] = 'Anda sudah mengulas item ini. Ulasan tidak dapat diubah.';
    $_SESSION['alert_type'] = 'warning';
    header('Location: my_orders.php'); 
    exit;
}

$book_title = htmlspecialchars($item['title']);

// Asumsi: Dark mode logic dari my_orders.php tetap digunakan di sini
$is_dark_mode = ($_SESSION['user']['theme_mode'] ?? 0) == 1;
$theme = $is_dark_mode ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TokoBook - Beri Ulasan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* === BASE DARK MODE STYLES === */
        body.dark-mode { 
            background-color: #121212; 
            color: #f5f5f5; /* Teks default Body menjadi putih */
        }
        body.dark-mode .card { 
            background-color: #1e1e1e; 
            border: 1px solid #333; 
        }
        
        /* === PENYESUAIAN TEKS HEADING DAN JUDUL KRITIS === */
        /* Memaksa teks penting di dalam card menjadi putih */
        body.dark-mode h2,
        body.dark-mode h4,
        body.dark-mode strong,
        body.dark-mode label.form-label {
            color: #f5f5f5 !important;
        }

        /* Form controls (input, textarea) */
        body.dark-mode .form-control {
            background-color: #2b2b2b;
            color: #f5f5f5; /* Teks input menjadi putih */
            border-color: #333;
        }
        body.dark-mode .form-control:focus {
             background-color: #383838;
             border-color: #0d6efd;
             color: #f5f5f5;
        }
        
        /* Mengatasi teks placeholder dan muted */
        body.dark-mode .form-control::placeholder { 
            color: #999; 
        }
        body.dark-mode .text-muted {
            color: #b0b0b0 !important;
        }
        
        /* Bintang dan Ulasan */
        .rating-stars { font-size: 2rem; cursor: pointer; color: #ccc; }
        .rating-stars.selected { color: gold; }
        
        /* Teks input saat light mode agar kontras */
        .form-control { color: #212529; } 
    </style>
</head>
<body class="<?= $theme === 'dark' ? 'dark-mode' : '' ?>">
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow p-4">
                <h2 class="mb-4 text-center">Beri Ulasan</h2>
                <h4 class="text-center mb-4">Item: **<?php echo $book_title; ?>**</h4>
                
                <form method="POST" action="submit_review.php">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                    <input type="hidden" name="rating" id="rating_input" required>
                    <?php echo csrf_input_field(); ?>

                    <div class="mb-4 text-center">
                        <label class="form-label d-block">Penilaian Anda:</label>
                        <div id="rating_container" class="rating-stars">
                            </div>
                        <p id="rating_text" class="mt-2 text-muted"></p>
                    </div>

                    <div class="mb-3">
                        <label for="review_text" class="form-label">Tulis Ulasan Anda:</label>
                        <textarea class="form-control" id="review_text" name="review_text" rows="5" required minlength="10" placeholder="Minimal 10 karakter..."></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning btn-lg text-dark">Kirim Ulasan</button>
                        <a href="my_orders.php" class="btn btn-outline-secondary">Kembali ke Pesanan</a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('rating_container');
        const ratingInput = document.getElementById('rating_input');
        const ratingText = document.getElementById('rating_text');
        const stars = [];

        const starDescriptions = {
            1: "Buruk",
            2: "Kurang",
            3: "Cukup Baik",
            4: "Baik Sekali",
            5: "Sangat Memuaskan"
        };

        function updateStars(rating) {
            ratingInput.value = rating;
            ratingText.textContent = starDescriptions[rating] || '';

            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('selected');
                    star.textContent = '★'; // Bintang terisi
                } else {
                    star.classList.remove('selected');
                    star.textContent = '☆'; // Bintang kosong
                }
            });
        }

        // Buat 5 bintang
        for (let i = 1; i <= 5; i++) {
            const star = document.createElement('span');
            star.textContent = '☆'; // Bintang kosong default
            star.dataset.rating = i;
            star.classList.add('rating-star');
            
            star.addEventListener('click', () => updateStars(i));
            
            // Hover effect
            star.addEventListener('mouseover', function() {
                stars.forEach((s, index) => {
                    if (index < i) {
                        s.style.color = 'yellow';
                    }
                });
            });

            star.addEventListener('mouseout', function() {
                stars.forEach((s) => {
                    s.style.color = ''; // Reset warna
                });
                // Kembalikan ke rating yang dipilih, jika ada
                updateStars(ratingInput.value || 0); 
            });

            container.appendChild(star);
            stars.push(star);
        }

        // Set default rating (misalnya, 5)
        updateStars(5); 
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>