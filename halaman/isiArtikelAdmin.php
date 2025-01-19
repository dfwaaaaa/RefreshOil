<?php
// isiArtikel.php

// Start the session
session_start();

// Cek apakah pengguna sudah login sebagai admin
if (!isset($_SESSION['id_admin'])) {
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

// Fetch data admin jika session 'id_admin' ada
$stmt_admin = $conn->prepare("SELECT * FROM admin WHERE id_admin = ?");
$stmt_admin->bind_param("i", $_SESSION['id_admin']);
$stmt_admin->execute();
$result_admin = $stmt_admin->get_result();
$admin = $result_admin->fetch_assoc();
$stmt_admin->close();

// Inisialisasi variabel artikel
$artikel = null;
$error = "";

// Memeriksa apakah 'id_artikel' ada dalam parameter GET
if (isset($_GET['id_artikel'])) {
    $id_artikel = $_GET['id_artikel'];

    // Validasi 'id_artikel' agar hanya angka yang diterima
    if (filter_var($id_artikel, FILTER_VALIDATE_INT)) {
        // Menyiapkan statement untuk mencegah SQL Injection
        $stmt = $conn->prepare("SELECT judul, tipe, jenis, tgl_publish, sumber, konten, gambar_artikel, gambar_sumber FROM artikel WHERE id_artikel = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id_artikel);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                // Bersihkan konten jika diperlukan
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
                <img src="../gambar/profil.png" alt="Admin Avatar" class="w-8 h-8 rounded-full" id="mobileProfileBtn">
                <div class="dropdown-content mt-2">
                    <a href="profil.php"><i class="fas fa-user mr-2"></i>Lihat Profil</a>
                    <a href="#" id="logoutBtnMobile" class="logoutLink"><i class="fas fa-sign-out-alt mr-2"></i>Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <div id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-white shadow-md transform -translate-x-full md:translate-x-0 transition-transform duration-300 z-50">
        <div class="p-5 flex flex-col justify-between h-full">
            <div>
                <div class="text-center mb-5">
                    <a href="dashboardAdmin.php">
                        <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="mt-5 w-full h-auto">
                    </a>
                </div>
                <nav class="flex flex-col px-4">
                    <a href="dashboardAdmin.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
                        <i class="fas fa-home mr-3"></i>Dashboard
                    </a>
                    
                    <a href="riwayatAdmin.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
                        <i class="fas fa-calendar-alt mr-3"></i>Riwayat
                    </a>
                    <a href="edukasiAdmin.php" class="flex items-center px-4 py-3 bg-yellow-400 text-gray-700 rounded-lg mb-2">
                        <i class="fas fa-book mr-3"></i>Edukasi
                    </a>
                    <a href="profilAdmin.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
                        <i class="fas fa-user mr-3"></i>Profil
                    </a>
                </nav>
            </div>
            <div class="flex flex-col px-4">
                <a href="#" id="logoutBtn" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-yellow-100">
                    <i class="fas fa-sign-out-alt mr-3"></i>Keluar
                </a>
            </div>
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

        <!-- Tombol Edit Artikel (Hanya untuk Admin) -->
        <?php if ($artikel): ?>
            <a href="editArtikel.php?id_artikel=<?= htmlspecialchars($id_artikel) ?>" class="fixed bottom-6 right-6 bg-yellow-400 text-white w-16 h-16 rounded-full flex items-center justify-center shadow-lg hover:bg-yellow-500 transition-colors">
                <i class="fas fa-pencil-alt fa-lg"></i>
            </a>
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

    <!-- Scripts -->
    <script>
        // Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById("mobileMenuBtn");
        const sidebar = document.getElementById("sidebar");
        const overlay = document.getElementById("overlay");

        mobileMenuBtn.addEventListener("click", () => {
            sidebar.classList.toggle("transform");
            sidebar.classList.toggle("-translate-x-full");
            overlay.classList.toggle("hidden");
        });

        // Logout Modal Elements
        const logoutBtnMobile = document.getElementById("logoutBtnMobile");
        const logoutBtn = document.getElementById("logoutBtn");
        const logoutModal = document.getElementById("logoutModal"); // Pastikan Anda memiliki modal logout di halaman ini
        const cancelBtn = document.getElementById("cancelBtn");
        const confirmBtn = document.getElementById("confirmBtn");

        // Pastikan Anda memiliki modal logout di halaman ini atau abaikan jika tidak
        // Jika tidak ada, Anda bisa menambahkan modal logout seperti di edukasiAdmin.php

        if (logoutBtnMobile) {
            logoutBtnMobile.addEventListener("click", () => {
                if (logoutModal) {
                    logoutModal.classList.add("active");
                } else {
                    // Jika modal tidak ada, redirect langsung atau tampilkan alert
                    window.location.href = "logout.php";
                }
            });
        }

        if (logoutBtn) {
            logoutBtn.addEventListener("click", () => {
                if (logoutModal) {
                    logoutModal.classList.add("active");
                } else {
                    // Jika modal tidak ada, redirect langsung atau tampilkan alert
                    window.location.href = "logout.php";
                }
            });
        }

        if (logoutModal) {
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
        }

        // Dropdown Functionality (Jika ada dropdown di halaman ini)
        const desktopProfileBtn = document.getElementById("desktopProfileBtn");
        const mobileProfileBtn = document.getElementById("mobileProfileBtn");
        const dropdowns = document.querySelectorAll(".dropdown");

        function closeAllDropdowns() {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove("show");
            });
        }

        if (desktopProfileBtn) {
            desktopProfileBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                dropdowns.forEach(dropdown => {
                    if (dropdown.contains(desktopProfileBtn)) {
                        dropdown.classList.toggle("show");
                    } else {
                        dropdown.classList.remove("show");
                    }
                });
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

        // Contoh Interaksi Hover dengan JS (opsional)
        const newsCards = document.querySelectorAll(".group");
        newsCards.forEach(card => {
            card.addEventListener("mouseenter", () => {
                console.log("Mouse masuk ke kartu!");
            });
            card.addEventListener("mouseleave", () => {
                console.log("Mouse keluar dari kartu!");
            });
        });
    </script>
</body>
</html>