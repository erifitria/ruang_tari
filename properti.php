
<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['id_user']) ||
    !in_array($_SESSION['role'], ['admin', 'petugas'])) {
    header("Location: login.php");
    exit;
}

$admin = $_SESSION['role'] == 'admin';
$pesan = "";

// TAMBAH PROPERTI
if (isset($_POST['tambah']) && $admin) {
    $id_kategori = (int) $_POST['id_kategori'];
    $kode = trim($_POST['kode_alat']);
    $nama = trim($_POST['nama_alat']);
    $stok = (int) $_POST['stok_total'];
    $kondisi = $_POST['kondisi'];
    $biaya_peminjaman = max(0, (int) $_POST['biaya_peminjaman']);

    if ($id_kategori <= 0 || $kode == "" || $nama == "" || $stok < 0) {
        $pesan = "Data wajib diisi dengan benar!";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO alat
            (id_kategori, kode_alat, nama_alat, stok_total,
             stok_tersedia, kondisi, biaya_peminjaman)
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $stmt, "issiisi",
            $id_kategori, $kode, $nama, $stok, $stok,
            $kondisi, $biaya_peminjaman
        );

        if (mysqli_stmt_execute($stmt)) {
            header("Location: properti.php?pesan=tambah");
            exit;
        } else {
            $pesan = "Gagal menambahkan properti. Periksa kode barang agar tidak duplikat.";
        }
    }
}

// EDIT PROPERTI
if (isset($_POST['edit']) && $admin) {
    $id = (int) $_POST['id_alat'];
    $id_kategori = (int) $_POST['id_kategori'];
    $kode = trim($_POST['kode_alat']);
    $nama = trim($_POST['nama_alat']);
    $stok = (int) $_POST['stok_total'];
    $kondisi = $_POST['kondisi'];
    $biaya_peminjaman = max(0, (int) $_POST['biaya_peminjaman']);

    if ($stok < 0 || $id_kategori <= 0 || $kode == "" || $nama == "") {
        $pesan = "Data wajib diisi dengan benar!";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE alat
             SET id_kategori = ?, kode_alat = ?, nama_alat = ?,
                 stok_tersedia = GREATEST(0, stok_tersedia + (? - stok_total)),
                 stok_total = ?, kondisi = ?,
                 biaya_peminjaman = ?
             WHERE id_alat = ?"
        );

        mysqli_stmt_bind_param(
            $stmt, "issiisii",
            $id_kategori, $kode, $nama, $stok, $stok,
            $kondisi, $biaya_peminjaman, $id
        );

        if (mysqli_stmt_execute($stmt)) {
            header("Location: properti.php?pesan=edit");
            exit;
        } else {
            $pesan = "Gagal mengubah properti. Periksa kode barang.";
        }
    }
}

// HAPUS PROPERTI
if (isset($_POST['hapus']) && $admin) {
    $id = (int) $_POST['id_alat'];

    $stmt = mysqli_prepare(
        $koneksi,
        "DELETE FROM alat WHERE id_alat = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: properti.php?pesan=hapus");
        exit;
    } else {
        $pesan = "Properti tidak bisa dihapus karena sudah tercatat dalam transaksi peminjaman.";
    }
}

// PESAN HASIL
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'tambah') {
        $pesan = "Properti berhasil ditambahkan!";
    } elseif ($_GET['pesan'] == 'edit') {
        $pesan = "Properti berhasil diubah!";
    } elseif ($_GET['pesan'] == 'hapus') {
        $pesan = "Properti berhasil dihapus!";
    }
}

// DATA YANG AKAN DIEDIT
$edit = null;

if (isset($_GET['edit']) && $admin) {
    $id = (int) $_GET['edit'];

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT * FROM alat WHERE id_alat = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $hasil_edit = mysqli_stmt_get_result($stmt);
    $edit = mysqli_fetch_assoc($hasil_edit);
}

// DATA KATEGORI
$kategori = mysqli_query(
    $koneksi,
    "SELECT * FROM kategori ORDER BY nama_kategori ASC"
);

// DATA PROPERTI
$data = mysqli_query(
    $koneksi,
    "SELECT alat.*, kategori.nama_kategori
     FROM alat
     JOIN kategori ON alat.id_kategori = kategori.id_kategori
     ORDER BY alat.id_alat DESC"
);

function aman($teks) {
    return htmlspecialchars($teks ?? "", ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Properti - Ruang Tari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="halaman-container">

    <header class="halaman-header">
        <div>
            <h1>Ruang Tari</h1>
            <p>Pengelolaan Properti Tari</p>
        </div>
        <a href="dashboard.php" class="tombol-kembali">
            Kembali ke Dashboard
        </a>
    </header>

    <?php if ($admin) { ?>
        <h2><?= $edit ? "Edit Properti" : "Tambah Properti" ?></h2>

        <?php if (mysqli_num_rows($kategori) == 0) { ?>
            <div class="pesan">
                Buat kategori terlebih dahulu melalui menu Kategori.
            </div>
        <?php } ?>

        <form method="POST" class="form-data">

            <?php if ($edit) { ?>
                <input type="hidden" name="id_alat"
                       value="<?= (int) $edit['id_alat'] ?>">
            <?php } ?>

            <label>Kode Properti</label>
            <input type="text" name="kode_alat"
                   placeholder="Contoh: PT001"
                   value="<?= aman($edit['kode_alat'] ?? '') ?>"
                   required>

            <label>Nama Properti</label>
            <input type="text" name="nama_alat"
                   placeholder="Contoh: Kipas Tari"
                   value="<?= aman($edit['nama_alat'] ?? '') ?>"
                   required>

            <label>Kategori</label>
            <select name="id_kategori" required>
                <option value="">-- Pilih Kategori --</option>
                <?php while ($kat = mysqli_fetch_assoc($kategori)) { ?>
                    <option value="<?= (int) $kat['id_kategori'] ?>"
                        <?= isset($edit['id_kategori']) &&
                            $edit['id_kategori'] == $kat['id_kategori']
                            ? 'selected' : '' ?>>
                        <?= aman($kat['nama_kategori']) ?>
                    </option>
                <?php } ?>
            </select>

            <label>Stok Total</label>
            <input type="number" name="stok_total" min="0"
                   value="<?= aman($edit['stok_total'] ?? '1') ?>"
                   required>

            <label>Kondisi</label>
            <select name="kondisi" required>
                <?php
                $daftar_kondisi = ['Baik', 'Rusak Ringan', 'Rusak Berat'];
                $kondisi_sekarang = $edit['kondisi'] ?? 'Baik';

                foreach ($daftar_kondisi as $k) {
                    $selected = ($kondisi_sekarang == $k) ? 'selected' : '';
                    echo '<option value="' . aman($k) . '" ' . $selected . '>'
                        . aman($k) . '</option>';
                }
                ?>
            </select>

            <label>Biaya Peminjaman per Alat (Rp)</label>
            <input type="number" name="biaya_peminjaman" min="0"
                   value="<?= aman($edit['biaya_peminjaman'] ?? '10000') ?>"
                   required>

            <?php if ($edit) { ?>
                <button type="submit" name="edit">Simpan Perubahan</button>
                <a href="properti.php" class="tombol-batal">Batal</a>
            <?php } else { ?>
                <button type="submit" name="tambah"
                    <?= mysqli_num_rows($kategori) == 0 ? 'disabled' : '' ?>>
                    Tambah Properti
                </button>
            <?php } ?>
        </form>
    <?php } ?>

    <?php if ($pesan != "") { ?>
        <div class="pesan"><?= aman($pesan) ?></div>
    <?php } ?>

    <h2>Daftar Properti Tari</h2>

    <div class="tabel-container">
        <table class="tabel-data">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Kode</th>
                    <th>Nama Properti</th>
                    <th>Kategori</th>
                    <th>Stok Total</th>
                    <th>Stok Tersedia</th>
                    <th>Kondisi</th>
                    <th>Biaya/Alat</th>
                    <?php if ($admin) { ?>
                        <th>Aksi</th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($data) > 0) { ?>
                    <?php $no = 1; ?>
                    <?php while ($row = mysqli_fetch_assoc($data)) { ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= aman($row['kode_alat']) ?></td>
                            <td><?= aman($row['nama_alat']) ?></td>
                            <td><?= aman($row['nama_kategori']) ?></td>
                            <td><?= (int) $row['stok_total'] ?></td>
                            <td><?= (int) $row['stok_tersedia'] ?></td>
                            <td><?= aman($row['kondisi']) ?></td>
                            <td>Rp<?= number_format((float) $row['biaya_peminjaman'], 0, ',', '.') ?></td>

                            <?php if ($admin) { ?>
                                <td>
                                    <div class="aksi-data">
                                        <a class="tombol-edit"
                                           href="properti.php?edit=<?= (int) $row['id_alat'] ?>">
                                            Edit
                                        </a>

                                        <form method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus properti ini?')">
                                            <input type="hidden" name="id_alat"
                                                   value="<?= (int) $row['id_alat'] ?>">
                                            <button type="submit" name="hapus"
                                                    class="tombol-hapus">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="<?= $admin ? 9 : 8 ?>">
                            Belum ada data properti.
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
