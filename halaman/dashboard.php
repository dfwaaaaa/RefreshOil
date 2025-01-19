<?php
// Start the session
session_start();

// Set timezone ke WIB
date_default_timezone_set('Asia/Jakarta');

// Database connection parameters
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "refresh_oil";

try {
    // Membuat koneksi database
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass); 
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Jika koneksi gagal, tampilkan pesan error dan hentikan eksekusi
    die("Database connection failed: " . $e->getMessage());
}

// Cek apakah permintaan adalah AJAX untuk klaim poin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_reward') {
    // Set header untuk JSON response
    header('Content-Type: application/json');

    // Cek apakah pengguna sudah login
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Pengguna tidak terautentikasi.']);
        exit();
    }

    try {
        // Fetch user data
        $stmt = $conn->prepare("SELECT poin, last_claim FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'Pengguna tidak ditemukan.']);
            exit();
        }

        $current_datetime = new DateTime();
        $last_claim = $user['last_claim'] ? new DateTime($user['last_claim']) : null;

        // Cek apakah sudah klaim pada hari ini
        if ($last_claim) {
            $last_claim_date = $last_claim->format('Y-m-d');
            $current_date = $current_datetime->format('Y-m-d');

            if ($last_claim_date === $current_date) {
                echo json_encode(['status' => 'error', 'message' => 'Kamu sudah klaim poin untuk hari ini.']);
                exit();
            }
        }

        // Tambahkan 1000 poin
        $new_poin = $user['poin'] + 1000;

        // Update poin dan last_claim
        $updateStmt = $conn->prepare("UPDATE users SET poin = ?, last_claim = ? WHERE id = ?");
        $updateStmt->execute([$new_poin, $current_datetime->format('Y-m-d H:i:s'), $_SESSION['user_id']]);

        echo json_encode(['status' => 'success', 'message' => '1000 poin berhasil diklaim!', 'new_poin' => $new_poin]);

    } catch(PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan pada sistem. Silakan coba beberapa saat lagi.']);
        exit();
    }
    // Setelah menangani klaim, hentikan eksekusi skrip
    exit();
}

// Jika bukan permintaan klaim poin, lanjutkan untuk menampilkan dashboard

// Cek apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user = array();
$history = array();
$news = array(); // Array untuk berita terbaru
$error_message = "";

// Array nama hari dalam Bahasa Indonesia
$nama_hari = [
    'Sun' => 'Min',
    'Mon' => 'Sen',
    'Tue' => 'Sel',
    'Wed' => 'Rab',
    'Thu' => 'Kam',
    'Fri' => 'Jum',
    'Sat' => 'Sab'
];

// Array nama bulan dalam Bahasa Indonesia
$nama_bulan = [
    'January' => 'Januari',
    'February' => 'Februari',
    'March' => 'Maret',
    'April' => 'April',
    'May' => 'Mei',
    'June' => 'Juni',
    'July' => 'Juli',
    'August' => 'Agustus',
    'September' => 'September',
    'October' => 'Oktober',
    'November' => 'November',
    'December' => 'Desember'
];

// Fungsi helper untuk memformat tanggal
function formatTanggalIndonesia($tanggal_jemput, $nama_hari, $nama_bulan) {
    $day_english = date('D', strtotime($tanggal_jemput));
    $tanggal = date('d', strtotime($tanggal_jemput));
    $bulan_english = date('F', strtotime($tanggal_jemput));
    $tahun = date('Y', strtotime($tanggal_jemput));

    // Memetakan hari dan bulan ke Bahasa Indonesia
    $day_indonesia = isset($nama_hari[$day_english]) ? $nama_hari[$day_english] : $day_english;
    $bulan_indonesia = isset($nama_bulan[$bulan_english]) ? $nama_bulan[$bulan_english] : $bulan_english;

    // Menyusun format akhir
    return "$day_indonesia, $tanggal $bulan_indonesia $tahun";
}

try {
    // Fetch user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        /**
         * 1. Jika di kolom `nama` di database sudah ada data, maka gunakan itu.
         * 2. Jika masih kosong, maka ambil bagian depan dari email.
         */
        if (!empty($user['nama'])) {
            // Kolom nama di DB sudah diisi (pengguna sudah pernah mengedit nama)
            // => Biarkan apa adanya
        } else {
            // Kolom nama di DB masih kosong (pengguna belum edit), maka ambil dari email
            $email = $user['email'];
            $parts = explode("@", $email);
            $fallbackName = $parts[0]; // Bagian sebelum @
            // Hapus karakter . atau - dan ganti dengan spasi (opsional)
            $fallbackName = str_replace([".", "-"], " ", $fallbackName);
            // Kapitalisasi
            $fallbackName = ucwords($fallbackName);

            // Pakai nama fallback ini untuk ditampilkan di dashboard
            $user['nama'] = $fallbackName;
        }

        // Fetch latest history data dari penjemputan table
        $historyStmt = $conn->prepare("SELECT * FROM penjemputan WHERE id_user = ? ORDER BY id_penjemputan DESC LIMIT 10");
        $historyStmt->execute([$_SESSION['user_id']]);
        $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch latest news articles with tipe 'berita'
        $newsStmt = $conn->prepare("SELECT * FROM artikel WHERE tipe = 'berita' ORDER BY tgl_publish DESC LIMIT 6");
        $newsStmt->execute();
        $news = $newsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch statistik pekan ini
        $statistik_pekan_ini = [
            'Sen' => 0,
            'Sel' => 0,
            'Rab' => 0,
            'Kam' => 0,
            'Jum' => 0,
            'Sab' => 0,
            'Min' => 0
        ];

        // Query untuk mengambil jumlah_liter per tanggal_jemput dalam 7 hari terakhir
        $statistikStmt = $conn->prepare("SELECT jumlah_liter, tanggal_jemput FROM penjemputan WHERE id_user = ? AND tanggal_jemput >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");
        $statistikStmt->execute([$_SESSION['user_id']]);
        $penjemputan_pekan_ini = $statistikStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($penjemputan_pekan_ini as $item) {
            $day_english = date('D', strtotime($item['tanggal_jemput']));
            if (isset($nama_hari[$day_english])) {
                $day_indonesia = $nama_hari[$day_english];
                if (isset($statistik_pekan_ini[$day_indonesia])) {
                    $statistik_pekan_ini[$day_indonesia] += $item['jumlah_liter'];
                }
            }
        }

        // Prepare data for Chart.js
        $statistik_pekan_ini_labels = array_keys($statistik_pekan_ini);
        $statistik_pekan_ini_data = array_values($statistik_pekan_ini);

        $statistik_pekan_ini_labels_json = json_encode($statistik_pekan_ini_labels);
        $statistik_pekan_ini_data_json = json_encode($statistik_pekan_ini_data);

        // Fetch ttl_liter and compute minyak_progress_percent
        $target_liter = 1000; // Define target liter as per requirement
        $ttl_liter = isset($user['ttl_liter']) && is_numeric($user['ttl_liter']) ? $user['ttl_liter'] : 0;
        $minyak_progress_percent = $ttl_liter > 0 ? ($ttl_liter / $target_liter) * 100 : 0;
        $minyak_progress_percent = min($minyak_progress_percent, 100); // Clamp ke 100%
        $minyak_progress_percent = round($minyak_progress_percent); // Membulatkan ke integer

    } else {
        // User not found
        $error_message = "Pengguna tidak ditemukan.";
    }

} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Terjadi kesalahan pada sistem. Silakan coba beberapa saat lagi.";
}

// Menghitung progressPercent berdasarkan poin pengguna
// Misalkan target poin adalah 100.000
$target_poin = 100000;
$poin = isset($user['poin']) && is_numeric($user['poin']) ? $user['poin'] : 0;
$progress_percent = $poin > 0 ? ($poin / $target_poin) * 100 : 0;
$progress_percent = min($progress_percent, 100); // Clamp ke 100%
$progress_percent = round($progress_percent); // Membulatkan ke integer
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - RefreshOil</title>
    <link href="../gambar/logo.png" rel="shortcut icon">
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Swiper CSS -->
    <link
      rel="stylesheet"
      href="https://unpkg.com/swiper/swiper-bundle.min.css"
    />

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
        /* Scrollbar Kustom untuk Bagian Riwayat Transaksi dan History Section */
        .scrollbar-custom {
            scrollbar-width: thin; /* Untuk Firefox */
            scrollbar-color: #4b5563 #f3f4f6; /* Warna thumb dan track untuk Firefox */
        }

        /* Untuk Browser Berbasis WebKit (Chrome, Safari) */
        .scrollbar-custom::-webkit-scrollbar {
            width: 4px; /* Scrollbar lebih tipis */
        }

        .scrollbar-custom::-webkit-scrollbar-track {
            background: #f3f4f6;
            border-radius: 9999px; /* Rounded full pada track */
            box-shadow: inset 0 0 5px rgba(0, 0, 0, 0.1); /* Menambahkan bayangan */
        }

        .scrollbar-custom::-webkit-scrollbar-thumb {
            background-color: #4b5563;
            border-radius: 9999px; /* Rounded full pada thumb */
            border: 1px solid #f3f4f6; /* Menambahkan ruang di sekitar thumb */
        }

        /* Hover Effect pada Scrollbar Thumb */
        .scrollbar-custom::-webkit-scrollbar-thumb:hover {
            background-color: #374151;
        }

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

        /* CSS yang sudah ada tetap dipertahankan */
        .checkpoint {
            position: absolute;
            top: 50%;
            transform: translate(-50%, -50%);
        }
        .checkpoint i {
            width: 12px;
            height: 12px;
            color: #D1D5DB; /* Warna abu-abu default */
            transition: color 0.3s ease;
        }
        .checkpoint.active i {
            color: #10B981; /* Warna hijau saat aktif */
        }

        /* CSS untuk Slider */
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

        /* Dropdown Styles */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 160px;
            box-shadow: 0px 8px 16px rgba(0,0,0,0.2);
            border-radius: 8px;
            z-index: 1000;
        }
        .dropdown-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }
        .dropdown.show .dropdown-content {
            display: block;
        }

        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 5px;
            font-size: 10px;
            display: none; /* Set to block jika ada notifikasi */
        }

        /* Swiper Custom Styles */
        .swiper {
            width: 100%;
            padding-bottom: 40px; /* Ruang untuk pagination */
        }
        .swiper-slide {
            /* Sesuaikan dengan ukuran kartu berita */
            width: 300px;
        }
        .swiper-button-next,
        .swiper-button-prev {
            color: #4B5563; /* Warna tombol navigasi */
        }
        .swiper-pagination-bullet {
            background: #4B5563;
            opacity: 0.7;
        }
        .swiper-pagination-bullet-active {
            background: #10B981;
            opacity: 1;
        }
    </style>
</head>

<body class="bg-gray-50 font-[Poppins]">
    <!-- Mobile Header dengan Hamburger -->
    <header class="md:hidden flex items-center justify-between p-4 bg-white shadow-md fixed top-0 left-0 right-0 z-50 flex-nowrap">
        <div class="flex items-center flex-shrink-0">
            <button id="mobileMenuBtn" class="text-gray-700 focus:outline-none">
                <i class="fas fa-bars fa-2x"></i>
            </button>
            <!-- Logo dengan ukuran yang lebih besar namun tetap proporsional dan jarak ke ikon profil -->
            <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="w-24 h-auto ml-3 flex-shrink-0">
        </div>
        <div class="relative flex items-center space-x-4 flex-shrink-0">
            <!-- Notification Icon -->
            <button class="relative text-gray-700 focus:outline-none mr-4">
                <i class="fas fa-bell fa-lg"></i>
                <span class="notification-badge" id="mobileNotificationBadge">3</span>
            </button>
            <!-- Dropdown Profile -->
            <div class="dropdown">
                <?php
                    // Tentukan sumber gambar profil dengan fallback
                    $profileImgMobile = (!empty($user['profil_img'])) ? htmlspecialchars($user['profil_img']) : '../gambar/profil.png';
                ?>
                <button id="mobileProfileBtn" class="focus:outline-none">
                    <img src="<?php echo $profileImgMobile; ?>" alt="User Avatar" class="w-8 h-8 rounded-full">
                </button>
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
                    <a href="dashboard.php">
                        <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="mt-5 w-full h-auto">
                    </a>
                </div>
                <nav class="flex flex-col px-4">
                    <a href="dashboard.php" class="flex items-center px-4 py-3 bg-yellow-400 text-gray-700 rounded-lg mb-2">
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
                    <a href="edukasi.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
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
    
    <!-- Main Content -->
    <div class="ml-0 md:ml-64 max-w-7xl mx-auto px-6 pt-16 md:pt-8">
        <!-- Header: Search Bar dan Account -->
        <div class="mb-4 flex items-center justify-between relative">
            <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>

            <!-- Account -->
            <div class="flex items-center space-x-4">
                <!-- Notification Icon -->
                <button class="relative text-gray-700 focus:outline-none mr-4">
                    <i class="fas fa-bell fa-lg"></i>
                    <span class="notification-badge" id="desktopNotificationBadge">5</span>
                </button>
                <!-- Dropdown Profile -->
                <div class="dropdown">
                    <?php
                        // Tentukan sumber gambar profil dengan fallback
                        $profileImgDesktop = (!empty($user['profil_img'])) ? htmlspecialchars($user['profil_img']) : '../gambar/profil.png';
                    ?>
                    <button id="desktopProfileBtn" class="focus:outline-none flex items-center">
                        <div class="flex flex-col text-right">
                            <!-- 
                                 Menampilkan nama:
                                 Jika ada di kolom nama DB, maka itu yang ditampilkan.
                                 Kalau kosong, menampilkan fallback dari email (sudah di-handle di atas)
                            -->
                            <span class="text-gray-800 font-semibold">
                                <?php echo isset($user['nama']) ? htmlspecialchars($user['nama']) : ''; ?>
                            </span>
                            <span class="text-gray-400 text-sm">
                                <?php echo isset($user['category']) ? htmlspecialchars($user['category']) : ''; ?>
                            </span>
                        </div>
                        <img src="<?php echo $profileImgDesktop; ?>" alt="User Avatar" class="w-10 h-10 rounded-full ml-3">
                    </button>
                    <div class="dropdown-content mt-2">
                        <a href="profil.php"><i class="fas fa-user mr-2"></i>Lihat Profil</a>
                        <a href="#" class="logoutLink"><i class="fas fa-sign-out-alt mr-2"></i>Keluar</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slider Section -->
        <div class="slider-container">
            <div class="slider-wrapper">
                <div class="slider-slide">
                    <img src="../gambar/poster1.png" alt="Poster 1" class="rounded-2xl w-full object-cover">
                </div>
                <div class="slider-slide">
                    <img src="../gambar/poster2.png" alt="Poster 2" class="rounded-2xl w-full object-cover">
                </div>
                <div class="slider-slide">
                    <img src="../gambar/poster3.png" alt="Poster 3" class="rounded-2xl w-full object-cover">
                </div>
            </div>
        </div>

        <!-- Info Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-10">
            <!-- Statistik Pekan Ini Card -->
            <div class="bg-white rounded-2xl p-6 shadow-lg flex flex-col justify-center items-center h-60">
                <p class="text-m text-gray-600 text-center mb-4">Statistik Pekan Ini (L)</p>
                <!-- Bar Chart -->
                <div class="w-full max-w-xs h-40">
                    <canvas id="minyakChart"></canvas>
                </div>
            </div>

            <!-- Points Card -->
            <div class="bg-white rounded-2xl p-6 shadow-lg flex flex-col justify-center items-center h-60 relative">
                <!-- Lingkaran Hadiah -->
                <a href="#" id="claimRewardBtn" class="absolute -top-3 -right-3 bg-red-500 text-white rounded-full w-10 h-10 flex items-center justify-center shadow-lg hover:bg-red-600 transition-colors" aria-label="Reward">
                    <i class="fas fa-gift"></i>
                </a>
                
                <h3 class="text-xl font-semibold text-center text-gray-800 mb-4">Total Poin</h3>
                <div class="flex justify-center items-center mb-4">
                    <!-- Menampilkan poin secara dinamis dengan validasi -->
                    <p class="text-4xl font-bold text-yellow-600 mr-4" id="currentPoin">
                        <?php 
                            // Validasi dan pengambilan poin
                            echo number_format($poin, 0, ',', '.'); 
                        ?>
                    </p>
                    <a href="penjemputan.php" class="bg-yellow-400 text-black font-semibold text-center rounded-full px-4 py-4 hover:bg-yellow-600 transition-colors duration-300 flex items-center justify-center w-6 h-6">
                        <span class="text-xl">+</span>
                    </a>
                </div>
                <a href="#" class="block w-full bg-yellow-400 text-gray-800 font-semibold text-center rounded-full px-4 py-2 hover:bg-yellow-300 transition-colors duration-300">
                    Tukar
                </a>
            </div>


            <!-- Minyak Terkumpul Card -->
            <div class="bg-white rounded-2xl p-6 shadow-lg flex flex-col justify-center items-center h-60 relative">
                <div class="mb-6">
                    <h3 class="text-xl font-semibold text-gray-800 text-center">Minyak Terkumpul</h3>
                </div>
                <div class="flex items-center justify-center mb-6 w-full relative">
                    <!-- Progress Bar -->
                    <div class="flex-grow h-2 bg-gray-200 rounded-full relative">
                        <div id="progressFill" class="h-full bg-yellow-400 rounded-full relative" style="width: 0%;">
                            <span class="absolute right-0 top-1/2 transform translate-x-1/2 -translate-y-1/2 bg-white text-xs font-semibold text-black px-2 py-0.5 rounded-full shadow">0%</span>
                        </div>
                    </div>
                    <!-- Checkpoints -->
                    <div class="absolute top-0 left-0 w-full h-2">
                        <!-- 5% Checkpoint -->
                        <div class="checkpoint" style="left: 5%;">
                            <i class="fas fa-check-circle w-2.5 h-2.5"></i>
                        </div>
                        <!-- 15% Checkpoint -->
                        <div class="checkpoint" style="left: 15%;">
                            <i class="fas fa-check-circle w-2.5 h-2.5"></i>
                        </div>
                        <!-- 50% Checkpoint -->
                        <div class="checkpoint" style="left: 50%;">
                            <i class="fas fa-gift w-2.5 h-2.5"></i>
                        </div>
                        <!-- 100% Checkpoint -->
                        <div class="checkpoint" style="left: 100%;">
                            <i class="fas fa-gift w-2.5 h-2.5"></i>
                        </div>
                    </div>
                </div>
                <a href="#" class="mt-4 block w-full bg-yellow-400 text-gray-800 font-semibold text-center rounded-full px-4 py-2 hover:bg-yellow-300 transition-colors duration-300">
                    Penjemputan
                </a>
            </div>
        </div>

        <!-- News Section -->
        <div class="mt-12 bg-white p-6 rounded-2xl shadow-lg">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Berita Terbaru</h3>
                <a href="semuaBerita.php" class="text-xs text-white bg-blue-600 hover:bg-blue-700 px-3 py-0.5 rounded-full transition duration-300">Lihat Semua</a>
            </div>
            <!-- Swiper Carousel -->
            <?php if (!empty($news)): ?>
                <div class="swiper mySwiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($news as $article): ?>
                            <div class="swiper-slide">
                                <div class="bg-gray-100 rounded-xl overflow-hidden transition-transform duration-300 hover:scale-95">
                                    <img src="<?php echo htmlspecialchars($article['gambar_artikel']); ?>" alt="<?php echo htmlspecialchars($article['judul']); ?>" class="w-full h-48 object-cover">
                                    <div class="p-4">
                                        <p class="text-sm text-black mb-2"><?php echo htmlspecialchars($article['judul']); ?></p>
                                        <p class="text-m font-semibold text-black"><?php echo htmlspecialchars($article['tipe']); ?></p>
                                        <a href="isiArtikel.php?id=<?php echo htmlspecialchars($article['id_artikel']); ?>" class="mt-2 inline-block text-blue-600 hover:text-blue-800">Baca Selengkapnya</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Add Pagination -->
                    <div class="swiper-pagination"></div>
                    <!-- Add Navigation -->
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-500">Tidak ada berita terbaru.</p>
            <?php endif; ?>
        </div>

        <!-- History Section -->
        <div class="mt-12 bg-white p-6 rounded-2xl shadow-lg">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Riwayat Terbaru</h3>
                <a href="riwayat.php" class="text-xs text-white bg-blue-600 hover:bg-blue-700 px-3 py-0.5 rounded-full transition duration-300">Lihat Semua</a>
            </div>
            <ul id="historyList" class="h-96 overflow-y-auto scrollbar-custom space-y-4">
                <!-- History Items -->
                <?php if (!empty($history)): ?>
                    <?php foreach ($history as $item): ?>
                        <?php
                            // Memformat tanggal menggunakan fungsi helper
                            $waktu = formatTanggalIndonesia($item['tanggal_jemput'], $nama_hari, $nama_bulan);
                            
                            // Status handling
                            $status = ucfirst(strtolower($item['status_penjemputan'])); // Pastikan status memiliki format yang konsisten
                            switch ($status) {
                                case 'Proses':
                                    $statusClass = 'bg-yellow-200 text-yellow-600';
                                    break;
                                case 'Selesai':
                                    $statusClass = 'bg-green-200 text-green-600';
                                    break;
                                case 'Dibatalkan':
                                    $statusClass = 'bg-red-200 text-red-600';
                                    break;
                                default:
                                    $statusClass = 'bg-gray-200 text-gray-600';
                            }
                        ?>
                        <li class="bg-gray-100 p-6 rounded-xl transition-transform duration-300 hover:scale-95">
                            <div class="flex items-center justify-between space-x-4">
                                <!-- ID Transaksi -->
                                <span class="text-gray-800 font-medium whitespace-nowrap">ID Transaksi: <?php echo htmlspecialchars($item['id_penjemputan']); ?></span>
                                <!-- Liter -->
                                <span class="bg-yellow-400 text-gray-800 px-4 py-2 rounded-full font-semibold text-sm whitespace-nowrap"><?php echo htmlspecialchars($item['jumlah_liter']); ?> Liter</span>
                                <!-- Waktu -->
                                <div class="flex-shrink-0">
                                    <span class="text-gray-800"><?php echo htmlspecialchars($waktu); ?></span>
                                </div>
                                <!-- Status -->
                                <span class="<?php echo $statusClass; ?> px-3 py-1 rounded-full text-sm font-medium whitespace-nowrap"><?php echo $status; ?></span>
                                <!-- Detail -->
                                <button onclick="openModal('<?php echo htmlspecialchars($item['id_penjemputan']); ?>')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-300 whitespace-nowrap">
                                    Detail
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="text-center text-gray-500">Tidak ada riwayat penjemputan.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Pop up detail transaksi -->
    <div id="transactionModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm"></div>
        <div class="fixed inset-0 flex items-center justify-center">
            <div class="bg-white rounded-lg p-6 shadow-xl w-[95%] max-w-7xl h-[90vh] flex flex-col overflow-hidden">
                <!-- Header tanpa judul -->
                <div class="flex items-center justify-end p-4 border-b">
                    <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <!-- Content area with iframe container -->
                <div class="flex-1 overflow-hidden relative" style="height: calc(90vh - 57px);">
                    <iframe id="transactionFrame" src="" class="w-full h-full border-none"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Claim Reward -->
    <div id="claimModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden">
        <div class="bg-white rounded-lg p-6 w-80 shadow-lg">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">Klaim Poin</h2>
                <button id="closeClaimModal" class="text-gray-600 hover:text-gray-800 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p class="text-center text-gray-700" id="claimMessage">1000 poin berhasil diklaim!</p>
            <div class="mt-6 flex justify-center">
                <button id="closeClaimModalBtn" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition-colors">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- Footer -->
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

    <!-- Swiper JS -->
    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>

    <script>
        // Sidebar Toggle for Mobile
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        });

        // Chart Configuration
        document.addEventListener('DOMContentLoaded', function() {
            const chartConfig = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 0,
                            minRotation: 0
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [2, 4]
                        }
                    }
                }
            };

            // Data dari PHP
            const chartLabels = <?php echo $statistik_pekan_ini_labels_json; ?>;
            const chartData = <?php echo $statistik_pekan_ini_data_json; ?>;

            const minyakChart = new Chart(
                document.getElementById('minyakChart').getContext('2d'),
                {
                    type: 'bar',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'Minyak Jelantah (L)',
                            data: chartData,
                            backgroundColor: '#f3ce49',
                            borderRadius: 5,
                            barThickness: 13,
                        }]
                    },
                    options: chartConfig
                }
            );

            // Initialize Swiper
            const swiper = new Swiper('.mySwiper', {
                slidesPerView: 1,
                spaceBetween: 16,
                loop: false,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                breakpoints: {
                    640: {
                        slidesPerView: 2,
                        spaceBetween: 16,
                    },
                    768: {
                        slidesPerView: 3,
                        spaceBetween: 24,
                    },
                },
            });
        });

        // Mendapatkan progress percent untuk minyak terkumpul dari PHP
        const minyakProgressPercent = <?php echo json_encode($minyak_progress_percent); ?>;

        // Update lebar progress fill untuk 'Minyak Terkumpul'
        const minyakProgressFill = document.getElementById('progressFill');
        minyakProgressFill.style.width = minyakProgressPercent + '%';

        // Update teks di dalam progress bar
        const minyakProgressText = minyakProgressFill.querySelector('span');
        minyakProgressText.textContent = minyakProgressPercent + ' %';

        // Mendapatkan semua checkpoint
        const checkpoints = document.querySelectorAll('.checkpoint');
        const checkpointPercents = [5, 15, 50, 100];

        // Iterasi melalui checkpoint dan ubah warna jika progres >= checkpoint
        checkpoints.forEach((checkpoint, index) => {
            const percent = checkpointPercents[index];
            if (minyakProgressPercent >= percent) {
                checkpoint.classList.add('active');
            }
        });

        // Logout Modal Functionality
        const logoutLinks = document.querySelectorAll('.logoutLink');
        const logoutModal = document.getElementById('logoutModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const confirmBtn = document.getElementById('confirmBtn');

        function toggleModal(show = true) {
            logoutModal.classList.toggle('active', show);
        }

        logoutLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                toggleModal(true);
            });
        });

        cancelBtn.addEventListener('click', () => toggleModal(false));

        confirmBtn.addEventListener('click', () => {
            // Logika logout
            window.location.href = 'logout.php'; // Pastikan untuk mengganti dengan URL logout yang benar
        });

        logoutModal.addEventListener('click', (e) => {
            if (e.target === logoutModal) {
                toggleModal(false);
            }
        });

        // Pop up detail transaksi
        const transactionModal = document.getElementById('transactionModal');
        const transactionFrame = document.getElementById('transactionFrame');

        function openModal(transactionId) {
            // Menambahkan parameter iframe=1 untuk mendeteksi bahwa ini dimuat dalam iframe
            transactionFrame.src = `prosesTransaksi.php?id=${transactionId}&iframe=1`;
            transactionModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Add event listener for iframe load
            transactionFrame.onload = function() {
                try {
                    // Try to hide the sidebar in the iframe menggunakan JavaScript
                    const iframeDoc = transactionFrame.contentWindow.document;
                    const sidebar = iframeDoc.getElementById('sidebar');
                    const mainContent = iframeDoc.querySelector('.ml-0.md\\:ml-64');
                    if (sidebar) sidebar.style.display = 'none';
                    if (mainContent) mainContent.style.marginLeft = '0';
                } catch (e) {
                    console.log('Could not modify iframe content');
                }
            };
        }

        function closeModal() {
            transactionModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            transactionFrame.src = '';
        }

        // Close modal when clicking outside
        transactionModal.addEventListener('click', (e) => {
            if (e.target === transactionModal) {
                closeModal();
            }
        });

        // Slider Functionality
        const sliderWrapper = document.querySelector('.slider-wrapper');
        const sliderSlides = document.querySelectorAll('.slider-slide');
        const totalSlides = sliderSlides.length;
        let currentIndex = 0;
        let direction = 1; // 1 untuk kanan, -1 untuk kiri
        const intervalTime = 2500; // Waktu antar slide dalam milidetik
        let sliderInterval;

        // Fungsi untuk memindahkan slider
        function moveSlider() {
            currentIndex += direction;

            // Jika mencapai akhir atau awal, ubah arah
            if (currentIndex === totalSlides - 1) {
                direction = -1; // Ubah arah ke kiri
            } else if (currentIndex === 0) {
                direction = 1; // Ubah arah ke kanan
            }

            // Hitung transform berdasarkan currentIndex
            const translateX = -currentIndex * 100;
            sliderWrapper.style.transform = `translateX(${translateX}%)`;
        }

        // Mulai slider otomatis
        function startSlider() {
            sliderInterval = setInterval(moveSlider, intervalTime);
        }

        // Berhenti slider otomatis
        function stopSlider() {
            clearInterval(sliderInterval);
        }

        // Inisialisasi slider
        startSlider();

        // Optional: Pause slider saat hover
        const sliderContainer = document.querySelector('.slider-container');
        sliderContainer.addEventListener('mouseenter', stopSlider);
        sliderContainer.addEventListener('mouseleave', startSlider);

        // Dropdown Functionality
        const desktopProfileBtn = document.getElementById('desktopProfileBtn');
        const mobileProfileBtn = document.getElementById('mobileProfileBtn');
        const dropdowns = document.querySelectorAll('.dropdown');

        function closeAllDropdowns() {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        }

        // Toggle dropdown for desktop
        if (desktopProfileBtn) {
            desktopProfileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdowns.forEach(dropdown => {
                    if (dropdown.contains(desktopProfileBtn)) {
                        dropdown.classList.toggle('show');
                    } else {
                        dropdown.classList.remove('show');
                    }
                });
            });
        }

        // Toggle dropdown for mobile
        if (mobileProfileBtn) {
            mobileProfileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdowns.forEach(dropdown => {
                    if (dropdown.contains(mobileProfileBtn)) {
                        dropdown.classList.toggle('show');
                    } else {
                        dropdown.classList.remove('show');
                    }
                });
            });
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', () => {
            closeAllDropdowns();
        });

        // Optional: Display notification badges if there are notifications
        // Example: Set display to 'block' if there are notifications
        const desktopNotificationBadge = document.getElementById('desktopNotificationBadge');
        const mobileNotificationBadge = document.getElementById('mobileNotificationBadge');
        const desktopNotifications = 5; // Ganti dengan jumlah notifikasi sebenarnya
        const mobileNotifications = 3; // Ganti dengan jumlah notifikasi sebenarnya

        if (desktopNotifications > 0) {
            desktopNotificationBadge.textContent = desktopNotifications;
            desktopNotificationBadge.style.display = 'block';
        }

        if (mobileNotifications > 0) {
            mobileNotificationBadge.textContent = mobileNotifications;
            mobileNotificationBadge.style.display = 'block';
        }

        // Modal Claim Reward Functionality
        const claimModal = document.getElementById('claimModal');
        const closeClaimModal = document.getElementById('closeClaimModal');
        const closeClaimModalBtn = document.getElementById('closeClaimModalBtn');

        // Fungsi untuk membuka modal
        function openClaimModalFunc(message) {
            document.getElementById('claimMessage').textContent = message;
            claimModal.classList.remove('hidden');
        }

        // Fungsi untuk menutup modal
        function closeClaimModalFunc() {
            claimModal.classList.add('hidden');
        }

        // Event listener untuk tombol close (X) di modal
        closeClaimModal.addEventListener('click', closeClaimModalFunc);

        // Event listener untuk tombol Tutup di modal
        closeClaimModalBtn.addEventListener('click', closeClaimModalFunc);

        // Event listener untuk menutup modal saat klik di luar konten modal
        window.addEventListener('click', (e) => {
            if (e.target == claimModal) {
                closeClaimModalFunc();
            }
        });

        // Event listener untuk tombol klaim poin
        const claimRewardBtn = document.getElementById('claimRewardBtn');
        const currentPoinElement = document.getElementById('currentPoin');

        if (claimRewardBtn) { // Pastikan elemen ada
            claimRewardBtn.addEventListener('click', (e) => {
                e.preventDefault(); // Mencegah aksi default link

                // Kirim permintaan AJAX ke dashboard.php dengan action=claim_reward
                const formData = new FormData();
                formData.append('action', 'claim_reward');

                fetch('dashboard.php', {
                    method: 'POST',
                    body: formData,
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Update tampilan poin
                        currentPoinElement.textContent = new Intl.NumberFormat('id-ID').format(data.new_poin);
                        // Tampilkan modal sukses
                        openClaimModalFunc(data.message);
                    } else {
                        // Tampilkan alert atau modal error
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat mengklaim poin. Silakan coba lagi.');
                });
            });
        }
    </script>
</body>
</html>