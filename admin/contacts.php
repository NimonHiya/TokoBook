<?php
session_start(); 
require_once __DIR__.'/../db.php';

// --- Global Feature Toggles ---
// **********************************************************
const FEATURE_THEME_TOGGLE = false;    // Menyembunyikan tombol ☀️/🌙
// **********************************************************


// --- Login Check & Theme Detection ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role']!=='admin') { 
    header('Location: ../login.php'); 
    exit; 
}
$admin_id = $_SESSION['user']['id'];

// --- Dark mode detection & Toggle Logic (KONDISIONAL) ---
$is_logged_in = true; 
$current_db_mode = $_SESSION['user']['theme_mode'] ?? 0;

if (FEATURE_THEME_TOGGLE && isset($_GET['toggle_theme']) && $_GET['toggle_theme'] === '1') {
    $new_db_mode = ($current_db_mode == 0) ? 1 : 0; 
    if (isset($pdo)) {
        $update_stmt = $pdo->prepare("UPDATE users SET theme_mode = ? WHERE id = ?");
        $update_stmt->execute([$new_db_mode, $admin_id]);
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

$flash_msg = '';
$msg_type = 'info';

// ==== LOGIKA BALAS PESAN (TICKET) ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_id'])) {
    $reply_id = (int)$_POST['reply_id'];
    $reply_message = trim($_POST['reply_message']);
    
    if (!empty($reply_message)) {
        // ASUMSI KOLOM: reply_message, replied_at, replied_by_user_id
        $stmt = $pdo->prepare("UPDATE contacts SET reply_message = ?, replied_at = NOW(), replied_by_user_id = ? WHERE id = ?");
        $stmt->execute([$reply_message, $admin_id, $reply_id]);
        
        $flash_msg = "Balasan berhasil dikirim untuk pesan ID #$reply_id!";
        $msg_type = 'success';
    } else {
        $flash_msg = "Gagal: Isi balasan tidak boleh kosong.";
        $msg_type = 'warning';
    }
    header("Location: contacts.php?msg=" . urlencode($flash_msg) . "&type=" . $msg_type);
    exit;
}

// ==== LOGIKA HAPUS PESAN ====
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM contacts WHERE id = ?')->execute([$delete_id]); 
    $flash_msg = "Pesan ID #$delete_id berhasil dihapus.";
    header("Location: contacts.php?msg=" . urlencode($flash_msg) . "&type=danger");
    exit;
}

// Ambil pesan notifikasi dari redirect
if (isset($_GET['msg'])) {
    $flash_msg = htmlspecialchars($_GET['msg']);
    $msg_type = htmlspecialchars($_GET['type'] ?? 'info');
}

// Ambil semua pesan (termasuk status balasan)
$contacts = $pdo->query('
    SELECT 
        c.*, 
        u.username,
        CASE 
            WHEN c.reply_message IS NULL THEN "Pending"
            ELSE "Dibalas" 
        END as reply_status
    FROM contacts c 
    LEFT JOIN users u ON c.user_id=u.id 
    ORDER BY c.sent_at DESC
')->fetchAll(PDO::FETCH_ASSOC);

// Hitung pesan baru yang belum dibaca (hanya untuk badge)
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
        .toggle-btn { border: none; background-color: transparent; color: inherit; padding: .5rem 1rem; text-decoration: none; }
        body.dark-mode { background-color: #121212; color: #f5f5f5; }
        .navbar-dark.bg-dark, body.dark-mode footer.bg-dark { background-color: #1f1f1f !important; }
        .sidebar { 
            min-height: 100vh; background-color: <?= $sidebar_color; ?>; border-right: 1px solid <?= $is_dark_mode ? '#333' : '#dee2e6'; ?>;
        }
        .sidebar .nav-link { color: <?= $is_dark_mode ? '#f5f5f5' : '#333'; ?>; }
        .sidebar .nav-link.active { background-color: #0d6efd; color: white !important; }
        .sidebar h4 { color: <?= $is_dark_mode ? '#0d6efd' : '#333'; ?>; }
        .card { background-color: <?= $is_dark_mode ? '#1e1e1e' : '#fff'; ?>; color: <?= $is_dark_mode ? '#f5f5f5' : '#333'; ?>; border: 1px solid <?= $is_dark_mode ? '#333' : 'rgba(0,0,0,.125)'; ?>; }
        body.dark-mode .table { color: #f5f5f5; }
        body.dark-mode .table-hover>tbody>tr:hover { --bs-table-bg-hover: #2b2b2b; }
        body.dark-mode .table-responsive { border: 1px solid #333; border-radius: 8px; overflow: hidden; }
        body.dark-mode .alert-info { background-color: #1f1f1f; border-color: #333; color: #ccc; }
        
        /* Modal & Reply Styles */
        .modal-content { background-color: <?= $is_dark_mode ? '#1e1e1e' : '#fff'; ?>; color: <?= $is_dark_mode ? '#f5f5f5' : '#333'; ?>; }
        .modal-header, .modal-footer { border-color: <?= $is_dark_mode ? '#333' : '#dee2e6'; ?>; }
        .modal-body .message-box { padding: 10px; background-color: <?= $is_dark_mode ? '#2a2a2a' : '#f1f1f1'; ?>; border-radius: 5px; margin-bottom: 15px; }
        .modal-body .reply-box { padding: 10px; border-left: 4px solid #198754; background-color: <?= $is_dark_mode ? '#1c3d3a' : '#d1e7dd'; ?>; color: <?= $is_dark_mode ? '#fff' : '#1a473b'; ?>; border-radius: 0 5px 5px 0; }
        .reply-form textarea { background-color: <?= $is_dark_mode ? '#2b2b2b' : '#fff'; ?>; color: <?= $is_dark_mode ? '#f5f5f5' : '#333'; ?>; border-color: <?= $is_dark_mode ? '#444' : '#ccc'; ?>; }
        
        /* Mengatasi button close di dark mode */
        body.dark-mode .modal-header .btn-close,
        body.dark-mode .alert .btn-close { filter: invert(1); }
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
            <li class="nav-item"><a class="nav-link" href="orders.php">Pesanan</a></li>
            <li class="nav-item"><a class="nav-link" href="report.php">Buat Laporan (CSV)</a></li>
            <li class="nav-item"><a class="nav-link" href="about_edit.php">Edit Tentang Kami</a></li>
            <li class="nav-item">
                <a class="nav-link active" href="contacts.php">
                    Pesan Kontak 
                    <span id="messageBadge" class="badge-notif" style="<?= $newMessagesCount > 0 ? 'display:inline-block;' : 'display:none;'; ?>">
                        <?= $newMessagesCount ?>
                    </span>
                </a>
            </li>
        </ul>
    </nav>

    <main class="content flex-grow-1">
        <div class="container">
            <h2 class="mb-4 text-primary">Pesan Kontak Masuk 📧</h2>
            
            <?php if($flash_msg): ?>
                <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0" role="alert">
                    <?php echo $flash_msg; ?>
                    <button type="button" class="btn-close <?= $is_dark_mode ? 'btn-close-white' : '' ?>" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-lg">
                <div class="card-body p-4">
                    <?php if (empty($contacts)): ?>
                        <div class="alert alert-info border-0 text-center">Belum ada pesan kontak yang masuk.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle <?= $is_dark_mode ? 'table-dark' : '' ?>">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Pengirim</th>
                                        <th>Status</th>
                                        <th>Dikirim Pada</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contacts as $c): ?>
                                        <tr data-id="<?= $c['id']; ?>" 
                                            data-username="<?= htmlspecialchars($c['username'] ?? 'Tamu'); ?>"
                                            data-message="<?= htmlspecialchars($c['message']); ?>"
                                            data-reply="<?= htmlspecialchars($c['reply_message'] ?? ''); ?>"
                                            data-replied-at="<?= $c['replied_at'] ? date('d M Y H:i', strtotime($c['replied_at'])) : ''; ?>">
                                            
                                            <td><?php echo $c['id']; ?></td>
                                            <td><?php echo htmlspecialchars($c['username'] ?? 'Tamu'); ?></td>
                                            <td>
                                                <span class="badge bg-<?= $c['reply_status'] === 'Dibalas' ? 'success' : 'warning text-dark'; ?>">
                                                    <?= $c['reply_status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('d M Y H:i', strtotime($c['sent_at']))); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary btn-reply" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#replyModal">
                                                    <?= $c['reply_message'] ? 'Lihat/Edit Balasan' : 'Balas'; ?>
                                                </button>
                                                <a href="?delete=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger mt-1 mt-md-0" onclick="return confirm('Hapus pesan ini?')">Hapus</a>
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

<div class="modal fade" id="replyModal" tabindex="-1" aria-labelledby="replyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" id="replyForm">
        <div class="modal-header">
          <h5 class="modal-title" id="replyModalLabel">Balas Pesan dari: <span id="modalUser"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="reply_id" id="modalReplyId">
          
          <h6 class="text-secondary">Pesan Customer:</h6>
          <div class="message-box">
            <p id="modalMessage"></p>
            <small class="text-muted d-block" id="modalSentAt"></small>
          </div>
          
          <div id="existingReplySection" style="display:none;">
            <h6 class="text-success">Balasan Anda Sebelumnya (<span id="modalRepliedAt"></span>):</h6>
            <div class="reply-box mb-3">
                <p id="modalExistingReply"></p>
            </div>
          </div>
          
          <h6 class="text-primary">Tulis Balasan Sekarang:</h6>
          <textarea name="reply_message" id="modalReplyMessage" class="form-control" rows="5" placeholder="Masukkan balasan Anda di sini..."></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-primary">Kirim/Simpan Balasan</button>
        </div>
      </form>
    </div>
  </div>
</div>
<footer class="py-3 mt-5 <?= $is_dark_mode ? 'bg-dark text-light' : 'bg-light text-dark' ?>">
    <div class="container text-center">
        <p>&copy; <?php echo date('Y'); ?> TokoBook</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const replyModal = document.getElementById('replyModal');
    replyModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const row = button.closest('tr');
        
        // Ambil data dari atribut data-*
        const id = row.dataset.id;
        const username = row.dataset.username;
        const message = row.dataset.message;
        const sentAt = row.dataset.sentat;
        const reply = row.dataset.reply;
        const repliedAt = row.dataset.repliedat;

        // Isi data ke dalam modal
        document.getElementById('modalReplyId').value = id;
        document.getElementById('modalUser').textContent = username;
        document.getElementById('modalMessage').innerHTML = message.replace(/\n/g, '<br>');
        document.getElementById('modalSentAt').textContent = 'Dikirim: ' + sentAt;

        // Balasan sebelumnya
        const existingReplySection = document.getElementById('existingReplySection');
        const modalExistingReply = document.getElementById('modalExistingReply');
        const modalReplyMessage = document.getElementById('modalReplyMessage');
        const modalRepliedAt = document.getElementById('modalRepliedAt');

        if (reply) {
            existingReplySection.style.display = 'block';
            modalExistingReply.innerHTML = reply.replace(/\n/g, '<br>');
            modalRepliedAt.textContent = repliedAt;
            // Tampilkan balasan lama di textarea untuk diedit
            modalReplyMessage.value = reply;
        } else {
            existingReplySection.style.display = 'none';
            // Kosongkan textarea jika belum ada balasan
            modalReplyMessage.value = '';
        }
        
        // Atur warna tombol close modal untuk dark mode
        const closeButton = replyModal.querySelector('.btn-close');
        if (document.body.classList.contains('dark-mode')) {
             closeButton.classList.add('btn-close-white');
        } else {
             closeButton.classList.remove('btn-close-white');
        }
    });
    
    // Sembunyikan badge setelah halaman dimuat (tidak ada elemen badge di sini, tapi good practice)
    // Cek jika badge notifikasi ada di sidebar dan sembunyikan jika sudah dibaca
    const sidebarBadge = document.getElementById('messageBadge');
    if (sidebarBadge) {
        sidebarBadge.style.display = 'none';
    }
});
</script>
</body>
</html>