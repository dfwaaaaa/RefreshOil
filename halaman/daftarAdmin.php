<?php 
// Konfigurasi koneksi ke database
$host = "localhost";          // Alamat host database, biasanya 'localhost' jika database berada di server yang sama
$username = "root";           // Username untuk mengakses database
$password = "";               // Password untuk mengakses database
$dbname = "refresh_oil";      // Nama database yang akan diakses

// Membuat koneksi ke database menggunakan PDO (PHP Data Objects)
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
// Mengatur atribut error mode PDO untuk melemparkan exception saat terjadi error
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Membuat koneksi ke database menggunakan MySQLi (untuk kompatibilitas dengan kode lain)
$conn = new mysqli($host, $username, $password, $dbname);

// Memeriksa apakah koneksi MySQLi berhasil
if ($conn->connect_error) {
    // Jika koneksi gagal, hentikan eksekusi dan tampilkan pesan error
    die("Koneksi gagal: " . $conn->connect_error);
}

// Inisialisasi variabel untuk menyimpan pesan yang akan ditampilkan kepada pengguna
$message = ''; 

// Memeriksa apakah form telah disubmit menggunakan metode POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mengambil dan membersihkan input username untuk mencegah SQL Injection
    $username_input = $conn->real_escape_string($_POST['username']);
    // Mengambil input password tanpa membersihkannya karena akan di-hash
    $password_input = $_POST['password'];
    
    // Mengambil jawaban dari formulir dan membersihkannya
    // Jika jawaban tidak diisi, set sebagai string kosong
    $jawaban1 = isset($_POST['jawaban1']) ? strtolower(trim($conn->real_escape_string($_POST['jawaban1']))) : '';
    $jawaban2 = isset($_POST['jawaban2']) ? strtolower(trim($conn->real_escape_string($_POST['jawaban2']))) : '';
    $jawaban3 = isset($_POST['jawaban3']) ? strtolower(trim($conn->real_escape_string($_POST['jawaban3']))) : '';

    // Mendefinisikan jawaban yang benar untuk setiap pertanyaan keamanan
    $benar1 = "semester 2"; // Jawaban benar untuk pertanyaan pertama
    $benar2 = "ricel";       // Jawaban benar untuk pertanyaan kedua
    $benar3 = "bumi";        // Jawaban benar untuk pertanyaan ketiga

    // Memeriksa apakah jawaban yang diberikan oleh pengguna sesuai dengan jawaban yang benar
    if ($jawaban1 !== strtolower($benar1) || $jawaban2 !== strtolower($benar2) || $jawaban3 !== strtolower($benar3)) {
        // Jika salah satu jawaban tidak cocok, set pesan sebagai "Akses Ditolak"
        $message = "Akses Ditolak";
    } else {
        // Jika semua jawaban benar, lanjutkan proses pendaftaran admin

        // Hash password menggunakan algoritma default (biasanya BCRYPT) untuk keamanan
        $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);

        // Membuat query SQL untuk memeriksa apakah username sudah ada di tabel 'admin'
        $query = "SELECT * FROM admin WHERE username='$username_input'";
        // Menjalankan query dan menyimpan hasilnya
        $result = $conn->query($query);

        // Memeriksa apakah ada baris yang dikembalikan, artinya username sudah ada
        if ($result->num_rows > 0) {
            // Jika username sudah ada, set pesan sebagai "Username sudah ada!"
            $message = "Username sudah ada!";
        } else {
            // Jika username belum ada, lanjutkan untuk menyimpan admin baru ke database

            // Membuat query SQL untuk memasukkan data admin baru ke tabel 'admin'
            $query = "INSERT INTO admin (username, password) VALUES ('$username_input', '$hashed_password')";
            // Menjalankan query dan memeriksa apakah berhasil
            if ($conn->query($query) === TRUE) {
                // Jika berhasil, set pesan sebagai "Admin baru berhasil didaftarkan!"
                $message = "Admin baru berhasil didaftarkan!";
            } else {
                // Jika terjadi kesalahan saat menjalankan query, set pesan dengan detail error
                $message = "Error: " . $query . "<br>" . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Admin - RefreshOil</title>
    <link href="../gambar/logo.png" rel="shortcut icon">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Styling untuk Modal */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.5); /* Black w/ opacity */
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto; /* 10% from the top and centered */
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px; /* Could be more or less, depending on screen size */
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }
        .close {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }
        /* Styling untuk Blur Efek */
        .blur {
            filter: blur(5px);
            transition: filter 0.3s ease;
        }
    </style>
</head>
<body class="min-h-screen flex justify-center items-center relative font-[Poppins] bg-[#f5f5f5]">
    <!-- Background Image with Overlay -->
    <div class="fixed inset-0 z-[-1]">
        <img src="../gambar/banyak.png" alt="RefreshOil Logo" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-black/50"></div>
    </div>

    <!-- Admin Login Link -->
    <a href="loginAdmin.php" class="absolute top-5 right-5 text-yellow-300 text-sm hover:text-yellow-200 md:text-base">
        Login Admin
    </a>

    <!-- Wrapper untuk Konten Utama -->
    <div id="main-content" class="bg-white p-8 md:p-10 rounded-2xl w-full max-w-[400px] mx-4 shadow-lg">
        <!-- Logo -->
        <div class="flex flex-col items-center mb-8">
            <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="w-3/5">
        </div>

        <!-- Registration Form -->
        <form id="register-form" method="POST" action="" class="space-y-6">
            <div class="space-y-4">
                <h3 class="text-center font-semibold">Daftar Admin</h3>
                <input type="text" name="username" id="username" placeholder="Username" required
                    class="w-full p-3 bg-gray-100 rounded-lg focus:ring-2 focus:ring-yellow-400 focus:outline-none">

                <div class="relative">
                    <input type="password" name="password" placeholder="Password" id="password-field" required
                        class="w-full p-3 bg-gray-100 rounded-lg border-none focus:ring-2 focus:ring-yellow-400 focus:outline-none">
                    <span id="toggle-password" 
                        class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-600 text-lg">
                        <img src="../gambar/eye.png" alt="Open Eye Icon" id="eye-icon" style="width: 20px; height: 20px;">
                    </span>
                </div>
            </div>

            <button type="submit" id="register-button" class="w-full py-3 px-4 bg-yellow-400 text-gray-800 rounded-lg font-medium transition-colors duration-200 
                hover:bg-yellow-500 hover:text-white">
                Daftar
            </button>
        </form>
    </div>

    <!-- Modal untuk Pertanyaan 1 -->
    <div id="modal1" class="modal">
        <div class="modal-content">
            <span class="close" id="close-modal1">&times;</span>
            <h2 class="text-lg font-semibold mb-4">Pertanyaan 1</h2>
            <p>Tanggal berapa platform Refresh Oil pertama kali di develop?</p>
            <input type="text" id="jawaban1" class="w-full p-2 mt-2 border border-gray-300 rounded" placeholder="Jawaban Anda">
            <button id="next1" class="mt-4 w-full py-2 px-4 bg-yellow-400 text-gray-800 rounded-lg">Berikutnya</button>
        </div>
    </div>

    <!-- Modal untuk Pertanyaan 2 -->
    <div id="modal2" class="modal">
        <div class="modal-content">
            <span class="close" id="close-modal2">&times;</span>
            <h2 class="text-lg font-semibold mb-4">Pertanyaan 2</h2>
            <p>Siapa yang mencetuskan ide membuat platform ini?</p>
            <input type="text" id="jawaban2" class="w-full p-2 mt-2 border border-gray-300 rounded" placeholder="Jawaban Anda">
            <button id="next2" class="mt-4 w-full py-2 px-4 bg-yellow-400 text-gray-800 rounded-lg">Berikutnya</button>
        </div>
    </div>

    <!-- Modal untuk Pertanyaan 3 -->
    <div id="modal3" class="modal">
        <div class="modal-content">
            <span class="close" id="close-modal3">&times;</span>
            <h2 class="text-lg font-semibold mb-4">Pertanyaan 3</h2>
            <p>Dimana Refresh Oil di develop?</p>
            <input type="text" id="jawaban3" class="w-full p-2 mt-2 border border-gray-300 rounded" placeholder="Jawaban Anda">
            <button id="submit-answers" class="mt-4 w-full py-2 px-4 bg-yellow-400 text-gray-800 rounded-lg">Daftar</button>
        </div>
    </div>

    <!-- Modal Akses Ditolak -->
    <div id="modalDenied" class="modal">
        <div class="modal-content">
            <span class="close" id="close-modalDenied">&times;</span>
            <h2 class="text-lg font-semibold mb-4">Akses Ditolak</h2>
            <p>Anda tidak memiliki izin untuk mendaftar sebagai admin.</p>
            <button id="redirect-login" class="mt-4 w-full py-2 px-4 bg-red-500 text-white rounded-lg">OK</button>
        </div>
    </div>

    <!-- Modal Sukses Pendaftaran -->
    <div id="modalSuccess" class="modal">
        <div class="modal-content">
            <span class="close" id="close-modalSuccess">&times;</span>
            <h2 class="text-lg font-semibold mb-4">Berhasil</h2>
            <p>Admin baru berhasil didaftarkan!</p>
            <button id="redirect-loginSuccess" class="mt-4 w-full py-2 px-4 bg-green-500 text-white rounded-lg">OK</button>
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

        // JavaScript untuk menangani modal pertanyaan
        const registerForm = document.getElementById("register-form");
        const modal1 = document.getElementById("modal1");
        const modal2 = document.getElementById("modal2");
        const modal3 = document.getElementById("modal3");
        const modalDenied = document.getElementById("modalDenied");
        const modalSuccess = document.getElementById("modalSuccess");

        const closeModal1 = document.getElementById("close-modal1");
        const closeModal2 = document.getElementById("close-modal2");
        const closeModal3 = document.getElementById("close-modal3");
        const closeModalDenied = document.getElementById("close-modalDenied");
        const closeModalSuccess = document.getElementById("close-modalSuccess");

        const next1 = document.getElementById("next1");
        const next2 = document.getElementById("next2");
        const submitAnswers = document.getElementById("submit-answers");

        const redirectLogin = document.getElementById("redirect-login");
        const redirectLoginSuccess = document.getElementById("redirect-loginSuccess");

        const mainContent = document.getElementById("main-content");

        // Fungsi untuk menambahkan blur
        function addBlur() {
            mainContent.classList.add("blur");
        }

        // Fungsi untuk menghapus blur
        function removeBlur() {
            mainContent.classList.remove("blur");
        }

        // Menampilkan Modal Pertanyaan 1 saat submit
        registerForm.addEventListener("submit", function(event) {
            event.preventDefault(); // Mencegah pengiriman form langsung
            addBlur();
            modal1.style.display = "block";
        });

        // Menutup Modal 1
        closeModal1.addEventListener("click", () => {
            modal1.style.display = "none";
            removeBlur();
        });

        // Menutup Modal 2
        closeModal2.addEventListener("click", () => {
            modal2.style.display = "none";
            removeBlur();
        });

        // Menutup Modal 3
        closeModal3.addEventListener("click", () => {
            modal3.style.display = "none";
            removeBlur();
        });

        // Menutup Modal Akses Ditolak
        closeModalDenied.addEventListener("click", () => {
            modalDenied.style.display = "none";
            removeBlur();
        });

        // Menutup Modal Sukses Pendaftaran
        closeModalSuccess.addEventListener("click", () => {
            modalSuccess.style.display = "none";
            removeBlur();
        });

        // Menangani tombol "Berikutnya" di Modal 1
        next1.addEventListener("click", () => {
            const jawaban1 = document.getElementById("jawaban1").value.trim().toLowerCase();
            if (jawaban1 === "") {
                alert("Silakan isi jawaban sebelum melanjutkan.");
                return;
            }
            modal1.style.display = "none";
            modal2.style.display = "block";
        });

        // Menangani tombol "Berikutnya" di Modal 2
        next2.addEventListener("click", () => {
            const jawaban2 = document.getElementById("jawaban2").value.trim().toLowerCase();
            if (jawaban2 === "") {
                alert("Silakan isi jawaban sebelum melanjutkan.");
                return;
            }
            modal2.style.display = "none";
            modal3.style.display = "block";
        });

        // Menangani tombol "Daftar" di Modal 3
        submitAnswers.addEventListener("click", () => {
            const jawaban3 = document.getElementById("jawaban3").value.trim().toLowerCase();
            if (jawaban3 === "") {
                alert("Silakan isi jawaban sebelum melanjutkan.");
                return;
            }

            // Menambahkan jawaban ke form sebagai hidden inputs
            const form = document.getElementById("register-form");

            // Buat atau update hidden inputs
            let inputJawaban1 = document.querySelector('input[name="jawaban1"]');
            if (!inputJawaban1) {
                inputJawaban1 = document.createElement('input');
                inputJawaban1.type = 'hidden';
                inputJawaban1.name = 'jawaban1';
                form.appendChild(inputJawaban1);
            }
            inputJawaban1.value = document.getElementById("jawaban1").value.trim();

            let inputJawaban2 = document.querySelector('input[name="jawaban2"]');
            if (!inputJawaban2) {
                inputJawaban2 = document.createElement('input');
                inputJawaban2.type = 'hidden';
                inputJawaban2.name = 'jawaban2';
                form.appendChild(inputJawaban2);
            }
            inputJawaban2.value = document.getElementById("jawaban2").value.trim();

            let inputJawaban3 = document.querySelector('input[name="jawaban3"]');
            if (!inputJawaban3) {
                inputJawaban3 = document.createElement('input');
                inputJawaban3.type = 'hidden';
                inputJawaban3.name = 'jawaban3';
                form.appendChild(inputJawaban3);
            }
            inputJawaban3.value = document.getElementById("jawaban3").value.trim();

            modal3.style.display = "none";
            removeBlur();

            // Kirim form
            form.submit();
        });

        // Menangani pesan dari PHP
        <?php if ($message != ''): ?>
            document.addEventListener("DOMContentLoaded", function() {
                <?php if ($message === "Akses Ditolak"): ?>
                    addBlur();
                    modalDenied.style.display = "block";
                <?php elseif ($message === "Admin baru berhasil didaftarkan!"): ?>
                    addBlur();
                    modalSuccess.style.display = "block";
                <?php else: ?>
                    // Untuk pesan lain seperti "Username sudah ada!"
                    alert("<?php echo $message; ?>");
                <?php endif; ?>
            });
        <?php endif; ?>

        // Menangani tombol "OK" di Modal Akses Ditolak
        redirectLogin.addEventListener("click", () => {
            modalDenied.style.display = "none";
            removeBlur();
            window.location.href = "login.php";
        });

        // Menangani tombol "OK" di Modal Sukses Pendaftaran
        redirectLoginSuccess.addEventListener("click", () => {
            modalSuccess.style.display = "none";
            removeBlur();
            window.location.href = "login.php";
        });

        // Menutup modal ketika klik di luar modal
        window.onclick = function(event) {
            if (event.target == modal1) {
                modal1.style.display = "none";
                removeBlur();
            }
            if (event.target == modal2) {
                modal2.style.display = "none";
                removeBlur();
            }
            if (event.target == modal3) {
                modal3.style.display = "none";
                removeBlur();
            }
            if (event.target == modalDenied) {
                modalDenied.style.display = "none";
                removeBlur();
                window.location.href = "login.php";
            }
            if (event.target == modalSuccess) {
                modalSuccess.style.display = "none";
                removeBlur();
                window.location.href = "login.php";
            }
        }
    </script>
</body>
</html>