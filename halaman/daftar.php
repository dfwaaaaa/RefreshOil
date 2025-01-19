<?php
// Mulai sesi untuk menyimpan data pengguna
session_start();

// Konfigurasi koneksi ke database
$host = "localhost";          // Alamat host database, biasanya 'localhost' jika database berada di server yang sama
$user = "root";               // Username untuk mengakses database
$password = "";               // Password untuk mengakses database
$dbname = "refresh_oil";      // Nama database yang akan diakses

// Membuat koneksi ke database menggunakan mysqli
$conn = new mysqli($host, $user, $password, $dbname);

// Cek apakah koneksi berhasil
if ($conn->connect_error) {
    // Jika terjadi kesalahan koneksi, hentikan eksekusi dan tampilkan pesan error
    die("Koneksi gagal: " . $conn->connect_error);
}

// Jika form disubmit, proses data yang dimasukkan
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Cek apakah semua field yang diperlukan diisi
    if (isset($_POST['phone']) && isset($_POST['email']) && isset($_POST['password']) && isset($_POST['confirm_password'])) {
        // Mengambil dan membersihkan data input untuk menghindari serangan SQL Injection
        $phone = $conn->real_escape_string($_POST['phone']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Cek apakah password dan konfirmasi password cocok
        if ($password !== $confirm_password) {
            // Jika password tidak cocok dengan konfirmasi, tampilkan alert
            echo "<script>alert('Password dan Konfirmasi Password tidak cocok.');</script>";
        } else {
            // Cek apakah email sudah terdaftar di database
            $checkEmail = "SELECT id FROM users WHERE email = '$email'";
            $result = $conn->query($checkEmail);

            if ($result->num_rows > 0) {
                // Jika email sudah terdaftar, tampilkan alert
                echo "<script>alert('Email sudah terdaftar, gunakan email lain');</script>";
            } else {
                // Enkripsi password menggunakan bcrypt untuk keamanan
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // Query untuk menyimpan data pengguna baru ke tabel 'users'
                $sql = "INSERT INTO users (phone, email, password) VALUES ('$phone', '$email', '$hashed_password')";

                if ($conn->query($sql) === TRUE) {
                    // Jika data berhasil disimpan, simpan ID pengguna yang baru dibuat ke dalam sesi
                    $_SESSION['user_id'] = $conn->insert_id;

                    // Arahkan pengguna ke halaman berikutnya (daftar2.php)
                    echo "<script>window.location.href='daftar2.php';</script>";
                    exit; // Hentikan eksekusi skrip setelah redirect
                } else {
                    // Jika terjadi kesalahan saat menyimpan data, tampilkan pesan error
                    echo "<script>alert('Terjadi kesalahan saat menyimpan data: " . $conn->error . "');</script>";
                }
            }
        }
    } else {
        // Jika ada field yang kosong, tampilkan alert
        echo "<script>alert('Semua field harus diisi!');</script>";
    }
}

// Tutup koneksi database setelah semua proses selesai
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - RefreshOil</title>
    <link href="../gambar/logo.png" rel="shortcut icon">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex justify-center items-center relative bg-gray-100 font-['Poppins']">
    <!-- Background dengan overlay -->
    <div class="fixed inset-0 z-0">
        <img src="../gambar/banyak.png" alt="Background" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
    </div>

    <!-- Link ke halaman Admin -->
    <a href="daftarAdmin.php" class="absolute top-5 right-5 text-yellow-400 text-sm hover:text-yellow-300 z-10">
        Daftar Admin
    </a>

    <!-- Formulir Pendaftaran -->
    <div class="bg-white p-8 md:p-10 rounded-2xl w-full max-w-md mx-4 shadow-lg z-10">
        <!-- Logo -->
        <div class="flex flex-col items-center mb-8">
            <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="w-3/5">
        </div>

        <!-- Form Pendaftaran -->
        <form id="register-form" method="POST" action="" class="space-y-4">
            <div class="space-y-4">
                <input type="tel" name="phone" 
                    class="w-full px-4 py-3 bg-gray-100 border-none rounded-lg focus:ring-2 focus:ring-yellow-400 focus:outline-none" 
                    placeholder="No Telp" required>
                
                <input type="email" name="email" 
                    class="w-full px-4 py-3 bg-gray-100 border-none rounded-lg focus:ring-2 focus:ring-yellow-400 focus:outline-none" 
                    placeholder="Email" required>
                
                <!-- Kolom Password -->
                <div class="relative">
                    <input type="password" name="password" placeholder="Password" id="password-field" required
                        class="w-full p-3 bg-gray-100 rounded-lg border-none focus:ring-2 focus:ring-yellow-400 focus:outline-none">
                    <span id="toggle-password" 
                        class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-600 text-lg">
                        <img src="../gambar/eye.png" alt="Open Eye Icon" id="eye-icon" style="width: 20px; height: 20px;">
                    </span>
                </div>

                <!-- Kolom Konfirmasi Password -->
                <div class="relative">
                    <input type="password" name="confirm_password" placeholder="Konfirmasi Password" id="confirm-password-field" required
                        class="w-full p-3 bg-gray-100 rounded-lg border-none focus:ring-2 focus:ring-yellow-400 focus:outline-none">
                    <span id="toggle-confirm-password" 
                        class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-600 text-lg">
                        <img src="../gambar/eye.png" alt="Open Eye Icon" id="eye-confirm-icon" style="width: 20px; height: 20px;">
                    </span>
                </div>

                <p id="error-message" class="text-red-500 text-sm hidden">Password tidak sesuai!</p>
            </div>

            <button type="submit" 
                class="w-full py-3 px-4 bg-white border border-gray-300 rounded-lg font-medium transition-colors duration-200 
                       hover:bg-yellow-400 hover:border-yellow-400 disabled:opacity-50 disabled:cursor-not-allowed">
                Lanjut
            </button>
        </form>

        <!-- Link ke Halaman Login -->
        <div class="text-center mt-6 text-sm text-gray-600">
            Sudah memiliki akun? 
            <a href="login.php" class="text-blue-600 hover:text-blue-700">Login</a>
        </div>
    </div>

    <script>
        // JavaScript untuk toggle password

        // Toggle untuk Kolom Password
        const passwordField = document.getElementById("password-field");
        const togglePassword = document.getElementById("toggle-password");
        const eyeIcon = document.getElementById("eye-icon");

        togglePassword.addEventListener("click", () => {
            const type = passwordField.getAttribute("type") === "password" ? "text" : "password";
            passwordField.setAttribute("type", type);
            eyeIcon.src = type === "password" ? "../gambar/eye.png" : "../gambar/eyeoff.png";
        });

        // Toggle untuk Kolom Konfirmasi Password
        const confirmPasswordField = document.getElementById("confirm-password-field");
        const toggleConfirmPassword = document.getElementById("toggle-confirm-password");
        const eyeConfirmIcon = document.getElementById("eye-confirm-icon");

        toggleConfirmPassword.addEventListener("click", () => {
            const type = confirmPasswordField.getAttribute("type") === "password" ? "text" : "password";
            confirmPasswordField.setAttribute("type", type);
            eyeConfirmIcon.src = type === "password" ? "../gambar/eye.png" : "../gambar/eyeoff.png";
        });

        // Referensi elemen form
        const registerForm = document.getElementById("register-form");
        const newPassword = document.getElementById("password-field");
        const confirmPassword = document.getElementById("confirm-password-field");
        const errorMessage = document.getElementById("error-message");
        const submitButton = document.querySelector("button[type='submit']");
        const inputFields = document.querySelectorAll("input");

        // Fungsi untuk mengecek apakah semua input sudah terisi
        function checkInputs() {
            const allFilled = Array.from(inputFields).every(input => input.value.trim() !== "");
            submitButton.disabled = !allFilled;
            if (allFilled) {
                submitButton.classList.add("bg-yellow-400", "border-yellow-400");
                submitButton.classList.remove("bg-white", "border-gray-300");
            } else {
                submitButton.classList.remove("bg-yellow-400", "border-yellow-400");
                submitButton.classList.add("bg-white", "border-gray-300");
            }
        }

        // Cek setiap perubahan input untuk update tombol
        inputFields.forEach(input => {
            input.addEventListener("input", checkInputs);
        });

        // Validasi saat form disubmit
        registerForm.addEventListener("submit", (event) => {
            if (newPassword.value !== confirmPassword.value) {
                event.preventDefault();
                confirmPassword.classList.add("ring-2", "ring-red-500");
                errorMessage.classList.remove("hidden");
            } else {
                confirmPassword.classList.remove("ring-2", "ring-red-500");
                errorMessage.classList.add("hidden");
            }
        });

        // Set status tombol saat halaman dimuat
        checkInputs();
    </script>
</body>
</html>