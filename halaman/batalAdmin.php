<?php
// batalAdmin.php

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

// Inisialisasi variabel status untuk timeline
$status_pembayaran = 'paid';
$status_petugas = 'cancelled';
$status_order = 'cancelled';

// Informasi status (untuk kelas CSS dan label)
$status_pembayaranInfo = [
    'bg_class' => 'bg-green-500',
    'label' => 'Paid'
];
$status_petugasInfo = [
    'bg_class' => 'bg-red-500',
    'label' => 'Dibatalkan'
];
$status_orderInfo = [
    'bg_class' => 'bg-red-500',
    'label' => 'Dibatalkan'
];

// Kelas untuk garis penghubung timeline
$line2Class = 'border-green-500';
$line3Class = 'border-red-500';

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
        // Jika transaksi tidak ditemukan, tampilkan pesan error
        $error_message = "Transaksi dengan ID tersebut tidak ditemukan.";
    } else {
        // Mengambil data pemesan dari tabel 'users' berdasarkan 'id_user'
        $userId = $penjemputan['id_user']; // Asumsikan kolom 'id_user' ada di tabel 'penjemputan'

        $userStmt = $pdo->prepare("SELECT nama, category, phone, profil_img FROM users WHERE id = :id LIMIT 1");
        $userStmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $userStmt->execute();
        $pemesan = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$pemesan) {
            // Jika pemesan tidak ditemukan, tampilkan pesan error
            $error_message = "Data pemesan tidak ditemukan.";
        } else {
            // Status telah diatur di atas, jadi tidak perlu mengambil dari database
            // Jika Anda ingin memastikan di database juga diubah, Anda bisa menambahkan query update di sini

            // Misalnya, untuk memastikan status di database:
            /*
            $updateStmt = $pdo->prepare("UPDATE penjemputan SET status_pembayaran = 'paid', status_petugas = 'cancelled', status_order = 'cancelled' WHERE id_penjemputan = :id");
            $updateStmt->bindParam(':id', $transaksiId, PDO::PARAM_STR);
            $updateStmt->execute();
            */
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detail Transaksi - RefreshOil</title>
  <link href="../gambar/logo.png" rel="shortcut icon">

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome for Icons -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js"></script>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- Tailwind Configuration -->
  <script>
      tailwind.config = {
          theme: {
              extend: {
                  colors: {
                      primary: '#F4C430',    /* Warna kuning dominan */
                      secondary: '#FFFFFF',  /* Warna putih */
                      accent: '#353535',     /* Warna abu-abu gelap */
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
    .dropdown {
        position: relative;
        display: inline-block;
    }

    .dropdown .dropdown-content {
        display: none;
        position: absolute;
        right: 0;
        background-color: white;
        min-width: 160px;
        box-shadow: 0px 8px 16px rgba(0,0,0,0.2);
        border-radius: 8px;
        z-index: 1000;
    }

    .dropdown .dropdown-content a {
        color: black;
        padding: 12px 16px;
        text-decoration: none;
        display: block;
    }

    .dropdown .dropdown-content a:hover {
        background-color: #f1f1f1;
    }

    .dropdown.show .dropdown-content {
        display: block;
    }
  </style>
</head>

<body class="font-[Poppins] bg-white text-gray-800">

    <!-- Mobile Header dengan Hamburger -->
    <header class="md:hidden flex items-center justify-between p-4 bg-white shadow-md fixed top-0 left-0 right-0 z-50 flex-nowrap">
        <div class="flex items-center flex-shrink-0">
            <button id="mobileMenuBtn" class="text-gray-700 focus:outline-none">
                <i class="fas fa-bars fa-2x"></i>
            </button>
            <!-- Logo dengan ukuran lebih besar tetapi proporsional dan berjauhan dari ikon profil -->
            <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="w-24 h-auto ml-3 flex-shrink-0">
        </div>
        <div class="flex items-center space-x-4 flex-shrink-0">
            <!-- Dropdown untuk Admin -->
            <div class="dropdown">
                <img src="<?php echo htmlspecialchars($admin['profil_img'] ?? '../gambar/profil.png'); ?>" alt="User Avatar" class="w-8 h-8 rounded-full" id="mobileProfileBtn">
                <div class="dropdown-content mt-2">
                    <a href="profil.php"><i class="fas fa-user mr-2"></i>Lihat Profil</a>
                    <a href="#" id="logoutBtnMobile" class="logoutLink"><i class="fas fa-sign-out-alt mr-2"></i>Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <div id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-white shadow-md transform -translate-x-full md:translate-x-0 transition-transform duration-300 z-50">
    <div class="fixed w-64 h-screen p-5 bg-white shadow-md flex flex-col justify-between">
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
        <a href="riwayatAdmin.php" class="flex items-center px-4 py-3 bg-yellow-400 text-gray-700 rounded-lg mb-2">
          <i class="fas fa-book mr-3"></i>Riwayat
        </a>
        <a href="edukasiAdmin.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
          <i class="fas fa-calendar-alt mr-3"></i>Edukasi
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
      <!-- Hide "Detail Transaksi" Header if in iframe -->
      <h1 class="text-2xl font-bold mb-6 mt-5 text-accent">Detail Transaksi</h1>

      <?php if ($error_message): ?>
          <div class="flex justify-center">
              <div class="bg-red-100 text-red-700 p-4 rounded-lg">
                  <?php echo htmlspecialchars($error_message); ?>
              </div>
          </div>
      <?php elseif ($penjemputan): ?>
          <!-- Kartu Awal -->
          <div class="flex justify-center py-4">
              <div class="bg-gray-100 p-6 rounded-lg w-full max-w-6xl">
                  <div class="flex justify-between items-center">
                      <div>
                          <div class="text-lg font-semibold">ID Transaksi: <?php echo htmlspecialchars($penjemputan['id_penjemputan']); ?></div>
                          <div class="text-sm font-medium text-gray-600"><?php echo htmlspecialchars($penjemputan['jumlah_liter']); ?> Liter</div>
                      </div>
                      <div class="border-l border-gray-300 h-16"></div>
                      <div class="flex flex-col">
                          <div class="text-xl font-bold">Rp <?php echo number_format($penjemputan['total_biaya'], 0, ',', '.'); ?></div>
                          <?php // if ($penjemputan['potongan_voucher'] > 0): ?>
                              <!-- <div class="text-sm font-medium text-yellow-600">Hemat Rp <?php // echo number_format($penjemputan['potongan_voucher'], 0, ',', '.'); ?></div> -->
                          <?php // endif; ?>
                      </div>
                      <div class="border-l border-gray-300 h-16"></div>
                      <div>
                          <div class="text-2xl font-semibold text-yellow-600 text-center"><?php echo htmlspecialchars($penjemputan['poin_didapat']); ?></div>
                          <div class="text-sm font-medium text-black">Poin di Dapat</div>
                      </div>
                      <div class="border-l border-gray-300 h-16"></div>
                      <div>
                          <div class="text-lg"><?php echo htmlspecialchars(format_tanggal_indonesia($penjemputan['tanggal_jemput'])); ?></div>
                      </div>
                  </div>
              </div>
          </div>

          <!-- Timeline -->
          <div class="flex justify-center py-4">
              <div class="bg-white p-6 rounded-lg w-full max-w-6xl">
                  <div class="flex items-center justify-between">
                      <!-- Pembayaran -->
                      <div class="flex items-center">
                          <div class="rounded-full <?php echo $status_pembayaranInfo['bg_class']; ?> flex items-center justify-center w-8 h-8 mr-4 border-2 border-white">
                              <!-- Ikon Pembayaran Paid (Hijau) -->
                              <i class="fas fa-wallet text-white"></i>
                          </div>
                          <div>
                              <p class="text-gray-600 text-sm">Pembayaran</p>
                              <p class="text-gray-400 text-xs"><?php echo $status_pembayaranInfo['label']; ?></p>
                          </div>
                      </div>
                      <!-- Connecting Line2 -->
                      <div class="border-t-2 <?php echo $line2Class; ?> flex-grow mx-4"></div>
                      <!-- Petugas Berangkat -->
                      <div class="flex items-center">
                          <div class="rounded-full <?php echo $status_petugasInfo['bg_class']; ?> flex items-center justify-center w-8 h-8 mr-4 border-2 border-white">
                              <!-- Ikon Petugas Dibatalkan (Merah) -->
                              <i class="fas fa-truck text-white"></i>
                          </div>
                          <div>
                              <p class="text-gray-600 text-sm">Petugas Berangkat</p>
                              <p class="text-gray-400 text-xs"><?php echo $status_petugasInfo['label']; ?></p>
                          </div>
                      </div>
                      <!-- Connecting Line3 -->
                      <div class="border-t-2 <?php echo $line3Class; ?> flex-grow mx-4"></div>
                      <!-- Orderan Dibatalkan -->
                      <div class="flex items-center gap-3">
                          <div class="rounded-full <?php echo $status_orderInfo['bg_class']; ?> flex items-center justify-center w-8 h-8 border-2 border-white">
                              <!-- Ikon Orderan Dibatalkan (Merah) -->
                              <i class="fas fa-times-circle text-white"></i>
                          </div>
                          <div>
                              <p class="text-gray-600 text-sm">Orderan</p>
                              <p class="text-gray-400 text-xs"><?php echo $status_orderInfo['label']; ?></p>
                          </div>
                      </div>
                  </div>
              </div>
          </div>

          <!-- Margin between timeline dan cards -->
          <div class="mt-2">
              <div class="flex justify-center gap-8 flex-col lg:flex-row">
                  <!-- Kartu Kiri -->
                  <div class="flex flex-col w-full lg:w-3/4 bg-gray-100 p-6 rounded-lg">
                      <div class="flex flex-col lg:flex-row lg:space-x-6">
                          <div class="flex-shrink-0 w-full lg:w-[155px] h-[155px] mt-4 lg:mb-0">
                              <img src="<?php echo htmlspecialchars($penjemputan['foto_limbah'] ?? '../gambar/minyak4.png'); ?>" alt="Order Image" class="w-full h-full object-cover rounded-lg">
                          </div>
                          <div class="flex flex-col flex-grow">
                                <div class="flex items-center justify-end mb-4">
                                  <div class="flex items-center text-md font-semibold <?php echo $status_orderInfo['bg_class']; ?> px-4 py-2 rounded-lg text-white shadow">
                                      <?php echo $status_orderInfo['label']; ?>
                                  </div>
                                </div>
                              <p class="text-gray-600 mb-4 text-right">
                                  Ambil ke <span class="font-bold text-black"><?php echo htmlspecialchars($penjemputan['alamat_jemput'] ?? 'N/A'); ?></span>
                              </p>
                              <div class="text-right">
                                  <h3 class="text-sm text-gray-600">Detail Lokasi</h3>
                                  <p class="text-black"><?php echo htmlspecialchars($penjemputan['detail_lokasi'] ?? 'N/A'); ?></p>
                                  <h3 class="text-sm text-gray-600">Patokan</h3>
                                  <p class="text-black"><?php echo htmlspecialchars($penjemputan['patokan'] ?? 'N/A'); ?></p>
                              </div>
                          </div>
                      </div>
                      <div class="mt-8 border-t border-gray-300 flex-grow"></div>
                      <div class="mt-6 text-center">
                          <p class="text-sm text-red-600 font-bold">Dibatalkan</a></p>
                      </div>
                  </div>

                  <!-- Kartu Kanan -->
                  <div class="w-80 bg-gray-100 rounded-lg p-6 text-center">
                      <div class="flex justify-between text-sm text-gray-500 mb-4 mt-2">
                          <span>Pemesan</span>
                          <span>Kategori: <?php echo htmlspecialchars($pemesan['category'] ?? 'N/A'); ?></span>
                      </div>
                      <div class="w-24 h-24 bg-black rounded-full mx-auto flex items-center justify-center mb-2 mt-10">
                          <img src="<?php echo htmlspecialchars($pemesan['profil_img'] ?? 'https://via.placeholder.com/60'); ?>" alt="User Avatar" class="rounded-full">
                      </div>
                      <h2 class="text-lg font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($pemesan['nama'] ?? 'N/A'); ?></h2>
                      <p class="text-sm text-gray-600"><?php echo htmlspecialchars($pemesan['phone'] ?? 'N/A'); ?></p>
                  </div>
              </div>
          </div>
      <?php endif; ?>
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

    // Logout Modal Functionality
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutBtnMobile = document.getElementById('logoutBtnMobile'); // Additional for mobile
    const logoutModal = document.getElementById('logoutModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const confirmBtn = document.getElementById('confirmBtn');

    function toggleModal(show = true) {
        if (show) {
            logoutModal.classList.add('active');
        } else {
            logoutModal.classList.remove('active');
        }
    }

    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleModal(true);
        });
    }

    if (logoutBtnMobile) { // Additional for mobile
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

    // Dropdown Functionality
    const mobileProfileBtnElement = document.getElementById('mobileProfileBtn');
    const dropdowns = document.querySelectorAll('.dropdown');

    function closeAllDropdowns() {
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }

    // Toggle dropdown for mobile
    if (mobileProfileBtnElement) {
        mobileProfileBtnElement.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdowns.forEach(dropdown => {
                if (dropdown.contains(mobileProfileBtnElement)) {
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
  </script>
</body>
</html>