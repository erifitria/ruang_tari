
<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    exit("Akses ditolak! Halaman ini hanya untuk admin.");
}

$cari = trim($_GET['cari'] ?? '');

if ($cari !== '') {
    $kata = "%" . $cari . "%";

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT l.id_log, u.nama, u.username, u.role,
                l.aktivitas, l.waktu
         FROM log_aktivitas l
         JOIN users u ON u.id_user = l.id_user
         WHERE u.nama LIKE ?
            OR u.username LIKE ?
            OR l.aktivitas LIKE ?
         ORDER BY l.waktu DESC, l.id_log DESC"
    );

    mysqli_stmt_bind_param($stmt, "sss", $kata, $kata, $kata);
} else {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT l.id_log, u.nama, u.username, u.role,
                l.aktivitas, l.waktu
         FROM log_aktivitas l
         JOIN users u ON u.id_user = l.id_user
         ORDER BY l.waktu DESC, l.id_log DESC"
    );
}

mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);
$total = mysqli_num_rows($hasil);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aktivitas - Ruang Tari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="halaman-container">

    <div class="halaman-header">
        <div>
            <h1>Log Aktivitas</h1>
            <p>Riwayat aktivitas pengguna aplikasi Ruang Tari.</p>
        </div>
        <a href="dashboard.php" class="tombol-kembali">Kembali</a>
    </div>

    <h2>Riwayat Pengguna</h2>

    <div class="form-data">
        <form method="GET">
            <label for="cari">Cari nama, username, atau aktivitas</label>
            <input
                type="text"
                id="cari"
                name="cari"
                value="<?= htmlspecialchars($cari) ?>"
                placeholder="Contoh: admin atau Login"
            >
            <button type="submit">Cari</button>
            <a href="log_aktivitas.php" class="tombol-batal">
                Reset
            </a>
        </form>
    </div>

    <p class="pesan">
        Jumlah catatan aktivitas: <strong><?= $total ?></strong>
    </p>

    <div class="tabel-container">
        <table class="tabel-data">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama Pengguna</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Aktivitas</th>
                    <th>Waktu</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($total > 0) { ?>
                <?php $no = 1; ?>
                <?php while ($baris = mysqli_fetch_assoc($hasil)) { ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($baris['nama']) ?></td>
                        <td><?= htmlspecialchars($baris['username']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($baris['role'])) ?></td>
                        <td><?= htmlspecialchars($baris['aktivitas']) ?></td>
                        <td><?= htmlspecialchars($baris['waktu']) ?></td>
                    </tr>
                <?php } ?>
            <?php } else { ?>
                <tr>
                    <td colspan="6">Belum ada aktivitas yang tercatat.</td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
