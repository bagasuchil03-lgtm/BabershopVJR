<?php
session_start();
require_once 'koneksi.php';

if (isset($_SESSION['id_user'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Vijer Barbershop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #111111;
            --text-main: #f8f9fa;
            --text-muted: #adb5bd;
            --gold-primary: #D4AF37;
            --gold-hover: #FFD700;
            --card-bg: #1a1a1a;
            --input-bg: #222;
            --input-border: #333;
            --border-color: rgba(212, 175, 55, 0.2);
        }
        [data-theme="light"] {
            --bg-color: #f0f2f5;
            --text-main: #212529;
            --text-muted: #6c757d;
            --gold-primary: #b5952f;
            --gold-hover: #D4AF37;
            --card-bg: #ffffff;
            --input-bg: #f8f9fa;
            --input-border: #dee2e6;
            --border-color: rgba(0, 0, 0, 0.1);
        }
        body { background-color: var(--bg-color); color: var(--text-main); font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px 0; transition: background-color 0.3s, color 0.3s; }
        .auth-card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 15px; padding: 40px; width: 100%; max-width: 450px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); transition: background-color 0.3s; }
        h2, h4 { color: var(--text-main); }
        .form-control { background-color: var(--input-bg); border: 1px solid var(--input-border); color: var(--text-main); }
        .form-control:focus { background-color: var(--input-bg); border-color: var(--gold-primary); color: var(--text-main); box-shadow: none; }
        .form-control::placeholder { color: var(--text-muted); }
        .form-label { color: var(--text-muted); }
        .btn-gold { background-color: var(--gold-primary); color: #000; font-weight: 600; border: none; transition: 0.3s; }
        .btn-gold:hover { background-color: var(--gold-hover); transform: translateY(-2px); }
        .text-gold { color: var(--gold-primary); }
        .text-muted-custom { color: var(--text-muted); }
        a { color: var(--gold-primary); text-decoration: none; }
        a:hover { color: var(--gold-hover); text-decoration: underline; }
        .theme-toggle { position: fixed; top: 15px; right: 15px; cursor: pointer; font-size: 1.2rem; color: var(--text-main); background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; transition: 0.3s; z-index: 999; }
        .theme-toggle:hover { color: var(--gold-primary); }
    </style>
</head>
<body>
    <!-- Theme Toggle -->
    <button class="theme-toggle" id="themeToggle" title="Ganti Tema">
        <i class="fas fa-moon"></i>
    </button>

    <div class="auth-card text-center">
        <h2 class="mb-4" style="font-family: 'Outfit', sans-serif;"><i class="fas fa-cut text-gold me-2"></i>VIJER</h2>
        <h4 class="mb-4">Daftar Akun Baru</h4>

        <?php if(isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger p-2" role="alert">
                <?= $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
            </div>
        <?php endif; ?>

        <form action="auth_proses.php" method="POST">
            <input type="hidden" name="action" value="register">
            <div class="mb-3 text-start">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama_lengkap" class="form-control" required placeholder="Contoh: Budi Santoso">
            </div>
            <div class="mb-3 text-start">
                <label class="form-label">No WhatsApp / HP</label>
                <input type="text" name="no_hp" class="form-control" required placeholder="Contoh: 08123456789">
            </div>
            <div class="mb-3 text-start">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required placeholder="Pilih username">
            </div>
            <div class="mb-4 text-start">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required placeholder="Buat password">
            </div>
            <button type="submit" class="btn btn-gold w-100 rounded-pill py-2 mb-3">Daftar</button>
        </form>
        <p class="text-muted-custom small">Sudah punya akun? <a href="login.php">Login di sini</a></p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggle = document.getElementById('themeToggle');
            const icon = themeToggle.querySelector('i');
            const htmlElement = document.documentElement;
            const savedTheme = localStorage.getItem('theme') || 'dark';
            setTheme(savedTheme);
            themeToggle.addEventListener('click', () => {
                const currentTheme = htmlElement.getAttribute('data-theme');
                setTheme(currentTheme === 'dark' ? 'light' : 'dark');
            });
            function setTheme(theme) {
                htmlElement.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
                icon.className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
            }
        });
    </script>
</body>
</html>
