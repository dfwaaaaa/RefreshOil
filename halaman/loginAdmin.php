<?php
// Memulai sesi PHP untuk mengelola data pengguna secara persisten
session_start();

// Konfigurasi koneksi ke database
$host = "localhost";          // Alamat host database, biasanya 'localhost' jika database berada di server yang sama
$username = "root";           // Username untuk mengakses database
$password = "";               // Password untuk mengakses database
$dbname = "refresh_oil";      // Nama database yang akan diakses

try {
    // Menggunakan PDO (PHP Data Objects) untuk koneksi database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    // Mengatur atribut PDO untuk melemparkan exception saat terjadi error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Atau menggunakan MySQLi untuk koneksi database
    $conn = new mysqli($host, $username, $password, $dbname);
    
    // Memeriksa apakah koneksi MySQLi berhasil
    if ($conn->connect_error) {
        // Jika koneksi gagal, lempar exception dengan pesan error
        throw new Exception("Koneksi gagal: " . $conn->connect_error);
    }
} catch (Exception $e) {
    // Menampilkan pesan kesalahan koneksi sebagai pop-up menggunakan JavaScript alert
    echo "<script>alert('{$e->getMessage()}');</script>";
    // Menghentikan eksekusi skrip setelah menampilkan pesan error
    exit;
}

// Inisialisasi variabel untuk menyimpan pesan error
$error = "";

// Memeriksa apakah form telah disubmit menggunakan metode POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mengambil dan membersihkan input pengguna untuk 'username'
    $username_input = $conn->real_escape_string(trim($_POST['username']));
    // Mengambil dan membersihkan input pengguna untuk 'password'
    $password_input = trim($_POST['password']);

    // Memeriksa apakah salah satu field kosong
    if (empty($username_input) || empty($password_input)) {
        // Jika ada field yang kosong, set pesan error
        $error = "Silakan isi semua field.";
    } else {
        // Membuat query SQL untuk memilih pengguna berdasarkan username menggunakan prepared statement
        $query = "SELECT * FROM admin WHERE username = ?";
        // Menyiapkan statement SQL
        $stmt = $conn->prepare($query);
        // Mengikat parameter 'username_input' ke statement sebagai string ('s')
        $stmt->bind_param("s", $username_input);
        // Menjalankan statement SQL
        $stmt->execute();
        // Mendapatkan hasil dari eksekusi statement
        $result = $stmt->get_result();

        // Memeriksa apakah ada pengguna yang ditemukan dengan username tersebut
        if ($result->num_rows > 0) {
            // Mengambil data pengguna sebagai array asosiatif
            $admin = $result->fetch_assoc();

            // Verifikasi password yang diinputkan dengan hash password di database
            if (password_verify($password_input, $admin['password'])) {
                // Jika password benar, simpan data pengguna di sesi
                $_SESSION['id_admin'] = $admin['id_admin'];                    // Menyimpan ID pengguna
                $_SESSION['username'] = $admin['username'];        // Menyimpan username pengguna
                $_SESSION['message'] = "Login berhasil!";          // Menyimpan pesan sukses

                // Mengarahkan pengguna ke halaman dashboard setelah login berhasil
                header("Location: dashboardAdmin.php");
                // Menghentikan eksekusi skrip setelah redirect
                exit;
            } else {
                // Jika password salah, set pesan error
                $error = "Password salah!";
            }
        } else {
            // Jika pengguna tidak ditemukan, set pesan error
            $error = "Pengguna tidak ditemukan!";
        }

        // Menutup statement setelah eksekusi selesai
        $stmt->close();
    }
}

// Menutup koneksi MySQLi setelah semua proses selesai
$conn->close();
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
    <!-- Background Image with Overlay -->
    <div class="fixed inset-0 z-[-1]">
        <img src="../gambar/banyak.png" alt="RefreshOil Logo" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-black/50"></div>
    </div>

    <!-- Admin Login Link -->
    <a href="daftarAdmin.php" class="absolute top-5 right-5 text-yellow-300 text-sm hover:text-yellow-200 md:text-base">
        Daftar Admin
    </a>

    <!-- Main Container -->
    <div class="bg-white p-8 md:p-10 rounded-2xl w-full max-w-[400px] mx-4 shadow-lg">
        <!-- Logo -->
        <div class="flex flex-col items-center mb-8">
            <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="w-3/5">
        </div>

        <!-- Login Form -->
        <form id="login-form" method="POST" action="" class="space-y-6">
            <div class="space-y-4">
                <h3 class="text-center font-semibold">Login Admin</h3>
                <input type="text" name="username" placeholder="Username" required
                    class="w-full p-3 bg-gray-100 rounded-lg border-none focus:ring-2 focus:ring-yellow-400 focus:outline-none">
                
                <div class="relative">
                    <input type="password" name="password" placeholder="Password" id="password-field" required
                        class="w-full p-3 bg-gray-100 rounded-lg border-none focus:ring-2 focus:ring-yellow-400 focus:outline-none">
                    <span id="toggle-password" 
                        class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-600 text-lg">
                        <img src="../gambar/eye.png" alt="Open Eye Icon" id="eye-icon" style="width: 20px; height: 20px;">
                    </span>
                </div>

            </div>

            <button type="submit" id="login-button"
                class="w-full py-3 px-4 bg-white border border-gray-300 rounded-lg font-medium transition-colors duration-200 
                    hover:bg-yellow-400 hover:border-yellow-400 hover:text-gray-800 disabled:opacity-50 disabled:cursor-not-allowed">
                Login
            </button>
        </form>
    </div>

    <?php if (!empty($error)): ?>
        <script>
            // Menampilkan pesan kesalahan sebagai pop-up
            alert('<?php echo addslashes($error); ?>');
        </script>
    <?php endif; ?>

    <script>
        // JavaScript untuk toggle password
        const inputs = document.querySelectorAll("input");
        const loginButton = document.querySelector("button[type='submit']");
        const passwordField = document.getElementById("password-field");
        const togglePassword = document.getElementById("toggle-password");
        const eyeIcon = document.getElementById("eye-icon");

        togglePassword.addEventListener("click", () => {
            const type = passwordField.getAttribute("type") === "password" ? "text" : "password";
            passwordField.setAttribute("type", type);
            eyeIcon.src = type === "password" ? "../gambar/eye.png" : "../gambar/eyeoff.png";
        });

        passwordField.addEventListener("input", () => {
            if (passwordField.value.trim() !== "") {
                forgotPassword.classList.remove("hidden");
            } else {
                forgotPassword.classList.add("hidden");
            }
        });

        function checkInputs() {
            const allFilled = Array.from(inputs).every(input => input.value.trim() !== "");
            loginButton.disabled = !allFilled; // Tambahkan atau hapus atribut 'disabled' berdasarkan kondisi
            
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

        // Inisialisasi status tombol login saat halaman dimuat
        document.addEventListener("DOMContentLoaded", checkInputs);
    </script>
</body>
</html>