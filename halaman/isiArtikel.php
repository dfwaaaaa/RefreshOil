<?php
// isiArtikel.php

// Start the session
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Koneksi ke database
$servername = "localhost"; // Ganti dengan nama server Anda
$username = "root";        // Ganti dengan username database Anda
$password = "";            // Ganti dengan password database Anda
$dbname = "refresh_oil";   // Ganti dengan nama database Anda

$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Fetch user data
$stmt_user = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->bind_param("i", $_SESSION['user_id']);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();
$stmt_user->close();

// Inisialisasi variabel artikel
$artikel = null;
$error = "";

// Memeriksa apakah 'id' ada dalam parameter GET
if (isset($_GET['id'])) {
    $id_artikel = $_GET['id'];

    // Validasi 'id_artikel' agar hanya angka yang diterima
    if (filter_var($id_artikel, FILTER_VALIDATE_INT)) {
        // Menyiapkan statement untuk mencegah SQL Injection
        // Ganti 'id_artikel' jika nama kolom identifier unik Anda berbeda
        $stmt = $conn->prepare("SELECT judul, tipe, jenis, tgl_publish, sumber, konten, gambar_artikel, gambar_sumber FROM artikel WHERE id_artikel = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id_artikel);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                // Ganti \r\n\r\ dengan \r\n\r\n jika diperlukan
                // Jika tidak diperlukan, Anda bisa menghapus baris ini
                $konten_bersih = str_replace("\r\n\r\\", "\r\n\r\n", $row['konten']);
                
                // Menyimpan data artikel dalam array
                $artikel = [
                    'judul' => $row['judul'],
                    'tipe' => $row['tipe'],
                    'jenis' => $row['jenis'],
                    'tgl_publish' => $row['tgl_publish'],
                    'sumber' => $row['sumber'],
                    'konten' => $konten_bersih,
                    'gambar_artikel' => $row['gambar_artikel'],
                    'gambar_sumber' => $row['gambar_sumber']
                ];

                // Update jumlah views
                $stmt_update = $conn->prepare("UPDATE artikel SET views = views + 1 WHERE id_artikel = ?");
                if ($stmt_update) {
                    $stmt_update->bind_param("i", $id_artikel);
                    if (!$stmt_update->execute()) {
                        // Log error jika gagal memperbarui views
                        error_log("Gagal memperbarui views untuk artikel ID: $id_artikel. Error: " . $stmt_update->error);
                    }
                    $stmt_update->close();
                } else {
                    // Log error jika gagal menyiapkan statement update
                    error_log("Prepare failed for UPDATE views: " . $conn->error);
                }
            } else {
                $error = "Artikel tidak ditemukan.";
            }

            $stmt->close();
        } else {
            $error = "Terjadi kesalahan pada database: " . htmlspecialchars($conn->error);
        }
    } else {
        $error = "ID artikel tidak valid.";
    }
} else {
    $error = "ID artikel tidak ditentukan.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $artikel ? htmlspecialchars($artikel['judul']) . " - RefreshOil" : "Artikel Tidak Ditemukan - RefreshOil" ?></title>
    <link href="../gambar/logo.png" rel="shortcut icon">
    
    <!-- External Resources -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#F4C430',    /* Warna kuning dominan */
                        secondary: '#FFFFFF',   /* Warna putih */
                        accent: '#353535',      /* Warna abu-abu gelap */
                    }
                }
            }
        }
    </script>

    <!-- Custom Styles -->
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        .modal {
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .modal.active {
            opacity: 1;
            pointer-events: auto;
        }

        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }

        /* Hide scrollbar for IE, Edge and Firefox */
        .scrollbar-hide {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }

        /* Dropdown Styles */
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 160px;
            box-shadow: 0px 8px 16px rgba(0,0,0,0.2);
            z-index: 1000;
            border-radius: 8px;
        }

        .dropdown.show .dropdown-content {
            display: block;
        }

        .dropdown-content a {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #4B5563;
        }

        .dropdown-content a:hover {
            background-color: #F4C430;
            color: #1F2937;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Mobile Header with Hamburger -->
    <header class="md:hidden flex items-center justify-between p-4 bg-white shadow-md fixed top-0 left-0 right-0 z-50 flex-nowrap">
        <div class="flex items-center flex-shrink-0">
            <button id="mobileMenuBtn" class="text-gray-700 focus:outline-none">
                <i class="fas fa-bars fa-2x"></i>
            </button>
            <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="w-24 h-auto ml-3 flex-shrink-0">
        </div>
        <div class="flex items-center space-x-4 flex-shrink-0">
            <div class="dropdown">
                <img src="../gambar/profil.png" alt="User Avatar" class="w-8 h-8 rounded-full" id="mobileProfileBtn">
                <div class="dropdown-content mt-2">
                    <a href="profil.php"><i class="fas fa-user mr-2"></i>Lihat Profil</a>
                    <a href="#" id="logoutBtnMobile" class="logoutLink"><i class="fas fa-sign-out-alt mr-2"></i>Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <div id="sidebar" class="fixed w-64 h-screen p-5 bg-white shadow-md flex flex-col justify-between">
        <div>
            <div class="text-center mb-5">
                <a href="dashboard.php">
                    <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="mt-5 w-full h-auto">
                </a>
            </div>
          <nav class="flex flex-col px-4">
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
              <i class="fas fa-home mr-3"></i>Dashboard
            </a>
            <a href="penjemputan.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
              <i class="fas fa-truck mr-3"></i>Penjemputan
            </a>
            <a href="reward.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
              <i class="fas fa-gift mr-3"></i>Reward
            </a>
            <a href="riwayat.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
              <i class="fas fa-calendar-alt mr-3"></i>Riwayat
            </a>
            <a href="edukasi.php" class="flex items-center px-4 py-3 bg-yellow-400 text-gray-700 rounded-lg mb-2">
              <i class="fas fa-book mr-3"></i>Edukasi
            </a>
          </nav>
        </div>
        <div class="border-t border-gray-300 w-40 mx-auto my-4"></div>
        <div class="flex flex-col px-4">
          <a href="faq.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
            <i class="fas fa-question-circle mr-3"></i>Butuh Bantuan?
          </a>
        </div>
    </div>
    <!-- Overlay -->
    <div id="overlay" class="fixed inset-0 bg-black opacity-50 hidden z-40"></div>

    <!-- Main Content (Isi Artikel) -->
    <div class="md:ml-64 p-6">
        <?php if ($artikel): ?>
            <article class="bg-white rounded-lg shadow-sm p-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-4"><?= htmlspecialchars($artikel['judul']) ?></h1>
                
                <div class="flex items-center space-x-4 text-sm text-gray-600 mb-6">
                    <?php if ($artikel['gambar_sumber']): ?>
                        <img src="<?= htmlspecialchars($artikel['gambar_sumber']) ?>" alt="Author" class="h-10 w-10 rounded-full">
                    <?php else: ?>
                        <img src="../gambar/kaltimkece.png" alt="Author" class="h-10 w-10 rounded-full">
                    <?php endif; ?>
                    <span><?= htmlspecialchars($artikel['sumber']) ?></span>
                    <span>•</span>
                    <span><?= date('d M Y', strtotime($artikel['tgl_publish'])) ?></span>
                </div>

                <?php if ($artikel['gambar_artikel']): ?>
                    <img src="<?= htmlspecialchars($artikel['gambar_artikel']) ?>" alt="Featured" class="w-full rounded-lg mb-6">
                <?php endif; ?>

                <!-- Menambahkan whitespace-pre-wrap -->
                <div class="prose max-w-none whitespace-pre-wrap">
                    <?= nl2br(htmlspecialchars($artikel['konten'])) ?>
                </div>
            </article>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h1 class="text-2xl font-bold text-red-600 mb-4">Artikel Tidak Ditemukan</h1>
                <p class="text-gray-700">Maaf, artikel yang Anda cari tidak ditemukan. Mungkin telah dihapus atau ID yang Anda masukkan salah.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-white mt-5 shadow-lg ml-0 md:ml-64 transition-all duration-300">
        <div class="w-full p-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <a href="dashboard.php" class="flex items-center">
                    <img src="../gambar/Banner2.png" class="h-8" alt="RefreshOil Logo" />
                </a>
                <span class="text-sm items-end text-gray-500">
                © <?= date("Y"); ?> <a href="dashboard.php" class="hover:text-yellow-500">RefreshOil™</a>. All Rights Reserved.
                </span>
            </div>
        </div>
    </footer>

    <!-- Logout Modal -->
    <div id="logoutModal" class="modal fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-lg p-6 w-80">
            <h2 class="text-xl font-bold mb-4">Konfirmasi Logout</h2>
            <p class="mb-6">Apakah Anda yakin ingin keluar?</p>
            <div class="flex justify-end space-x-4">
                <button id="cancelBtn" class="px-4 py-2 bg-gray-300 rounded">Batal</button>
                <button id="confirmBtn" class="px-4 py-2 bg-red-500 text-white rounded">Keluar</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById("mobileMenuBtn");
        const sidebar = document.getElementById("sidebar");
        const overlay = document.getElementById("overlay");

        mobileMenuBtn.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
            overlay.classList.toggle("hidden");
        });

        overlay.addEventListener("click", () => {
            sidebar.classList.add("-translate-x-full");
            overlay.classList.add("hidden");
        });

        // Logout Modal
        const logoutBtnMobile = document.getElementById("logoutBtnMobile");
        const logoutModal = document.getElementById("logoutModal");
        const cancelBtn = document.getElementById("cancelBtn");
        const confirmBtn = document.getElementById("confirmBtn");

        if (logoutBtnMobile) {
            logoutBtnMobile.addEventListener("click", () => {
                logoutModal.classList.add("active");
            });
        }

        cancelBtn.addEventListener("click", () => {
            logoutModal.classList.remove("active");
        });

        confirmBtn.addEventListener("click", () => {
            window.location.href = "logout.php";
        });

        // Close modal when clicking outside the modal content
        logoutModal.addEventListener("click", (e) => {
            if (e.target === logoutModal) {
                logoutModal.classList.remove("active");
            }
        });

        // Close modal on Escape key
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && logoutModal.classList.contains("active")) {
                logoutModal.classList.remove("active");
            }
        });

        // Dropdown
        const mobileProfileBtn = document.getElementById("mobileProfileBtn");
        const dropdowns = document.querySelectorAll(".dropdown");

        function closeAllDropdowns() {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove("show");
            });
        }

        if (mobileProfileBtn) {
            mobileProfileBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                dropdowns.forEach(dropdown => {
                    if (dropdown.contains(mobileProfileBtn)) {
                        dropdown.classList.toggle("show");
                    } else {
                        dropdown.classList.remove("show");
                    }
                });
            });
        }

        document.addEventListener("click", () => {
            closeAllDropdowns();
        });
    </script>
</body>
</html>