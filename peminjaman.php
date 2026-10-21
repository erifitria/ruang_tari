
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

$pengelola = in_array($role, ['admin', 'petugas']);

// AJUKAN PEMINJAMAN
if (isset($_POST['ajukan']) && $role == 'peminjam') {
    $id_alat = (int) $_POST['id_alat'];
    $jumlah = (int) $_POST['jumlah'];
    $tanggal_pinjam = $_POST['tanggal_pinjam'];
    $rencana_kembali = $_POST['rencana_kembali'];
    $jenis_jaminan = $_POST['jenis_jaminan'] ?? '';

    $tgl_pinjam = DateTime::createFromFormat('Y-m-d', $tanggal_pinjam);
    $tgl_kembali = DateTime::createFromFormat('Y-m-d', $rencana_kembali);

    $tanggal_valid = $tgl_pinjam &&
        $tgl_pinjam->format('Y-m-d') == $tanggal_pinjam &&
        $tgl_kembali &&
        $tgl_kembali->format('Y-m-d') == $rencana_kembali &&
        $tanggal_pinjam >= date('Y-m-d') &&
        $rencana_kembali >= $tanggal_pinjam;

    if (
        $id_alat <= 0 ||
        $jumlah <= 0 ||
        !$tanggal_valid ||
        !in_array($jenis_jaminan, ['KTP', 'Kartu Pelajar'])
    ) {
        $pesan = "Periksa kembali properti, jumlah, tanggal, dan jenis jaminan!";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT stok_tersedia, kondisi, biaya_peminjaman
             FROM alat WHERE id_alat = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_alat);
        mysqli_stmt_execute($stmt);
        $alat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (
            !$alat ||
            $alat['kondisi'] != 'Baik' ||
            $alat['stok_tersedia'] < $jumlah
        ) {
            $pesan = "Stok tidak mencukupi atau kondisi properti tidak layak dipinjam!";
        } else {
            $biaya_per_alat = (float) $alat['biaya_peminjaman'];
            $total_biaya = $biaya_per_alat * $jumlah;

            mysqli_begin_transaction($koneksi);

            try {
                $stmt = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO peminjaman
                     (id_peminjam, tanggal_pinjam, rencana_kembali, status)
                     VALUES (?, ?, ?, 'menunggu')"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "iss",
                    $id_user,
                    $tanggal_pinjam,
                    $rencana_kembali
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Gagal menyimpan peminjaman.");
                }

                $id_peminjaman = mysqli_insert_id($koneksi);

                $stmt = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO detail_peminjaman
                     (id_peminjaman, id_alat, jumlah,
                      biaya_per_alat, total_biaya,
                      jenis_jaminan, status_pembayaran, status_jaminan)
                     VALUES (?, ?, ?, ?, ?, ?, 'belum_bayar', 'belum_diserahkan')"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "iiidds",
                    $id_peminjaman,
                    $id_alat,
                    $jumlah,
                    $biaya_per_alat,
                    $total_biaya,
                    $jenis_jaminan
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Gagal menyimpan detail peminjaman.");
                }

                $aktivitas = "Mengajukan peminjaman properti";

                $stmt = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO log_aktivitas (id_user, aktivitas)
                     VALUES (?, ?)"
                );

                mysqli_stmt_bind_param($stmt, "is", $id_user, $aktivitas);
                mysqli_stmt_execute($stmt);

                mysqli_commit($koneksi);
                header("Location: peminjaman.php?pesan=ajukan");
                exit;
            } catch (Throwable $e) {
                mysqli_rollback($koneksi);
                $pesan = "Peminjaman gagal disimpan. Silakan coba lagi.";
            }
        }
    }
}

// SETUJUI PEMINJAMAN
if (isset($_POST['setujui']) && $pengelola) {
    $id = (int) $_POST['id_peminjaman'];

    mysqli_begin_transaction($koneksi);

    try {
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT status FROM peminjaman
             WHERE id_peminjaman = ? FOR UPDATE"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $pinjam = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$pinjam || $pinjam['status'] != 'menunggu') {
            throw new Exception("Permintaan sudah diproses.");
        }

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT d.id_alat, d.jumlah, a.stok_tersedia, a.kondisi
             FROM detail_peminjaman d
             JOIN alat a ON d.id_alat = a.id_alat
             WHERE d.id_peminjaman = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $detail = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($detail) == 0) {
            throw new Exception("Detail peminjaman tidak ditemukan.");
        }

        while ($item = mysqli_fetch_assoc($detail)) {
            if (
                $item['kondisi'] != 'Baik' ||
                $item['stok_tersedia'] < $item['jumlah']
            ) {
                throw new Exception("Stok tidak cukup atau properti tidak layak.");
            }

            $stmt_stok = mysqli_prepare(
                $koneksi,
                "UPDATE alat
                 SET stok_tersedia = stok_tersedia - ?
                 WHERE id_alat = ? AND stok_tersedia >= ?"
            );

            mysqli_stmt_bind_param(
                $stmt_stok,
                "iii",
                $item['jumlah'],
                $item['id_alat'],
                $item['jumlah']
            );

            mysqli_stmt_execute($stmt_stok);

            if (mysqli_stmt_affected_rows($stmt_stok) != 1) {
                throw new Exception("Stok berubah. Periksa kembali persediaan.");
            }
        }

        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE peminjaman
             SET status = 'disetujui', id_petugas = ?
             WHERE id_peminjaman = ? AND status = 'menunggu'"
        );

        mysqli_stmt_bind_param($stmt, "ii", $id_user, $id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) != 1) {
            throw new Exception("Status peminjaman gagal diperbarui.");
        }

        $aktivitas = "Menyetujui peminjaman #" . $id;

        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO log_aktivitas (id_user, aktivitas)
             VALUES (?, ?)"
        );

        mysqli_stmt_bind_param($stmt, "is", $id_user, $aktivitas);
        mysqli_stmt_execute($stmt);

        mysqli_commit($koneksi);
        header("Location: peminjaman.php?pesan=setuju");
        exit;
    } catch (Throwable $e) {
        mysqli_rollback($koneksi);
        $pesan = $e->getMessage();
    }
}

// CATAT PEMBAYARAN LUNAS DAN TERIMA JAMINAN
if (isset($_POST['catat_pembayaran']) && $pengelola) {
    $id = (int) $_POST['id_peminjaman'];

    mysqli_begin_transaction($koneksi);

    try {
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT status FROM peminjaman
             WHERE id_peminjaman = ? FOR UPDATE"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $pinjam = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$pinjam || $pinjam['status'] != 'disetujui') {
            throw new Exception("Peminjaman belum disetujui.");
        }

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT id_detail, status_pembayaran,
                    status_jaminan, jenis_jaminan
             FROM detail_peminjaman
             WHERE id_peminjaman = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $detail = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($detail) == 0) {
            throw new Exception("Detail peminjaman tidak ditemukan.");
        }

        while ($item = mysqli_fetch_assoc($detail)) {
            if (
                !in_array($item['jenis_jaminan'], ['KTP', 'Kartu Pelajar'])
            ) {
                throw new Exception("Jenis jaminan peminjam belum dipilih.");
            }

            if (
                $item['status_pembayaran'] == 'lunas' ||
                $item['status_jaminan'] != 'belum_diserahkan'
            ) {
                throw new Exception("Pembayaran atau jaminan sudah dicatat sebelumnya.");
            }
        }

        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE detail_peminjaman
             SET status_pembayaran = 'lunas',
                 status_jaminan = 'ditahan'
             WHERE id_peminjaman = ?
               AND status_pembayaran = 'belum_bayar'
               AND status_jaminan = 'belum_diserahkan'"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) == 0) {
            throw new Exception("Data pembayaran dan jaminan gagal diperbarui.");
        }

        $aktivitas = "Mencatat pembayaran lunas dan menerima jaminan peminjaman #" . $id;

        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO log_aktivitas (id_user, aktivitas)
             VALUES (?, ?)"
        );

        mysqli_stmt_bind_param($stmt, "is", $id_user, $aktivitas);
        mysqli_stmt_execute($stmt);

        mysqli_commit($koneksi);
        header("Location: peminjaman.php?pesan=catat");
        exit;
    } catch (Throwable $e) {
        mysqli_rollback($koneksi);
        $pesan = $e->getMessage();
    }
}

// TOLAK PEMINJAMAN
if (isset($_POST['tolak']) && $pengelola) {
    $id = (int) $_POST['id_peminjaman'];

    $stmt = mysqli_prepare(
        $koneksi,
        "UPDATE peminjaman
         SET status = 'ditolak', id_petugas = ?
         WHERE id_peminjaman = ? AND status = 'menunggu'"
    );

    mysqli_stmt_bind_param($stmt, "ii", $id_user, $id);

    if (
        mysqli_stmt_execute($stmt) &&
        mysqli_stmt_affected_rows($stmt) == 1
    ) {
        $aktivitas = "Menolak peminjaman #" . $id;

        $log = mysqli_prepare(
            $koneksi,
            "INSERT INTO log_aktivitas (id_user, aktivitas)
             VALUES (?, ?)"
        );

        mysqli_stmt_bind_param($log, "is", $id_user, $aktivitas);
        mysqli_stmt_execute($log);

        header("Location: peminjaman.php?pesan=tolak");
        exit;
    } else {
        $pesan = "Peminjaman gagal ditolak atau sudah diproses.";
    }
}

// PESAN HASIL
if (isset($_GET['pesan'])) {
    $daftar_pesan = [
        'ajukan' => 'Permintaan peminjaman berhasil diajukan!',
        'setuju' => 'Peminjaman berhasil disetujui!',
        'tolak' => 'Peminjaman berhasil ditolak!',
        'catat' => 'Pembayaran lunas dan jaminan berhasil dicatat!'
    ];

    if (isset($daftar_pesan[$_GET['pesan']])) {
        $pesan = $daftar_pesan[$_GET['pesan']];
    }
}

// DAFTAR PROPERTI UNTUK FORM
$alat = mysqli_query(
    $koneksi,
    "SELECT id_alat, kode_alat, nama_alat,
            stok_tersedia, biaya_peminjaman
     FROM alat
     WHERE stok_tersedia > 0 AND kondisi = 'Baik'
     ORDER BY nama_alat ASC"
);

// DAFTAR PEMINJAMAN
$sql = "SELECT p.id_peminjaman, p.id_peminjam,
               p.id_petugas, p.tanggal_pinjam,
               p.rencana_kembali, p.status,
               u.nama AS nama_peminjam,
               pet.nama AS nama_petugas,
               GROUP_CONCAT(
                   CONCAT(a.nama_alat, ' (', d.jumlah, ')')
                   ORDER BY a.nama_alat SEPARATOR ', '
               ) AS daftar_alat,
               COALESCE(SUM(d.total_biaya), 0) AS total_biaya,
               GROUP_CONCAT(DISTINCT d.status_pembayaran) AS pembayaran,
               GROUP_CONCAT(DISTINCT d.status_jaminan) AS jaminan,
               GROUP_CONCAT(DISTINCT d.jenis_jaminan) AS jenis_jaminan
        FROM peminjaman p
        JOIN users u ON p.id_peminjam = u.id_user
        LEFT JOIN users pet ON p.id_petugas = pet.id_user
        LEFT JOIN detail_peminjaman d
            ON p.id_peminjaman = d.id_peminjaman
        LEFT JOIN alat a ON d.id_alat = a.id_alat";

if ($pengelola) {
    $sql .= " GROUP BY p.id_peminjaman
              ORDER BY p.id_peminjaman DESC";
} else {
    $sql .= " WHERE p.id_peminjam = " . $id_user . "
              GROUP BY p.id_peminjaman
              ORDER BY p.id_peminjaman DESC";
}

$data = mysqli_query($koneksi, $sql);

function aman($teks) {
    return htmlspecialchars($teks ?? "", ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peminjaman - Ruang Tari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="halaman-container">

    <header class="halaman-header">
        <div>
            <h1>Ruang Tari</h1>
            <p>Pengajuan dan Pengelolaan Peminjaman</p>
        </div>
        <a href="dashboard.php" class="tombol-kembali">
            Kembali ke Dashboard
        </a>
    </header>

    <?php if ($pesan != "") { ?>
        <div class="pesan"><?= aman($pesan) ?></div>
    <?php } ?>

    <?php if ($role == 'peminjam') { ?>
        <h2>Ajukan Peminjaman</h2>

        <form method="POST" class="form-data">

            <label>Pilih Properti</label>
            <select name="id_alat" id="id_alat" required>
                <option value="">-- Pilih Properti --</option>
                <?php while ($item = mysqli_fetch_assoc($alat)) { ?>
                    <option value="<?= (int) $item['id_alat'] ?>"
                        data-biaya="<?= (float) $item['biaya_peminjaman'] ?>">
                        <?= aman($item['kode_alat']) ?> -
                        <?= aman($item['nama_alat']) ?>
                        (Tersedia: <?= (int) $item['stok_tersedia'] ?>)
                        - Rp<?= number_format((float) $item['biaya_peminjaman'], 0, ',', '.') ?>/alat
                    </option>
                <?php } ?>
            </select>

            <label>Jenis Jaminan</label>
            <select name="jenis_jaminan" required>
                <option value="">-- Pilih Jenis Jaminan --</option>
                <option value="KTP">KTP</option>
                <option value="Kartu Pelajar">Kartu Pelajar</option>
            </select>

            <label>Jumlah</label>
            <input type="number" name="jumlah" id="jumlah" min="1" required>

            <p id="total-biaya">
                Total biaya sewa: pilih properti dan jumlah terlebih dahulu.
            </p>

            <label>Tanggal Pinjam</label>
            <input type="date" name="tanggal_pinjam"
                   min="<?= date('Y-m-d') ?>"
                   value="<?= date('Y-m-d') ?>" required>

            <label>Rencana Kembali</label>
            <input type="date" name="rencana_kembali"
                   min="<?= date('Y-m-d') ?>" required>

            <button type="submit" name="ajukan">
                Ajukan Peminjaman
            </button>
        </form>

        <script>
        const pilihanAlat = document.getElementById('id_alat');
        const jumlahAlat = document.getElementById('jumlah');
        const totalBiaya = document.getElementById('total-biaya');

        function hitungBiaya() {
            const opsi = pilihanAlat.options[pilihanAlat.selectedIndex];
            const biaya = Number(opsi.dataset.biaya || 0);
            const jumlah = Number(jumlahAlat.value || 0);
            const total = biaya * jumlah;

            if (!pilihanAlat.value || jumlah <= 0) {
                totalBiaya.textContent =
                    'Total biaya sewa: pilih properti dan jumlah terlebih dahulu.';
                return;
            }

            totalBiaya.textContent = 'Total biaya sewa: Rp' +
                total.toLocaleString('id-ID');
        }

        pilihanAlat.addEventListener('change', hitungBiaya);
        jumlahAlat.addEventListener('input', hitungBiaya);
        </script>
    <?php } ?>

    <h2><?= $pengelola ? "Semua Data Peminjaman" : "Peminjaman Saya" ?></h2>

    <div class="tabel-container">
        <table class="tabel-data">
            <thead>
                <tr>
                    <th>No.</th>
                    <?php if ($pengelola) { ?>
                        <th>Peminjam</th>
                    <?php } ?>
                    <th>Properti</th>
                    <th>Tanggal Pinjam</th>
                    <th>Rencana Kembali</th>
                    <th>Petugas</th>
                    <th>Total Biaya</th>
                    <th>Pembayaran</th>
                    <th>Jenis Jaminan</th>
                    <th>Status Jaminan</th>
                    <th>Status Peminjaman</th>
                    <?php if ($pengelola) { ?>
                        <th>Aksi</th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($data && mysqli_num_rows($data) > 0) { ?>
                    <?php $no = 1; ?>
                    <?php while ($row = mysqli_fetch_assoc($data)) { ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <?php if ($pengelola) { ?>
                                <td><?= aman($row['nama_peminjam']) ?></td>
                            <?php } ?>

                            <td><?= aman($row['daftar_alat'] ?? '-') ?></td>
                            <td><?= aman($row['tanggal_pinjam']) ?></td>
                            <td><?= aman($row['rencana_kembali']) ?></td>
                            <td><?= aman($row['nama_petugas'] ?? '-') ?></td>

                            <td>
                                Rp<?= number_format((float) $row['total_biaya'], 0, ',', '.') ?>
                            </td>

                            <td><?= aman($row['pembayaran'] ?? 'belum_bayar') ?></td>
                            <td><?= aman($row['jenis_jaminan'] ?? '-') ?></td>
                            <td><?= aman($row['jaminan'] ?? 'belum_diserahkan') ?></td>
                            <td><?= aman(ucfirst($row['status'])) ?></td>

                            <?php if ($pengelola) { ?>
                                <td>
                                    <?php if ($row['status'] == 'menunggu') { ?>
                                        <div class="aksi-data">
                                            <form method="POST"
                                                onsubmit="return confirm('Setujui peminjaman ini?')">
                                                <input type="hidden"
                                                    name="id_peminjaman"
                                                    value="<?= (int) $row['id_peminjaman'] ?>">
                                                <button type="submit" name="setujui">
                                                    Setujui
                                                </button>
                                            </form>

                                            <form method="POST"
                                                onsubmit="return confirm('Tolak peminjaman ini?')">
                                                <input type="hidden"
                                                    name="id_peminjaman"
                                                    value="<?= (int) $row['id_peminjaman'] ?>">
                                                <button type="submit" name="tolak"
                                                    class="tombol-hapus">
                                                    Tolak
                                                </button>
                                            </form>
                                        </div>

                                    <?php } elseif (
                                        $row['status'] == 'disetujui' &&
                                        strpos((string) $row['pembayaran'], 'belum_bayar') !== false
                                    ) { ?>

                                        <form method="POST"
                                            onsubmit="return confirm('Pastikan pembayaran sudah lunas dan kartu identitas fisik sudah diterima sebelum mencatatnya. Lanjutkan?')">

                                            <p>
                                                Jenis jaminan:
                                                <strong><?= aman($row['jenis_jaminan'] ?? '-') ?></strong>
                                            </p>

                                            <button type="submit"
                                                name="catat_pembayaran">
                                                <input type="hidden"
                                                    name="id_peminjaman"
                                                    value="<?= (int) $row['id_peminjaman'] ?>">
                                                Catat Lunas & Terima Jaminan
                                            </button>
                                        </form>

                                    <?php } else { ?>
                                        -
                                    <?php } ?>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="<?= $pengelola ? 12 : 10 ?>">
                            Belum ada data peminjaman.
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
