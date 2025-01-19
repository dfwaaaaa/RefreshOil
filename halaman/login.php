<?php
// Mulai sesi untuk mengelola status login pengguna
session_start();

// Proses jika ada pengiriman form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Pengaturan koneksi database
    $host = "localhost";
    $user = "root";
    $password = "";
    $dbname = "refresh_oil";

    // Koneksi ke database
    $conn = new mysqli($host, $user, $password, $dbname);

    // Cek apakah koneksi berhasil
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }

    // Cek apakah email dan password sudah diisi
    if (isset($_POST['email']) && isset($_POST['password'])) {
        $email = $conn->real_escape_string($_POST['email']);
        $password = $_POST['password'];

        // Cari user berdasarkan email
        $sql = "SELECT * FROM users WHERE email = '$email'";
        $result = $conn->query($sql);

        // Jika user ditemukan, cek passwordnya
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Verifikasi password
            if (password_verify($password, $user['password'])) {
                // Simpan informasi sesi dan arahkan ke dashboard
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                header("Location: dashboard.php");
                exit;
            } else {
                // Tampilkan pesan jika password salah
                echo "<script>alert('Email atau password salah, tolong cek kembali');</script>";
            }
        } else {
            // Tampilkan pesan jika email tidak ditemukan
            echo "<script>alert('Email atau password salah, tolong cek kembali');</script>";
        }
    } else {
        // Tampilkan pesan jika field kosong
        echo "<script>alert('Semua field harus diisi!');</script>";
    }

    // Tutup koneksi ke database
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RefreshOil</title>
    <link href="../gambar/logo.png" rel="shortcut icon">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex justify-center items-center relative font-[Poppins] bg-[#f5f5f5]">
    <!-- Gambar Background dengan Overlay -->
    <div class="fixed inset-0 z-[-1]">
        <img src="../gambar/banyak.png" alt="Logo RefreshOil" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-black/50"></div>
    </div>

    <!-- Tautan untuk Login Admin -->
    <a href="loginAdmin.php" class="absolute top-5 right-5 text-yellow-300 text-sm hover:text-yellow-200 md:text-base">
        Login Admin
    </a>

    <!-- Form Login Utama -->
    <div class="bg-white p-8 md:p-10 rounded-2xl w-full max-w-[400px] mx-4 shadow-lg">
        <!-- Logo -->
        <div class="flex flex-col justify-center items-center mb-8">
        <a href="../landing_page/landing.php">
        <img src="../gambar/Banner2.png" alt="Logo RefreshOil" class="w-3/5">
        </a>
        </div>

        <!-- Formulir Login -->
        <form id="login-form" method="POST" action="" class="space-y-6">
            <div class="space-y-4">
                <input type="email" name="email" placeholder="Email" required
                    class="w-full p-3 bg-gray-100 rounded-lg border-none focus:ring-2 focus:ring-yellow-400 focus:outline-none">
                
                <div class="relative">
                    <input type="password" name="password" placeholder="Password" id="password-field" required
                        class="w-full p-3 bg-gray-100 rounded-lg border-none focus:ring-2 focus:ring-yellow-400 focus:outline-none">
                    <span id="toggle-password" 
                        class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-600 text-lg">
                        <img src="../gambar/eye.png" alt="Open Eye Icon" id="eye-icon" style="width: 20px; height: 20px;">
                    </span>
                </div>

                <!-- Tautan Lupa Password -->
                <div id="lupaPassword" class="text-right hidden">
                    <a href="lupaPW.php" class="text-xs text-blue-600 hover:text-blue-800">
                        Lupa Password?
                    </a>
                </div>
            </div>

            <button type="submit" id="login-button"
                class="w-full py-3 px-4 bg-white border border-gray-300 rounded-lg font-medium transition-colors duration-200 
                    hover:bg-yellow-400 hover:border-yellow-400 hover:text-gray-800 disabled:opacity-50 disabled:cursor-not-allowed">
                Login
            </button>
        </form>

        <!-- Pemisah -->
        <div class="text-center text-gray-600 my-6">atau login dengan</div>

        <!-- Opsi Login dengan Google -->
        <div class="flex justify-center mb-6">
            <img src="../gambar/googleLogo.png" alt="Ikon Google" class="w-6 h-6">
        </div>

        <!-- Tautan Daftar Pengguna Baru -->
        <div class="text-center text-sm text-gray-700">
            Belum memiliki akun? 
            <a href="daftar.php" class="text-blue-600 hover:text-blue-800">Daftar</a>
        </div>
    </div>

    <script>
        // JavaScript untuk toggle password
        const passwordField = document.getElementById("password-field");
        const togglePassword = document.getElementById("toggle-password");
        const eyeIcon = document.getElementById("eye-icon");

        togglePassword.addEventListener("click", () => {
            const type = passwordField.getAttribute("type") === "password" ? "text" : "password";
            passwordField.setAttribute("type", type);
            eyeIcon.src = type === "password" ? "../gambar/eye.png" : "../gambar/eyeoff.png";
        });

        // Tampilkan atau sembunyikan tautan "Lupa Password" berdasarkan input password
        const lupaPassword = document.getElementById("lupaPassword");
        passwordField.addEventListener("input", () => {
            if (passwordField.value.trim() !== "") {
                lupaPassword.classList.remove("hidden");
            } else {
                lupaPassword.classList.add("hidden");
            }
        });

        // Aktifkan/Nonaktifkan tombol login berdasarkan status input field
        const inputs = document.querySelectorAll("input");
        const loginButton = document.querySelector("button[type='submit']");

        function checkInputs() {
            const allFilled = Array.from(inputs).every(input => input.value.trim() !== "");
            loginButton.disabled = !allFilled;
            
            if (allFilled) {
                loginButton.classList.add("bg-yellow-400", "text-gray-800");
                loginButton.classList.remove("bg-white", "text-gray-600");
            } else {
                loginButton.classList.remove("bg-yellow-400", "text-gray-800");
                loginButton.classList.add("bg-white", "text-gray-600");
            }
        }

        inputs.forEach(input => {
            input.addEventListener("input", checkInputs);
        });
    </script>
</body>
</html>