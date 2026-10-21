
<?php
session_start();
include "koneksi.php";

if (isset($_SESSION['id_user'])) {
    header("Location: dashboard.php");
    exit;
}

$pesan = "";
$berhasil = false;

if (isset($_POST['daftar'])) {
    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $konfirmasi = $_POST['konfirmasi'];

    if ($nama == "" || $username == "" || $password == "") {
        $pesan = "Semua kolom wajib diisi!";
    } elseif (!preg_match('/^[a-zA-Z0-9._]{4,50}$/', $username)) {
        $pesan = "Username minimal 4 karakter dan hanya boleh menggunakan huruf, angka, titik, atau garis bawah.";
    } elseif (strlen($password) < 8) {
        $pesan = "Password minimal 8 karakter!";
    } elseif ($password != $konfirmasi) {
        $pesan = "Konfirmasi password tidak sama!";
    } else {
        $cek = mysqli_prepare(
            $koneksi,
            "SELECT id_user FROM users WHERE username = ?"
        );
        mysqli_stmt_bind_param($cek, "s", $username);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            $pesan = "Username sudah digunakan. Silakan pilih username lain.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $role = "peminjam";
            $status = "aktif";

            $simpan = mysqli_prepare(
                $koneksi,
                "INSERT INTO users (nama, username, password, role, status)
                 VALUES (?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $simpan,
                "sssss",
                $nama,
                $username,
                $password_hash,
                $role,
                $status
            );

            if (mysqli_stmt_execute($simpan)) {
                $berhasil = true;
                $pesan = "Pendaftaran berhasil! Silakan login menggunakan akun kamu.";
            } else {
                $pesan = "Pendaftaran gagal. Silakan coba lagi.";
            }

            mysqli_stmt_close($simpan);
        }

        mysqli_stmt_close($cek);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Ruang Tari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="login-container">
    <div class="login-box">

        <h1>💃 Ruang Tari</h1>
        <p class="login-subtitle">Buat Akun Peminjam</p>

        <?php if ($pesan != "") { ?>
            <div class="pesan">
                <?= htmlspecialchars($pesan) ?>
            </div>
        <?php } ?>

        <?php if ($berhasil) { ?>

            <a href="login.php" class="login-button"
               style="display:block; text-align:center; text-decoration:none;">
                Kembali ke Login
            </a>

        <?php } else { ?>

            <form method="POST" action="register.php">

                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama"
                       placeholder="Masukkan nama lengkap"
                       value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
                       required>

                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       placeholder="Minimal 4 karakter"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="Minimal 8 karakter"
                       required>

                <label for="konfirmasi">Konfirmasi Password</label>
                <input type="password" id="konfirmasi" name="konfirmasi"
                       placeholder="Ulangi password"
                       required>

                <button type="submit" name="daftar" class="login-button">
                    Daftar Sekarang
                </button>

            </form>

            <p style="text-align:center; margin-top:18px;">
                Sudah punya akun?
                <a href="login.php">Login di sini</a>
            </p>

        <?php } ?>

    </div>
</div>

</body>
</html>