
<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] !== 'peminjam') {
    exit("Halaman ini khusus untuk peminjam.");
}

$cari = trim($_GET['cari'] ?? '');

$sql = "SELECT a.id_alat, a.kode_alat, a.nama_alat,
               a.stok_total, a.stok_tersedia, a.kondisi,
               k.nama_kategori, k.deskripsi
        FROM alat a
        JOIN kategori k ON k.id_kategori = a.id_kategori";

if ($cari !== '') {
    $kata = "%" . $cari . "%";
    $stmt = mysqli_prepare(
        $koneksi,
        $sql . " WHERE a.nama_alat LIKE ?
                 OR a.kode_alat LIKE ?
                 OR k.nama_kategori LIKE ?
                 ORDER BY a.nama_alat ASC"
    );
    mysqli_stmt_bind_param($stmt, "sss", $kata, $kata, $kata);
} else {
    $stmt = mysqli_prepare(
        $koneksi,
        $sql . " ORDER BY a.nama_alat ASC"
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
    <title>Daftar Properti - Ruang Tari</title>
    <link rel="stylesheet" href="style.css">

    <style>
        .kartu-properti {
            background: white;
            border: 1px solid #e3d7c5;
            border-top: 4px solid #d2ac67;
            border-radius: 9px;
            padding: 18px;
        }

        .kartu-properti h3 {
            color: #5b1824;
            margin-top: 0;
        }

        .info-properti {
            margin: 8px 0;
            font-size: 14px;
        }

        .stok-tersedia {
            color: #246b42;
            font-weight: bold;
        }

        .stok-habis {
            color: #a21d2d;
            font-weight: bold;
        }

        .daftar-kartu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 18px;
            margin-top: 20px;
        }

        .form-cari {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            background: white;
            padding: 18px;
            border-radius: 8px;
            border: 1px solid #e3d7c5;
        }

        .form-cari input {
            flex: 1;
            min-width: 180px;
        }

        .form-cari button {
            width: auto;
            margin: 0;
        }
    </style>
</head>
<body>
<div class="halaman-container">

    <div class="halaman-header">
        <div>
            <h1>Daftar Properti Tari</h1>
            <p>Selamat datang, <?= htmlspecialchars($_SESSION['nama']) ?>.</p>
        </div>
        <a href="dashboard.php" class="tombol-kembali">
            Kembali ke Dashboard
        </a>
    </div>

    <h2>Properti yang Tersedia</h2>

    <form method="GET" class="form-cari">
        <input
            type="text"
            name="cari"
            value="<?= htmlspecialchars($cari) ?>"
            placeholder="Cari nama, kode, atau kategori properti..."
        >
        <button type="submit">Cari</button>
        <a href="daftar_properti.php" class="tombol-batal">
            Reset
        </a>
    </form>

    <p class="pesan">
        Jumlah jenis properti yang ditemukan: <strong><?= $total ?></strong>
    </p>

    <?php if ($total > 0) { ?>
        <div class="daftar-kartu">
            <?php while ($item = mysqli_fetch_assoc($hasil)) { ?>
                <div class="kartu-properti">
                    <h3><?= htmlspecialchars($item['nama_alat']) ?></h3>

                    <p class="info-properti">
                        <strong>Kode:</strong>
                        <?= htmlspecialchars($item['kode_alat']) ?>
                    </p>

                    <p class="info-properti">
                        <strong>Kategori:</strong>
                        <?= htmlspecialchars($item['nama_kategori']) ?>
                    </p>

                    <p class="info-properti">
                        <strong>Deskripsi:</strong>
                        <?= $item['deskripsi']
                            ? nl2br(htmlspecialchars($item['deskripsi']))
                            : '-' ?>
                    </p>

                    <p class="info-properti">
                        <strong>Kondisi:</strong>
                        <?= htmlspecialchars($item['kondisi']) ?>
                    </p>

                    <p class="info-properti">
                        <strong>Stok total:</strong>
                        <?= (int) $item['stok_total'] ?>
                    </p>

                    <p class="info-properti">
                        <strong>Stok tersedia:</strong>
                        <?php if (
                            $item['stok_tersedia'] > 0 &&
                            $item['kondisi'] === 'Baik'
                        ) { ?>
                            <span class="stok-tersedia">
                                <?= (int) $item['stok_tersedia'] ?> tersedia
                            </span>
                        <?php } else { ?>
                            <span class="stok-habis">
                                Tidak tersedia untuk dipinjam
                            </span>
                        <?php } ?>
                    </p>
                </div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="pesan">
            Properti tidak ditemukan. Coba gunakan kata kunci lain.
        </div>
    <?php } ?>

    <div style="margin-top:25px;">
        <a href="peminjaman.php" class="tombol-kembali">
            Ajukan Peminjaman
        </a>
    </div>

</div>
</body>
</html>
