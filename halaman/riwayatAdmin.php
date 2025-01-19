<?php
// Memulai sesi PHP untuk mengelola data pengguna secara persisten
session_start();

// Memeriksa apakah pengguna sudah login
if (!isset($_SESSION['id_admin'])) {
    header("Location: loginAdmin.php");
    exit;
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

    // Inisialisasi filter status
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : 'Semua';
    $allowedStatus = ['Semua', 'Selesai', 'Dibatalkan', 'Proses'];
    if (!in_array($statusFilter, $allowedStatus)) {
        $statusFilter = 'Semua';
    }

    // Inisialisasi pencarian
    $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Menentukan jumlah data per halaman
    $perPage = 10;

    // Menentukan halaman saat ini
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($page < 1) $page = 1;

    // Menghitung total data berdasarkan filter dan pencarian
    if ($statusFilter == 'Semua') {
        // Menambahkan 'Proses' ke dalam filter 'Semua'
        $countSql = "SELECT COUNT(*) FROM penjemputan WHERE status_penjemputan IN ('Proses', 'Selesai', 'Dibatalkan')";
        if ($searchQuery !== '') {
            $countSql .= " AND id_penjemputan LIKE :search";
        }
        $countStmt = $pdo->prepare($countSql);
        if ($searchQuery !== '') {
            $likeSearch = '%' . $searchQuery . '%';
            $countStmt->bindParam(':search', $likeSearch, PDO::PARAM_STR);
        }
    } else {
        $countSql = "SELECT COUNT(*) FROM penjemputan WHERE status_penjemputan = :status";
        if ($searchQuery !== '') {
            $countSql .= " AND id_penjemputan LIKE :search";
        }
        $countStmt = $pdo->prepare($countSql);
        $countStmt->bindParam(':status', $statusFilter, PDO::PARAM_STR);
        if ($searchQuery !== '') {
            $likeSearch = '%' . $searchQuery . '%';
            $countStmt->bindParam(':search', $likeSearch, PDO::PARAM_STR);
        }
    }
    $countStmt->execute();
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $perPage);
    if ($page > $totalPages && $totalPages > 0) {
        $page = $totalPages;
    }

    // Menghitung offset
    $offset = ($page - 1) * $perPage;

    // Mengambil data riwayat pesanan berdasarkan filter, pencarian, dan paginasi
    if ($statusFilter == 'Semua') {
        $dataSql = "SELECT * FROM penjemputan WHERE status_penjemputan IN ('Proses', 'Selesai', 'Dibatalkan')";
        if ($searchQuery !== '') {
            $dataSql .= " AND id_penjemputan LIKE :search";
        }
        $dataSql .= " ORDER BY id_penjemputan DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($dataSql);
        if ($searchQuery !== '') {
            $stmt->bindParam(':search', $likeSearch, PDO::PARAM_STR);
        }
    } else {
        $dataSql = "SELECT * FROM penjemputan WHERE status_penjemputan = :status";
        if ($searchQuery !== '') {
            $dataSql .= " AND id_penjemputan LIKE :search";
        }
        $dataSql .= " ORDER BY id_penjemputan DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($dataSql);
        $stmt->bindParam(':status', $statusFilter, PDO::PARAM_STR);
        if ($searchQuery !== '') {
            $stmt->bindParam(':search', $likeSearch, PDO::PARAM_STR);
        }
    }
    $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $historyOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log kesalahan dan tampilkan pesan umum
    error_log($e->getMessage());
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Terjadi kesalahan koneksi. Silakan coba lagi nanti.</div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - RefreshOil</title>
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
        /* Scrollbar Kustom untuk Bagian Riwayat Transaksi */
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
    </style>
</head>

<body class="bg-secondary">

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
                    <a href="riwayatAdmin.php?status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" class="flex items-center px-4 py-3 bg-yellow-400 text-gray-700 rounded-lg mb-2">
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
                <a href="#" id="logoutBtn" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-yellow-100 logoutLink">
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
                <form method="POST" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button
                        type="submit"
                        id="confirmBtn"
                        class="px-4 py-2 text-sm font-medium text-black bg-yellow-100 rounded-lg hover:bg-yellow-400 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors"
                    >
                        Iya
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="ml-0 md:ml-64 p-8 pt-20 md:pt-5 transition-all duration-300">
        <!-- Combined Top Bar and Title (Only for Desktop) -->
        <div class="hidden md:flex items-center justify-between mb-6 mt-2">
            <!-- Left Side: Judul Riwayat Transaksi -->
            <h1 class="text-2xl font-bold text-gray-800">Riwayat Transaksi</h1>

            <!-- Right Side: Search Bar dan Informasi Akun -->
            <div class="flex items-center space-x-4">
                <!-- Search Bar -->
                <div class="relative w-64">
                    <form method="GET" action="riwayatAdmin.php">
                        <input type="hidden" name="status" value="<?php echo urlencode($statusFilter); ?>">
                        <input type="text" 
                            name="search"
                            class="w-full border border-gray-300 rounded-full py-1.5 px-4 pl-10 focus:outline-none focus:ring-2 focus:ring-yellow-400" 
                            placeholder="Cari ID Transaksi..."
                            value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">
                            <i class="fas fa-search"></i>
                        </span>
                    </form>
                </div>

                <!-- Informasi Akun -->
                <div class="flex items-center space-x-4">
                    <!-- Dropdown Profile -->
                    <div class="dropdown">
                        <button class="focus:outline-none flex items-center">
                            <div class="flex flex-col text-right">
                                <span class="text-gray-800 font-semibold"><?php echo htmlspecialchars($admin['username']); ?></span>
                                <span class="text-gray-400 text-sm">Admin</span>
                            </div>
                            <img src="<?php echo htmlspecialchars($admin['profile_img'] ?? '../gambar/profil.png'); ?>" alt="User Avatar" class="w-10 h-10 rounded-full ml-3">
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction List -->
        <div class="shadow-md rounded-lg mb-2"> <!-- Added mb-12 for spacing from footer -->
            <!-- Transaction List -->
            <div class="bg-white shadow-md rounded-lg overflow-auto scrollbar-custom" style="height: calc(100vh - 250px);">
                <!-- Filter Buttons -->
                <div class="flex items-center justify-start gap-4 px-6 py-4 bg-yellow-400 rounded-t-lg overflow-x-auto sticky top-0 z-10">
                    <a href="riwayatAdmin.php?status=Semua<?php echo ($searchQuery !== '') ? '&search=' . urlencode($searchQuery) : ''; ?>" 
                       class="<?php echo ($statusFilter == 'Semua') ? 'text-white bg-yellow-600' : 'text-gray-500 hover:text-gray-700'; ?> font-medium rounded-full text-sm px-5 py-2.5 flex-shrink-0">
                        Semua
                    </a>
                    <a href="riwayatAdmin.php?status=Proses<?php echo ($searchQuery !== '') ? '&search=' . urlencode($searchQuery) : ''; ?>" 
                       class="<?php echo ($statusFilter == 'Proses') ? 'text-white bg-yellow-600' : 'text-gray-500 hover:text-gray-700'; ?> font-medium rounded-full text-sm px-5 py-2.5 flex-shrink-0">
                        Proses
                    </a>
                    <a href="riwayatAdmin.php?status=Selesai<?php echo ($searchQuery !== '') ? '&search=' . urlencode($searchQuery) : ''; ?>" 
                       class="<?php echo ($statusFilter == 'Selesai') ? 'text-white bg-green-600' : 'text-gray-500 hover:text-gray-700'; ?> font-medium rounded-full text-sm px-5 py-2.5 flex-shrink-0">
                        Selesai
                    </a>
                    <a href="riwayatAdmin.php?status=Dibatalkan<?php echo ($searchQuery !== '') ? '&search=' . urlencode($searchQuery) : ''; ?>" 
                       class="<?php echo ($statusFilter == 'Dibatalkan') ? 'text-white bg-red-600' : 'text-gray-500 hover:text-gray-700'; ?> font-medium rounded-full text-sm px-5 py-2.5 flex-shrink-0">
                        Dibatalkan
                    </a>
                </div>

                <!-- Column Headers (Hidden on Mobile) -->
                <div class="hidden md:grid grid-cols-5 text-gray-400 px-6 py-4 text-sm sticky top-16 bg-white z-10">
                    <div>ID Transaksi</div>
                    <div class="text-center">Tanggal Transaksi</div>
                    <div class="text-center">Jumlah Minyak</div>
                    <div class="text-center">Status</div>
                    <div class="text-center">Biaya Kirim</div>
                </div>

                <!-- Table: Data -->
                <div class="divide-y">
                    <?php if (!empty($historyOrders)): ?>
                        <?php foreach ($historyOrders as $order): ?>
                            <?php
                                // Menentukan halaman tujuan berdasarkan status_penjemputan
                                switch ($order['status_penjemputan']) {
                                    case 'Selesai':
                                        $targetPage = 'selesaiAdmin.php';
                                        break;
                                    case 'Dibatalkan':
                                        $targetPage = 'batalAdmin.php';
                                        break;
                                    case 'Proses':
                                        $targetPage = 'prosesAdmin.php';
                                        break;
                                    default:
                                        $targetPage = 'prosesTransaksiadmin.php';
                                }
                            ?>
                            <a href="<?php echo htmlspecialchars($targetPage) . '?id=' . urlencode($order['id_penjemputan']); ?>" class="block">
                                <div class="grid grid-cols-1 md:grid-cols-5 items-center px-6 py-4 hover:bg-secondary text-sm transition-colors duration-300">
                                    <div class="flex items-center">
                                        <img src="<?php echo htmlspecialchars($order['foto_limbah'] ?? '../gambar/minyak4.png'); ?>" alt="Thumbnail" class="w-10 h-10 rounded mr-4">
                                        <span class="text-sm"><?php echo htmlspecialchars($order['id_penjemputan']); ?></span>
                                    </div>
                                    <div class="text-sm text-center"><?php echo htmlspecialchars(date('d F Y', strtotime($order['tanggal_jemput']))); ?></div>
                                    <div class="text-sm text-center"><?php echo htmlspecialchars($order['jumlah_liter']); ?> Liter</div>
                                    <div class="text-center">
                                        <?php
                                            $status = htmlspecialchars($order['status_penjemputan']);
                                            $statusClass = '';
                                            switch ($status) {
                                                case 'Proses':
                                                    $statusClass = 'bg-yellow-400 text-black';
                                                    break;
                                                case 'Selesai':
                                                    $statusClass = 'bg-green-600 text-white';
                                                    break;
                                                case 'Dibatalkan':
                                                    $statusClass = 'bg-red-600 text-white';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-gray-400 text-white';
                                            }
                                        ?>
                                        <span class="<?php echo $statusClass; ?> px-3 py-1 rounded-full text-xs"><?php echo $status; ?></span>
                                    </div>
                                    <div class="text-sm text-center">Rp <?php echo number_format($order['total_biaya'], 0, ',', '.'); ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center p-4 text-gray-500">Tidak ada riwayat pesanan.</div>
                    <?php endif; ?>
                </div>

                <!-- Paginasi -->
                <?php if ($totalPages > 1): ?>
                    <div class="flex justify-center mt-4">
                        <nav class="inline-flex -space-x-px">
                            <!-- Previous Page Link -->
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" 
                                   class="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-100 hover:text-gray-700">
                                    Previous
                                </a>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" 
                                   class="<?php echo ($i == $page) ? 'z-10 px-3 py-2 leading-tight text-blue-600 bg-blue-50 border border-blue-300' : 'px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <!-- Next Page Link -->
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" 
                                   class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-100 hover:text-gray-700">
                                    Next
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-50 mt-4 shadow-lg ml-0 md:ml-64 transition-all duration-300">
        <div class="w-full p-8">
            <!-- Bottom Section with Logo and Copyright -->
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

    <!-- JavaScript -->
    <script>
        // Sidebar Toggle for Mobile
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            });
        }

        // Logout Modal Functionality
        const logoutLinks = document.querySelectorAll('.logoutLink');
        const logoutModal = document.getElementById('logoutModal');
        const cancelBtn = document.getElementById('cancelBtn');

        function toggleModal(show = true) {
            if (show) {
                logoutModal.classList.add('active');
            } else {
                logoutModal.classList.remove('active');
            }
        }

        logoutLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                toggleModal(true);
            });
        });

        cancelBtn.addEventListener('click', () => toggleModal(false));

        logoutModal.addEventListener('click', (e) => {
            if (e.target === logoutModal) {
                toggleModal(false);
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && logoutModal.classList.contains('active')) {
                toggleModal(false);
            }
        });
    </script>
</body>
</html>