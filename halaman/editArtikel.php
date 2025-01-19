<?php
// editArtikel.php

// Mulai sesi untuk autentikasi dan pesan flash
session_start();

// Cek apakah pengguna sudah login
if (!isset($_SESSION['id_admin'])) {
    header("Location: loginAdmin.php");
    exit();
}

// Koneksi ke database menggunakan mysqli
$servername = "localhost"; // Ganti dengan nama server Anda
$username = "root";        // Ganti dengan username database Anda
$password = "";            // Ganti dengan password database Anda
$dbname = "refresh_oil";   // Ganti dengan nama database Anda

$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Inisialisasi variabel untuk pesan
$success = "";
$error = "";

// Mengambil pesan sukses dari session jika ada
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Mengambil pesan error dari session jika ada
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Inisialisasi variabel form dengan nilai default
$id_artikel = "";
$judul = "";
$tipe = "";
$jenis = "";
$tanggal = date('Y-m-d'); // Default tanggal hari ini
$sumber = "";
$konten = "";
$gambar = "";
$gambar_sumber = "";

// Mengecek apakah ada 'id_artikel' yang diberikan melalui GET
if (isset($_GET['id_artikel'])) {
    $id_artikel = (int)$_GET['id_artikel'];

    // Mengambil data artikel berdasarkan 'id_artikel'
    $stmt_fetch = $conn->prepare("SELECT * FROM artikel WHERE id_artikel = ?");
    if ($stmt_fetch === false) {
        error_log("Prepare failed for SELECT: " . $conn->error);
        $_SESSION['error_message'] = "Terjadi kesalahan pada database. Silakan coba lagi.";
        header("Location: edukasiAdmin.php");
        exit();
    }
    $stmt_fetch->bind_param("i", $id_artikel);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();

    if ($result->num_rows === 1) {
        $artikel = $result->fetch_assoc();
        $judul = $artikel['judul'];
        $tipe = $artikel['tipe'];
        $jenis = $artikel['jenis'];
        $tanggal = $artikel['tgl_publish'];
        $sumber = $artikel['sumber'];
        $konten = $artikel['konten'];
        $gambar = $artikel['gambar_artikel'];
        $gambar_sumber = $artikel['gambar_sumber'];
    } else {
        $_SESSION['error_message'] = "Artikel tidak ditemukan.";
        header("Location: edukasiAdmin.php");
        exit();
    }
    $stmt_fetch->close();
} else {
    $_SESSION['error_message'] = "ID artikel tidak diberikan.";
    header("Location: edukasiAdmin.php");
    exit();
}

// Pemrosesan form saat disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Mengambil data dari form dan sanitasi input
    $id_artikel = (int)$_POST['id_artikel'];
    $judul = trim($_POST['title']);
    $tipe = trim($_POST['tipe']);
    $jenis = trim($_POST['jenis']);
    $tanggal = $_POST['date'];
    $sumber = trim($_POST['source']);
    $konten = trim($_POST['content']);

    // Validasi input
    if (empty($judul) || empty($tipe) || empty($jenis) || empty($tanggal) || empty($sumber) || empty($konten)) {
        $error .= "Semua field wajib diisi.<br>";
    }

    // Handling upload gambar
    // Direktori tempat menyimpan gambar
    $target_dir = "../uploads/";

    // Proses upload gambar Artikel
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $gambar_filename = basename($_FILES['gambar']['name']);
        $gambar_extension = strtolower(pathinfo($gambar_filename, PATHINFO_EXTENSION));

        // Cek tipe file
        if (in_array($gambar_extension, $allowed_types)) {
            // Membuat nama file unik
            $gambar_new_filename = uniqid() . "_" . preg_replace("/[^A-Za-z0-9_\-\.]/", '_', $gambar_filename);
            $gambar_target = $target_dir . $gambar_new_filename;

            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $gambar_target)) {
                // Menghapus gambar lama jika ada dan bukan default
                if (!empty($gambar) && file_exists($gambar)) {
                    unlink($gambar);
                }
                $gambar = $gambar_target;
            } else {
                $error .= "Gagal mengupload gambar artikel.<br>";
            }
        } else {
            $error .= "Format gambar artikel tidak diperbolehkan. Hanya JPG, JPEG, PNG, dan GIF yang diizinkan.<br>";
        }
    }

    // Proses upload gambar Sumber Artikel
    if (isset($_FILES['gambar_sumber']) && $_FILES['gambar_sumber']['error'] == 0) {
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $gambar_sumber_filename = basename($_FILES['gambar_sumber']['name']);
        $gambar_sumber_extension = strtolower(pathinfo($gambar_sumber_filename, PATHINFO_EXTENSION));

        // Cek tipe file
        if (in_array($gambar_sumber_extension, $allowed_types)) {
            // Membuat nama file unik
            $gambar_sumber_new_filename = uniqid() . "_" . preg_replace("/[^A-Za-z0-9_\-\.]/", '_', $gambar_sumber_filename);
            $gambar_sumber_target = $target_dir . $gambar_sumber_new_filename;

            if (move_uploaded_file($_FILES['gambar_sumber']['tmp_name'], $gambar_sumber_target)) {
                // Menghapus gambar sumber lama jika ada dan bukan default
                if (!empty($gambar_sumber) && file_exists($gambar_sumber)) {
                    unlink($gambar_sumber);
                }
                $gambar_sumber = $gambar_sumber_target;
            } else {
                $error .= "Gagal mengupload gambar sumber artikel.<br>";
            }
        } else {
            $error .= "Format gambar sumber artikel tidak diperbolehkan. Hanya JPG, JPEG, PNG, dan GIF yang diizinkan.<br>";
        }
    }

    // Jika tidak ada error, lanjutkan memperbarui data di database
    if (empty($error)) {
        // Cek apakah judul sudah ada untuk artikel lain
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM artikel WHERE judul = ? AND id_artikel != ?");
        if ($stmt_check === false) {
            // Log error dan set error message
            error_log("Prepare failed for SELECT COUNT(*): " . $conn->error);
            $_SESSION['error_message'] = "Terjadi kesalahan pada database. Silakan coba lagi.";
            header("Location: editArtikel.php?id_artikel=" . $id_artikel);
            exit();
        }
        $stmt_check->bind_param("si", $judul, $id_artikel);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) {
            $error .= "Judul artikel sudah ada. Silakan gunakan judul lain.<br>";
        } else {
            // Siapkan statement untuk UPDATE
            $stmt = $conn->prepare("UPDATE artikel SET judul = ?, tipe = ?, jenis = ?, tgl_publish = ?, sumber = ?, konten = ?, gambar_artikel = ?, gambar_sumber = ? WHERE id_artikel = ?");
            if ($stmt === false) {
                // Log error dan set error message
                error_log("Prepare failed for UPDATE: " . $conn->error);
                $_SESSION['error_message'] = "Terjadi kesalahan pada database saat memperbarui artikel. Silakan coba lagi.";
                header("Location: editArtikel.php?id_artikel=" . $id_artikel);
                exit();
            }

            $stmt->bind_param("ssssssssi", $judul, $tipe, $jenis, $tanggal, $sumber, $konten, $gambar, $gambar_sumber, $id_artikel);

            if ($stmt->execute()) {
                // Log sukses
                error_log("Artikel ID: " . $id_artikel . " diperbarui oleh user ID: " . $_SESSION['id_admin']);
                // Set pesan sukses di session
                $_SESSION['success_message'] = "Artikel berhasil diperbarui.";
                // Redirect ke edukasiAdmin.php atau halaman lain sesuai kebutuhan
                header("Location: edukasiAdmin.php");
                exit();
            } else {
                // Tangani error
                $error .= "Terjadi kesalahan saat memperbarui artikel: " . htmlspecialchars($stmt->error) . "<br>";
                // Log error
                error_log("Error saat memperbarui artikel ID: " . $id_artikel . " - " . $stmt->error);
            }

            $stmt->close();
        }

        // Jika ada error, set error message di session dan tetap pada form dengan data yang sudah diisi
        if (!empty($error)) {
            $_SESSION['error_message'] = $error;
            // Form akan menampilkan kembali data yang diisi karena variabel tetap terisi
        }
    }

    // Jika ada error, pesan akan ditampilkan di bawah
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Artikel - RefreshOil</title>
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

        /* Kartu Form */
        .form-card {
            background-color: #FFFFFF;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        /* Label */
        .form-label {
            font-weight: 500;
            color: #4B5563;
        }

        /* Input dan Select */
        .form-input, .form-select, .form-textarea {
            border: 1px solid #D1D5DB;
            padding: 0.75rem;
            border-radius: 0.375rem;
            width: 100%;
            transition: border-color 0.3s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: #F4C430;
            outline: none;
            box-shadow: 0 0 0 3px rgba(244, 196, 48, 0.3);
        }

        /* Tombol Submit */
        .submit-btn {
            background-color: #3B82F6;
            color: #FFFFFF;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            transition: background-color 0.3s;
        }

        .submit-btn:hover {
            background-color: #2563EB;
        }

        /* Pesan Sukses */
        .success-message {
            background-color: #D1FAE5;
            color: #065F46;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }

        /* Pesan Error */
        .error-message {
            background-color: #FEE2E2;
            color: #B91C1C;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }

        /* Preview Gambar */
        .image-preview {
            margin-top: 0.5rem;
        }

        .image-preview img {
            width: 8rem;
            height: 8rem;
            object-cover: cover;
            border-radius: 0.375rem;
            border: 1px solid #D1D5DB;
        }

        /* Responsive Adjustments for Image Uploads */
        @media (min-width: 768px) {
            .image-upload-container {
                display: flex;
                gap: 1.5rem;
            }
        }
    </style>
</head>

<body class="bg-gray-100">
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
                <div class="dropdown-content mt-2 bg-white rounded-lg shadow-lg">
                    <a href="profil.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100"><i class="fas fa-user mr-2"></i>Lihat Profil</a>
                    <a href="#" id="logoutBtnMobile" class="logoutLink block px-4 py-2 text-gray-700 hover:bg-gray-100"><i class="fas fa-sign-out-alt mr-2"></i>Keluar</a>
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

    <!-- Main Content (Edit Artikel) -->
    <div class="md:ml-64 p-6 mt-4"> <!-- Adjusted top margin to account for fixed header -->
        <div class="flex items-center space-x-4 mb-6">
            <!-- Tombol Kembali Bulat dengan Ikon Panah Kiri -->
            <a href="edukasiAdmin.php" aria-label="Kembali ke Edukasi Admin" class="flex items-center justify-center w-8 h-8 bg-blue-500 text-white rounded-full hover:bg-blue-600 transition-colors">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-3xl font-bold text-gray-900">Edit Artikel</h1>
        </div>

        <!-- Menampilkan Pesan Sukses atau Error -->
        <?php if (!empty($success)): ?>
            <div class="mb-4 success-message">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="mb-4 error-message">
                <?= nl2br(htmlspecialchars($error)) ?>
            </div>
        <?php endif; ?>

        <!-- Kartu Form Edit Artikel -->
        <div class="form-card">
            <form action="editArtikel.php?id_artikel=<?= htmlspecialchars($id_artikel) ?>" method="POST" enctype="multipart/form-data" autocomplete="off">
                <!-- Input Tersembunyi untuk ID Artikel -->
                <input type="hidden" name="id_artikel" value="<?= htmlspecialchars($id_artikel) ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Input Judul Artikel -->
                    <div>
                        <label for="title" class="form-label">Judul Artikel:</label>
                        <input type="text" name="title" id="title" value="<?= htmlspecialchars($judul) ?>" class="form-input" required>
                    </div>
                    
                    <!-- Dropdown Tipe Artikel -->
                    <div>
                        <label for="tipe" class="form-label">Tipe Artikel:</label>
                        <select name="tipe" id="tipe" class="form-select" required>
                            <option value="" disabled <?= empty($tipe) ? 'selected' : '' ?>>-- Pilih Tipe Artikel --</option>
                            <option value="edukasi" <?= ($tipe == 'edukasi') ? 'selected' : '' ?>>Edukasi</option>
                            <option value="berita" <?= ($tipe == 'berita') ? 'selected' : '' ?>>Berita</option>
                        </select>
                    </div>

                    <!-- Dropdown Jenis Artikel -->
                    <div>
                        <label for="jenis" class="form-label">Jenis Artikel:</label>
                        <select name="jenis" id="jenis" class="form-select" required>
                            <option value="" disabled <?= empty($jenis) ? 'selected' : '' ?>>-- Pilih Jenis Artikel --</option>
                            <option value="umum" <?= ($jenis == 'umum') ? 'selected' : '' ?>>Umum</option>
                            <option value="highlight" <?= ($jenis == 'highlight') ? 'selected' : '' ?>>Highlight</option>
                        </select>
                    </div>

                    <!-- Input Tanggal Artikel -->
                    <div>
                        <label for="date" class="form-label">Tanggal Artikel:</label>
                        <input type="date" name="date" id="date" value="<?= htmlspecialchars($tanggal) ?>" class="form-input" required>
                    </div>

                    <!-- Input Sumber Artikel -->
                    <div>
                        <label for="source" class="form-label">Sumber Artikel:</label>
                        <input type="text" name="source" id="source" value="<?= htmlspecialchars($sumber) ?>" class="form-input" required>
                    </div>
                </div>

                <!-- Input Konten Artikel -->
                <div class="mt-6">
                    <label for="content" class="form-label">Konten Artikel:</label>
                    <textarea name="content" id="content" rows="8" class="form-textarea" required><?= htmlspecialchars($konten) ?></textarea>
                </div>

                <!-- Container untuk Upload Gambar Artikel dan Sumber -->
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Gambar Artikel -->
                    <div>
                        <label for="gambar" class="form-label">Gambar Artikel:</label>
                        <?php if (!empty($gambar) && file_exists($gambar)): ?>
                            <div class="image-preview">
                                <img src="<?= htmlspecialchars($gambar) ?>" alt="Gambar Artikel">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="gambar" id="gambar" accept="image/*" class="form-input">
                    </div>

                    <!-- Gambar Sumber Artikel -->
                    <div>
                        <label for="gambar_sumber" class="form-label">Gambar Sumber Artikel:</label>
                        <?php if (!empty($gambar_sumber) && file_exists($gambar_sumber)): ?>
                            <div class="image-preview">
                                <img src="<?= htmlspecialchars($gambar_sumber) ?>" alt="Gambar Sumber Artikel">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="gambar_sumber" id="gambar_sumber" accept="image/*" class="form-input">
                    </div>
                </div>

                <!-- Tombol Submit -->
                <div class="mt-8 flex justify-end">
                    <button type="submit" class="submit-btn">Perbarui Artikel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logout Modal -->
    <div id="logoutModal" class="modal fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm"></div>
        <div class="bg-white rounded-lg p-6 shadow-xl max-w-sm w-full mx-4 relative z-10">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
                Yakin ingin logout?
            </h3>
            <div class="flex justify-end gap-3">
                <button
                    id="cancelBtn"
                    class="px-4 py-2 text-sm font-medium text-black bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300 transition-colors"
                >
                    Tidak
                </button>
                <button
                    id="confirmBtn"
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors"
                >
                    Iya
                </button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white mt-4 shadow-lg ml-0 md:ml-64 transition-all duration-300">
        <div class="w-full p-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <a href="dashboardAdmin.php" class="flex items-center">
                    <img src="../gambar/Banner2.png" class="h-8" alt="RefreshOil Logo" />
                </a>
                <span class="text-sm items-end text-gray-500">
                    © <?php echo date("Y"); ?> <a href="dashboardAdmin.php" class="hover:text-yellow-500">RefreshOil™</a>. All Rights Reserved.
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

        overlay.addEventListener("click", () => {
            sidebar.classList.add("-translate-x-full");
            overlay.classList.add("hidden");
        });

        // Logout Modal
        const logoutBtnMobile = document.getElementById("logoutBtnMobile");
        const logoutBtn = document.getElementById("logoutBtn");
        const logoutModal = document.getElementById("logoutModal");
        const cancelBtn = document.getElementById("cancelBtn");
        const confirmBtn = document.getElementById("confirmBtn");

        if (logoutBtnMobile) {
            logoutBtnMobile.addEventListener("click", () => {
                logoutModal.classList.add("active");
            });
        }

        if (logoutBtn) {
            logoutBtn.addEventListener("click", () => {
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