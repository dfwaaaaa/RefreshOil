<?php
// Start the session
session_start(); // Memulai sesi pengguna

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

$user = array();
$penjemputanList = array();
$error_message = "";

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass); 
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        /**
         * Cek apakah kolom `nama` di DB kosong.
         * Jika kosong => fallback ke potongan email
         */
        if (empty($user['nama'])) {
            $email = $user['email'];
            $parts = explode("@", $email);
            $fallbackName = $parts[0]; // Bagian sebelum '@'
            // Ganti tanda '.' atau '-' dengan spasi
            $fallbackName = str_replace([".", "-"], " ", $fallbackName);
            // Kapitalisasi kata
            $fallbackName = ucwords($fallbackName);
            $user['nama'] = $fallbackName;
        }

        // Initialize search
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        // Prepare base SQL
        $sql = "SELECT * FROM penjemputan WHERE id_user = :id_user";

        // Initialize parameters
        $params = [':id_user' => $_SESSION['user_id']];

        // Modify SQL based on search
        if ($search !== '') {
            if (is_numeric($search)) {
                // Search by id_penjemputan
                $sql .= " AND id_penjemputan = :id_penjemputan";
                $params[':id_penjemputan'] = $search;
            } else {
                // Invalid search term, no results
                $sql .= " AND 1=0";
            }
        }

        // Add order
        $sql .= " ORDER BY id_penjemputan DESC";

        // Prepare and execute
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $penjemputanList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // Jika user tidak ditemukan, logout
        $error_message = "Pengguna tidak ditemukan.";
    }

} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Terjadi kesalahan pada sistem. Silakan coba beberapa saat lagi.";
}

// Mapping status to CSS classes
$statusMapping = [
    'proses' => ['link' => 'prosesTransaksi.php', 'class' => 'bg-yellow-400 text-black'],
    'selesai' => ['link' => 'selesaiTransaksi.php', 'class' => 'bg-green-600 text-white'],
    'dibatalkan' => ['link' => 'batalTransaksi.php', 'class' => 'bg-red-600 text-white'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <!-- Head content -->
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
                        primary: '#F4C430',   /* Warna kuning dominan */
                        secondary: '#FFFFFF', /* Warna putih */
                        accent: '#353535',    /* Warna abu-abu gelap */
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
        .scrollbar-hide {
            -ms-overflow-style: none;  
            scrollbar-width: none;  
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
    </style>
</head>

<body class="bg-secondary">
    <!-- Mobile Header dengan Hamburger -->
    <header class="md:hidden flex items-center justify-between p-4 bg-white shadow-md fixed top-0 left-0 right-0 z-50 flex-nowrap">
        <div class="flex items-center flex-shrink-0">
            <button id="mobileMenuBtn" class="text-gray-700 focus:outline-none">
                <i class="fas fa-bars fa-2x"></i>
            </button>
            <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="w-24 h-auto ml-3 flex-shrink-0">
        </div>
        <div class="flex items-center space-x-4 flex-shrink-0">
            <div class="dropdown">
                <?php
                    // Tentukan sumber gambar profil dengan fallback untuk Mobile Header
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
                    <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
                        <i class="fas fa-home mr-3"></i>Dashboard
                    </a>
                    <a href="penjemputan.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
                        <i class="fas fa-truck mr-3"></i>Penjemputan
                    </a>
                    <a href="reward.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
                        <i class="fas fa-gift mr-3"></i>Reward
                    </a>
                    <a href="riwayat.php" class="flex items-center px-4 py-3 bg-yellow-400 text-gray-700 rounded-lg mb-2">
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
    <div class="ml-0 md:ml-64 p-8 pt-20 md:pt-5 transition-all duration-300">
        <!-- Combined Top Bar and Title (Desktop) -->
        <div class="hidden md:flex items-center justify-between space-x-4 mb-6 mt-3">
            <h1 class="text-2xl font-bold text-gray-800">Riwayat Transaksi</h1>
            
            <!-- Search Input and Account Info -->
            <div class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-4">
                <!-- Search Bar -->
                <form method="GET" class="relative w-full md:w-64">
                    <input 
                        type="text" 
                        name="search"
                        class="w-full border border-gray-300 rounded-full py-1.5 px-4 pl-10 focus:outline-none focus:ring-2 focus:ring-yellow-400" 
                        placeholder="Cari ID Transaksi..."
                        id="searchInput"
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">
                        <i class="fas fa-search"></i>
                    </span>
                </form>

                <!-- Account -->
                <div class="flex items-center space-x-4">
                    <div class="dropdown">
                        <?php
                            // Tentukan sumber gambar profil dengan fallback untuk Desktop Header
                            $profileImgDesktop = (!empty($user['profil_img'])) ? htmlspecialchars($user['profil_img']) : '../gambar/profil.png';
                        ?>
                        <button id="desktopProfileBtn" class="focus:outline-none flex items-center">
                            <div class="flex flex-col text-right">
                                <!-- Menampilkan nama pengguna -->
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
                            <a href="#" id="logoutBtn" class="logoutLink"><i class="fas fa-sign-out-alt mr-2"></i>Keluar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction List -->
        <div class="shadow-md rounded-lg">
            <div class="bg-white shadow-md rounded-lg overflow-auto" style="height: calc(100vh - 250px);">
                <!-- Filter Buttons -->
                <div class="flex items-center justify-start gap-4 px-6 py-4 bg-yellow-400 rounded-t-lg overflow-x-auto sticky top-0 z-10">
                    <button 
                        id="button-semua" 
                        class="text-white bg-yellow-700 font-medium rounded-full text-sm px-5 py-2.5 flex-shrink-0"
                        data-bg-color="bg-yellow-700"
                        data-text-color="text-white"
                    >
                        Semua
                    </button>
                    <button 
                        id="button-proses" 
                        class="text-gray-500 hover:text-gray-700 focus:bg-yellow-400 focus:text-black font-medium rounded-full text-sm px-5 py-2.5 flex-shrink-0"
                        data-bg-color="bg-yellow-400"
                        data-text-color="text-black"
                    >
                        Proses
                    </button>
                    <button 
                        id="button-selesai" 
                        class="text-gray-500 hover:text-gray-700 focus:bg-green-200 focus:text-green-800 font-medium rounded-full text-sm px-5 py-2.5 flex-shrink-0"
                        data-bg-color="bg-green-200"
                        data-text-color="text-green-800"
                    >
                        Selesai
                    </button>
                    <button 
                        id="button-dibatalkan" 
                        class="text-gray-500 hover:text-gray-700 focus:bg-red-200 focus:text-red-800 font-medium rounded-full text-sm px-5 py-2.5 flex-shrink-0"
                        data-bg-color="bg-red-200"
                        data-text-color="text-red-800"
                    >
                        Dibatalkan
                    </button>
                </div>

                <!-- Column Headers (Hidden on Mobile) -->
                <div class="hidden md:grid grid-cols-5 text-gray-400 px-6 py-4 text-sm sticky top-[72px] bg-white z-10">
                    <div>ID Transaksi</div>
                    <div class="text-center">Tanggal Transaksi</div>
                    <div class="text-center">Jumlah Minyak</div>
                    <div class="text-center">Status</div>
                    <div class="text-center">Biaya Kirim</div>
                </div>

                <!-- Data Transaksi -->
                <div class="divide-y transaksi">
                    <?php if (!empty($penjemputanList)): ?>
                        <?php foreach ($penjemputanList as $penjemputan): ?>
                            <?php
                                $status = strtolower(trim($penjemputan['status_penjemputan']));
                                if (array_key_exists($status, $statusMapping)) {
                                    $link = $statusMapping[$status]['link'] . "?id=" . htmlspecialchars($penjemputan['id_penjemputan']);
                                    $statusClass = $statusMapping[$status]['class'];
                                } else {
                                    $link = "#";
                                    $statusClass = "bg-gray-400 text-white";
                                }
                            ?>
                            <a href="<?php echo $link; ?>" class="block">
                                <div class="grid grid-cols-1 md:grid-cols-5 items-center px-6 py-4 hover:bg-secondary text-sm transition-colors duration-300">
                                    <div class="flex items-center">
                                        <!-- Ganti dengan kode yang diinginkan -->
                                        <img 
                                            src="<?php echo htmlspecialchars($penjemputan['foto_limbah'] ?? '../gambar/minyak4.png'); ?>" 
                                            alt="Thumbnail" 
                                            class="w-10 h-10 rounded mr-4"
                                        >
                                        <span class="text-sm">
                                            <?php echo htmlspecialchars($penjemputan['id_penjemputan']); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-center">
                                        <?php echo htmlspecialchars(date("d F Y", strtotime($penjemputan['tanggal_jemput']))); ?>
                                    </div>
                                    <div class="text-sm text-center">
                                        <?php echo htmlspecialchars($penjemputan['jumlah_liter']); ?> Liter
                                    </div>
                                    <div class="text-center">
                                        <span class="inline-block <?php echo $statusClass; ?> px-3 py-1 rounded-lg text-xs">
                                            <?php echo htmlspecialchars(ucfirst($penjemputan['status_penjemputan'])); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-center">
                                        Rp <?php echo number_format($penjemputan['total_biaya'], 0, ',', '.'); ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="px-6 py-4 text-center text-gray-500">
                            <?php if ($search !== ''): ?>
                                Tidak ada riwayat transaksi untuk pencarian ID Penjemputan "<?php echo htmlspecialchars($search); ?>".
                            <?php else: ?>
                                Tidak ada riwayat transaksi.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-50 mt-6 shadow-lg ml-0 md:ml-64 transition-all duration-300">
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

        // Filter Buttons
        const buttonSemua = document.getElementById('button-semua');
        const buttonProses = document.getElementById('button-proses');
        const buttonSelesai = document.getElementById('button-selesai');
        const buttonDibatalkan = document.getElementById('button-dibatalkan');

        function setActiveButton(button) {
            [buttonSemua, buttonProses, buttonSelesai, buttonDibatalkan].forEach(btn => {
                btn.className = 'text-gray-500 hover:text-gray-700 font-medium rounded-full text-sm px-5 py-2.5 flex-shrink-0';
            });
            button.classList.remove('text-gray-500', 'hover:text-gray-700');
            button.classList.add(button.getAttribute('data-bg-color'), button.getAttribute('data-text-color'));
        }

        // Atribut data untuk tombol
        buttonSemua.setAttribute('data-bg-color', 'bg-yellow-700');
        buttonSemua.setAttribute('data-text-color', 'text-white');
        buttonProses.setAttribute('data-bg-color', 'bg-yellow-400');
        buttonProses.setAttribute('data-text-color', 'text-black');
        buttonSelesai.setAttribute('data-bg-color', 'bg-green-200');
        buttonSelesai.setAttribute('data-text-color', 'text-green-800');
        buttonDibatalkan.setAttribute('data-bg-color', 'bg-red-200');
        buttonDibatalkan.setAttribute('data-text-color', 'text-red-800');

        // Fungsi filter transaksi
        function filterTransaksi(status) {
            const transactionItems = document.querySelectorAll('.divide-y.transaksi > a');
            transactionItems.forEach(item => {
                const statusSpan = item.querySelector('span[class*="bg-"]'); 
                const statusText = statusSpan.textContent.toLowerCase().trim();
                
                if (status === 'semua') {
                    item.style.display = 'block';
                } else if (status === 'proses') {
                    item.style.display = statusText === 'proses' ? 'block' : 'none';
                } else if (status === 'selesai') {
                    item.style.display = statusText === 'selesai' ? 'block' : 'none';
                } else if (status === 'dibatalkan') {
                    item.style.display = statusText === 'dibatalkan' ? 'block' : 'none';
                }
            });
        }

        // Event listeners filter
        buttonSemua.addEventListener('click', () => {
            setActiveButton(buttonSemua);
            filterTransaksi('semua');
        });
        buttonProses.addEventListener('click', () => {
            setActiveButton(buttonProses);
            filterTransaksi('proses');
        });
        buttonSelesai.addEventListener('click', () => {
            setActiveButton(buttonSelesai);
            filterTransaksi('selesai');
        });
        buttonDibatalkan.addEventListener('click', () => {
            setActiveButton(buttonDibatalkan);
            filterTransaksi('dibatalkan');
        });

        // Set default
        setActiveButton(buttonSemua);
        filterTransaksi('semua');

        // Logout Modal
        const logoutBtn = document.getElementById('logoutBtn');
        const logoutBtnMobile = document.getElementById('logoutBtnMobile');
        const logoutModal = document.getElementById('logoutModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const confirmBtn = document.getElementById('confirmBtn');

        function toggleModal(show = true) {
            logoutModal.classList.toggle('active', show);
        }

        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                toggleModal(true);
            });
        }
        if (logoutBtnMobile) {
            logoutBtnMobile.addEventListener('click', (e) => {
                e.preventDefault();
                toggleModal(true);
            });
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => toggleModal(false));
        }
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                window.location.href = 'logout.php';
            });
        }
        if (logoutModal) {
            logoutModal.addEventListener('click', (e) => {
                if (e.target === logoutModal) {
                    toggleModal(false);
                }
            });
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && logoutModal.classList.contains('active')) {
                toggleModal(false);
            }
        });

        // Dropdown
        const desktopProfileBtn = document.getElementById('desktopProfileBtn');
        const mobileProfileBtn = document.getElementById('mobileProfileBtn');
        const dropdowns = document.querySelectorAll('.dropdown');

        function closeAllDropdowns() {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        }

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
        document.addEventListener('click', () => {
            closeAllDropdowns();
        });
    </script>
</body>
</html>