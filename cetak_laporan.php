<?php
session_start();
include "koneksi.php";

// Periksa login
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

// Hanya admin dan petugas yang boleh mengakses
$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['admin', 'petugas'])) {
    exit("Akses ditolak! Halaman ini hanya untuk admin dan petugas.");
}

// Ambil tanggal dari laporan.php
$tanggal_awal = $_GET['tanggal_awal'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');

// Validasi tanggal
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

// Ambil data laporan
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

mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $tanggal_awal,
    $tanggal_akhir
);

mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);

$data_laporan = [];
$total_denda = 0;
$total_barang = 0;

while ($baris = mysqli_fetch_assoc($hasil)) {
    $data_laporan[] = $baris;
    $total_barang += (int) $baris['jumlah'];

    if ($baris['status_pengembalian'] === 'selesai') {
        $total_denda += (float) $baris['denda'];
    }
}

$total_data = count($data_laporan);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cetak Laporan - Ruang Tari</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f1eb;
            color: #333;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            background: white;
            padding: 25px;
            border: 1px solid #e3d7c5;
            border-radius: 8px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #5b1824;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header h1 {
            color: #5b1824;
            margin-bottom: 8px;
        }

        .header p {
            margin: 5px 0;
        }

        .info-laporan {
            margin-bottom: 20px;
            line-height: 1.8;
        }

        .ringkasan {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 25px;
        }

        .kartu {
            border: 1px solid #d2ac67;
            border-left: 4px solid #d2ac67;
            padding: 15px;
            border-radius: 5px;
        }

        .kartu p {
            margin: 0 0 8px;
            color: #75685d;
        }

        .kartu h3 {
            margin: 0;
            color: #5b1824;
        }

        h2 {
            color: #5b1824;
            font-size: 18px;
        }

        .tabel-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th, td {
            border: 1px solid #999;
            padding: 8px 6px;
            text-align: center;
        }

        th {
            background: #5b1824;
            color: white;
        }

        td:nth-child(2),
        td:nth-child(3) {
            text-align: left;
        }

        .tombol {
            display: inline-block;
            padding: 11px 16px;
            border: none;
            border-radius: 5px;
            background: #5b1824;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            margin: 15px 5px 15px 0;
        }

        .tombol-kembali {
            background: #75685d;
        }

        .tanggal-cetak {
            text-align: right;
            margin-top: 25px;
            font-size: 12px;
        }

        @media print {
            @page {
                size: landscape;
                margin: 10mm;
            }

            body {
                background: white;
                padding: 0;
            }

            .container {
                max-width: none;
                border: none;
                padding: 0;
                border-radius: 0;
            }

            .aksi {
                display: none !important;
            }

            .ringkasan {
                grid-template-columns: repeat(3, 1fr);
            }

            table {
                font-size: 9px;
            }

            th, td {
                padding: 5px 4px;
            }

            th {
                background: #eee !important;
                color: black !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="header">
        <h1>LAPORAN RUANG TARI</h1>
        <p>Laporan Peminjaman dan Pengembalian Properti Tari</p>
        <p>Periode:
            <?= htmlspecialchars(date('d-m-Y', strtotime($tanggal_awal))) ?>
            sampai
            <?= htmlspecialchars(date('d-m-Y', strtotime($tanggal_akhir))) ?>
        </p>
    </div>

    <div class="aksi">
        <button type="button" class="tombol" onclick="window.print()">
            Cetak Laporan
        </button>

        <a href="laporan.php?tanggal_awal=<?= urlencode($tanggal_awal) ?>&tanggal_akhir=<?= urlencode($tanggal_akhir) ?>"
           class="tombol tombol-kembali">
            Kembali ke Laporan
        </a>
    </div>

    <div class="ringkasan">
        <div class="kartu">
            <p>Jumlah Transaksi Detail</p>
            <h3><?= $total_data ?></h3>
        </div>

        <div class="kartu">
            <p>Total Properti Dipinjam</p>
            <h3><?= $total_barang ?></h3>
        </div>

        <div class="kartu">
            <p>Total Denda Terkonfirmasi</p>
            <h3>Rp<?= number_format($total_denda, 0, ',', '.') ?></h3>
        </div>
    </div>

    <h2>Data Peminjaman dan Pengembalian</h2>

    <div class="tabel-container">
        <table>
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

                        <td>
                            <?= htmlspecialchars($baris['nama_peminjam']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($baris['nama_alat']) ?>
                        </td>

                        <td><?= (int) $baris['jumlah'] ?></td>

                        <td>
                            <?= htmlspecialchars($baris['tanggal_pinjam']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($baris['rencana_kembali']) ?>
                        </td>

                        <td>
                            <?= $baris['tanggal_kembali']
                                ? htmlspecialchars($baris['tanggal_kembali'])
                                : '-' ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(ucfirst($baris['status_pinjam'])) ?>
                        </td>

                        <td>
                            <?= $baris['status_pengembalian']
                                ? htmlspecialchars(ucfirst($baris['status_pengembalian']))
                                : 'Belum diajukan' ?>
                        </td>

                        <td>
                            Rp<?= number_format(
                                $baris['status_pengembalian'] === 'selesai'
                                    ? (float) $baris['denda']
                                    : 0,
                                0,
                                ',',
                                '.'
                            ) ?>
                        </td>
                    </tr>
                <?php } ?>
            <?php } else { ?>
                <tr>
                    <td colspan="10">
                        Belum ada data pada rentang tanggal ini.
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

    <p class="tanggal-cetak">
        Dicetak pada: <?= date('d-m-Y H:i:s') ?>
    </p>

</div>

</body>
</html>