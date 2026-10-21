
<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['id_user']) || $_SESSION['role'] != 'peminjam') {
    header("Location: login.php");
    exit;
}

$id_peminjam = (int) $_SESSION['id_user'];

$query = mysqli_query($koneksi, "
    SELECT
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.rencana_kembali,
        p.status,
        GROUP_CONCAT(
            CONCAT(a.nama_alat, ' (', dp.jumlah, ')')
            SEPARATOR ', '
        ) AS daftar_alat,
        COALESCE(SUM(dp.total_biaya), 0) AS total_biaya,
        GROUP_CONCAT(
            DISTINCT dp.status_pembayaran
            SEPARATOR ', '
        ) AS status_pembayaran,
        GROUP_CONCAT(
            DISTINCT dp.jenis_jaminan
            SEPARATOR ', '
        ) AS jenis_jaminan,
        GROUP_CONCAT(
            DISTINCT dp.status_jaminan
            SEPARATOR ', '
        ) AS status_jaminan
    FROM peminjaman p
    LEFT JOIN detail_peminjaman dp
        ON p.id_peminjaman = dp.id_peminjaman
    LEFT JOIN alat a
        ON dp.id_alat = a.id_alat
    WHERE p.id_peminjam = '$id_peminjam'
    GROUP BY
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.rencana_kembali,
        p.status
    ORDER BY p.id_peminjaman DESC
");

function formatStatus($status)
{
    switch ($status) {
        case 'menunggu':
            return 'Menunggu Persetujuan';
        case 'disetujui':
            return 'Disetujui';
        case 'ditolak':
            return 'Ditolak';
        case 'selesai':
            return 'Selesai';
        default:
            return ucfirst($status);
    }
}

function formatPembayaran($status)
{
    if ($status === null || $status === '') {
        return '-';
    }

    $daftar = explode(', ', $status);
    $hasil = [];

    foreach ($daftar as $item) {
        if ($item === 'lunas') {
            $hasil[] = 'Lunas';
        } elseif ($item === 'belum_bayar') {
            $hasil[] = 'Belum Bayar';
        } else {
            $hasil[] = ucfirst($item);
        }
    }

    return implode(', ', array_unique($hasil));
}

function formatJaminan($status)
{
    if ($status === null || $status === '') {
        return 'Belum Diserahkan';
    }

    $daftar = explode(', ', $status);
    $hasil = [];

    foreach ($daftar as $item) {
        if ($item === 'belum_diserahkan') {
            $hasil[] = 'Belum Diserahkan';
        } elseif ($item === 'ditahan') {
            $hasil[] = 'Sedang Ditahan';
        } elseif ($item === 'dikembalikan') {
            $hasil[] = 'Sudah Dikembalikan';
        } elseif ($item === 'dipotong') {
            $hasil[] = 'Diproses';
        } else {
            $hasil[] = ucfirst($item);
        }
    }

    return implode(', ', array_unique($hasil));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peminjaman Saya - Ruang Tari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="halaman-container">

    <header class="halaman-header">
        <div>
            <h1>💃 Peminjaman Saya</h1>
            <p>Riwayat peminjaman properti tari</p>
        </div>

        <a href="dashboard.php" class="tombol-kembali">
            Kembali ke Dashboard
        </a>
    </header>

    <div class="tabel-container">
        <table class="tabel-data">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Properti Tari</th>
                    <th>Tanggal Pinjam</th>
                    <th>Rencana Kembali</th>
                    <th>Total Biaya Sewa</th>
                    <th>Status Pembayaran</th>
                    <th>Jenis Jaminan</th>
                    <th>Status Jaminan</th>
                    <th>Status Peminjaman</th>
                </tr>
            </thead>

            <tbody>
                <?php
                $no = 1;

                if ($query && mysqli_num_rows($query) > 0) {
                    while ($data = mysqli_fetch_assoc($query)) {
                ?>
                    <tr>
                        <td><?= $no++ ?></td>

                        <td>
                            <?= htmlspecialchars(
                                $data['daftar_alat'] ?? '-'
                            ) ?>
                        </td>

                        <td>
                            <?= date(
                                'd-m-Y',
                                strtotime($data['tanggal_pinjam'])
                            ) ?>
                        </td>

                        <td>
                            <?= date(
                                'd-m-Y',
                                strtotime($data['rencana_kembali'])
                            ) ?>
                        </td>

                        <td>
                            Rp<?= number_format(
                                (float) $data['total_biaya'],
                                0,
                                ',',
                                '.'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                formatPembayaran(
                                    $data['status_pembayaran']
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $data['jenis_jaminan'] ?: 'Belum Dipilih'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                formatJaminan(
                                    $data['status_jaminan']
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                formatStatus($data['status'])
                            ) ?>
                        </td>
                    </tr>
                <?php
                    }
                } else {
                ?>
                    <tr>
                        <td colspan="9" style="text-align:center;">
                            Kamu belum memiliki riwayat peminjaman.
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <footer class="dashboard-footer">
        <p>Ruang Tari &copy; <?= date("Y") ?></p>
    </footer>

</div>

</body>
</html>
