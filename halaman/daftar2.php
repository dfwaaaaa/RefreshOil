<?php
// Memulai sesi PHP untuk mengelola data pengguna secara persisten
session_start();

// Memeriksa apakah pengguna sudah login dengan memeriksa keberadaan 'user_id' dalam sesi
if (!isset($_SESSION['user_id'])) {
    // Jika 'user_id' tidak ada, hentikan eksekusi dan tampilkan pesan akses tidak sah
    die("Akses tidak sah! Anda harus mendaftar terlebih dahulu.");
}

// Konfigurasi koneksi ke database
$host = "localhost";          // Alamat host database, biasanya 'localhost' jika database berada di server yang sama
$user = "root";               // Username untuk mengakses database
$password = "";               // Password untuk mengakses database
$dbname = "refresh_oil";      // Nama database yang akan diakses

// Membuat koneksi ke database menggunakan mysqli
$conn = new mysqli($host, $user, $password, $dbname);

// Memeriksa apakah koneksi berhasil
if ($conn->connect_error) {
    // Jika terjadi kesalahan koneksi, hentikan eksekusi dan tampilkan pesan error
    die("Koneksi gagal: " . $conn->connect_error);
}

// Memeriksa apakah form telah disubmit menggunakan metode POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Memeriksa apakah field 'category' telah diisi dalam form
    if (isset($_POST['category'])) {
        // Mengambil dan membersihkan data input kategori untuk menghindari serangan SQL Injection
        $category = $conn->real_escape_string($_POST['category']);
        // Mengambil 'user_id' dari sesi untuk digunakan dalam query
        $user_id = $_SESSION['user_id'];

        // Membuat query SQL untuk memperbarui kategori pengguna berdasarkan 'user_id'
        $sql = "UPDATE users SET category = '$category' WHERE id = $user_id";

        // Menjalankan query SQL dan memeriksa apakah berhasil
        if ($conn->query($sql) === TRUE) {
            // Jika pembaruan berhasil, arahkan pengguna ke halaman login
            header("Location: login.php");
            exit; // Menghentikan eksekusi skrip setelah redirect
        } else {
            // Jika terjadi kesalahan saat menjalankan query, tampilkan pesan error
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    } else {
        // Jika field 'category' tidak diisi, hentikan eksekusi dan tampilkan pesan error
        die("Kategori harus dipilih!");
    }
}

// Menutup koneksi ke database setelah semua proses selesai
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Kategori - RefreshOil</title>
    <link href="../gambar/logo.png" rel="shortcut icon">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex justify-center items-center relative font-['Poppins'] bg-gray-100">
    <div class="fixed inset-0 z-0">
        <img src="../gambar/banyak.png" alt="Background" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
    </div>

    <div class="bg-white p-8 md:p-10 rounded-2xl w-full max-w-md mx-4 shadow-lg z-10">
        <div class="flex flex-col items-center">
            <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="w-3/5 mb-6">
        </div>

        <h3 class="text-xl font-medium text-center text-gray-800 mb-8">
            Apa kategori Anda?
        </h3>

        <form id="category-form" method="POST" action="daftar2.php" class="space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <div class="relative group">
                    <input type="radio" name="category" value="bisnis" id="bisnis" required
                        class="absolute opacity-0 w-full h-full cursor-pointer z-10">
                    <label for="bisnis" 
                        class="block text-center p-4 border-2 border-gray-200 rounded-lg transition-all duration-300 
                        group-hover:border-yellow-400 group-hover:shadow-lg group-hover:scale-105 cursor-pointer
                        hover:bg-yellow-50">
                        <div class="overflow-hidden rounded-lg mb-2">
                            <img src="../gambar/bisnis.png" alt="Kategori Bisnis" 
                                class="w-full h-32 object-cover transition-transform duration-300 
                                group-hover:scale-110">
                        </div>
                        <p class="text-gray-700 font-medium group-hover:text-yellow-600 transition-colors duration-300">
                            Bisnis
                        </p>
                    </label>
                </div>

                <div class="relative group">
                    <input type="radio" name="category" value="umum" id="umum" required
                        class="absolute opacity-0 w-full h-full cursor-pointer z-10">
                    <label for="umum" 
                        class="block text-center p-4 border-2 border-gray-200 rounded-lg transition-all duration-300 
                        group-hover:border-yellow-400 group-hover:shadow-lg group-hover:scale-105 cursor-pointer
                        hover:bg-yellow-50">
                        <div class="overflow-hidden rounded-lg mb-2">
                            <img src="../gambar/umum.png" alt="Kategori Umum" 
                                class="w-full h-32 object-cover transition-transform duration-300 
                                group-hover:scale-110">
                        </div>
                        <p class="text-gray-700 font-medium group-hover:text-yellow-600 transition-colors duration-300">
                            Umum
                        </p>
                    </label>
                </div>
            </div>

            <button type="submit" id="submit-btn"
                class="w-full py-3 px-4 bg-white border border-gray-300 rounded-lg font-medium 
                transition-all duration-300 hover:bg-yellow-400 hover:border-yellow-400 hover:shadow-lg
                disabled:opacity-50 disabled:cursor-not-allowed">
                Daftar
            </button>
        </form>
    </div>

    <script>
        const radioInputs = document.querySelectorAll('input[type="radio"]');
        const submitButton = document.getElementById('submit-btn');

        radioInputs.forEach(input => {
            input.addEventListener('change', () => {
                document.querySelectorAll('label').forEach(label => {
                    label.classList.remove('border-yellow-400', 'bg-yellow-50', 'shadow-lg', 'scale-105');
                    label.classList.add('border-gray-200');
                });
                
                if (input.checked) {
                    const label = input.nextElementSibling;
                    label.classList.remove('border-gray-200');
                    label.classList.add('border-yellow-400', 'bg-yellow-50', 'shadow-lg', 'scale-105');
                    
                    submitButton.classList.remove('bg-white');
                    submitButton.classList.add('bg-yellow-400', 'shadow-lg');
                }
            });
        });
    </script>
</body>
</html>