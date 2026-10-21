<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'];

if (!in_array($role, ['admin', 'petugas'])) {
    exit("Akses ditolak! Halaman ini hanya untuk admin dan petugas.");
}

$tanggal_awal = $_GET['tanggal_awal'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');

$valid_awal = DateTime::createFromFormat('!Y-m-d', $tanggal_awal);
$valid_akhir = DateTime::createFromFormat('!Y-m-d', $tanggal_akhir);

if (
    !$valid_awal || $valid_awal->format('Y-m-d') !== $tanggal_awal ||
    !$valid_akhir || $valid_akhir->format('Y-m-d') !== $tanggal_akhir ||
    $tanggal_awal > $tanggal_akhir
) {
    $tanggal_awal = date('Y-m-01');
    $tanggal_akhir = date('Y-m-d');
}

$sql = "SELECT
            p.id_peminjaman,
            u.nama AS nama_peminjam,
            a.nama_alat,
            dp.jumlah,
            p.tanggal_pinjam,
            p.rencana_kembali,
            p.status AS status_pinjam,
            pg.tanggal_kembali,
            pg.denda,
            pg.status AS status_pengembalian
        FROM peminjaman p
        JOIN users u
            ON u.id_user = p.id_peminjam
        JOIN detail_peminjaman dp
            ON dp.id_peminjaman = p.id_peminjaman
        JOIN alat a
            ON a.id_alat = dp.id_alat
        LEFT JOIN pengembalian pg
            ON pg.id_detail = dp.id_detail
        WHERE p.tanggal_pinjam BETWEEN ? AND ?
        ORDER BY p.tanggal_pinjam DESC, p.id_peminjaman DESC";

$stmt = mysqli_prepare($koneksi, $sql);
mysqli_stmt_bind_param($stmt, "ss", $tanggal_awal, $tanggal_akhir);
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);

$data_laporan = [];
$total_denda = 0;

while ($baris = mysqli_fetch_assoc($hasil)) {
    $data_laporan[] = $baris;

    if ($baris['status_pengembalian'] === 'selesai') {
        $total_denda += (float) $baris['denda'];
    }
}

$total_data = count($data_laporan);
$total_barang = 0;

foreach ($data_laporan as $baris) {
    $total_barang += (int) $baris['jumlah'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Ruang Tari</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .ringkasan-laporan {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .kartu-laporan {
            background: white;
            border: 1px solid #e3d7c5;
            border-left: 5px solid #d2ac67;
            border-radius: 8px;
            padding: 18px;
        }

        .kartu-laporan p {
            margin: 0 0 8px;
            color: #75685d;
        }

        .kartu-laporan h2 {
            margin: 0;
            color: #5b1824;
        }

        .filter-laporan {
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 12px;
            background: white;
            border: 1px solid #e3d7c5;
            border-radius: 8px;
            padding: 18px;
        }

        .filter-laporan .kolom-filter {
            flex: 1;
            min-width: 170px;
        }

        .filter-laporan input {
            margin-top: 7px;
        }

        .filter-laporan button {
            width: auto;
            margin: 0;
        }

        .tombol-cetak {
            display: inline-block;
            padding: 11px 16px;
            border: none;
            border-radius: 6px;
            background: #5b1824;
            color: white;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
        }

        .aksi-laporan {
            display: flex;
            justify-content: flex-end;
            margin: 15px 0;
        }

        @media print {
            body {
                display: block;
                background: white;
                padding: 0;
                color: black;
            }

            .halaman-container {
                max-width: none;
                margin: 0;
            }

            .halaman-header {
                background: white;
                color: black;
                border: 1px solid #999;
            }

            .halaman-header h1 {
                color: black;
            }

            .filter-laporan,
            .aksi-laporan,
            .tombol-kembali {
                display: none !important;
            }

            .tabel-container,
            .kartu-laporan {
                border: 1px solid #999;
            }

            .tabel-data {
                min-width: 0;
                font-size: 10px;
            }

            .tabel-data th {
                background: #eee !important;
                color: black !important;
            }

            .tabel-data th,
            .tabel-data td {
                padding: 6px;
            }

            .ringkasan-laporan {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>
<body>
<div class="halaman-container">

    <div class="halaman-header">
        <div>
            <h1>Laporan Ruang Tari</h1>
            <p>Laporan peminjaman dan pengembalian properti tari.</p>
        </div>
        <a href="dashboard.php" class="tombol-kembali">Kembali</a>
    </div>

    <h2>Filter Tanggal Peminjaman</h2>

    <form method="GET" class="filter-laporan">
        <div class="kolom-filter">
            <label for="tanggal_awal">Dari tanggal</label>
            <input type="date" id="tanggal_awal"
                   name="tanggal_awal"
                   value="<?= htmlspecialchars($tanggal_awal) ?>"
                   required>
        </div>

        <div class="kolom-filter">
            <label for="tanggal_akhir">Sampai tanggal</label>
            <input type="date" id="tanggal_akhir"
                   name="tanggal_akhir"
                   value="<?= htmlspecialchars($tanggal_akhir) ?>"
                   required>
        </div>

        <button type="submit">Tampilkan</button>
    </form>

    <div class="ringkasan-laporan">
        <div class="kartu-laporan">
            <p>Jumlah Transaksi</p>
            <h2><?= $total_data ?></h2>
        </div>

        <div class="kartu-laporan">
            <p>Total Properti Dipinjam</p>
            <h2><?= $total_barang ?></h2>
        </div>

        <div class="kartu-laporan">
            <p>Total Denda Terkonfirmasi</p>
            <h2>Rp<?= number_format($total_denda, 0, ',', '.') ?></h2>
        </div>
    </div>

    <div class="aksi-laporan">
        <a
            href="cetak_laporan.php?tanggal_awal=<?= urlencode($tanggal_awal) ?>&tanggal_akhir=<?= urlencode($tanggal_akhir) ?>"
            class="tombol-cetak"
            target="_blank"
        >
            Cetak Laporan
        </a>
    </div>

    <h2>Data Peminjaman dan Pengembalian</h2>

    <div class="tabel-container">
        <table class="tabel-data">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Peminjam</th>
                    <th>Properti</th>
                    <th>Jumlah</th>
                    <th>Tanggal Pinjam</th>
                    <th>Batas Kembali</th>
                    <th>Tanggal Pengembalian</th>
                    <th>Status Pinjam</th>
                    <th>Status Kembali</th>
                    <th>Denda</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($total_data > 0) { ?>
                <?php foreach ($data_laporan as $index => $baris) { ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($baris['nama_peminjam']) ?></td>
                        <td><?= htmlspecialchars($baris['nama_alat']) ?></td>
                        <td><?= (int) $baris['jumlah'] ?></td>
                        <td><?= htmlspecialchars($baris['tanggal_pinjam']) ?></td>
                        <td><?= htmlspecialchars($baris['rencana_kembali']) ?></td>
                        <td>
                            <?= $baris['tanggal_kembali']
                                ? htmlspecialchars($baris['tanggal_kembali'])
                                : '-' ?>
                        </td>
                        <td><?= htmlspecialchars(ucfirst($baris['status_pinjam'])) ?></td>
                        <td>
                            <?= $baris['status_pengembalian']
                                ? htmlspecialchars(ucfirst($baris['status_pengembalian']))
                                : 'Belum diajukan' ?>
                        </td>
                        <td>
                            Rp<?= number_format(
                                $baris['status_pengembalian'] === 'selesai'
                                    ? (float) $baris['denda'] : 0,
                                0, ',', '.'
                            ) ?>
                        </td>
                    </tr>
                <?php } ?>
            <?php } else { ?>
                <tr>
                    <td colspan="10">Belum ada data pada rentang tanggal ini.</td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

    <p style="text-align:right; margin-top:20px;">
        Dicetak pada: <?= date('d-m-Y H:i:s') ?>
    </p>

</div>
</body>
</html>