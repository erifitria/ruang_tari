
<?php
session_start();

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$nama = $_SESSION['nama'];
$role = $_SESSION['role'];

$menu = [];

if ($role == "admin") {
    $menu = [
        ["Kelola Pengguna", "pengguna.php"],
        ["Kelola Kategori", "kategori.php"],
        ["Kelola Properti Tari", "properti.php"],
        ["Data Peminjaman", "peminjaman.php"],
        ["Data Pengembalian", "pengembalian.php"],
        ["Laporan", "laporan.php"],
        ["Log Aktivitas", "log_aktivitas.php"]
    ];
} elseif ($role == "petugas") {
    $menu = [
        ["Data Properti Tari", "properti.php"],
        ["Persetujuan Peminjaman", "peminjaman.php"],
        ["Pengembalian Properti", "pengembalian.php"],
        ["Laporan Peminjaman", "laporan.php"],
        ["Data Pengguna", "pengguna.php"]
    ];
} elseif ($role == "peminjam") {
    $menu = [
        ["Daftar Properti Tari", "daftar_properti.php"],
        ["Peminjaman Saya", "peminjaman_saya.php"],
        ["Pengembalian Saya", "pengembalian_saya.php"]
    ];
} else {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Ruang Tari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="dashboard-container">
    <header class="dashboard-header">
        <div>
            <h1>💃 Ruang Tari</h1>
            <p>Aplikasi Peminjaman Properti Tari</p>
        </div>

        <a href="logout.php" class="logout-button"
           onclick="return confirm('Yakin ingin logout?')">
            Logout
        </a>
    </header>

    <section class="welcome-box">
        <h2>Selamat datang, <?= htmlspecialchars($nama) ?>!</h2>
        <p>Anda login sebagai
            <strong><?= htmlspecialchars(ucfirst($role)) ?></strong>.
        </p>
    </section>

    <h2 class="section-title">Menu Utama</h2>

    <div class="menu-grid">
        <?php foreach ($menu as $item) { ?>
            <a class="menu-card"
               href="<?= htmlspecialchars($item[1]) ?>">
                <span><?= htmlspecialchars($item[0]) ?></span>
                <span class="menu-arrow">›</span>
            </a>
        <?php } ?>
    </div>

    <footer class="dashboard-footer">
        <p>Ruang Tari &copy; <?= date("Y") ?></p>
    </footer>
</div>

</body>
</html>
