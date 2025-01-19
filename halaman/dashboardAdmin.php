<?php
// Memulai sesi PHP untuk mengelola data pengguna secara sementara di server.
// Mekanisme untuk menyimpan data pengguna sementara di server selama kunjungan mereka ke situs web
session_start();

// isset adalah memeriksa apakah sebuah variabel telah diatur dan tidak bernilai null.
// Jika session id_admin tidak diatur / tidak tersedia, maka bernilai true.
// Fungsi header digunakan untuk mengirimkan header HTTP mentah ke browser.
// Menginstruksikan browser untuk mengalihkan pengguna ke halaman loginAdmin.php.
if (!isset($_SESSION['id_admin'])) {
    header("Location: loginAdmin.php");
    exit;
}

// Konfigurasi koneksi ke database
$host = "localhost";   // Host database
$username = "root";    // Username
$password = "";        // Password
$dbname = "refresh_oil"; // Nama database

// Sebuah fungsi untuk menetapkan zona waktu default yang akan digunakan 
// oleh semua fungsi tanggal dan waktu di halaman ini
// Setel timezone WIB
date_default_timezone_set('Asia/Jakarta');

// Variabel hari indo adalah sebuah array asosiatif yang berfungsi
// untuk menerjemahkan nama hari dalam Bahasa Inggris ke Bahasa Indonesia.
$hariIndo = [
    'Sunday'    => 'Minggu',
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu'
];

// Variabel bulan indo adalah sebuah array asosiatif yang berfungsi
// untuk menerjemahkan nama bulan dalam Bahasa Inggris ke Bahasa Indonesia.
$bulanIndo = [
    'January'   => 'Januari',
    'February'  => 'Februari',
    'March'     => 'Maret',
    'April'     => 'April',
    'May'       => 'Mei',
    'June'      => 'Juni',
    'July'      => 'Juli',
    'August'    => 'Agustus',
    'September' => 'September',
    'October'   => 'Oktober',
    'November'  => 'November',
    'December'  => 'Desember'
];

// Fungsi date() yang digunakan untuk mengambil informasi tanggal dan waktu saat ini.
// format l menghasilkan nama hari lengkap dalam Bahasa Inggris (Monday, Tuesday)
$hariInggris  = date('l');
// Format d menghasilkan tanggal dalam sebulan dengan dua digit, dari 01 hingga 31.
$tanggal      = date('d');
// Format F menghasilkan nama bulan lengkap dalam Bahasa Inggris (January, February).
$bulanInggris = date('F');
// Format Y menghasilkan tahun dalam format empat digit (2025).
$tahun        = date('Y');

// hariDisplay menyimpan nama hari dalam Bahasa Indonesia. 
// hariIndo menggunakan nilai dari $hariInggris sebagai kunci untuk mengambil nilai yang sesuai dari array $hariIndo.
$hariDisplay  = $hariIndo[$hariInggris];
$bulanDisplay = $bulanIndo[$bulanInggris];
// Variabel ini menyimpan tanggal lengkap dalam format yang diinginkan.
// Kenapa pakai tanda "", karena ingin menggabungkan keempat variabel tersebut tanpa menggunakan (.)
$tanggalLengkap = "$hariDisplay, $tanggal $bulanDisplay $tahun";

try {
    // Variabel PDO itu menyimpan objek baru berupa dsn (Data Source Name) ke dalam objek PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ambil data admin dari session
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE id_admin = :id_admin LIMIT 1");
    // bindParam = Metode mengikat parameter ke dalam prepared statement sebelum eksekusi query ke database.
    $stmt->bindParam(':id_admin', $_SESSION['id_admin'], PDO::PARAM_INT);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        // Jika admin tidak ditemukan, logout
        header("Location: logout.php");
        exit();
    }

    // Mapping slot parameter URL -> nilai enum di database
    $slotMap = [
        'pagi' => 'Pagi (08.00-11.00)',
        'siang' => 'Siang (13.00-15.00)',
        'sore' => 'Sore (15.30-18.00)',
    ];

    // Cek apakah ada parameter GET slot
    $slotParam = isset($_GET['slot']) ? $_GET['slot'] : '';

    // Tentukan nilai slot yang akan digunakan di query
    $actualSlot = '';
    if (!empty($slotParam) && isset($slotMap[$slotParam])) {
        $actualSlot = $slotMap[$slotParam];
    }

    // Query: hanya ambil yang status_penjemputan = 'Proses'
    if ($actualSlot) {
        // Jika slot ada, filter juga berdasarkan slot
        $stmt = $pdo->prepare("SELECT * FROM penjemputan 
                              WHERE status_penjemputan = 'Proses' 
                              AND waktu_slot = :slot 
                              ORDER BY id_penjemputan DESC");
        $stmt->bindParam(':slot', $actualSlot, PDO::PARAM_STR);
    } else {
        // Jika slot tidak ada, tampilkan semua 'Proses'
        $stmt = $pdo->prepare("SELECT * FROM penjemputan 
                              WHERE status_penjemputan = 'Proses' 
                              ORDER BY id_penjemputan DESC");
    }

    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>".
         htmlspecialchars($e->getMessage())."</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - RefreshOil</title>
    <link href="../gambar/logo.png" rel="shortcut icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js" crossorigin="anonymous"></script>
    <style>  
        /* Scrollbar Kustom untuk Bagian Pesanan */
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
    </style>
</head>
<body class="bg-gray-50 font-[Poppins]">
    <!-- Mobile Header -->
    <header class="md:hidden flex items-center justify-between p-4 bg-white shadow-md fixed top-0 left-0 right-0 z-50">
        <div class="flex items-center">
            <button id="mobileMenuBtn" class="text-gray-700 focus:outline-none">
                <i class="fas fa-bars fa-2x"></i>
            </button>
            <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="w-24 h-auto ml-3">
        </div>
        <div class="relative flex items-center space-x-4">
            <button class="relative text-gray-700 focus:outline-none">
                <i class="fas fa-bell fa-lg"></i>
                <span class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full px-2">3</span>
            </button>
            <div class="dropdown">
                <button id="mobileProfileBtn" class="focus:outline-none">
                    <img src="<?php echo htmlspecialchars($admin['profile_image'] ?? '../gambar/profil.png'); ?>" alt="User Avatar" class="w-8 h-8 rounded-full">
                </button>
                <div class="dropdown-content mt-2 hidden bg-white shadow-md rounded-lg">
                    <a href="profiladmin.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-user mr-2"></i>Lihat Profil
                    </a>
                    <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 logoutLink">
                        <i class="fas fa-sign-out-alt mr-2"></i>Keluar
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="flex">
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
                        <a href="dashboardAdmin.php" class="flex items-center px-4 py-3 bg-yellow-400 text-gray-700 rounded-lg mb-2">
                            <i class="fas fa-home mr-3"></i>Dashboard
                        </a>
                        <a href="riwayatAdmin.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
                            <i class="fas fa-calendar-alt mr-3"></i>Riwayat
                        </a>
                        <a href="edukasiAdmin.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
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

        <!-- Logout Modal -->
        <div id="logoutModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
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
        <main class="flex-1 pt-8 px-6 pb-6 md:ml-64">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold tracking-wide">
                        Hi, <?php echo htmlspecialchars($admin['username']); ?> <span class="wave">👋</span>
                    </h1>
                    <p class="text-gray-400">Selamat Datang</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <div class="flex flex-col text-right">
                            <span class="text-gray-800 font-semibold"><?php echo htmlspecialchars($admin['username']); ?></span>
                            <span class="text-gray-400 text-sm">Admin</span>
                        </div>
                        <div>
                            <img src="<?php echo htmlspecialchars($admin['profile_image'] ?? '../gambar/profil.png'); ?>"
                                 alt="Admin Profile" class="w-10 h-10 rounded-full ml-3">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Pesanan (status 'Proses') -->
            <section class="bg-white shadow-md rounded-lg p-4">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h2 class="text-lg font-bold">Pesanan Terbaru</h2>
                        <p class="text-sm text-gray-500"><?php echo $tanggalLengkap; ?></p>
                    </div>
                    
                    <!-- Tombol Filter Slot -->
                    <?php 
                    // Supaya tombol tetap active jika parameter slot sesuai
                    // $slotParam adalah `pagi`, `siang`, atau `sore`
                    ?>
                    <div class="flex space-x-2">
                        <!-- Tombol Filter Pagi -->
                        <button 
                            class="px-4 py-2 rounded-full text-sm 
                            <?php echo ($slotParam === 'pagi') ? 'bg-blue-500 text-white' : 'text-gray-500 hover:bg-blue-100'; ?>"
                            onclick="window.location.href='dashboardAdmin.php?slot=pagi'">
                            Pagi
                        </button>
                        <!-- Tombol Filter Siang -->
                        <button 
                            class="px-4 py-2 rounded-full text-sm 
                            <?php echo ($slotParam === 'siang') ? 'bg-green-500 text-white' : 'text-gray-500 hover:bg-green-100'; ?>"
                            onclick="window.location.href='dashboardAdmin.php?slot=siang'">
                            Siang
                        </button>
                        <!-- Tombol Filter Sore -->
                        <button 
                            class="px-4 py-2 rounded-full text-sm 
                            <?php echo ($slotParam === 'sore') ? 'bg-red-500 text-white' : 'text-gray-500 hover:bg-red-100'; ?>"
                            onclick="window.location.href='dashboardAdmin.php?slot=sore'">
                            Sore
                        </button>
                        <!-- Tombol Tampilkan Semua -->
                        <button 
                            class="px-4 py-2 rounded-full text-sm 
                            <?php echo (empty($slotParam)) ? 'bg-yellow-500 text-black' : 'text-gray-500 hover:bg-gray-200'; ?>"
                            onclick="window.location.href='dashboardAdmin.php'">
                            Semua
                        </button>
                    </div>
                </div>

                <!-- Tabel -->
                <div class="overflow-y-auto max-h-[400px] scrollbar-custom">
                    <!-- Header Tabel -->
                    <div class="hidden md:grid grid-cols-4 bg-gray-100 text-gray-600 text-sm font-medium px-6 py-3 sticky top-0 z-10 rounded-lg">
                        <div>ID Transaksi</div>
                        <div class="text-center">Alamat</div>
                        <div class="text-center">Jumlah Minyak</div>
                        <div class="text-center">Status</div>
                    </div>

                    <!-- Data Pesanan -->
                    <div class="divide-y">
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $order): ?>
                                <a href="prosesAdmin.php?id=<?php echo urlencode($order['id_penjemputan']); ?>" class="block">
                                    <div class="grid grid-cols-1 md:grid-cols-4 items-center px-6 py-4 hover:bg-gray-50 text-sm">
                                        <div class="flex items-center">
                                            <img src="<?php echo htmlspecialchars($order['foto_limbah'] ?? '../gambar/minyak4.png'); ?>" 
                                                 alt="Thumbnail" class="w-10 h-10 rounded mr-4">
                                            <span><?php echo htmlspecialchars($order['id_penjemputan']); ?></span>
                                        </div>
                                        <div class="text-sm text-center">
                                            <?php echo htmlspecialchars($order['patokan']); ?>
                                        </div>
                                        <div class="text-sm text-center">
                                            <?php echo htmlspecialchars($order['jumlah_liter']); ?> Liter
                                        </div>
                                        <div class="text-center">
                                            <?php
                                                $status = htmlspecialchars($order['status_penjemputan']);
                                                $statusClass = '';
                                                if ($status === 'Proses') {
                                                    $statusClass = 'bg-yellow-400 text-black';
                                                } elseif ($status === 'Selesai') {
                                                    $statusClass = 'bg-green-400 text-white';
                                                } elseif ($status === 'Dibatalkan') {
                                                    $statusClass = 'bg-red-400 text-white';
                                                }
                                            ?>
                                            <span class="<?php echo $statusClass; ?> px-3 py-1 rounded-lg text-xs">
                                                <?php echo $status; ?>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center p-4 text-gray-500">
                                Tidak ada pesanan <strong>Proses</strong>
                                <?php 
                                // Tampilkan keterangan slot jika ada
                                if ($actualSlot) {
                                    echo " untuk slot <strong>{$actualSlot}</strong>";
                                }
                                ?>.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Footer -->
    <footer class="bg-white mt-4 shadow-lg ml-0 md:ml-64 transition-all duration-300">
        <div class="w-full p-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <a href="dashboardadmin.php" class="flex items-center">
                    <img src="../gambar/Banner2.png" class="h-8" alt="RefreshOil Logo" />
                </a>
                <span class="text-sm items-end text-gray-500">
                    © <?php echo date("Y"); ?> <a href="dashboardadmin.php" class="hover:text-yellow-500">RefreshOil™</a>. All Rights Reserved.
                </span>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Toggle Sidebar (Mobile)
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('overlay').classList.toggle('hidden');
        });

        // Show logout modal for logoutLink elements
        document.querySelectorAll('.logoutLink').forEach(function(element) {
            element.addEventListener('click', function(event) {
                event.preventDefault();
                document.getElementById('logoutModal').classList.remove('hidden');
                document.getElementById('overlay').classList.remove('hidden');
            });
        });

        // Show logout modal for logoutBtn
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

        // Toggle Sidebar (Mobile) - tombol di pojok kiri
        document.getElementById('toggleSidebar')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('overlay').classList.toggle('hidden');
        });

        // Tambahan Keyboard Escape (opsional)
        const logoutModal = document.getElementById('logoutModal');
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !logoutModal.classList.contains('hidden')) {
                logoutModal.classList.add('hidden');
                document.getElementById('overlay').classList.add('hidden');
            }
        });
    </script>
</body>
</html>