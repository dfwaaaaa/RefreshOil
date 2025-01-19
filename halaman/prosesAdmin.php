<?php
// prosesAdmin.php

// Memulai sesi PHP untuk mengelola data pengguna secara persisten
session_start();

// Memeriksa apakah pengguna sudah login
if (!isset($_SESSION['id_admin'])) {
    header("Location: loginAdmin.php");
    exit;
}

// Deteksi jika halaman dimuat dalam iframe
$iframe = isset($_GET['iframe']) && $_GET['iframe'] == '1';

// Inisialisasi variabel untuk menghindari peringatan undefined variable
$error_message = null;
$success_message = null; // Inisialisasi $success_message
$penjemputan = null;
$pemesan = null;

// Fungsi untuk memformat tanggal ke format Indonesia
function format_tanggal_indonesia($tanggal) {
    $bulan = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    $timestamp = strtotime($tanggal);
    if (!$timestamp) {
        return 'Tanggal Tidak Valid';
    }
    $tgl = date('j', $timestamp);
    $bln = $bulan[(int)date('n', $timestamp)];
    $thn = date('Y', $timestamp);
    return "$tgl $bln $thn";
}

// Konfigurasi koneksi ke database
$host = "localhost";          // Alamat host database
$username = "root";           // Username untuk mengakses database
$password = "";               // Password untuk mengakses database
$dbname = "refresh_oil";      // Nama database yang akan diakses

try {
    // Menggunakan PDO (PHP Data Objects) untuk koneksi database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Mengatur atribut PDO untuk melemparkan exception saat terjadi error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Mengambil data admin berdasarkan session 'id_admin'
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE id_admin = :id_admin LIMIT 1");
    $stmt->bindParam(':id_admin', $_SESSION['id_admin'], PDO::PARAM_INT);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        // Jika admin tidak ditemukan, logout
        header("Location: logout.php");
        exit;
    }

    // Generate CSRF token jika belum ada
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Mendapatkan ID Transaksi dari parameter URL
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $transaksiId = $_GET['id'];
    } else {
        // Jika ID tidak diberikan, arahkan ke riwayat transaksi
        header("Location: riwayatAdmin.php?status=Semua");
        exit;
    }

    // Mengambil detail transaksi berdasarkan ID
    $transaksiStmt = $pdo->prepare("SELECT * FROM penjemputan WHERE id_penjemputan = :id LIMIT 1");
    $transaksiStmt->bindParam(':id', $transaksiId, PDO::PARAM_STR);
    $transaksiStmt->execute();
    $penjemputan = $transaksiStmt->fetch(PDO::FETCH_ASSOC);

    if (!$penjemputan) {
        // Jika transaksi tidak ditemukan
        $error_message = "Transaksi dengan ID tersebut tidak ditemukan.";
    } else {
        // Mengambil data pemesan dari tabel 'users' berdasarkan 'id_user'
        $userId = $penjemputan['id_user']; 

        // Mengambil data user
        $userStmt = $pdo->prepare("SELECT nama, email, category, phone, profil_img FROM users WHERE id = :id LIMIT 1");
        $userStmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $userStmt->execute();
        $pemesan = $userStmt->fetch(PDO::FETCH_ASSOC);
    }

    // Penanganan Formulir (POST Request)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Cek Token CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_message = "Token CSRF tidak valid.";
        } else {
            if (isset($_POST['action'])) {
                $action = $_POST['action'];
                if ($action === 'Selesai') {
                    // Update status_penjemputan menjadi 'selesai'
                    $updateStmt = $pdo->prepare("UPDATE penjemputan SET status_penjemputan = 'selesai' WHERE id_penjemputan = :id");
                    $updateStmt->bindParam(':id', $transaksiId, PDO::PARAM_STR);
                    if ($updateStmt->execute()) {
                        // Jika berhasil, arahkan ke halaman selesaiAdmin.php
                        header("Location: selesaiAdmin.php?id=$transaksiId");
                        exit;
                    } else {
                        $error_message = "Gagal menyelesaikan pesanan. Silakan coba lagi.";
                    }
                } elseif ($action === 'Dibatalkan') {
                    // Update status_penjemputan menjadi 'dibatalkan'
                    $updateStmt = $pdo->prepare("UPDATE penjemputan SET status_penjemputan = 'dibatalkan' WHERE id_penjemputan = :id");
                    $updateStmt->bindParam(':id', $transaksiId, PDO::PARAM_STR);
                    if ($updateStmt->execute()) {
                        // Jika berhasil, arahkan ke halaman batalAdmin.php
                        header("Location: batalAdmin.php?id=$transaksiId");
                        exit;
                    } else {
                        $error_message = "Gagal membatalkan pesanan. Silakan coba lagi.";
                    }
                } else {
                    $error_message = "Aksi tidak dikenali.";
                }
            }
        }
    }

} catch (PDOException $e) {
    // Log kesalahan dan tampilkan pesan umum
    error_log($e->getMessage());
    $error_message = "Terjadi kesalahan koneksi. Silakan coba lagi nanti.";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Proses Transaksi - RefreshOil</title>
    <link href="../gambar/logo.png" rel="shortcut icon">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js" crossorigin="anonymous"></script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Custom Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        fadeIn: "fadeIn 1s ease-in-out",
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                    },
                },
            },
        }
    </script>
    <style>
        .modal {
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s ease;
        }
        .modal.active {
            opacity: 1;
            pointer-events: auto;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen font-[Poppins]">
    <!-- Sidebar -->
    <div id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-white shadow-md transform -translate-x-full md:translate-x-0 transition-transform duration-300 z-50">
        <div class="p-5 flex flex-col justify-between h-full">
            <div>
                <div class="text-center mb-5">
                    <a href="dashboardAdmin.php">
                        <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="mt-5 w-full h-auto">
                    </a>
                </div>
                <nav class="flex flex-col px-4 space-y-2">
                    <a href="dashboardAdmin.php" class="flex items-center px-4 py-3 bg-yellow-400 text-gray-700 rounded-lg mb-2 transition hover:bg-yellow-500">
                        <i class="fas fa-home mr-3"></i>Dashboard
                    </a>
                    <a href="riwayatAdmin.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100 transition">
                        <i class="fas fa-calendar-alt mr-3"></i>Riwayat
                    </a>
                    <a href="edukasiAdmin.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100 transition">
                        <i class="fas fa-book mr-3"></i>Edukasi
                    </a>
                    <a href="profilAdmin.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100 transition">
                        <i class="fas fa-user mr-3"></i>Profil
                    </a>
                </nav>
            </div>
            <div class="flex flex-col px-4">
                <a href="#" id="logoutBtn" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-yellow-100 transition">
                    <i class="fas fa-sign-out-alt mr-3"></i>Keluar
                </a>
            </div>
        </div>
    </div>

    <!-- Overlay -->
    <div id="overlay" class="fixed inset-0 bg-black opacity-50 hidden z-40"></div>

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
                    class="px-4 py-2 text-sm font-medium text-black bg-yellow-100 rounded-lg hover:bg-yellow-400 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors"
                >
                    Iya
                </button>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="flex flex-col min-h-screen ml-0 md:ml-64 transition-all duration-300">
        <!-- Toggle Sidebar Button for Mobile -->
        <button id="toggleSidebar" class="p-4 fixed top-4 left-4 z-50 bg-yellow-500 text-white rounded-lg shadow-lg md:hidden" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Main Content -->
        <div class="flex-grow p-6">
            <div class="max-w-7xl mx-auto p-4">
                
                <div class="flex items-center space-x-4 mb-6">
                    <!-- Tombol Kembali Bulat dengan Ikon Panah Kiri -->
                    <a href="dashboardAdmin.php" aria-label="Kembali ke Dashboard Admin" class="flex items-center justify-center w-8 h-8 bg-blue-500 text-white rounded-full hover:bg-blue-600 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-3xl font-bold text-gray-800 ml-4 animate-fadeIn">ID Transaksi: <?php echo htmlspecialchars($penjemputan['id_penjemputan'] ?? 'N/A'); ?></h1>
                </div>

                <!-- Pesan Kesalahan -->
                <?php if ($error_message): ?>
                    <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Pesan Sukses -->
                <?php if ($success_message): ?>
                    <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-6">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Jika transaksi ditemukan -->
                <?php if ($penjemputan): ?>
                    <!-- Transaction Details -->
                    <div class="bg-white p-8 rounded-lg shadow-lg mb-8 animate-fadeIn">
                        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Detail Pesanan</h2>
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Left Section: Order Details -->
                            <div class="lg:col-span-2 space-y-6">
                                <div class="bg-gray-100 p-6 rounded-lg">
                                    <h3 class="text-lg font-medium text-gray-700 mb-4">Informasi Transaksi</h3>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-sm text-gray-600">Tanggal Jemput</p>
                                            <p class="text-gray-800 font-medium"><?php echo format_tanggal_indonesia($penjemputan['tanggal_jemput']); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">Jumlah Minyak</p>
                                            <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($penjemputan['jumlah_liter'] ?? '0'); ?> Liter</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">Nominal</p>
                                            <p class="text-gray-800 font-medium">Rp <?php echo number_format($penjemputan['total_biaya'] ?? 0, 0, ',', '.'); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">Status</p>
                                            <p class="text-gray-800 font-medium capitalize"><?php echo htmlspecialchars($penjemputan['status_penjemputan'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Section: Pemesan -->
                            <div class="bg-gray-100 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-700 mb-4">Pemesan</h3>
                                <div class="flex items-center space-x-4 mt-6">
                                    <?php
                                        // Tentukan sumber gambar profil dengan fallback
                                        $profileImg = (!empty($pemesan['profil_img'])) ? htmlspecialchars($pemesan['profil_img']) : 'https://placehold.co/100x100';
                                    ?>
                                    <img src="<?php echo $profileImg; ?>" alt="User profile picture" class="rounded-full w-20 h-20 object-cover">
                                    <div>
                                        <p class="text-gray-800 font-medium text-lg"><?php echo htmlspecialchars($pemesan['nama'] ?? 'N/A'); ?></p>
                                        <p class="text-gray-600"><?php echo htmlspecialchars($pemesan['phone'] ?? 'N/A'); ?></p>
                                        <p class="text-gray-600">Kategori: <span class="text-gray-800 font-medium"><?php echo htmlspecialchars($pemesan['category'] ?? 'N/A'); ?></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Content -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Foto Limbah -->
                        <div class="bg-white p-6 rounded-lg shadow-md animate-fadeIn">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Foto Limbah</h2>
                            <div class="flex justify-center mt-6">
                                <img src="<?php echo htmlspecialchars($penjemputan['foto_limbah'] ?? '../gambar/minyak4.png'); ?>" alt="Order Image" class="object-cover h-64 rounded-lg">
                            </div>
                        </div>

                        <!-- Detail Lokasi dan Aksi -->
                        <div class="bg-white p-6 rounded-lg shadow-md animate-fadeIn">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Detail Lokasi</h2>
                            <div class="bg-gray-100 p-6 rounded-lg shadow-inner">
                                <form method="POST" action="">
                                    <!-- CSRF Token -->
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <!-- Hidden Input untuk Aksi -->
                                    <input type="hidden" name="action" id="actionInput" value="">

                                    <!-- Improved Detail Lokasi Card -->
                                    <div class="space-y-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-address-book text-yellow-500 mr-3"></i>
                                            <div>
                                                <p class="text-gray-700"><span class="font-medium">Latitude:</span></p>
                                                <p class="text-gray-800"><?php echo htmlspecialchars($penjemputan['alamat_jemput'] ?? 'N/A'); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-map-signs text-yellow-500 mr-3"></i>
                                            <div>
                                                <p class="text-gray-700"><span class="font-medium">Longitude:</span></p>
                                                <p class="text-gray-800"><?php echo htmlspecialchars($penjemputan['detail_lokasi'] ?? 'N/A'); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-bullseye text-yellow-500 mr-3"></i>
                                            <div>
                                                <p class="text-gray-700"><span class="font-medium">Patokan:</span></p>
                                                <p class="text-gray-800"><?php echo htmlspecialchars($penjemputan['patokan'] ?? 'N/A'); ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-between mt-6">
                                        <!-- Tombol Batalkan -->
                                        <button type="button" id="btnBatal" class="flex items-center bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition">
                                            Batalkan
                                        </button>
                                        <!-- Tombol Selesaikan -->
                                        <button type="button" id="btnSelesai" class="flex items-center bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition">
                                            Selesaikan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Konfirmasi Selesai -->
                    <div id="modalSelesai" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50">
                        <div class="bg-white rounded-lg p-6 w-96 shadow-lg">
                            <h2 class="text-xl font-semibold mb-4">Konfirmasi Selesai</h2>
                            <p>Apakah Anda yakin ingin menyelesaikan pesanan ini?</p>
                            <div class="flex justify-end mt-6 space-x-3">
                                <button id="yaSelesai" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition">
                                    Ya
                                </button>
                                <button id="tidakSelesai" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition">
                                    Tidak
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Konfirmasi Batal -->
                    <div id="modalBatal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50">
                        <div class="bg-white rounded-lg p-6 w-96 shadow-lg">
                            <h2 class="text-xl font-semibold mb-4">Konfirmasi Pembatalan</h2>
                            <p>Apakah Anda yakin ingin membatalkan pesanan ini?</p>
                            <div class="flex justify-end mt-6 space-x-3">
                                <button id="yaBatal" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition">
                                    Ya
                                </button>
                                <button id="tidakBatal" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition">
                                    Tidak
                                </button>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Jika transaksi tidak ditemukan -->
                    <div class="bg-white p-8 rounded-lg shadow-lg mb-8 animate-fadeIn">
                        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Transaksi Tidak Ditemukan</h2>
                        <p class="text-gray-600">ID Transaksi yang Anda cari tidak ditemukan. <a href="dashboardAdmin.php" class="text-yellow-500 hover:underline">Kembali ke Dashboard</a>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-white shadow-lg w-full">
            <div class="w-full p-8">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <a href="dashboardadmin.php" class="flex items-center">
                        <img src="../gambar/Banner2.png" class="h-8" alt="RefreshOil Logo" />
                    </a>
                    <span class="text-sm text-gray-500">
                        © <?php echo date("Y"); ?> <a href="dashboardadmin.php" class="hover:text-yellow-500">RefreshOil™</a>. All Rights Reserved.
                    </span>
                </div>
            </div>
        </footer>
    </div>

    <!-- Script -->
    <script>
        // Toggle Sidebar (Mobile)
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
        });

        // Show logout modal
        document.getElementById('logoutBtn').addEventListener('click', function(event) {
            event.preventDefault();
            document.getElementById('logoutModal').classList.remove('hidden');
            document.getElementById('overlay').classList.remove('hidden');
        });

        // Hide logout modal
        document.getElementById('cancelBtn').addEventListener('click', function() {
            document.getElementById('logoutModal').classList.add('hidden');
            document.getElementById('overlay').classList.add('hidden');
        });

        // Confirm logout
        document.getElementById('confirmBtn').addEventListener('click', function() {
            window.location.href = 'logout.php'; // Ganti dengan URL logout yang sesuai
        });

        // Tambahan Keyboard Escape (opsional)
        const logoutModal = document.getElementById('logoutModal');
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !logoutModal.classList.contains('hidden')) {
                logoutModal.classList.add('hidden');
                document.getElementById('overlay').classList.add('hidden');
            }
        });

        // Event listeners untuk Selesai
        const btnSelesai = document.getElementById('btnSelesai');
        const modalSelesai = document.getElementById('modalSelesai');
        const yaSelesai = document.getElementById('yaSelesai');
        const tidakSelesai = document.getElementById('tidakSelesai');

        btnSelesai.addEventListener('click', () => {
            modalSelesai.classList.remove('hidden');
        });

        yaSelesai.addEventListener('click', () => {
            document.getElementById('actionInput').value = 'Selesai';
            document.querySelector('form').submit();
        });

        tidakSelesai.addEventListener('click', () => {
            modalSelesai.classList.add('hidden');
        });

        // Event listeners untuk Batal
        const btnBatal = document.getElementById('btnBatal');
        const modalBatal = document.getElementById('modalBatal');
        const yaBatal = document.getElementById('yaBatal');
        const tidakBatal = document.getElementById('tidakBatal');

        btnBatal.addEventListener('click', () => {
            modalBatal.classList.remove('hidden');
        });

        yaBatal.addEventListener('click', () => {
            document.getElementById('actionInput').value = 'Dibatalkan';
            document.querySelector('form').submit();
        });

        tidakBatal.addEventListener('click', () => {
            modalBatal.classList.add('hidden');
        });

        // Optional: Close modal when clicking outside the modal content
        window.addEventListener('click', (event) => {
            if (event.target === modalSelesai) {
                modalSelesai.classList.add('hidden');
            }
            if (event.target === modalBatal) {
                modalBatal.classList.add('hidden');
            }
        });

        // Optional: Close modal with Esc key
        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                modalSelesai.classList.add('hidden');
                modalBatal.classList.add('hidden');
            }
        });
    </script>
</body>
</html>