
<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$role = $_SESSION['role'];
$pesan = "";

if (!in_array($role, ['admin', 'petugas', 'peminjam'])) {
    exit("Akses ditolak!");
}

/* Pengajuan pengembalian oleh peminjam */
if (isset($_POST['ajukan']) && $role === 'peminjam') {
    $id_detail = (int) $_POST['id_detail'];
    $kondisi = $_POST['kondisi_kembali'];

    if (!in_array($kondisi, ['Baik', 'Rusak Ringan', 'Rusak Berat'])) {
        $pesan = "Kondisi barang tidak valid.";
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            $cek = mysqli_prepare(
                $koneksi,
                "SELECT dp.id_detail
                 FROM detail_peminjaman dp
                 JOIN peminjaman p
                   ON p.id_peminjaman = dp.id_peminjaman
                 JOIN detail_peminjaman d
                   ON d.id_detail = dp.id_detail
                 WHERE dp.id_detail = ?
                   AND p.id_peminjam = ?
                   AND p.status = 'disetujui'
                   AND d.status_pembayaran = 'lunas'
                   AND d.status_jaminan = 'ditahan'
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param($cek, "ii", $id_detail, $id_user);
            mysqli_stmt_execute($cek);
            $hasil = mysqli_stmt_get_result($cek);

            if (!mysqli_fetch_assoc($hasil)) {
                throw new Exception(
                    "Barang tidak ditemukan, belum disetujui, atau pembayaran dan jaminan belum lengkap."
                );
            }

            $cek2 = mysqli_prepare(
                $koneksi,
                "SELECT id_pengembalian
                 FROM pengembalian
                 WHERE id_detail = ?
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param($cek2, "i", $id_detail);
            mysqli_stmt_execute($cek2);

            if (mysqli_num_rows(mysqli_stmt_get_result($cek2)) > 0) {
                throw new Exception(
                    "Pengembalian untuk barang ini sudah diajukan."
                );
            }

            $stmt = mysqli_prepare(
                $koneksi,
                "INSERT INTO pengembalian
                 (id_detail, id_petugas, tanggal_kembali,
                  denda, kondisi_kembali, status)
                 VALUES (?, NULL, CURDATE(), 0, ?, 'menunggu')"
            );

            mysqli_stmt_bind_param($stmt, "is", $id_detail, $kondisi);
            mysqli_stmt_execute($stmt);

            $aktivitas = "Mengajukan pengembalian barang";

            $log = mysqli_prepare(
                $koneksi,
                "INSERT INTO log_aktivitas (id_user, aktivitas)
                 VALUES (?, ?)"
            );

            mysqli_stmt_bind_param($log, "is", $id_user, $aktivitas);
            mysqli_stmt_execute($log);

            mysqli_commit($koneksi);
            $pesan = "Pengajuan pengembalian berhasil dikirim.";
        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $pesan = $e->getMessage();
        }
    }
}

/* Konfirmasi pengembalian oleh admin atau petugas */
if (
    isset($_POST['konfirmasi']) &&
    in_array($role, ['admin', 'petugas'])
) {
    $id_pengembalian = (int) $_POST['id_pengembalian'];

    mysqli_begin_transaction($koneksi);

    try {
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT pg.id_pengembalian,
                    pg.id_detail,
                    pg.tanggal_kembali,
                    pg.status,
                    pg.kondisi_kembali,
                    dp.id_alat,
                    dp.id_peminjaman,
                    p.rencana_kembali,
                    dp.status_jaminan
             FROM pengembalian pg
             JOIN detail_peminjaman dp
               ON dp.id_detail = pg.id_detail
             JOIN peminjaman p
               ON p.id_peminjaman = dp.id_peminjaman
             WHERE pg.id_pengembalian = ?
             FOR UPDATE"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_pengembalian);
        mysqli_stmt_execute($stmt);

        $data = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmt)
        );

        if (!$data || $data['status'] !== 'menunggu') {
            throw new Exception(
                "Pengajuan tidak ditemukan atau sudah diproses."
            );
        }

        if ($data['status_jaminan'] !== 'ditahan') {
            throw new Exception(
                "Jaminan KTP atau Kartu Pelajar belum tercatat ditahan."
            );
        }

        $tanggal_kembali = new DateTime($data['tanggal_kembali']);
        $rencana_kembali = new DateTime($data['rencana_kembali']);

        $terlambat = 0;

        if ($tanggal_kembali > $rencana_kembali) {
            $terlambat = $rencana_kembali
                ->diff($tanggal_kembali)->days;
        }

        /* Denda Rp5.000 per hari keterlambatan */
        $denda = $terlambat * 5000;

        $ubah = mysqli_prepare(
            $koneksi,
            "UPDATE pengembalian
             SET id_petugas = ?,
                 denda = ?,
                 status = 'selesai'
             WHERE id_pengembalian = ?
               AND status = 'menunggu'"
        );

        mysqli_stmt_bind_param(
            $ubah,
            "idi",
            $id_user,
            $denda,
            $id_pengembalian
        );

        mysqli_stmt_execute($ubah);

        if (mysqli_stmt_affected_rows($ubah) !== 1) {
            throw new Exception("Pengembalian gagal diproses.");
        }

        /* Tambah stok hanya jika barang kembali dalam kondisi baik */
        $id_alat = (int) $data['id_alat'];

        if ($data['kondisi_kembali'] === 'Baik') {
            $stok = mysqli_prepare(
                $koneksi,
                "UPDATE alat
                 SET stok_tersedia = stok_tersedia + 1
                 WHERE id_alat = ?
                   AND stok_tersedia < stok_total"
            );

            mysqli_stmt_bind_param($stok, "i", $id_alat);
            mysqli_stmt_execute($stok);

            if (mysqli_stmt_affected_rows($stok) !== 1) {
                throw new Exception("Stok tidak dapat diperbarui.");
            }
        }

        /* Kembalikan status jaminan fisik */
        $jaminan = mysqli_prepare(
            $koneksi,
            "UPDATE detail_peminjaman
             SET status_jaminan = 'dikembalikan',
                 jaminan_dikembalikan = 0
             WHERE id_detail = ?
               AND status_jaminan = 'ditahan'"
        );

        $id_detail = (int) $data['id_detail'];

        mysqli_stmt_bind_param($jaminan, "i", $id_detail);
        mysqli_stmt_execute($jaminan);

        if (mysqli_stmt_affected_rows($jaminan) !== 1) {
            throw new Exception(
                "Status pengembalian jaminan gagal diperbarui."
            );
        }

        $id_peminjaman = (int) $data['id_peminjaman'];

        /* Selesaikan peminjaman jika semua barang sudah dikembalikan */
        $cek_sisa = mysqli_prepare(
            $koneksi,
            "SELECT COUNT(*) AS jumlah_sisa
             FROM detail_peminjaman dp
             WHERE dp.id_peminjaman = ?
               AND NOT EXISTS (
                   SELECT 1
                   FROM pengembalian pg
                   WHERE pg.id_detail = dp.id_detail
                     AND pg.status = 'selesai'
               )"
        );

        mysqli_stmt_bind_param($cek_sisa, "i", $id_peminjaman);
        mysqli_stmt_execute($cek_sisa);

        $sisa = mysqli_fetch_assoc(
            mysqli_stmt_get_result($cek_sisa)
        );

        if ((int) $sisa['jumlah_sisa'] === 0) {
            $selesai = mysqli_prepare(
                $koneksi,
                "UPDATE peminjaman
                 SET status = 'selesai'
                 WHERE id_peminjaman = ?"
            );

            mysqli_stmt_bind_param(
                $selesai,
                "i",
                $id_peminjaman
            );

            mysqli_stmt_execute($selesai);
        }

        $aktivitas = "Mengonfirmasi pengembalian barang dan mengembalikan jaminan";

        $log = mysqli_prepare(
            $koneksi,
            "INSERT INTO log_aktivitas (id_user, aktivitas)
             VALUES (?, ?)"
        );

        mysqli_stmt_bind_param($log, "is", $id_user, $aktivitas);
        mysqli_stmt_execute($log);

        mysqli_commit($koneksi);

        $pesan = "Pengembalian berhasil dikonfirmasi. "
            . "Jaminan KTP/Kartu Pelajar dikembalikan. Denda: Rp"
            . number_format($denda, 0, ',', '.');

    } catch (Throwable $e) {
        mysqli_rollback($koneksi);
        $pesan = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengembalian - Ruang Tari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="halaman-container">
    <div class="halaman-header">
        <div>
            <h1>Pengembalian Properti Tari</h1>
            <p>Kelola pengajuan dan konfirmasi pengembalian.</p>
        </div>
        <a href="dashboard.php" class="tombol-kembali">Kembali</a>
    </div>

    <?php if ($pesan !== "") { ?>
        <div class="pesan">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php } ?>

    <?php if ($role === 'peminjam') { ?>
        <h2>Ajukan Pengembalian</h2>

        <div class="form-data">
            <form method="POST">
                <label>Pilih barang yang akan dikembalikan</label>

                <select name="id_detail" required>
                    <option value="">-- Pilih barang --</option>

                    <?php
                    $stmt = mysqli_prepare(
                        $koneksi,
                        "SELECT dp.id_detail,
                                a.nama_alat,
                                dp.jumlah,
                                p.tanggal_pinjam,
                                p.rencana_kembali
                         FROM detail_peminjaman dp
                         JOIN peminjaman p
                           ON p.id_peminjaman = dp.id_peminjaman
                         JOIN alat a
                           ON a.id_alat = dp.id_alat
                         JOIN pengembalian pg
                           ON pg.id_detail = dp.id_detail
                         WHERE p.id_peminjam = ?
                           AND p.status = 'disetujui'
                           AND dp.status_pembayaran = 'lunas'
                           AND dp.status_jaminan = 'ditahan'
                           AND pg.id_pengembalian IS NULL
                         ORDER BY p.tanggal_pinjam DESC"
                    );

                    /*
                     * Query di atas perlu menampilkan barang yang BELUM
                     * memiliki pengajuan pengembalian. Gunakan LEFT JOIN
                     * agar barang tersebut tetap muncul.
                     */
                    mysqli_stmt_close($stmt);

                    $stmt = mysqli_prepare(
                        $koneksi,
                        "SELECT dp.id_detail,
                                a.nama_alat,
                                dp.jumlah,
                                p.tanggal_pinjam,
                                p.rencana_kembali
                         FROM detail_peminjaman dp
                         JOIN peminjaman p
                           ON p.id_peminjaman = dp.id_peminjaman
                         JOIN alat a
                           ON a.id_alat = dp.id_alat
                         LEFT JOIN pengembalian pg
                           ON pg.id_detail = dp.id_detail
                         WHERE p.id_peminjam = ?
                           AND p.status = 'disetujui'
                           AND dp.status_pembayaran = 'lunas'
                           AND dp.status_jaminan = 'ditahan'
                           AND pg.id_pengembalian IS NULL
                         ORDER BY p.tanggal_pinjam DESC"
                    );

                    mysqli_stmt_bind_param($stmt, "i", $id_user);
                    mysqli_stmt_execute($stmt);
                    $daftar = mysqli_stmt_get_result($stmt);

                    while ($item = mysqli_fetch_assoc($daftar)) {
                    ?>
                        <option value="<?= (int) $item['id_detail'] ?>">
                            <?= htmlspecialchars($item['nama_alat']) ?>
                            (Jumlah: <?= (int) $item['jumlah'] ?>,
                            batas kembali:
                            <?= htmlspecialchars($item['rencana_kembali']) ?>)
                        </option>
                    <?php } ?>
                </select>

                <label>Kondisi barang saat dikembalikan</label>

                <select name="kondisi_kembali" required>
                    <option value="Baik">Baik</option>
                    <option value="Rusak Ringan">Rusak Ringan</option>
                    <option value="Rusak Berat">Rusak Berat</option>
                </select>

                <button type="submit" name="ajukan">
                    Ajukan Pengembalian
                </button>
            </form>
        </div>
    <?php } ?>

    <h2>
        <?= $role === 'peminjam'
            ? 'Status Pengembalian Saya'
            : 'Daftar Pengembalian' ?>
    </h2>

    <div class="tabel-container">
        <table class="tabel-data">
            <thead>
            <tr>
                <th>No.</th>
                <th>Peminjam</th>
                <th>Properti</th>
                <th>Jumlah</th>
                <th>Batas Kembali</th>
                <th>Tanggal Pengajuan</th>
                <th>Kondisi</th>
                <th>Denda</th>
                <th>Status</th>
                <th>Status Jaminan</th>

                <?php if (in_array($role, ['admin', 'petugas'])) { ?>
                    <th>Aksi</th>
                <?php } ?>
            </tr>
            </thead>

            <tbody>
            <?php
            $sql = "SELECT pg.*,
                           u.nama AS nama_peminjam,
                           a.nama_alat,
                           dp.jumlah,
                           dp.jenis_jaminan,
                           dp.status_jaminan,
                           p.rencana_kembali
                    FROM pengembalian pg
                    JOIN detail_peminjaman dp
                      ON dp.id_detail = pg.id_detail
                    JOIN peminjaman p
                      ON p.id_peminjaman = dp.id_peminjaman
                    JOIN users u
                      ON u.id_user = p.id_peminjam
                    JOIN alat a
                      ON a.id_alat = dp.id_alat";

            if ($role === 'peminjam') {
                $sql .= " WHERE p.id_peminjam = $id_user";
            }

            $sql .= " ORDER BY pg.id_pengembalian DESC";

            $daftar = mysqli_query($koneksi, $sql);
            $no = 1;

            while ($item = mysqli_fetch_assoc($daftar)) {
            ?>
                <tr>
                    <td><?= $no++ ?></td>

                    <td>
                        <?= htmlspecialchars($item['nama_peminjam']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($item['nama_alat']) ?>
                    </td>

                    <td><?= (int) $item['jumlah'] ?></td>

                    <td>
                        <?= htmlspecialchars($item['rencana_kembali']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($item['tanggal_kembali']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($item['kondisi_kembali']) ?>
                    </td>

                    <td>
                        Rp<?= number_format($item['denda'], 0, ',', '.') ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(ucfirst($item['status'])) ?>
                    </td>

                    <td>
                        <?php
                        if ($item['status_jaminan'] === 'dikembalikan') {
                            echo "Sudah dikembalikan";
                        } elseif ($item['status_jaminan'] === 'ditahan') {
                            echo "Masih ditahan";
                        } elseif ($item['status_jaminan'] === 'dipotong') {
                            echo "Diproses";
                        } else {
                            echo "Belum diserahkan";
                        }

                        if (!empty($item['jenis_jaminan'])) {
                            echo " (" . htmlspecialchars(
                                $item['jenis_jaminan']
                            ) . ")";
                        }
                        ?>
                    </td>

                    <?php if (in_array($role, ['admin', 'petugas'])) { ?>
                        <td>
                            <?php if ($item['status'] === 'menunggu') { ?>
                                <form method="POST">
                                    <input
                                        type="hidden"
                                        name="id_pengembalian"
                                        value="<?= (int) $item['id_pengembalian'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="konfirmasi"
                                        onclick="return confirm('Konfirmasi pengembalian barang dan kembalikan jaminan?')"
                                    >
                                        Konfirmasi
                                    </button>
                                </form>
                            <?php } else { ?>
                                Selesai
                            <?php } ?>
                        </td>
                    <?php } ?>
                </tr>
            <?php } ?>

            <?php if ($daftar && mysqli_num_rows($daftar) === 0) { ?>
                <tr>
                    <td colspan="<?= in_array($role, ['admin', 'petugas']) ? 11 : 10 ?>">
                        Belum ada data pengembalian.
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
