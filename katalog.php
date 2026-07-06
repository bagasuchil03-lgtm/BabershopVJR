<?php
session_start();
require_once 'koneksi.php';

// Fetch Layanan
$layanan_query = $conn->query("SELECT * FROM layanan ORDER BY id_layanan ASC");
$layanan_data = [];
while ($row = $layanan_query->fetch_assoc()) {
    $layanan_data[] = $row;
}

// Fetch Gaya Rambut
$gaya_query = $conn->query("SELECT * FROM gaya_rambut ORDER BY id_gaya ASC");
$gaya_data = [];
while ($row = $gaya_query->fetch_assoc()) {
    $gaya_data[] = $row;
}

// Fetch Barber
$barber_query = $conn->query("SELECT * FROM barber WHERE status = 'aktif'");
$barber_data = [];
while ($row = $barber_query->fetch_assoc()) {
    $barber_data[] = $row;
}

// Fungsi untuk mengecek gambar lokal (dari root)
function get_local_image($name, $fallback) {
    $exts = ['jpg', 'jpeg', 'png', 'webp'];
    foreach($exts as $ext) {
        if(file_exists($name . '.' . $ext)) {
            return $name . '.' . $ext;
        }
    }
    return $fallback;
}

// Gambar dummy elegan untuk layanan
$service_images = [
    'assets/img/haircut_detail.png',
    'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?auto=format&fit=crop&w=800&q=80',
    'https://images.unsplash.com/photo-1590407180294-1d3c65810e30?auto=format&fit=crop&w=800&q=80',
    'https://images.unsplash.com/photo-1522335789204-8e1c4e986853?auto=format&fit=crop&w=800&q=80',
    'assets/img/shaving_trim.png'
];

// Gambar fallback elegan untuk gaya rambut
$gaya_images_fallback = [
    'https://images.unsplash.com/photo-1515387361122-c8fc0d9610a1?auto=format&fit=crop&w=600&q=80', // Undercut
    'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?auto=format&fit=crop&w=600&q=80', // Crew cut
    'https://images.unsplash.com/photo-1596473925995-59afcf6c4730?auto=format&fit=crop&w=600&q=80', // Fade
    'https://images.unsplash.com/photo-1620002166564-94672e811cb2?auto=format&fit=crop&w=600&q=80', // Mullet
    'https://images.unsplash.com/photo-1520975963910-517d81c2e89c?auto=format&fit=crop&w=600&q=80' // Pompadour
];

// Gambar fallback elegan untuk barber (master barber)
$barber_images = [
    get_local_image('barber1', 'https://images.unsplash.com/photo-1600180758895-1bdf6a0e3db2?auto=format&fit=crop&w=600&q=80'),
    get_local_image('barber2', 'https://images.unsplash.com/photo-1559163499-413811f7c5c5?auto=format&fit=crop&w=600&q=80'),
    get_local_image('barber3', 'https://images.unsplash.com/photo-1517964603305-3fdf1b6a0bf1?auto=format&fit=crop&w=600&q=80')
];
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Lengkap - Vijer Barbershop</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Montserrat & Playfair Display -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-color: #111111;
            --text-main: #f8f9fa;
            --text-muted: #adb5bd;
            --gold-primary: #D4AF37;
            --gold-hover: #c5a059;
            --card-bg: #1a1a1a;
            --border-color: rgba(212, 175, 55, 0.2);
            --section-bg: #0f0f0f;
            --nav-bg: rgba(17, 17, 17, 0.95);
        }

        [data-theme="light"] {
            --bg-color: #f8f9fa;
            --text-main: #1a1a1a;
            --text-muted: #666666;
            --gold-primary: #b58c42;
            --card-bg: #ffffff;
            --border-color: rgba(0, 0, 0, 0.1);
            --section-bg: #ffffff;
            --nav-bg: rgba(255, 255, 255, 0.95);
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Montserrat', sans-serif;
            overflow-x: hidden;
            transition: background-color 0.3s, color 0.3s;
        }

        h1, h2, h3, h4, h5, h6, .brand-text {
            font-family: 'Playfair Display', serif;
            color: var(--text-main);
            font-weight: 700;
        }
        
        .text-muted { color: var(--text-muted) !important; }

        /* Navbar */
        .navbar {
            background-color: var(--nav-bg);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s;
        }
        
        .nav-link { color: var(--text-main) !important; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .nav-link:hover, .nav-link.active { color: var(--gold-primary) !important; }

        .theme-toggle-btn {
            background: transparent;
            border: none;
            color: var(--text-main);
            font-size: 1.2rem;
            cursor: pointer;
            transition: color 0.3s;
        }
        .theme-toggle-btn:hover { color: var(--gold-primary); }

        /* Buttons */
        .btn-gold {
            background-color: var(--gold-primary); color: #000; font-weight: 600; border: none; transition: all 0.3s ease;
        }
        .btn-gold:hover { background-color: var(--gold-hover); transform: translateY(-2px); }
        .btn-outline-gold {
            background-color: transparent; color: var(--gold-primary); border: 2px solid var(--gold-primary); font-weight: 600; transition: all 0.3s ease;
        }
        .btn-outline-gold:hover { background-color: var(--gold-primary); color: #000; }
        
        .text-gold { color: var(--gold-primary) !important; }

        /* Layanan Cards */
        .service-card {
            position: relative;
            height: 400px;
            overflow: hidden;
            display: block;
            text-decoration: none;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        .service-card img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform 1s ease;
            filter: grayscale(20%) brightness(0.8);
        }
        .service-card:hover img { transform: scale(1.05); filter: grayscale(0%) brightness(0.4); }
        .service-content {
            position: absolute; bottom: 0; left: 0; width: 100%;
            padding: 30px 20px;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            transition: 0.3s;
        }
        .service-price { color: var(--gold-primary); font-weight: 700; font-size: 1.1rem; margin-bottom: 5px; display: block; }
        .service-title { font-family: 'Playfair Display', serif; font-size: 1.5rem; color: #fff; margin-bottom: 5px; }

        /* Gaya Cards */
        .style-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease;
            height: 100%;
        }
        .style-card:hover { transform: translateY(-10px); box-shadow: 0 15px 30px rgba(212, 175, 55, 0.15); }
        .style-img { width: 100%; height: 250px; object-fit: cover; border-bottom: 2px solid var(--gold-primary); }

        /* Barber Cards */
        .barber-card { text-align: center; margin-bottom: 30px; }
        .barber-img-wrapper { position: relative; overflow: hidden; margin-bottom: 20px; border-radius: 12px; aspect-ratio: 3/4; border: 1px solid var(--border-color); }
        .barber-img-wrapper img { width: 100%; height: 100%; object-fit: cover; filter: grayscale(100%); transition: 0.5s; }
        .barber-card:hover .barber-img-wrapper img { filter: grayscale(0%); transform: scale(1.03); }
        .barber-name { font-size: 1.3rem; margin-bottom: 5px; }

        /* Headers */
        .page-header {
            padding: 140px 0 60px;
            background: linear-gradient(rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.9)), 
                        url('https://images.unsplash.com/photo-1520338661084-680395057c93?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') center/cover;
            border-bottom: 1px solid var(--border-color);
        }
        .section-header { text-align: center; margin-bottom: 50px; }
        .section-subtitle { color: var(--gold-primary); font-size: 0.8rem; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; display: block; margin-bottom: 10px; }
        .bg-custom-section { background-color: var(--section-bg); transition: background-color 0.3s; }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand fs-3" href="index.php" style="font-family:'Montserrat', sans-serif; font-weight:900; letter-spacing:3px;">
                VIJER
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fas fa-bars text-white fs-4"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link active" href="katalog.php">Katalog</a></li>
                    
                    <li class="nav-item ms-lg-3 d-flex align-items-center gap-3">
                        <button class="theme-toggle-btn" id="themeToggle" title="Ganti Tema">
                            <i class="fas fa-moon"></i>
                        </button>
                        <a class="btn btn-outline-gold px-4 rounded-pill" href="login.php" style="font-size: 0.85rem;"><i class="fas fa-lock"></i> Admin</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header text-center">
        <div class="container">
            <h1 class="display-4 fw-bold mb-3">Katalog <span class="text-gold">Premium</span></h1>
            <p class="lead mx-auto" style="max-width: 600px; color: #ddd;">Eksplorasi layanan, gaya rambut, dan para ahli grooming kami sebelum membuat reservasi.</p>
        </div>
    </section>

    <!-- Layanan -->
    <section class="py-5">
        <div class="container py-4">
            <div class="section-header">
                <span class="section-subtitle">Pilihan Perawatan</span>
                <h2>Layanan Kami</h2>
            </div>
            
            <div class="row g-4 justify-content-center">
                <?php foreach($layanan_data as $i => $layanan): $img = !empty($layanan['image_path']) ? $layanan['image_path'] : $service_images[$i % count($service_images)]; ?>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <a href="booking.php?layanan=<?= $layanan['id_layanan'] ?>" class="service-card">
                        <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($layanan['nama_layanan']); ?>">
                        <div class="service-content">
                            <span class="service-price">Rp <?= number_format($layanan['harga'], 0, ',', '.') ?></span>
                            <h3 class="service-title"><?= htmlspecialchars($layanan['nama_layanan']) ?></h3>
                            <div class="text-light small"><i class="far fa-clock text-gold"></i> <?= $layanan['durasi_menit'] ?> Menit</div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Gaya Rambut -->
    <section class="py-5 bg-custom-section">
        <div class="container py-4">
            <div class="section-header">
                <span class="section-subtitle">Inspirasi Tatanan</span>
                <h2>Gaya Rambut Pria</h2>
            </div>

            <div class="row g-4">
                <?php foreach($gaya_data as $i => $gaya): 
                    // Fallback to high quality image if missing or default
                    $foto_tampil = (!empty($gaya['foto_gaya']) && $gaya['foto_gaya'] !== 'default_gaya.png') 
                        ? htmlspecialchars($gaya['foto_gaya']) 
                        : $gaya_images_fallback[$i % count($gaya_images_fallback)];
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card style-card">
                        <img src="<?= $foto_tampil ?>" alt="<?= htmlspecialchars($gaya['nama_gaya']) ?>" class="style-img">
                        <div class="card-body p-4 text-center">
                            <h4 class="fw-bold mb-3"><?= htmlspecialchars($gaya['nama_gaya']) ?></h4>
                            <p class="text-muted small mb-4"><?= htmlspecialchars($gaya['deskripsi']) ?></p>
                            <a href="booking.php?gaya=<?= $gaya['id_gaya'] ?>" class="btn btn-gold rounded-pill px-4 w-100">Pilih Gaya Ini</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Master Barber -->
    <section class="py-5">
        <div class="container py-4">
            <div class="section-header">
                <span class="section-subtitle">Eksekutor Andal</span>
                <h2>Master Barber Kami</h2>
            </div>

            <div class="row g-5 justify-content-center">
                <?php foreach($barber_data as $i => $barber): $img = $barber_images[$i % count($barber_images)]; ?>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="barber-card">
                        <div class="barber-img-wrapper">
                            <img src="<?= $img ?>" alt="<?= htmlspecialchars($barber['nama_barber']) ?>">
                        </div>
                        <h4 class="barber-name"><?= htmlspecialchars($barber['nama_barber']) ?></h4>
                        <span class="text-gold small fw-bold text-uppercase tracking-wider">Senior Barber</span>
                        <p class="text-muted small mt-2 mb-0" style="opacity: 0.9;">
                            <i class="fas fa-cut me-1 text-gold"></i> Pengalaman: <?= 5 + ($i * 2) ?> Tahun
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-5 pt-3 border-top border-secondary">
                <h4 class="mb-4">Siap Mengubah Penampilan Anda?</h4>
                <a href="booking.php" class="btn btn-outline-gold btn-lg rounded-pill px-5">
                    <i class="fas fa-calendar-check me-2"></i>Buat Reservasi Sekarang
                </a>
            </div>
        </div>
    </section>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme Toggle Script
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
                if (theme === 'dark') {
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                } else {
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                }
            }
        });
    </script>
</body>
</html>
