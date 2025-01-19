<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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
    $tgl = date('j', $timestamp); // Ambil hari dari tanggal
    $bln = $bulan[(int)date('n', $timestamp)]; // Ambil bulan dari tanggal
    $thn = date('Y', $timestamp); // Ambil tahun dari tanggal
    return "$tgl $bln $thn"; // Kembalikan tanggal dalam format Indonesia
}

// Inisialisasi variabel status untuk timeline
$status_pembayaran = 'paid';
$status_petugas = 'proses';
$status_order = 'pending';

// Informasi status (untuk kelas CSS dan label)
$status_pembayaranInfo = [
    'bg_class' => 'bg-green-500',
    'label' => 'Paid'
];
$status_petugasInfo = [
    'bg_class' => 'bg-yellow-500',
    'label' => 'Proses'
];
$status_orderInfo = [
    'bg_class' => 'bg-gray-600',
    'label' => 'Pending',
    // Menambahkan ikon SVG sesuai dengan status
    'icon' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 
    10-10S17.52 2 12 2zm-1 15l-5-5 1.41-1.41L11 
    14.17l7.59-7.59L20 8l-9 9z'
];

// Kelas untuk garis penghubung timeline
$line2Class = '';
$line3Class = 'border-yellow-500';


$host = "localhost";         
$username = "root";
$password = "";              
$dbname = "refresh_oil";      

try {
    // Menggunakan PDO (PHP Data Objects) untuk koneksi database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
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
        header("Location: riwayatAdmin.php?status=Semua");
        exit;
    }

    // Mengambil detail transaksi berdasarkan ID
    $transaksiStmt = $pdo->prepare("SELECT * FROM penjemputan WHERE id_penjemputan = :id LIMIT 1");
    $transaksiStmt->bindParam(':id', $transaksiId, PDO::PARAM_STR);
    $transaksiStmt->execute();
    $penjemputan = $transaksiStmt->fetch(PDO::FETCH_ASSOC);

    if (!$penjemputan) {
        $error_message = "Transaksi dengan ID tersebut tidak ditemukan.";
    } else {
        $userId = $penjemputan['id_user']; // Asumsikan kolom 'id_user' ada di tabel 'penjemputan'

        $userStmt = $pdo->prepare("SELECT nama, category, phone, profil_img FROM users WHERE id = :id LIMIT 1");
        $userStmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $userStmt->execute();
        $pemesan = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$pemesan) {
            // Jika pemesan tidak ditemukan, tampilkan pesan error
            $error_message = "Data pemesan tidak ditemukan.";
        } else {
            // Semua status diatur ke 'selesai', jadi tidak perlu mengambil dari database
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
      <!-- Hide "Detail Transaksi" Header jika dalam iframe -->
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
                              <!-- Ikon Dompet Selesai (Hijau) -->
                              <i class="fas fa-wallet text-white"></i>
                          </div>
                          <div>
                              <p class="text-gray-600 text-sm">Pembayaran</p>
                              <p class="text-gray-400 text-xs"><?php echo $status_pembayaranInfo['label']; ?></p>
                          </div>
                      </div>
                      <!-- Connecting Line2 -->
                      <div class=" border-green-500 border-t-2 <?php echo $line2Class; ?> flex-grow mx-4 "></div>
                      <!-- Petugas Berangkat -->
                      <div class="flex items-center">
                          <div class="rounded-full <?php echo $status_petugasInfo['bg_class']; ?> flex items-center justify-center w-8 h-8 mr-4 border-2 border-white">
                              <!-- Ikon Truck Selesai (Hijau) -->
                              <i class="fas fa-truck-moving text-white"></i>
                          </div>
                          <div>
                              <p class="text-gray-600 text-sm">Petugas Berangkat</p>
                              <p class="text-gray-400 text-xs"><?php echo $status_petugasInfo['label']; ?></p>
                          </div>
                      </div>
                      <!-- Connecting Line3 -->
                      <div class="border-t-2 <?php echo $line3Class; ?> flex-grow mx-4"></div>
                      <!-- Orderan Selesai -->
                      <div class="flex items-center gap-3">
                          <div class="rounded-full <?php echo $status_orderInfo['bg_class']; ?> flex items-center justify-center w-8 h-8 border-2 border-white">
                              <!-- Ikon Orderan Selesai (Hijau) -->
                              <i class="fas fa-check-circle text-white"></i>
                          </div>
                          <div>
                              <p class="text-gray-600 text-sm">Orderan Selesai</p>
                              <p class="text-gray-400 text-xs"><?php echo $status_orderInfo['label']; ?></p>
                          </div>
                      </div>
                  </div>
              </div>
          </div>

          <!-- Margin between timeline and cards -->
          <div class="mt-2">
              <div class="flex justify-center gap-8 flex-col lg:flex-row">
                  <!-- Kartu Kiri -->
                  <div class="flex flex-col w-full lg:w-3/4 bg-gray-100 p-6 rounded-lg">
                      <div class="flex flex-col lg:flex-row lg:space-x-6">
                          <div class="flex-shrink-0 w-full lg:w-[155px] h-[155px] mt-4 lg:mb-0">
                              <img src="<?php echo htmlspecialchars($penjemputan['foto_limbah'] ?? '../gambar/minyak4.png'); ?>" alt="Order Image" class="w-full h-full object-cover rounded-lg">
                          </div>
                          <div class="flex flex-col flex-grow">
                              <!-- Badge Status Order yang Diperbarui -->
                              <div class="flex items-center justify-end mb-4">
                                  <div class="flex items-center text-md font-semibold <?php echo $status_petugasInfo['bg_class']; ?> px-4 py-2 rounded-lg text-white shadow">
                                      <?php echo $status_petugasInfo['label']; ?>
                                  </div>
                              </div>
                              <p class="text-gray-600 mb-4 text-center lg:text-right">
                                Latitude <span class="font-bold text-black"><?php echo htmlspecialchars($penjemputan['alamat_jemput'] ?? 'N/A'); ?></span>
                              </p>
                              <div class="text-center lg:text-right">
                                  <h3 class="text-sm text-gray-600">Longitude</h3>
                                  <p class="text-black"><?php echo htmlspecialchars($penjemputan['detail_lokasi'] ?? 'N/A'); ?></p>
                                  <h3 class="text-sm text-gray-600">Patokan</h3>
                                  <p class="text-black"><?php echo htmlspecialchars($penjemputan['patokan'] ?? 'N/A'); ?></p>
                              </div>
                          </div>
                      </div>
                      <div class="mt-8 border-t border-gray-300 flex-grow"></div>
                      <div class="mt-6 text-center">
                        <p class="text-sm text-gray-600">
                        Ingin batalkan pesanan? 
                        <a href="https://wa.me/6283872839074?text=Halo%2C%20saya%20ingin%20membatalkan%20pesanan" 
                            class="text-blue-500 font-semibold hover:underline" 
                            target="_blank" 
                            rel="noopener noreferrer">
                            Chat kami
                        </a>
                        </p>
                      </div>
                  </div>

                  <!-- Kartu Kanan -->
                  <div class="w-full lg:w-1/3 bg-gray-100 p-6 rounded-lg">
                      <h2 class="text-xl font-semibold mb-4 text-center">Detail Pembayaran</h2>
                      <div class="mt-8 space-y-4">
                            <div class="flex justify-between">
                              <span class="text-sm font-medium">Biaya Kirim /L</span>
                              <span class="text-sm text-gray-600">Rp <?php echo number_format($penjemputan['biaya_kirim'], 0, ',', '.'); ?></span>
                            </div>
                            <div class="flex justify-between">
                              <span class="text-sm font-medium">Jumlah Liter</span>
                              <span class="text-sm text-gray-600"><?php echo htmlspecialchars($penjemputan['jumlah_liter']); ?> Liter</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-yellow-600">Potongan Voucher</span>
                                <span class="text-sm font-medium">-</span>
                            </div>
                      </div>
                      <div class="mt-8 border-t border-gray-300 flex-grow"></div>
                      <div class="pt-4 mt-6 flex justify-between items-center">
                          <span class="text-sm font-semibold">Total</span>
                          <span class="text-2xl font-bold">Rp <?php echo number_format($penjemputan['total_biaya'], 0, ',', '.'); ?></span>
                      </div>
                      <!-- Akhir Kartu Kanan -->
                  </div>
              </div>
          </div>
      <?php endif; ?>
    </div>

    <?php if (!isset($iframe) || !$iframe): ?>
        <!-- Footer hanya ditampilkan jika tidak dalam iframe -->
        <footer class="bg-gray-50 mt-4 shadow-lg ml-0 md:ml-64 transition-all duration-300">
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
    <?php endif; ?>

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