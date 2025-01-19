<?php
// isiArtikel.php

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
                
                // Memperbaiki pola penggantian string
                $konten_bersih = str_replace("\r\n\\", "\r\n\r\n", $row['konten']);
                
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
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data: https://*; script-src 'self' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap;">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $artikel ? htmlspecialchars($artikel['judul']) . " - RefreshOil" : "Artikel Tidak Ditemukan - RefreshOil" ?></title>
    <link href="../gambar/logo.png" rel="shortcut icon">
    
    <!-- External Resources -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js" defer></script>
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
    <!-- Main Content (Isi Artikel) -->
    <div class="md:ml-64 p-6 mt-16">
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
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="max-w-7xl mx-auto py-8 px-4 text-center">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <a href="/" class="flex items-center">
                    <img src="../gambar/Banner2.png" class="h-8" alt="RefreshOil Logo" />
                </a>
                <span class="text-sm items-end text-gray-500">
                    © 2024 <a href="/" class="hover:text-yellow-500">RefreshOil™</a>. All Rights Reserved.
                </span>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Mobile Menu Toggle
            const mobileMenuBtn = document.getElementById("mobileMenuBtn");
            const sidebar = document.getElementById("sidebar");
            const overlay = document.getElementById("overlay");

            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener("click", () => {
                    sidebar.classList.toggle("transform");
                    sidebar.classList.toggle("-translate-x-full");
                    overlay.classList.toggle("hidden");
                });
            }

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

            if (cancelBtn) {
                cancelBtn.addEventListener("click", () => {
                    logoutModal.classList.remove("active");
                });
            }

            if (confirmBtn) {
                confirmBtn.addEventListener("click", () => {
                    window.location.href = "logout.php";
                });
            }

            // Close modal when clicking outside the modal content
            if (logoutModal) {
                logoutModal.addEventListener("click", (e) => {
                    if (e.target === logoutModal) {
                        logoutModal.classList.remove("active");
                    }
                });
            }

            // Close modal on Escape key
            document.addEventListener("keydown", (e) => {
                if (e.key === "Escape" && logoutModal && logoutModal.classList.contains("active")) {
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
        });
    </script>
</body>
</html>