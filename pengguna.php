
<?php
session_start();

include "koneksi.php";

if (!isset($_SESSION['id_user']) ||
    !in_array($_SESSION['role'], ['admin', 'petugas'])) {
    header("Location: login.php");
    exit;
}

$role_login = $_SESSION['role'];
$admin = ($role_login === 'admin');
$pesan = "";
$edit = null;

/* TAMBAH PENGGUNA - ADMIN SAJA */
if (isset($_POST['tambah']) && $admin) {
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? '';

    if ($nama === '' || $username === '' || $password === '') {
        $pesan = "Semua data wajib diisi!";
    } elseif (!in_array($role, ['admin', 'petugas', 'peminjam']) ||
              !in_array($status, ['aktif', 'nonaktif'])) {
        $pesan = "Role atau status tidak valid!";
    } elseif (strlen($password) < 8) {
        $pesan = "Password minimal 8 karakter!";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO users (nama, username, password, role, status)
             VALUES (?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $stmt, "sssss",
            $nama, $username, $hash, $role, $status
        );

        if (mysqli_stmt_execute($stmt)) {
            header("Location: pengguna.php?pesan=tambah");
            exit;
        } else {
            $pesan = "Gagal menambahkan pengguna. Username mungkin sudah digunakan.";
        }
    }
}

/* EDIT PENGGUNA - ADMIN SAJA */
if (isset($_POST['edit']) && $admin) {
    $id = (int) ($_POST['id_user'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? '';

    if ($nama === '' || $username === '') {
        $pesan = "Nama dan username wajib diisi!";
    } elseif (!in_array($role, ['admin', 'petugas', 'peminjam']) ||
              !in_array($status, ['aktif', 'nonaktif'])) {
        $pesan = "Role atau status tidak valid!";
    } elseif ($id === (int) $_SESSION['id_user'] &&
              ($role !== 'admin' || $status !== 'aktif')) {
        $pesan = "Akun admin yang sedang digunakan harus tetap aktif.";
    } else {
        if ($password !== '') {
            if (strlen($password) < 8) {
                $pesan = "Password minimal 8 karakter!";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = mysqli_prepare(
                    $koneksi,
                    "UPDATE users
                     SET nama=?, username=?, password=?,
                         role=?, status=?
                     WHERE id_user=?"
                );

                mysqli_stmt_bind_param(
                    $stmt, "sssssi",
                    $nama, $username, $hash, $role, $status, $id
                );
            }
        } else {
            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE users
                 SET nama=?, username=?, role=?, status=?
                 WHERE id_user=?"
            );

            mysqli_stmt_bind_param(
                $stmt, "ssssi",
                $nama, $username, $role, $status, $id
            );
        }

        if ($pesan === '') {
            if (isset($stmt) && mysqli_stmt_execute($stmt)) {
                if ($id === (int) $_SESSION['id_user']) {
                    $_SESSION['nama'] = $nama;
                }

                header("Location: pengguna.php?pesan=edit");
                exit;
            } elseif (isset($stmt)) {
                $pesan = "Gagal mengubah pengguna. Username mungkin sudah digunakan.";
            }
        }
    }
}

/* HAPUS PENGGUNA - ADMIN SAJA */
if (isset($_POST['hapus']) && $admin) {
    $id = (int) ($_POST['id_user'] ?? 0);

    if ($id === (int) $_SESSION['id_user']) {
        $pesan = "Akun yang sedang digunakan tidak bisa dihapus!";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "DELETE FROM users WHERE id_user=?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: pengguna.php?pesan=hapus");
            exit;
        } else {
            $pesan = "Pengguna terkait transaksi atau aktivitas tidak bisa dihapus. Gunakan status nonaktif.";
        }
    }
}

/* PESAN HASIL */
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'tambah') {
        $pesan = "Pengguna berhasil ditambahkan!";
    } elseif ($_GET['pesan'] === 'edit') {
        $pesan = "Data pengguna berhasil diubah!";
    } elseif ($_GET['pesan'] === 'hapus') {
        $pesan = "Pengguna berhasil dihapus!";
    }
}

/* DATA UNTUK EDIT - ADMIN SAJA */
if ($admin && isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id_user, nama, username, role, status
         FROM users WHERE id_user=?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

/* PENCARIAN NAMA, USERNAME, DAN ROLE */
$cari = trim($_GET['cari'] ?? '');
$role_filter = $_GET['role'] ?? '';

$sql = "SELECT id_user, nama, username, role, status
        FROM users
        WHERE 1=1";

if ($cari !== '') {
    $sql .= " AND (nama LIKE ? OR username LIKE ?)";
}

if (in_array($role_filter, ['admin', 'petugas', 'peminjam'])) {
    $sql .= " AND role = ?";
}

$sql .= " ORDER BY id_user DESC";

$stmt_data = mysqli_prepare($koneksi, $sql);

if ($cari !== '' && in_array($role_filter, ['admin', 'petugas', 'peminjam'])) {
    $kata_cari = "%$cari%";
    mysqli_stmt_bind_param(
        $stmt_data, "sss",
        $kata_cari, $kata_cari, $role_filter
    );
} elseif ($cari !== '') {
    $kata_cari = "%$cari%";
    mysqli_stmt_bind_param(
        $stmt_data, "ss",
        $kata_cari, $kata_cari
    );
} elseif (in_array($role_filter, ['admin', 'petugas', 'peminjam'])) {
    mysqli_stmt_bind_param($stmt_data, "s", $role_filter);
}

mysqli_stmt_execute($stmt_data);
$data = mysqli_stmt_get_result($stmt_data);

function aman($teks) {
    return htmlspecialchars($teks ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pengguna - Ruang Tari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="halaman-container">

    <header class="halaman-header">
        <div>
            <h1>Ruang Tari</h1>
            <p>
                <?= $admin
                    ? 'Pengelolaan Pengguna'
                    : 'Data Pengguna dan Peminjam' ?>
            </p>
        </div>
        <a href="dashboard.php" class="tombol-kembali">
            Kembali ke Dashboard
        </a>
    </header>

    <?php if ($pesan !== '') { ?>
        <div class="pesan"><?= aman($pesan) ?></div>
    <?php } ?>

    <?php if ($admin) { ?>
        <h2><?= $edit ? 'Edit Pengguna' : 'Tambah Pengguna' ?></h2>

        <form method="POST" class="form-data">
            <?php if ($edit) { ?>
                <input type="hidden" name="id_user"
                       value="<?= (int) $edit['id_user'] ?>">
            <?php } ?>

            <label>Nama Lengkap</label>
            <input type="text" name="nama"
                   value="<?= aman($edit['nama'] ?? '') ?>"
                   placeholder="Masukkan nama lengkap" required>

            <label>Username</label>
            <input type="text" name="username"
                   value="<?= aman($edit['username'] ?? '') ?>"
                   placeholder="Masukkan username" required>

            <label>
                Password
                <?= $edit ? '(kosongkan jika tidak diganti)' : '' ?>
            </label>
            <input type="password" name="password"
                   <?= $edit ? '' : 'required' ?>
                   minlength="8"
                   placeholder="<?= $edit ? 'Password baru (opsional)' : 'Minimal 8 karakter' ?>"
                   autocomplete="new-password">

            <label>Role</label>
            <select name="role" required>
                <?php
                $roles = [
                    'admin' => 'Admin',
                    'petugas' => 'Petugas',
                    'peminjam' => 'Peminjam'
                ];

                foreach ($roles as $nilai => $label) {
                    $terpilih = ($edit['role'] ?? 'peminjam') === $nilai
                        ? 'selected' : '';
                    echo '<option value="' . $nilai . '" ' .
                         $terpilih . '>' . $label . '</option>';
                }
                ?>
            </select>

            <label>Status Akun</label>
            <select name="status" required>
                <option value="aktif"
                    <?= ($edit['status'] ?? 'aktif') === 'aktif'
                        ? 'selected' : '' ?>>
                    Aktif
                </option>
                <option value="nonaktif"
                    <?= ($edit['status'] ?? '') === 'nonaktif'
                        ? 'selected' : '' ?>>
                    Nonaktif
                </option>
            </select>

            <?php if ($edit) { ?>
                <button type="submit" name="edit">
                    Simpan Perubahan
                </button>
                <a href="pengguna.php" class="tombol-batal">Batal</a>
            <?php } else { ?>
                <button type="submit" name="tambah">
                    Tambah Pengguna
                </button>
            <?php } ?>
        </form>
    <?php } ?>

    <h2>Data Pengguna Terdaftar</h2>
    <p class="pesan">
        <?= $admin
            ? 'Admin dapat mengelola akun pengguna.'
            : 'Petugas hanya dapat melihat data pengguna.' ?>
    </p>

    <!-- FORM PENCARIAN -->
    <form method="GET" class="form-data">
        <label>Cari Nama atau Username</label>
        <input
            type="text"
            name="cari"
            placeholder="Masukkan nama atau username..."
            value="<?= aman($cari) ?>"
        >

        <label>Filter Berdasarkan Role</label>
        <select name="role">
            <option value="">Semua Role</option>
            <option value="admin"
                <?= $role_filter === 'admin' ? 'selected' : '' ?>>
                Admin
            </option>
            <option value="petugas"
                <?= $role_filter === 'petugas' ? 'selected' : '' ?>>
                Petugas
            </option>
            <option value="peminjam"
                <?= $role_filter === 'peminjam' ? 'selected' : '' ?>>
                Peminjam
            </option>
        </select>

        <button type="submit">Cari</button>
        <a href="pengguna.php" class="tombol-batal">Reset</a>
    </form>

    <div class="tabel-container">
        <table class="tabel-data">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
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
                            <td><?= aman($row['nama']) ?></td>
                            <td><?= aman($row['username']) ?></td>
                            <td><?= aman(ucfirst($row['role'])) ?></td>
                            <td><?= aman(ucfirst($row['status'])) ?></td>

                            <?php if ($admin) { ?>
                                <td>
                                    <div class="aksi-data">
                                        <a class="tombol-edit"
                                           href="pengguna.php?edit=<?= (int) $row['id_user'] ?>">
                                            Edit
                                        </a>

                                        <?php if ((int) $row['id_user'] !==
                                                  (int) $_SESSION['id_user']) { ?>
                                            <form method="POST"
                                                  onsubmit="return confirm('Yakin ingin menghapus pengguna ini?')">
                                                <input type="hidden"
                                                       name="id_user"
                                                       value="<?= (int) $row['id_user'] ?>">
                                                <button type="submit"
                                                        name="hapus"
                                                        class="tombol-hapus">
                                                    Hapus
                                                </button>
                                            </form>
                                        <?php } ?>
                                    </div>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="<?= $admin ? 6 : 5 ?>">
                            Data pengguna tidak ditemukan.
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
