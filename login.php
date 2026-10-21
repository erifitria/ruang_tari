
<?php
session_start();
include "koneksi.php";

$error = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT * FROM users
         WHERE username = ? AND status = 'aktif'
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);

    $hasil = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($hasil);

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        $_SESSION['id_user'] = $user['id_user'];
        $_SESSION['nama'] = $user['nama'];
        $_SESSION['role'] = $user['role'];

        $id_user = $user['id_user'];
        $aktivitas = "Login ke aplikasi";

        $log = mysqli_prepare(
            $koneksi,
            "INSERT INTO log_aktivitas (id_user, aktivitas)
             VALUES (?, ?)"
        );

        mysqli_stmt_bind_param($log, "is", $id_user, $aktivitas);
        mysqli_stmt_execute($log);

        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ruang Tari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="login-container">
    <h1>💃 Ruang Tari</h1>
    <p class="subtitle">Aplikasi Peminjaman Properti Tari</p>

    <?php if ($error != "") { ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php } ?>

    <form method="POST">
        <label>Username</label>
        <input type="text" name="username"
               placeholder="Masukkan username"
               required autocomplete="username">

        <label>Password</label>
        <input type="password" name="password"
               placeholder="Masukkan password"
               required autocomplete="current-password">

        <button type="submit" name="login">Login</button>
    </form>
    <a href="register.php" class="tautan-akun">
    Belum punya akun? Daftar di sini
</a>
</div>

</body>
</html>
