<?php
session_start();

/**
 * Koneksi ke database refresh_oil menggunakan PDO
 * dan penanganan error dengan try-catch
 */
$host     = "localhost";    // Sesuaikan
$username = "root";         // Sesuaikan
$password = "";             // Sesuaikan
$dbname   = "refresh_oil";  // Sesuaikan

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Setel mode error
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// --- [1] Pengecekan Login ---
if (!isset($_SESSION['user_id'])) {
    // Jika user belum login, arahkan ke login
    header("Location: login.php");
    exit;
}

// Ambil id user dari session
$user_id = $_SESSION['user_id'];

// --- [2] Ambil Data Poin dan ttl_voucher Pengguna dari tabel users ---
$userPoints = 0;
$ttlVoucher = 0;
try {
    $queryPoints = "SELECT poin, ttl_voucher FROM users WHERE id = :id LIMIT 1";
    $stmtPoints  = $conn->prepare($queryPoints);
    $stmtPoints->execute([':id' => $user_id]);
    $rowPoints = $stmtPoints->fetch(PDO::FETCH_ASSOC);
    if ($rowPoints) {
        $userPoints = $rowPoints['poin'];
        $ttlVoucher = $rowPoints['ttl_voucher'];
    }
} catch (PDOException $e) {
    die("Terjadi kesalahan saat mengambil poin pengguna: " . $e->getMessage());
}

// --- [3] Penanganan penukaran (redeem) Reward ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_reward'])) {
    // Cek apakah permintaan dilakukan via AJAX
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        // Jika bukan AJAX, arahkan kembali
        header("Location: reward.php");
        exit;
    }

    // Ambil id reward yang dipilih
    $chosenRewardId = $_POST['id_reward'];

    // Ambil informasi reward terpilih
    try {
        $queryReward  = "SELECT * FROM rewards WHERE id_reward = :id_reward LIMIT 1";
        $stmtReward   = $conn->prepare($queryReward);
        $stmtReward->execute([':id_reward' => $chosenRewardId]);
        $rewardData   = $stmtReward->fetch(PDO::FETCH_ASSOC);

        if ($rewardData) {
            $requiredPoints = $rewardData['point_cost'];
            $rewardType     = strtolower(trim($rewardData['tipe_reward'])); // 'voucher' atau 'sembako'
            $currentStok    = $rewardData['stok'];

            // Cek apakah user punya poin yang cukup dan stok tersedia
            if ($userPoints >= $requiredPoints && $currentStok > 0) {
                // Mulai transaksi
                $conn->beginTransaction();
                try {
                    // Kurangi poin user
                    $queryUpdatePoints = "UPDATE users 
                                          SET poin = poin - :required_points 
                                          WHERE id = :user_id";
                    $stmtUpdatePoints  = $conn->prepare($queryUpdatePoints);
                    $stmtUpdatePoints->execute([
                        ':required_points' => $requiredPoints,
                        ':user_id'         => $user_id
                    ]);

                    // Kurangi stok reward
                    $queryUpdateStok = "UPDATE rewards 
                                        SET stok = stok - 1 
                                        WHERE id_reward = :id_reward AND stok > 0";
                    $stmtUpdateStok  = $conn->prepare($queryUpdateStok);
                    $stmtUpdateStok->execute([
                        ':id_reward' => $chosenRewardId
                    ]);

                    // Jika reward bertipe Voucher, tambahkan ttl_voucher
                    if ($rewardType === 'voucher') {
                        $queryUpdateVoucher = "UPDATE users 
                                               SET ttl_voucher = ttl_voucher + 1 
                                               WHERE id = :user_id";
                        $stmtUpdateVoucher  = $conn->prepare($queryUpdateVoucher);
                        $stmtUpdateVoucher->execute([
                            ':user_id' => $user_id
                        ]);
                    }

                    // Catat ke tabel penukaran
                    $queryTransaction = "INSERT INTO penukaran (id_user, id_reward, tgl_tukar, status) 
                                         VALUES (:id_user, :id_reward, NOW(), 'Tersedia')";
                    $stmtTransaction = $conn->prepare($queryTransaction);
                    $stmtTransaction->execute([
                        ':id_user'   => $user_id,
                        ':id_reward' => $chosenRewardId
                    ]);

                    // Commit transaksi
                    $conn->commit();

                    // Kembalikan respons JSON sukses
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Penukaran poin sebesar ' . number_format($requiredPoints, 0, ',', '.') . ' berhasil.',
                        'icon' => 'check_circle' // Material Icons name for green check
                    ]);
                    exit;
                } catch (Exception $ex) {
                    // Rollback jika ada error
                    $conn->rollBack();
                    // Kembalikan respons JSON error
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Terjadi kesalahan saat menukar poin: ' . $ex->getMessage(),
                        'icon' => 'error' // Material Icons name for red cross
                    ]);
                    exit;
                }
            } else {
                // Menentukan alasan kegagalan
                $errors = [];
                if ($userPoints < $requiredPoints) {
                    $errors[] = 'Maaf poin anda tidak cukup.';
                }
                if ($currentStok <= 0) {
                    $errors[] = 'Stok reward ini sudah habis.';
                }

                // Gabungkan pesan error
                $errorMessage = implode(' ', $errors);

                // Kembalikan respons JSON error
                echo json_encode([
                    'status' => 'error',
                    'message' => $errorMessage,
                    'icon' => 'error' // Material Icons name for red cross
                ]);
                exit;
            }
        } else {
            // Reward tidak ditemukan
            echo json_encode([
                'status' => 'error',
                'message' => 'Reward tidak ditemukan.',
                'icon' => 'error' // Material Icons name for red cross
            ]);
            exit;
        }
    } catch (PDOException $e) {
        // Kembalikan respons JSON error
        echo json_encode([
            'status' => 'error',
            'message' => 'Terjadi kesalahan saat mengambil data reward: ' . $e->getMessage(),
            'icon' => 'error' // Material Icons name for red cross
        ]);
        exit;
    }
}

// --- [4] Ambil Data Voucher, Sembako, dan Rekomendasi ---
try {
    // Query Voucher
    $queryVoucher = "SELECT * FROM rewards WHERE LOWER(TRIM(tipe_reward)) = 'voucher'";
    $stmtVoucher  = $conn->prepare($queryVoucher);
    $stmtVoucher->execute();
    $voucherResult = $stmtVoucher->fetchAll(PDO::FETCH_ASSOC);

    // Query Sembako
    $querySembako = "SELECT * FROM rewards WHERE LOWER(TRIM(tipe_reward)) = 'sembako'";
    $stmtSembako  = $conn->prepare($querySembako);
    $stmtSembako->execute();
    $sembakoResult = $stmtSembako->fetchAll(PDO::FETCH_ASSOC);

    // Query Rekomendasi (Sembako dengan limit 6, case-insensitive)
    $queryRekomendasi = "SELECT * FROM rewards 
                         ORDER BY point_cost ASC 
                         LIMIT 6";
    $stmtRekomendasi = $conn->prepare($queryRekomendasi);
    $stmtRekomendasi->execute();
    $rekomendasiResult = $stmtRekomendasi->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Terjadi kesalahan saat mengambil data rewards: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reward - RefreshOil</title>
  <link href="../gambar/logo.png" rel="shortcut icon">
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome CDN -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet"/>
  <!-- Material Icons -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons"/>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
    }
    .carousel-container {
      position: relative;
      overflow: hidden;
    }
    .carousel-slide {
      display: flex;
      transition: transform 0.5s ease-in-out;
    }
    .carousel-item {
      min-width: 100%;
      box-sizing: border-box;
    }
    .btn-active {
      background-color: #fbbf24;
      color: white;
    }
    .btn-inactive {
      background-color: #e5e7eb;
      color: #374151;
    }
  </style>
</head>

<body class="bg-gray-100">
  <div class="min-h-screen flex flex-col">
    <div class="flex flex-1">
      <!-- SIDEBAR -->
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
                      <a href="reward.php" class="flex items-center px-4 py-3 bg-yellow-400 text-gray-700 rounded-lg mb-2">
                          <i class="fas fa-gift mr-3"></i>Reward
                      </a>
                      <a href="riwayat.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
                          <i class="fas fa-calendar-alt mr-3"></i>Riwayat
                      </a>
                      <a href="edukasi.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
                          <i class="fas fa-book mr-3"></i>Edukasi
                      </a>
                      <!-- Tambahkan Link Logout jika Diperlukan -->
                      <!-- <a href="#" class="logoutLink flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
                          <i class="fas fa-sign-out-alt mr-3"></i>Logout
                      </a> -->
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
      
      <!-- Modal Konfirmasi Penukaran -->
      <div id="confirmModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-lg p-6 w-80">
          <h3 class="text-lg font-semibold mb-4">Konfirmasi Penukaran</h3>
          <p>Yakin ingin menukar poin dengan reward ini?</p>
          <div class="mt-6 flex justify-end space-x-4">
            <button id="cancelConfirmBtn" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">Tidak</button>
            <button id="yesConfirmBtn" class="px-4 py-2 bg-yellow-400 text-white rounded hover:bg-yellow-500">Iya</button>
          </div>
        </div>
      </div>

      <!-- Modal Notifikasi -->
      <div id="notificationModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-lg p-6 w-80 flex flex-col items-center">
          <span id="notificationIcon" class="material-icons text-4xl mb-4"></span>
          <p id="notificationMessage" class="text-center mb-6"></p>
          <button id="closeNotificationBtn" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">Tutup</button>
        </div>
      </div>

      <!-- MAIN CONTENT -->
      <div class="flex-1 p-6 space-y-6 ml-0 md:ml-64">
        <!-- CAROUSEL SECTION -->
        <div class="carousel-container relative rounded-xl overflow-hidden shadow-lg">
          <div class="carousel-slide">
            <div class="carousel-item flex items-center justify-center">
              <img src="../gambar/poster1.png" alt="Poster 1" class="w-full h-auto object-cover"/>
            </div>
            <div class="carousel-item flex items-center justify-center">
              <img src="../gambar/poster2.png" alt="Poster 2" class="w-full h-auto object-cover"/>
            </div>
            <div class="carousel-item flex items-center justify-center">
              <img src="../gambar/poster3.png" alt="Poster 3" class="w-full h-auto object-cover"/>
            </div>
          </div>
          <!-- Menghilangkan tombol kontrol carousel -->
        </div>
        
        <!-- CARD SECTION -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- Total Poin Card -->
          <div class="bg-white rounded-xl shadow-md p-4 flex flex-col justify-between hover:shadow-xl transition-shadow duration-200">
            <div class="flex items-center justify-center flex-grow space-x-4">
              <div class="flex flex-col items-center">
                <p class="text-gray-600 text-sm">Total Poin</p>
                <!-- Tampilkan poin user dari database -->
                <h2 class="text-2xl font-extrabold text-gray-800">
                  <?php echo number_format($userPoints, 0, ',', '.'); ?>
                </h2>
              </div>
              <div class="bg-white border border-gray-300 rounded-lg p-1 flex flex-col items-center shadow hover:shadow-md transition">
                <button class="bg-yellow-400 text-white rounded-full w-8 h-8 flex items-center justify-center shadow-md hover:bg-yellow-500 transition">
                  <span class="material-icons text-sm">add</span>
                </button>
              </div>
            </div>
            <button id="donasiBtn" class="mt-4 bg-yellow-400 text-white px-4 py-1.5 w-full rounded-lg hover:bg-yellow-500 transition-colors duration-200">
              Donasi
            </button>
          </div>
          
          <!-- Voucher Owned Card -->
          <div class="bg-white rounded-xl shadow-md p-4 flex flex-col justify-between hover:shadow-lg transition-shadow duration-200">
            <div class="flex flex-col items-center flex-grow">
              <div class="bg-yellow-400 w-10 h-10 rounded-full flex items-center justify-center mb-2 shadow-md">
                <span class="material-icons text-white text-lg">card_giftcard</span>
              </div>
              <p class="text-gray-700 font-semibold text-sm">Voucher Dimiliki</p>
              <h2 class="text-2xl font-bold text-yellow-600">
                <?php echo htmlspecialchars($ttlVoucher); ?>
              </h2>
            </div>
            <button id="lihatBtn" class="mt-4 bg-yellow-400 text-white px-4 py-1.5 w-full rounded-lg hover:bg-yellow-500 transition-colors duration-200">
              Lihat
            </button>
          </div>
          
          <!-- Voucher Used Card -->
          <div class="bg-white rounded-xl shadow-md p-4 flex items-center justify-center hover:shadow-lg transition-shadow duration-200">
            <div class="flex flex-col items-center">
              <div class="bg-gray-400 w-10 h-10 rounded-full flex items-center justify-center mb-2 shadow-md">
                <span class="material-icons text-white text-lg">history</span>
              </div>
              <p class="text-gray-700 font-semibold text-sm">Voucher Digunakan</p>
              <h2 class="text-2xl font-bold text-gray-600">0</h2>
            </div>
          </div>
        </div>
        
        <!-- SPECIAL OFFER SECTION -->
        <div>
          <h2 class="text-2xl font-bold mb-4">Kami sediakan yang istimewa untukmu</h2>
          <div class="flex flex-wrap md:flex-nowrap md:space-x-4 space-y-3 md:space-y-0 mb-6">
            <button id="rekomendasiBtn" class="btn-active px-4 py-1.5 rounded-xl transition-colors duration-200">Rekomendasi</button>
            <button id="voucherBtn" class="btn-inactive px-4 py-1.5 rounded-xl transition-colors duration-200">Voucher</button>
            <button id="sembakoBtn" class="btn-inactive px-4 py-1.5 rounded-xl transition-colors duration-200">Sembako</button>
          </div>
          
          <!-- Rekomendasi Section -->
          <div id="rekomendasiSection" class="mt-12 bg-white p-6 rounded-2xl shadow-lg flex-shrink-0">
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-xl font-semibold text-gray-800">Rekomendasi Untuk Anda</h3>
            </div>
            <div class="flex flex-wrap gap-6 pb-4">
              <?php if (!empty($rekomendasiResult)): ?>
                <?php foreach ($rekomendasiResult as $row): ?>
                  <div class="bg-gray-100 rounded-xl overflow-hidden transition-transform duration-300 hover:scale-95 flex flex-col items-center p-3 max-w-xs w-full">
                    <img 
                      src="<?php echo htmlspecialchars($row['foto_reward']); ?>" 
                      alt="<?php echo htmlspecialchars($row['nama_reward']); ?>" 
                      class="w-full h-20 object-cover rounded-lg"
                    >
                    <p class="mt-2 text-xs text-black text-center">
                      <?php echo htmlspecialchars($row['nama_reward']); ?>
                    </p>
                    
                    <!-- Menampilkan poin reward -->
                    <div class="mt-1 bg-yellow-300 text-black font-semibold text-center rounded-md px-2 py-1">
                      <?php echo number_format($row['point_cost'], 0, ',', '.'); ?> Poin
                    </div>

                    <!-- Menampilkan stok reward -->
                    <p class="mt-2 text-xs text-black text-center">
                      Stok: <?php echo htmlspecialchars($row['stok']); ?>
                    </p>

                    <!-- Form penukaran (redeem) -->
                    <form method="POST" action="" class="mt-2 w-full redeemForm">
                      <input type="hidden" name="id_reward" value="<?php echo htmlspecialchars($row['id_reward']); ?>">
                      <button 
                        type="button" 
                        class="bg-yellow-400 text-white px-2 py-1 rounded-lg hover:bg-yellow-500 w-full text-sm btnTukar"
                        data-reward-name="<?php echo htmlspecialchars($row['nama_reward']); ?>"
                        data-point-cost="<?php echo htmlspecialchars($row['point_cost']); ?>"
                        data-reward-type="<?php echo htmlspecialchars($row['tipe_reward']); ?>"
                      >
                        Tukar
                      </button>
                    </form>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p class="text-gray-500">Belum ada rekomendasi rewards tersedia.</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Voucher Section -->
          <div id="voucherSection" class="mt-12 bg-white p-6 rounded-2xl shadow-lg flex-shrink-0 hidden"> <!-- Hidden by default -->
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-xl font-semibold text-gray-800">Voucher Untuk Anda</h3>
            </div>
            <div class="flex flex-wrap gap-6 pb-4">
              <?php if (!empty($voucherResult)): ?>
                <?php foreach ($voucherResult as $row): ?>
                  <div class="bg-gray-100 rounded-xl overflow-hidden transition-transform duration-300 hover:scale-95 flex flex-col items-center p-3 max-w-xs w-full">
                    <img 
                      src="<?php echo htmlspecialchars($row['foto_reward']); ?>" 
                      alt="<?php echo htmlspecialchars($row['nama_reward']); ?>" 
                      class="w-full h-20 object-cover rounded-lg"
                    >
                    <!-- Menampilkan poin reward -->
                    <div class="mt-1 bg-yellow-300 text-black font-semibold text-center rounded-md px-2 py-1">
                      <?php echo number_format($row['point_cost'], 0, ',', '.'); ?> Poin
                    </div>

                    <!-- Menampilkan stok reward -->
                    <p class="mt-2 text-xs text-black text-center">
                      Stok: <?php echo htmlspecialchars($row['stok']); ?>
                    </p>

                    <!-- Form penukaran (redeem) -->
                    <form method="POST" action="" class="mt-2 w-full redeemForm">
                      <input type="hidden" name="id_reward" value="<?php echo htmlspecialchars($row['id_reward']); ?>">
                      <button 
                        type="button" 
                        class="bg-yellow-400 text-white px-2 py-1 rounded-lg hover:bg-yellow-500 w-full text-sm btnTukar"
                        data-reward-name="<?php echo htmlspecialchars($row['nama_reward']); ?>"
                        data-point-cost="<?php echo htmlspecialchars($row['point_cost']); ?>"
                        data-reward-type="<?php echo htmlspecialchars($row['tipe_reward']); ?>"
                      >
                        Tukar
                      </button>
                    </form>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p class="text-gray-500">Belum ada Voucher tersedia.</p>
              <?php endif; ?>
            </div>
          </div>
          
          <!-- Sembako Section -->
          <div id="sembakoSection" class="mt-12 bg-white p-6 rounded-2xl shadow-lg flex-shrink-0 hidden"> <!-- Hidden by default -->
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-xl font-semibold text-gray-800">Sembako Untuk Anda</h3>
            </div>
            <div class="flex flex-wrap gap-6 pb-4">
              <?php if (!empty($sembakoResult)): ?>
                <?php foreach ($sembakoResult as $row): ?>
                  <div class="bg-gray-100 rounded-xl overflow-hidden transition-transform duration-300 hover:scale-95 flex flex-col items-center p-3 max-w-xs w-full">
                    <div class="w-full h-20 bg-gray-300 rounded-lg overflow-hidden flex items-center justify-center">
                      <img 
                        src="<?php echo htmlspecialchars($row['foto_reward']); ?>" 
                        alt="<?php echo htmlspecialchars($row['nama_reward']); ?>" 
                        class="w-full h-full object-cover"
                      >
                    </div>
                    <p class="mt-2 text-xs text-black text-center">
                      <?php echo htmlspecialchars($row['nama_reward']); ?>
                    </p>
                    
                    <!-- Menampilkan poin reward -->
                    <div class="mt-1 bg-yellow-400 text-black font-semibold text-center rounded-md px-2 py-1">
                      <?php echo number_format($row['point_cost'], 0, ',', '.'); ?> Poin
                    </div>

                    <!-- Menampilkan stok reward -->
                    <p class="mt-2 text-xs text-black text-center">
                      Stok: <?php echo htmlspecialchars($row['stok']); ?>
                    </p>

                    <!-- Form penukaran (redeem) -->
                    <form method="POST" action="" class="mt-2 w-full redeemForm">
                      <input type="hidden" name="id_reward" value="<?php echo htmlspecialchars($row['id_reward']); ?>">
                      <button 
                        type="button" 
                        class="bg-yellow-400 text-white px-2 py-1 rounded-lg hover:bg-yellow-500 w-full text-sm btnTukar"
                        data-reward-name="<?php echo htmlspecialchars($row['nama_reward']); ?>"
                        data-point-cost="<?php echo htmlspecialchars($row['point_cost']); ?>"
                        data-reward-type="<?php echo htmlspecialchars($row['tipe_reward']); ?>"
                      >
                        Tukar
                      </button>
                    </form>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p class="text-gray-500">Belum ada Sembako tersedia.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Pop-up Modals -->
    <!-- Donasi Modal -->
    <div id="donasiModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white p-6 rounded-2xl shadow-lg w-80">
        <h3 class="text-xl font-semibold mb-4">Formulir Donasi Poin</h3>
        <form action="donasi.php" method="POST"> <!-- Ganti dengan skrip penanganan donasi -->
          <div class="mb-4">
            <label for="jumlahPoin" class="block text-gray-700 text-sm font-bold mb-2">Jumlah Poin</label>
            <input type="number" id="jumlahPoin" name="jumlahPoin" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Masukkan jumlah poin" required>
          </div>
          <button type="submit" class="bg-yellow-400 text-white px-4 py-2 rounded-lg hover:bg-yellow-500 transition-colors duration-200">Kirim</button>
        </form>
        <button id="closeModalBtn" class="mt-4 bg-gray-400 text-white px-4 py-1.5 w-full rounded-lg hover:bg-gray-500 transition-colors duration-200">Tutup</button>
      </div>
    </div>
    
    <!-- Voucher Modal (Contoh pop-up statis) -->
    <div id="voucherModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white p-6 rounded-2xl shadow-lg w-80">
        <div class="flex justify-between items-center mb-6">
          <h3 class="text-xl font-semibold text-gray-800">Voucher Untuk Anda</h3>
        </div>
        <div class="flex flex-wrap gap-6 pb-4">
          <!-- Contoh statis -->
          <div class="bg-gray-100 rounded-xl overflow-hidden transition-transform duration-300 hover:scale-95 flex flex-col items-center p-3 max-w-xs w-full">
            <img src="../gambar/vou1.jpg" alt="Voucher 1" class="w-full h-20 object-cover rounded-lg">
            <p class="mt-2 text-xs text-black text-center">Potongan biaya kirim</p>
            <div class="mt-1 bg-yellow-300 text-black font-semibold text-center rounded-md px-2 py-1">
              688 Poin
            </div>
          </div>
          <div class="bg-gray-100 rounded-xl overflow-hidden transition-transform duration-300 hover:scale-95 flex flex-col items-center p-3 max-w-xs w-full">
            <img src="../gambar/vou2.jpg" alt="Voucher 2" class="w-full h-20 object-cover rounded-lg">
            <p class="mt-2 text-xs text-black text-center">Potongan biaya kirim</p>
            <div class="mt-1 bg-yellow-300 text-black font-semibold text-center rounded-md px-2 py-1">
              688 Poin
            </div>
          </div>
        </div>
        <button id="closeVoucherBtn" class="mt-4 bg-yellow-400 text-white px-4 py-1.5 w-full rounded-lg hover:bg-yellow-500 transition-colors duration-200">
          Tutup
        </button>
      </div>
    </div>

    <!-- FOOTER -->
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
  </div>
  
  <!-- SCRIPTS -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // CAROUSEL FUNCTIONALITY
      const carouselSlide = document.querySelector('.carousel-slide');
      const carouselItems = document.querySelectorAll('.carousel-item');
      let counter = 0;
      let size = carouselItems[0].clientWidth;

      // Clone first and last items for infinite effect
      const firstClone = carouselItems[0].cloneNode(true);
      const lastClone = carouselItems[carouselItems.length - 1].cloneNode(true);
      firstClone.id = 'firstClone';
      lastClone.id = 'lastClone';
      carouselSlide.appendChild(firstClone);
      carouselSlide.insertBefore(lastClone, carouselItems[0]);
      carouselSlide.style.transform = 'translateX(' + (-size * (counter + 1)) + 'px)';

      // Update carouselItems after cloning
      const updatedCarouselItems = document.querySelectorAll('.carousel-item');

      // Transition End
      carouselSlide.addEventListener('transitionend', () => {
        if (updatedCarouselItems[counter + 1].id === 'firstClone') {
          carouselSlide.style.transition = 'none';
          counter = 0;
          carouselSlide.style.transform = 'translateX(' + (-size * (counter + 1)) + 'px)';
        }
        if (updatedCarouselItems[counter + 1].id === 'lastClone') {
          carouselSlide.style.transition = 'none';
          counter = updatedCarouselItems.length - 3;
          carouselSlide.style.transform = 'translateX(' + (-size * (counter + 1)) + 'px)';
        }
      });

      // AUTOMATIC SLIDE
      let autoSlide = setInterval(() => {
        counter++;
        carouselSlide.style.transition = 'transform 0.5s ease-in-out';
        carouselSlide.style.transform = 'translateX(' + (-size * (counter + 1)) + 'px)';
      }, 3000);

      // PAUSE SLIDE ON HOVER
      const carouselContainer = document.querySelector('.carousel-container');
      carouselContainer.addEventListener('mouseenter', () => {
        clearInterval(autoSlide);
      });
      carouselContainer.addEventListener('mouseleave', () => {
        autoSlide = setInterval(() => {
          counter++;
          carouselSlide.style.transition = 'transform 0.5s ease-in-out';
          carouselSlide.style.transform = 'translateX(' + (-size * (counter + 1)) + 'px)';
        }, 3000);
      });

      // SPECIAL OFFER BUTTON FUNCTIONALITY
      const rekomendasiBtn = document.getElementById('rekomendasiBtn');
      const voucherBtn = document.getElementById('voucherBtn');
      const sembakoBtn = document.getElementById('sembakoBtn');
      const rekomendasiSection = document.getElementById('rekomendasiSection');
      const voucherSection = document.getElementById('voucherSection');
      const sembakoSection = document.getElementById('sembakoSection');

      function toggleSections(buttonClicked) {
        // Reset semua button ke inactive
        rekomendasiBtn.classList.remove('btn-active');
        rekomendasiBtn.classList.add('btn-inactive');
        voucherBtn.classList.remove('btn-active');
        voucherBtn.classList.add('btn-inactive');
        sembakoBtn.classList.remove('btn-active');
        sembakoBtn.classList.add('btn-inactive');

        // Sembunyikan semua section
        rekomendasiSection.classList.add('hidden');
        voucherSection.classList.add('hidden');
        sembakoSection.classList.add('hidden');

        // Atur tampilan section dan aktivasi button yang diklik
        if (buttonClicked === 'Rekomendasi') {
          rekomendasiSection.classList.remove('hidden');
          rekomendasiBtn.classList.remove('btn-inactive');
          rekomendasiBtn.classList.add('btn-active');
        } else if (buttonClicked === 'Voucher') {
          voucherSection.classList.remove('hidden');
          voucherBtn.classList.remove('btn-inactive');
          voucherBtn.classList.add('btn-active');
        } else if (buttonClicked === 'Sembako') {
          sembakoSection.classList.remove('hidden');
          sembakoBtn.classList.remove('btn-inactive');
          sembakoBtn.classList.add('btn-active');
        }
      }

      rekomendasiBtn.addEventListener('click', () => toggleSections('Rekomendasi'));
      voucherBtn.addEventListener('click', () => toggleSections('Voucher'));
      sembakoBtn.addEventListener('click', () => toggleSections('Sembako'));

      // Inisialisasi halaman dengan Rekomendasi terbuka
      toggleSections('Rekomendasi');

      // DONASI MODAL FUNCTIONALITY
      const donasiBtn = document.getElementById('donasiBtn');
      const donasiModal = document.getElementById('donasiModal');
      const closeModalBtn = document.getElementById('closeModalBtn');

      donasiBtn.addEventListener('click', () => {
        donasiModal.classList.remove('hidden');
      });

      closeModalBtn.addEventListener('click', () => {
        donasiModal.classList.add('hidden');
      });

      // VOUCHER MODAL FUNCTIONALITY (Contoh pop-up statis)
      const lihatBtn = document.getElementById('lihatBtn');
      const voucherModal = document.getElementById('voucherModal');
      const closeVoucherBtn = document.getElementById('closeVoucherBtn');

      lihatBtn.addEventListener('click', () => {
        voucherModal.classList.remove('hidden');
      });

      closeVoucherBtn.addEventListener('click', () => {
        voucherModal.classList.add('hidden');
      });

      // RESPONSIVE FOOTER ADJUSTMENT
      window.addEventListener('resize', () => {
        size = carouselItems[0].clientWidth;
        carouselSlide.style.transform = 'translateX(' + (-size * (counter + 1)) + 'px)';
      });

      // MODAL KONFIRMASI PENUKARAN FUNCTIONALITY
      const btnTukar = document.querySelectorAll('.btnTukar');
      const confirmModalElement = document.getElementById('confirmModal');
      const cancelConfirmBtn = document.getElementById('cancelConfirmBtn');
      const yesConfirmBtn = document.getElementById('yesConfirmBtn');

      const notificationModal = document.getElementById('notificationModal');
      const notificationIcon = document.getElementById('notificationIcon');
      const notificationMessage = document.getElementById('notificationMessage');
      const closeNotificationBtn = document.getElementById('closeNotificationBtn');

      let selectedRewardId = null;
      let selectedRewardName = '';
      let selectedPointCost = 0;
      let selectedRewardType = '';

      btnTukar.forEach(button => {
        button.addEventListener('click', () => {
          selectedRewardId = button.parentElement.querySelector('input[name="id_reward"]').value;
          selectedRewardName = button.getAttribute('data-reward-name');
          selectedPointCost = button.getAttribute('data-point-cost');
          selectedRewardType = button.getAttribute('data-reward-type');
          console.log('Tukar diklik:', selectedRewardId, selectedRewardName, selectedPointCost, selectedRewardType);
          // Tampilkan modal konfirmasi
          confirmModalElement.classList.remove('hidden');
        });
      });

      // Tutup modal konfirmasi saat klik "Tidak"
      cancelConfirmBtn.addEventListener('click', () => {
        confirmModalElement.classList.add('hidden');
        selectedRewardId = null;
        selectedRewardName = '';
        selectedPointCost = 0;
        selectedRewardType = '';
        console.log('Konfirmasi dibatalkan.');
      });

      // Saat klik "Iya", lakukan AJAX penukaran
      yesConfirmBtn.addEventListener('click', () => {
        if (!selectedRewardId) {
          console.log('Tidak ada reward yang dipilih.');
          return;
        }

        console.log('Mengirim AJAX request untuk reward ID:', selectedRewardId);

        // Kirim AJAX request
        fetch('reward.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest' // Menandai sebagai AJAX
          },
          body: new URLSearchParams({
            'id_reward': selectedRewardId
          })
        })
        .then(response => response.json())
        .then(data => {
          console.log('Respons dari server:', data);
          confirmModalElement.classList.add('hidden'); // Tutup modal konfirmasi
          if (data.status === 'success') {
            // Tampilkan modal notifikasi sukses
            notificationIcon.textContent = data.icon;
            notificationIcon.classList.add('text-green-500'); // Warna hijau
            notificationMessage.textContent = data.message;
            notificationModal.classList.remove('hidden');
            
            // Update poin dan ttl_voucher di halaman tanpa reload
            // Ambil elemen poin dan voucher
            const poinElement = document.querySelector('h2.text-2xl.font-extrabold.text-gray-800');
            const voucherElement = document.querySelector('h2.text-2xl.font-bold.text-yellow-600');
            
            // Kurangi poin
            const currentPoin = parseInt(poinElement.textContent.replace(/\./g, '')) || 0;
            const newPoin = currentPoin - parseInt(selectedPointCost);
            poinElement.textContent = newPoin.toLocaleString('id-ID');

            // Tambah ttl_voucher jika reward adalah voucher
            if (selectedRewardType.toLowerCase() === 'voucher') {
              const currentVoucher = parseInt(voucherElement.textContent) || 0;
              const newVoucher = currentVoucher + 1;
              voucherElement.textContent = newVoucher;
            }

          } else if (data.status === 'error') {
            // Tampilkan modal notifikasi error
            notificationIcon.textContent = data.icon;
            notificationIcon.classList.add('text-red-500'); // Warna merah
            notificationMessage.textContent = data.message;
            notificationModal.classList.remove('hidden');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          // Tampilkan modal notifikasi error
          notificationIcon.textContent = 'error';
          notificationIcon.classList.add('text-red-500'); // Warna merah
          notificationMessage.textContent = 'Terjadi kesalahan saat melakukan penukaran.';
          notificationModal.classList.remove('hidden');
        });

        // Reset pilihan setelah AJAX
        selectedRewardId = null;
        selectedRewardName = '';
        selectedPointCost = 0;
        selectedRewardType = '';
      });

      // Tutup modal notifikasi saat klik "Tutup"
      closeNotificationBtn.addEventListener('click', () => {
        notificationModal.classList.add('hidden');
        notificationIcon.classList.remove('text-green-500', 'text-red-500');
        notificationIcon.textContent = ''; // Reset ikon
        notificationMessage.textContent = ''; // Reset pesan
        console.log('Notifikasi ditutup.');
      });

      // Tambahkan fungsi untuk menghapus kelas warna ikon saat modal ditutup
      notificationModal.addEventListener('click', (e) => {
        if (e.target === notificationModal) {
          notificationModal.classList.add('hidden');
          notificationIcon.classList.remove('text-green-500', 'text-red-500');
          notificationIcon.textContent = ''; // Reset ikon
          notificationMessage.textContent = ''; // Reset pesan
          console.log('Notifikasi modal diklik di luar konten.');
        }
      });

      // Tambahkan keyboard support untuk konfirmasi modal dan notifikasi modal
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          // Tutup confirmModal jika terbuka
          if (!confirmModalElement.classList.contains('hidden')) {
            confirmModalElement.classList.add('hidden');
            selectedRewardId = null;
            selectedRewardName = '';
            selectedPointCost = 0;
            selectedRewardType = '';
            console.log('Confirm modal ditutup dengan Escape.');
          }
          // Tutup notificationModal jika terbuka
          if (!notificationModal.classList.contains('hidden')) {
            notificationModal.classList.add('hidden');
            notificationIcon.classList.remove('text-green-500', 'text-red-500');
            notificationIcon.textContent = ''; // Reset ikon
            notificationMessage.textContent = ''; // Reset pesan
            console.log('Notification modal ditutup dengan Escape.');
          }
        }
      });
    });
  </script>
</body>
</html>