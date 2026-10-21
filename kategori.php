
<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['id_user']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$pesan = "";
$edit = null;

// TAMBAH KATEGORI
if (isset($_POST['tambah'])) {
    $nama = trim($_POST['nama_kategori']);
    $deskripsi = trim($_POST['deskripsi']);

    if ($nama == "") {
        $pesan = "Nama kategori wajib diisi!";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO kategori (nama_kategori, deskripsi)
             VALUES (?, ?)"
        );

        mysqli_stmt_bind_param($stmt, "ss", $nama, $deskripsi);

        if (mysqli_stmt_execute($stmt)) {
            $pesan = "Kategori berhasil ditambahkan!";
        } else {
            $pesan = "Gagal menambahkan kategori!";
        }
    }
}

// EDIT KATEGORI
if (isset($_POST['edit'])) {
    $id = (int) $_POST['id_kategori'];
    $nama = trim($_POST['nama_kategori']);
    $deskripsi = trim($_POST['deskripsi']);

    if ($nama == "") {
        $pesan = "Nama kategori wajib diisi!";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE kategori
             SET nama_kategori = ?, deskripsi = ?
             WHERE id_kategori = ?"
        );

        mysqli_stmt_bind_param(
            $stmt, "ssi", $nama, $deskripsi, $id
        );

        if (mysqli_stmt_execute($stmt)) {
            header("Location: kategori.php?pesan=edit");
            exit;
        } else {
            $pesan = "Gagal mengubah kategori!";
        }
    }
}

// HAPUS KATEGORI
if (isset($_POST['hapus'])) {
    $id = (int) $_POST['id_kategori'];

    $stmt = mysqli_prepare(
        $koneksi,
        "DELETE FROM kategori WHERE id_kategori = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: kategori.php?pesan=hapus");
        exit;
    } else {
        $pesan = "Kategori tidak bisa dihapus karena masih digunakan oleh data properti.";
    }
}

// PESAN SETELAH AKSI
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'edit') {
        $pesan = "Kategori berhasil diubah!";
    } elseif ($_GET['pesan'] == 'hapus') {
        $pesan = "Kategori berhasil dihapus!";
    }
}

// AMBIL DATA UNTUK DIEDIT
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT * FROM kategori WHERE id_kategori = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $hasil_edit = mysqli_stmt_get_result($stmt);
    $edit = mysqli_fetch_assoc($hasil_edit);
}

// AMBIL SEMUA KATEGORI
$data = mysqli_query(
    $koneksi,
    "SELECT * FROM kategori ORDER BY id_kategori DESC"
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
    <title>Kelola Kategori - Ruang Tari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="halaman-container">

    <header class="halaman-header">
        <div>
            <h1>Ruang Tari</h1>
            <p>Pengelolaan Kategori Properti Tari</p>
        </div>
        <a href="dashboard.php" class="tombol-kembali">
            Kembali ke Dashboard
        </a>
    </header>

    <h2><?= $edit ? "Edit Kategori" : "Tambah Kategori" ?></h2>

    <?php if ($pesan != "") { ?>
        <div class="pesan">
            <?= aman($pesan) ?>
        </div>
    <?php } ?>

    <form method="POST" class="form-data">

        <?php if ($edit) { ?>
            <input type="hidden" name="id_kategori"
                   value="<?= (int) $edit['id_kategori'] ?>">
        <?php } ?>

        <label>Nama Kategori</label>
        <input type="text" name="nama_kategori"
               placeholder="Contoh: Properti Tangan"
               value="<?= aman($edit['nama_kategori'] ?? '') ?>"
               required>

        <label>Deskripsi</label>
        <textarea name="deskripsi" rows="3"
                  placeholder="Keterangan kategori"><?= aman($edit['deskripsi'] ?? '') ?></textarea>

        <?php if ($edit) { ?>
            <button type="submit" name="edit">Simpan Perubahan</button>
            <a href="kategori.php" class="tombol-batal">Batal</a>
        <?php } else { ?>
            <button type="submit" name="tambah">Tambah Kategori</button>
        <?php } ?>

    </form>

    <h2>Data Kategori</h2>

    <div class="tabel-container">
        <table class="tabel-data">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama Kategori</th>
                    <th>Deskripsi</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($data) > 0) { ?>
                    <?php $no = 1; ?>
                    <?php while ($row = mysqli_fetch_assoc($data)) { ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= aman($row['nama_kategori']) ?></td>
                            <td><?= aman($row['deskripsi']) ?></td>
                            <td>
                                <div class="aksi-data">
                                    <a class="tombol-edit"
                                       href="kategori.php?edit=<?= (int) $row['id_kategori'] ?>">
                                        Edit
                                    </a>

                                    <form method="POST"
                                          onsubmit="return confirm('Yakin ingin menghapus kategori ini?')">
                                        <input type="hidden"
                                               name="id_kategori"
                                               value="<?= (int) $row['id_kategori'] ?>">
                                        <button type="submit" name="hapus"
                                                class="tombol-hapus">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="4">Belum ada data kategori.</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
