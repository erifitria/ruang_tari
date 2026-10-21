
<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['id_user']) || $_SESSION['role'] != 'peminjam') {
    header("Location: login.php");
    exit;
}

$id_peminjam = (int) $_SESSION['id_user'];
$pesan = "";

if (isset($_POST['ajukan_kembali'])) {
    $id_detail = (int) $_POST['id_detail'];
    $kondisi = $_POST['kondisi_kembali'] ?? '';

    if (!in_array($kondisi, ['Baik', 'Rusak Ringan', 'Rusak Berat'])) {
        $pesan = "Kondisi pengembalian tidak valid.";
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            // Pastikan peminjaman milik pengguna yang sedang login.
            // Pembayaran harus lunas dan jaminan masih ditahan.
            $cek = mysqli_prepare($koneksi, "
                SELECT dp.id_detail
                FROM detail_peminjaman dp
                JOIN peminjaman p
                    ON dp.id_peminjaman = p.id_peminjaman
                WHERE dp.id_detail = ?
                  AND p.id_peminjam = ?
                  AND p.status = 'disetujui'
                  AND dp.status_pembayaran = 'lunas'
                  AND dp.status_jaminan = 'ditahan'
                  AND NOT EXISTS (
                      SELECT 1
                      FROM pengembalian pg
                      WHERE pg.id_detail = dp.id_detail
                  )
                FOR UPDATE
            ");

            mysqli_stmt_bind_param($cek, "ii", $id_detail, $id_peminjam);
            mysqli_stmt_execute($cek);
            $hasil_cek = mysqli_stmt_get_result($cek);

            if (mysqli_num_rows($hasil_cek) == 0) {
                throw new Exception(
                    "Data tidak valid, pembayaran belum lunas, jaminan belum diterima, atau pengembalian sudah diajukan."
                );
            }

            $tanggal = date('Y-m-d');

            // id_petugas NULL sampai petugas mengonfirmasi.
            $simpan = mysqli_prepare($koneksi, "
                INSERT INTO pengembalian
                    (id_detail, id_petugas, tanggal_kembali, denda,
                     kondisi_kembali, status)
                VALUES (?, NULL, ?, 0, ?, 'menunggu')
            ");

            mysqli_stmt_bind_param(
                $simpan,
                "iss",
                $id_detail,
                $tanggal,
                $kondisi
            );

            mysqli_stmt_execute($simpan);

            $aktivitas = "Mengajukan pengembalian barang";

            $log = mysqli_prepare($koneksi, "
                INSERT INTO log_aktivitas (id_user, aktivitas)
                VALUES (?, ?)
            ");

            mysqli_stmt_bind_param($log, "is", $id_peminjam, $aktivitas);
            mysqli_stmt_execute($log);

            mysqli_commit($koneksi);

            $pesan = "Pengajuan pengembalian berhasil dikirim. Tunggu konfirmasi petugas.";
        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $pesan = $e->getMessage();
        }
    }
}

$query = mysqli_query($koneksi, "
    SELECT
        dp.id_detail,
        a.nama_alat,
        dp.jumlah,
        p.tanggal_pinjam,
        p.rencana_kembali,
        p.status AS status_peminjaman,
        dp.status_pembayaran,
        dp.jenis_jaminan,
        dp.status_jaminan,
        pg.tanggal_kembali,
        pg.kondisi_kembali,
        pg.denda,
        pg.status AS status_pengembalian
    FROM detail_peminjaman dp
    JOIN peminjaman p
        ON dp.id_peminjaman = p.id_peminjaman
    JOIN alat a
        ON dp.id_alat = a.id_alat
    LEFT JOIN pengembalian pg
        ON dp.id_detail = pg.id_detail
    WHERE p.id_peminjam = $id_peminjam
      AND p.status IN ('disetujui', 'selesai')
    ORDER BY dp.id_detail DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengembalian Saya - Ruang Tari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="halaman-container">

    <header class="halaman-header">
        <div>
            <h1>💃 Pengembalian Saya</h1>
            <p>Ajukan pengembalian dan lihat statusnya</p>
        </div>

        <a href="dashboard.php" class="tombol-kembali">
            Kembali ke Dashboard
        </a>
    </header>

    <?php if ($pesan != "") { ?>
        <div class="pesan">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php } ?>

    <div class="tabel-container">
        <table class="tabel-data">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Properti Tari</th>
                    <th>Jumlah</th>
                    <th>Tanggal Pinjam</th>
                    <th>Rencana Kembali</th>
                    <th>Status Pembayaran</th>
                    <th>Jenis Jaminan</th>
                    <th>Status Jaminan</th>
                    <th>Status Pengembalian</th>
                    <th>Denda</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
            <?php
            $no = 1;

            if ($query && mysqli_num_rows($query) > 0) {
                while ($data = mysqli_fetch_assoc($query)) {
                    $status = $data['status_pengembalian'];
            ?>
                <tr>
                    <td><?= $no++ ?></td>

                    <td>
                        <?= htmlspecialchars($data['nama_alat']) ?>
                    </td>

                    <td><?= (int) $data['jumlah'] ?></td>

                    <td>
                        <?= date('d-m-Y', strtotime($data['tanggal_pinjam'])) ?>
                    </td>

                    <td>
                        <?= date('d-m-Y', strtotime($data['rencana_kembali'])) ?>
                    </td>

                    <td>
                        <?php
                        if ($data['status_pembayaran'] === 'lunas') {
                            echo 'Lunas';
                        } else {
                            echo 'Belum Bayar';
                        }
                        ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $data['jenis_jaminan'] ?: 'Belum Dipilih'
                        ) ?>
                    </td>

                    <td>
                        <?php
                        if ($data['status_jaminan'] === 'dikembalikan') {
                            echo 'Sudah Dikembalikan';
                        } elseif ($data['status_jaminan'] === 'ditahan') {
                            echo 'Sedang Ditahan';
                        } elseif ($data['status_jaminan'] === 'dipotong') {
                            echo 'Diproses';
                        } else {
                            echo 'Belum Diserahkan';
                        }
                        ?>
                    </td>

                    <td>
                        <?php
                        if ($status == 'selesai') {
                            echo 'Selesai';
                        } elseif ($status == 'menunggu') {
                            echo 'Menunggu Konfirmasi';
                        } else {
                            echo 'Belum Diajukan';
                        }
                        ?>
                    </td>

                    <td>
                        Rp<?= number_format(
                            (float) ($data['denda'] ?? 0),
                            0,
                            ',',
                            '.'
                        ) ?>
                    </td>

                    <td>
                        <?php if (
                            $status === null
                            && $data['status_peminjaman'] === 'disetujui'
                            && $data['status_pembayaran'] === 'lunas'
                            && $data['status_jaminan'] === 'ditahan'
                        ) { ?>
                            <form method="POST" action="pengembalian_saya.php">
                                <input
                                    type="hidden"
                                    name="id_detail"
                                    value="<?= (int) $data['id_detail'] ?>"
                                >

                                <select name="kondisi_kembali" required>
                                    <option value="">Pilih kondisi</option>
                                    <option value="Baik">Baik</option>
                                    <option value="Rusak Ringan">Rusak Ringan</option>
                                    <option value="Rusak Berat">Rusak Berat</option>
                                </select>

                                <button type="submit" name="ajukan_kembali">
                                    Ajukan Pengembalian
                                </button>
                            </form>
                        <?php } elseif ($status === null) { ?>
                            Belum dapat diajukan
                        <?php } elseif ($status == 'menunggu') { ?>
                            Menunggu petugas
                        <?php } else { ?>
                            Selesai
                        <?php } ?>
                    </td>
                </tr>
            <?php
                }
            } else {
            ?>
                <tr>
                    <td colspan="11" style="text-align:center;">
                        Belum ada properti yang dapat dikembalikan.
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
