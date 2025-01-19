<?php
// Start the session
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "refresh_oil";

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass); 
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Extract first name from email
    $email = $user['email'];
    $parts = explode("@", $email);
    $name = $parts[0];
    $name = str_replace([".", "-"], " ", $name);
    $user['name'] = ucwords($name);

} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Terjadi kesalahan pada sistem. Silakan coba beberapa saat lagi.";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Butuh Bantuan - RefreshOil</title>
    <link href="../gambar/logo.png" rel="shortcut icon">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        .font-poppins {
            font-family: 'Poppins', sans-serif;
        }

        /* CSS untuk Modal */
        .modal {
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s ease;
        }
        .modal.active {
            opacity: 1;
            pointer-events: auto;
        }

        /* CSS untuk Slider (Jika Dibutuhkan) */
        .slider-container {
            overflow: hidden;
            position: relative;
            width: 100%;
            max-width: 1200px; /* Sesuaikan dengan lebar yang diinginkan */
            margin: 0 auto;
        }
        .slider-wrapper {
            display: flex;
            transition: transform 0.5s ease-in-out;
        }
        .slider-slide {
            min-width: 100%;
            box-sizing: border-box;
        }

        /* Animasi untuk FAQ */
        details > summary:hover {
            background-color: #f9fafb;
            cursor: pointer;
        }

        /* Rotasi Ikon Chevron saat Open */
        details[open] > summary > .chevron {
            transform: rotate(180deg);
        }
    </style>
</head>

<body class="bg-gray-50 font-poppins">
    <!-- Mobile Header -->
    <header class="md:hidden flex items-center justify-between p-4 bg-white shadow-md fixed top-0 left-0 right-0 z-50">
        <div class="flex items-center flex-shrink-0">
            <button id="mobileMenuBtn" class="text-gray-700 focus:outline-none">
                <i class="fas fa-bars fa-2x"></i>
            </button>
            <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="w-24 h-auto ml-3 flex-shrink-0">
        </div>
        <!-- Bagian Profil Dihapus -->
    </header>

    <!-- Sidebar -->
    <div class="fixed w-64 h-screen p-5 bg-white shadow-md flex flex-col justify-between hidden md:flex">
        <div>
            <div class="text-center mb-5">
                <a href="dashboard.php">
                    <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="mt-5 w-full h-auto">
                </a>
            </div>
            <nav class="flex flex-col px-4">
                <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100 transition-colors">
                    <i class="fas fa-home mr-3"></i>Dashboard
                </a>
                <a href="penjemputan.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100 transition-colors">
                    <i class="fas fa-truck mr-3"></i>Penjemputan
                </a>
                <a href="reward.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100 transition-colors">
                    <i class="fas fa-gift mr-3"></i>Reward
                </a>
                <a href="riwayat.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100 transition-colors">
                    <i class="fas fa-calendar-alt mr-3"></i>Riwayat
                </a>
                <a href="edukasi.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100 transition-colors">
                    <i class="fas fa-book mr-3"></i>Edukasi
                </a>
            </nav>
        </div>
        <div class="border-t border-gray-300 w-40 mx-auto my-4"></div>
        <div class="flex flex-col px-4">
            <a href="faq.php" class="flex items-center px-4 py-3 bg-yellow-400 text-gray-700 rounded-lg mb-2 transition-colors hover:bg-yellow-500">
                <i class="fas fa-question-circle mr-3"></i>Butuh Bantuan?
            </a>
        </div>
    </div>

    <!-- Main Content & Sidebar -->
    <div class="ml-0 md:ml-64 max-w-7xl mx-auto">

        <!-- Main Content: Butuh Bantuan -->
        <section class="dark:bg-gray-50 dark:text-gray-800">
            <div class="container mx-auto px-4 py-8 md:p-12">

                <!-- Gambar Header Tanpa Background Kartu -->
                <!-- Mengurangi margin-bottom dari mb-6 menjadi mb-4 -->
                <div class="text-center mb-4">
                    <img src="../gambar/faq3.png" alt="Butuh Bantuan" class="w-full h-auto object-cover mx-auto">
                </div>

                <!-- Flex Container untuk Dua Kartu -->
                <div class="flex flex-col lg:flex-row lg:space-x-12 space-y-12 lg:space-y-0 lg:items-center">

                    <!-- Tentang Platform Kami -->
                    <div class="w-full lg:w-1/2 bg-white p-8 rounded-2xl shadow-lg transition-transform transform hover:-translate-y-1 hover:shadow-xl">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-info-circle text-3xl text-yellow-500 mr-3"></i>
                            <h2 class="text-3xl font-semibold text-gray-800">Tentang Platform Kami</h2>
                        </div>
                        <p class="text-gray-600 leading-relaxed">
                            RefreshOil adalah sebuah platform inovatif yang dikembangkan oleh tim 100%, atau lebih dikenal sebagai kelompok 4, dalam rangka memenuhi tugas mata kuliah Proyek 1 dan IUXD. Kami berdedikasi untuk menyediakan solusi terbaik dalam manajemen dan daur ulang minyak jelantah, dengan fitur-fitur yang memudahkan pengguna dalam proses pengumpulan dan pemanfaatan ulang minyak jelantah.
                        </p>
                    </div>

                    <!-- Dropdown Q&A -->
                    <div class="w-full lg:w-1/2 bg-white p-8 rounded-2xl shadow-lg transition-transform transform hover:-translate-y-1 hover:shadow-xl">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-question-circle text-3xl text-yellow-500 mr-3"></i>
                            <h2 class="text-3xl font-semibold text-gray-800 mb-6">FAQ</h2>
                        </div>
                        <div class="space-y-6">
                            <details class="w-full bg-white border-2 border-yellow-400 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-200">
                                <summary class="px-6 py-4 flex items-center justify-between text-lg font-medium text-gray-800 focus:outline-none">
                                    <span>Bagaimana cara mendapatkan poin?</span>
                                    <i class="fas fa-chevron-down chevron transition-transform duration-200"></i>
                                </summary>
                                <p class="px-6 py-4 text-gray-600">
                                    Kamu akan mendapatkan poin setiap melakukan transaksi dengan minimal 0.5 liter.
                                </p>
                            </details>
                            <details class="w-full bg-white border-2 border-yellow-400 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-200">
                                <summary class="px-6 py-4 flex items-center justify-between text-lg font-medium text-gray-800 focus:outline-none">
                                    <span>Apakah poin dapat ditukar?</span>
                                    <i class="fas fa-chevron-down chevron transition-transform duration-200"></i>
                                </summary>
                                <p class="px-6 py-4 text-gray-600">
                                    Ya, dapat ditukar dengan voucher potongan biaya ongkir ataupun sembako yang telah kami sediakan di halaman Reward. Kamu juga dapat mendonasikan poinmu untuk platform ini.
                                </p>
                            </details>
                            <details class="w-full bg-white border-2 border-yellow-400 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-200">
                                <summary class="px-6 py-4 flex items-center justify-between text-lg font-medium text-gray-800 focus:outline-none">
                                    <span>Bagaimana cara melakukan pembayaran?</span>
                                    <i class="fas fa-chevron-down chevron transition-transform duration-200"></i>
                                </summary>
                                <ul class="px-6 py-4 text-gray-600 list-disc list-inside space-y-2">
                                    <li>Buka aplikasi pembayaran.</li>
                                    <li>Scan kode QRIS yang kami sediakan.</li>
                                    <li>Masukkan nominal transaksi yang tampil di layar.</li>
                                </ul>
                            </details>
                            <!-- Tambahkan pertanyaan dan jawaban lainnya sesuai kebutuhan -->
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Footer Section -->
    <footer class="bg-white mt-4 shadow-lg ml-0 md:ml-64 transition-all duration-300">
        <div class="w-full p-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <a href="dashboard.php" class="flex items-center">
                    <img src="../gambar/Banner2.png" class="h-8" alt="RefreshOil Logo" />
                </a>
                <span class="text-sm items-end text-gray-500">
                    © <?php echo date("Y"); ?> <a href="dashboard.php" class="hover:text-yellow-500">RefreshOil™</a>. All Rights Reserved.
                </span>
            </div>
        </div>
    </footer>

    <script>
        // Animasi Ikon Chevron pada Dropdown
        const detailsElements = document.querySelectorAll('details');
        detailsElements.forEach(detail => {
            const summary = detail.querySelector('summary');
            const icon = summary.querySelector('.chevron');

            summary.addEventListener('click', () => {
                // Toggle rotate class
                icon.classList.toggle('rotate-180');
            });
        });

        // Contoh Interaksi Hover dengan JS (opsional)
        const newsCards = document.querySelectorAll('.group');
        newsCards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                console.log('Mouse masuk ke kartu!');
            });
            card.addEventListener('mouseleave', () => {
                console.log('Mouse keluar dari kartu!');
            });
        });
    </script>
</body>
</html>