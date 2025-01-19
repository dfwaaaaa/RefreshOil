<?php
// Start the session
session_start(); // Memulai sesi PHP untuk mengelola data pengguna

// Check if user is not logged in
if (!isset($_SESSION['id_admin'])) { // Memeriksa apakah sesi 'id_admin' ada
    header("Location: loginAdmin.php"); // Jika tidak ada, arahkan ke halaman login
    exit(); // Hentikan eksekusi skrip
}

// Database connection
$db_host = "localhost"; // Nama server database
$db_user = "root";        // Username database
$db_pass = "";            // Password database
$db_name = "refresh_oil";   // Nama database

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass); // Membuat koneksi ke database menggunakan PDO
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Mengatur mode error ke exception

    // Fetch user data
    $stmt = $conn->prepare("SELECT * FROM admin WHERE id_admin = ?"); // Menyiapkan statement untuk mengambil data admin
    $stmt->execute([$_SESSION['id_admin']]); // Menjalankan statement dengan parameter 'id_admin'
    $user = $stmt->fetch(PDO::FETCH_ASSOC); // Mengambil hasil sebagai array asosiatif

    // Handle Deletion if POST request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_artikel'])) { // Memeriksa apakah metode request adalah POST dan 'id_artikel' ada
        // CSRF Protection (Optional but Recommended)
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { // Memeriksa token CSRF
            header("Location: edukasiAdmin.php?error=csrf_invalid"); // Arahkan ke halaman edukasiAdmin dengan pesan error jika token tidak valid
            exit(); // Hentikan eksekusi skrip
        }

        $id_artikel = (int)$_POST['id_artikel']; // Mengambil dan mengonversi 'id_artikel' menjadi integer

        // Prepare and execute deletion
        $stmt = $conn->prepare("DELETE FROM artikel WHERE id_artikel = ?"); // Menyiapkan statement untuk menghapus artikel
        $stmt->execute([$id_artikel]); // Menjalankan statement dengan parameter 'id_artikel'

        // Redirect dengan pesan sukses
        header("Location: edukasiAdmin.php?message=artikel_terhapus"); // Arahkan ke halaman edukasiAdmin dengan pesan sukses
        exit(); // Hentikan eksekusi skrip
    }

    // Retrieve search term if exists
    $search = isset($_GET['search']) ? trim($_GET['search']) : ''; // Mengambil nilai pencarian dari parameter URL jika ada

    // Initialize variables
    $highlight_articles = []; // Inisialisasi array kosong untuk artikel highlight
    $recent_articles = []; // Inisialisasi array kosong untuk artikel terbaru
    $popular_articles = []; // Inisialisasi array kosong untuk artikel populer
    $total_articles = 0; // Inisialisasi jumlah total artikel
    $total_pages = 0; // Inisialisasi jumlah total halaman

    if ($search !== '') { // Jika melakukan pencarian
        // Jika melakukan pencarian, tampilkan hanya hasil pencarian
        // Pagination Setup
        $limit = 10; // Jumlah artikel per halaman
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1; // Mengambil nilai halaman dari parameter URL jika ada
        if ($page < 1) { // Memastikan halaman minimal adalah 1
            $page = 1;
        }
        $offset = ($page - 1) * $limit; // Menghitung offset untuk pagination

        // Fetch total number of articles matching search
        $stmt = $conn->prepare("SELECT COUNT(*) FROM artikel WHERE judul LIKE :search OR konten LIKE :search"); // Menyiapkan statement untuk menghitung total artikel yang cocok dengan pencarian
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR); // Mengikat nilai pencarian ke parameter statement
        $stmt->execute(); // Menjalankan statement
        $total_articles = (int)$stmt->fetchColumn(); // Mengambil jumlah total artikel yang cocok
        $total_pages = ceil($total_articles / $limit); // Menghitung jumlah total halaman

        // Fetch articles matching search dengan pagination
        $stmt = $conn->prepare("SELECT * FROM artikel WHERE judul LIKE :search OR konten LIKE :search ORDER BY created_at DESC LIMIT :limit OFFSET :offset"); // Menyiapkan statement untuk mengambil artikel yang cocok dengan pencarian
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR); // Mengikat nilai pencarian ke parameter statement
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); // Mengikat nilai limit ke parameter statement
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); // Mengikat nilai offset ke parameter statement
        $stmt->execute(); // Menjalankan statement
        $recent_articles = $stmt->fetchAll(PDO::FETCH_ASSOC); // Mengambil semua hasil sebagai array asosiatif
    } else {
        // Jika tidak melakukan pencarian, tampilkan Highlight, Recent, dan Popular Posts

        // Fetch Highlight Berita (3 Artikel Terbaru dengan tipe 'highlight')
        $stmt = $conn->prepare("SELECT * FROM artikel WHERE jenis = 'highlight' ORDER BY tgl_publish DESC LIMIT 3"); // Menyiapkan statement untuk mengambil artikel highlight terbaru
        $stmt->execute(); // Menjalankan statement
        $highlight_articles = $stmt->fetchAll(PDO::FETCH_ASSOC); // Mengambil semua hasil sebagai array asosiatif

        // Pagination Setup
        $limit = 10; // Jumlah artikel per halaman
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1; // Mengambil nilai halaman dari parameter URL jika ada
        if ($page < 1) { // Memastikan halaman minimal adalah 1
            $page = 1;
        }
        $offset = ($page - 1) * $limit; // Menghitung offset untuk pagination

        // Fetch total number of recent articles
        $stmt = $conn->prepare("SELECT COUNT(*) FROM artikel"); // Menyiapkan statement untuk menghitung total artikel terbaru
        $stmt->execute(); // Menjalankan statement
        $total_articles = (int)$stmt->fetchColumn(); // Mengambil jumlah total artikel terbaru
        $total_pages = ceil($total_articles / $limit); // Menghitung jumlah total halaman

        // Fetch Recent Posts (10 Artikel per halaman)
        $stmt = $conn->prepare("SELECT * FROM artikel ORDER BY created_at DESC LIMIT :limit OFFSET :offset"); // Menyiapkan statement untuk mengambil artikel terbaru
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); // Mengikat nilai limit ke parameter statement
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); // Mengikat nilai offset ke parameter statement
        $stmt->execute(); // Menjalankan statement
        $recent_articles = $stmt->fetchAll(PDO::FETCH_ASSOC); // Mengambil semua hasil sebagai array asosiatif

        // Fetch Popular Posts (5 Artikel dengan Views Terbanyak)
        $stmt = $conn->prepare("SELECT * FROM artikel ORDER BY views DESC LIMIT 5"); // Menyiapkan statement untuk mengambil artikel populer berdasarkan views
        $stmt->execute(); // Menjalankan statement
        $popular_articles = $stmt->fetchAll(PDO::FETCH_ASSOC); // Mengambil semua hasil sebagai array asosiatif
    }

    // Generate CSRF Token
    if (empty($_SESSION['csrf_token'])) { // Memeriksa apakah token CSRF belum ada di sesi
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Generate token baru dan simpan di sesi
    }

} catch(PDOException $e) { // Menangani kesalahan PDO
    error_log("Database Error: " . $e->getMessage()); // Mencatat pesan kesalahan ke log
    $error_message = "Terjadi kesalahan pada sistem. Silakan coba beberapa saat lagi."; // Mengatur pesan kesalahan umum
    // Pastikan variabel diinisialisasi meskipun terjadi error
    $highlight_articles = []; // Inisialisasi array kosong untuk artikel highlight
    $recent_articles = []; // Inisialisasi array kosong untuk artikel terbaru
    $popular_articles = []; // Inisialisasi array kosong untuk artikel populer
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edukasi Admin - RefreshOil</title>
  <link href="../gambar/logo.png" rel="shortcut icon">

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <style>
    .font-poppins {
      font-family: 'Poppins', sans-serif;
    }

    /* Dropdown Styles */
    .dropdown {
      position: relative;
      display: inline-block;
      z-index: 10; /* Added z-index */
    }
    .dropdown-content {
      display: none;
      position: absolute;
      right: 0;
      background-color: white;
      min-width: 120px;
      box-shadow: 0px 8px 16px rgba(0,0,0,0.2);
      border-radius: 8px;
      z-index: 1000;
    }
    .dropdown-content a, .dropdown-content button {
      color: black;
      padding: 8px 12px;
      text-decoration: none;
      display: block;
      font-size: 14px;
      background: none;
      border: none;
      width: 100%;
      text-align: left;
      cursor: pointer;
    }
    .dropdown-content a:hover, .dropdown-content button:hover {
      background-color: #f1f1f1;
    }
    .dropdown.show .dropdown-content {
      display: block;
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

    /* Custom CSS untuk Multi-line Truncation dengan Ellipsis */
    .line-clamp-2 {
      display: -webkit-box;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 2; /* Ganti angka ini untuk menyesuaikan jumlah baris */
      overflow: hidden;
    }
  </style>
</head>

<body class="bg-gray-100 font-poppins">
  <!-- Mobile Header -->
  <header class="md:hidden flex items-center justify-between p-4 bg-white shadow-md fixed top-0 left-0 right-0 z-50">
    <div class="flex items-center flex-shrink-0">
      <button id="mobileMenuBtn" class="text-gray-700 focus:outline-none">
          <i class="fas fa-bars fa-2x"></i>
      </button>
      <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="w-24 h-auto ml-3 flex-shrink-0">
    </div>
    <div class="flex items-center space-x-4 flex-shrink-0">
      <div class="dropdown">
        <img src="../gambar/profil.png" alt="User Avatar" class="w-8 h-8 rounded-full" id="mobileProfileBtn">
        <div class="dropdown-content mt-2">
          <a href="profilAdmin.php"><i class="fas fa-user mr-2"></i>Lihat Profil</a>
          <a href="#" id="logoutBtnMobile" class="logoutLink"><i class="fas fa-sign-out-alt mr-2"></i>Keluar</a>
        </div>
      </div>
    </div>
  </header>

  <!-- Sidebar -->
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

  <!-- Logout Modal -->
  <div id="logoutModal" class="modal fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/30 backdrop-blur-sm"></div>
    <div class="bg-white rounded-lg p-6 shadow-xl max-w-sm w-full mx-4 relative z-10">
      <h3 class="text-lg font-medium text-gray-900 mb-4">Yakin ingin logout?</h3>
      <div class="flex justify-end gap-3">
        <button
          id="cancelBtn"
          class="px-4 py-2 text-sm font-medium text-black bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300 transition-colors"
        >Tidak</button>
        <button
          id="confirmBtn"
          class="px-4 py-2 text-sm font-medium text-black bg-yellow-100 rounded-lg hover:bg-yellow-400 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors"
        >Iya</button>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="modal fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/30 backdrop-blur-sm"></div>
      <div class="bg-white rounded-lg p-6 shadow-xl max-w-sm w-full mx-4 relative z-10">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Yakin ingin menghapus artikel ini?</h3>
          <div class="flex justify-end gap-3">
              <button
                  id="cancelDeleteBtn"
                  class="px-4 py-2 text-sm font-medium text-black bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300 transition-colors"
              >Tidak</button>
              <button
                  id="confirmDeleteBtn"
                  class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors"
              >Iya</button>
          </div>
      </div>
  </div>

  <!-- Main Content & Sidebar -->
  <div class="ml-64 max-w-7xl mx-auto px-6 pt-16 md:pt-8">
  
    <!-- Header: Search Bar dan Account -->
    <div class="mb-4 flex items-center justify-between">
      <!-- Search Bar -->
      <div class="relative w-full md:w-1/3">
        <form method="GET" action="edukasiAdmin.php">
          <input type="text" name="search" placeholder="Cari di sini..." value="<?php echo htmlspecialchars($search); ?>" class="w-full border border-gray-300 rounded-full py-2 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-yellow-400">
          <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M16 11a5 5 0 11-10 0 5 5 0 0110 0z"></path>
            </svg>
          </span>
        </form>
      </div>

      <!-- Account -->
      <div class="flex items-center space-x-4">
        <div class="dropdown">
          <button id="desktopProfileBtn" class="focus:outline-none flex items-center">
            <div class="flex flex-col text-right">
              <span class="text-gray-800 font-semibold"><?php echo isset($user['username']) ? htmlspecialchars($user['username']) : ''; ?></span>
              <p class="text-gray-400">Admin</p>
            </div>
            <img src="<?php echo isset($user['profil_img']) ? htmlspecialchars($user['profil_img']) : '../gambar/profil.png'; ?>" alt="User Avatar" class="w-10 h-10 rounded-full ml-3">
          </button>
          <div class="dropdown-content mt-2">
            <a href="profilAdmin.php"><i class="fas fa-user mr-2"></i>Lihat Profil</a>
            <a href="#" id="logoutBtnDesktop" class="logoutLink"><i class="fas fa-sign-out-alt mr-2"></i>Keluar</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Menampilkan Pesan Sukses atau Error -->
    <?php if (isset($_GET['message']) && $_GET['message'] === 'artikel_terhapus'): ?>
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
            Artikel berhasil dihapus.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
            <?php 
                if ($_GET['error'] === 'csrf_invalid') {
                    echo "Token keamanan tidak valid. Silakan coba lagi.";
                } elseif ($_GET['error'] === 'terjadi_kesalahan') {
                    echo "Terjadi kesalahan saat menghapus artikel.";
                } elseif ($_GET['error'] === 'id_tidak_valid') {
                    echo "ID artikel tidak valid.";
                } else {
                    echo "Terjadi kesalahan.";
                }
            ?>
        </div>
    <?php endif; ?>

    <?php if ($search !== ''): ?>
        <!-- Hasil Pencarian -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold mb-4">Hasil Pencarian untuk "<?php echo htmlspecialchars($search); ?>"</h2>
            <?php if (count($recent_articles) > 0): ?>
                <div class="space-y-6">
                    <?php foreach ($recent_articles as $article): ?>
                        <!-- Kartu List Post Kecil dengan Dropdown -->
                        <div class="relative flex items-stretch space-x-4 bg-white shadow-md rounded-lg overflow-hidden">
                            <!-- Dropdown Menu -->
                            <div class="absolute top-2 right-2 dropdown">
                                <button class="text-gray-600 hover:text-gray-800 focus:outline-none articleDropdownBtn p-2 rounded-full hover:bg-gray-200">
                                    <i class="fas fa-ellipsis-v fa-lg"></i>
                                </button>
                                <div class="dropdown-content mt-2">
                                    <a href="editArtikel.php?id_artikel=<?php echo htmlspecialchars($article['id_artikel']); ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit</a>
                                    <a href="#" class="block px-4 py-2 text-sm text-red-700 hover:bg-gray-100 hapusBtn" data-id="<?php echo htmlspecialchars($article['id_artikel']); ?>">Hapus</a>
                                </div>
                            </div>

                            <!-- Gambar Artikel -->
                            <div class="w-32 flex-shrink-0 overflow-hidden relative">
                                <img src="<?php echo htmlspecialchars($article['gambar_artikel']); ?>" alt="<?php echo htmlspecialchars($article['judul']); ?>" class="w-full h-full object-cover">
                                <div class="absolute top-2 left-2 bg-yellow-400 text-black text-xs uppercase font-bold px-2 py-1 rounded">
                                    <?php echo htmlspecialchars($article['tipe']); ?>
                                </div>
                            </div>
                            <div class="p-4 flex-1">
                                <div class="flex items-center space-x-2 mb-2">
                                    <span class="bg-yellow-400 text-black text-xs uppercase font-bold px-2 py-1 rounded">
                                        <?php echo htmlspecialchars($article['tipe']); ?>
                                    </span>
                                    <span class="text-gray-500 text-sm"><?php echo date("F d, Y", strtotime($article['tgl_publish'])); ?></span>
                                </div>
                                <h5 class="text-lg font-semibold text-gray-900 line-clamp-2">
                                    <a href="isiArtikelAdmin.php?id=<?php echo htmlspecialchars($article['id_artikel']); ?>" class="hover:text-yellow-400 transition-colors">
                                        <?php echo htmlspecialchars($article['judul']); ?>
                                    </a>
                                </h5>
                                <p class="text-gray-600 mt-2">
                                    <?php 
                                        // Tampilkan potongan isi artikel (misalnya 150 karakter)
                                        echo htmlspecialchars(substr($article['konten'], 0, 150)) . '...';
                                    ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center text-gray-500">Tidak ada artikel yang sesuai dengan pencarian Anda.</div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-10">
                <nav class="flex space-x-1" aria-label="Pagination">
                    <!-- Tombol Halaman Sebelumnya -->
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(['search' => $search, 'page' => $page - 1]); ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-200 hover:bg-gray-100 hover:text-gray-700 rounded-l-lg">
                            &laquo;
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-2 leading-tight text-gray-400 bg-white border border-gray-200 cursor-not-allowed rounded-l-lg">&laquo;</span>
                    <?php endif; ?>

                    <?php
                        // Menentukan rentang halaman yang akan ditampilkan
                        $range = 2; // Jumlah halaman sebelum dan sesudah halaman saat ini
                        $start = max(1, $page - $range);
                        $end = min($total_pages, $page + $range);

                        // Jika start lebih dari 1, tampilkan ellipsis
                        if ($start > 1) {
                            echo '<a href="?' . http_build_query(['search' => $search, 'page' => 1]) . '" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-200 hover:bg-gray-100 hover:text-gray-700">1</a>';
                            if ($start > 2) {
                                echo '<span class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-200">...</span>';
                            }
                        }

                        // Tampilkan halaman dalam rentang
                        for ($i = $start; $i <= $end; $i++):
                            if ($i == $page):
                    ?>
                                <span class="px-3 py-2 leading-tight text-white bg-blue-600 border border-blue-300 hover:bg-blue-700 hover:text-white"><?php echo $i; ?></span>
                    <?php
                            else:
                    ?>
                                <a href="?<?php echo http_build_query(['search' => $search, 'page' => $i]); ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-200 hover:bg-gray-100 hover:text-gray-700"><?php echo $i; ?></a>
                    <?php
                            endif;
                        endfor;

                        // Jika end kurang dari total_pages, tampilkan ellipsis
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<span class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-200">...</span>';
                            }
                            echo '<a href="?' . http_build_query(['search' => $search, 'page' => $total_pages]) . '" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-200 hover:bg-gray-100 hover:text-gray-700">' . $total_pages . '</a>';
                        }
                    ?>

                    <!-- Tombol Halaman Berikutnya -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(['search' => $search, 'page' => $page + 1]); ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-200 hover:bg-gray-100 hover:text-gray-700 rounded-r-lg">
                            &raquo;
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-2 leading-tight text-gray-400 bg-white border border-gray-200 cursor-not-allowed rounded-r-lg">&raquo;</span>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Tampilan Normal (Tanpa Pencarian) -->

        <!-- Kartu Utama (Highlight Berita) -->
        <div class="slider-container mb-8">
            <div class="slider-wrapper">
                <?php if (count($highlight_articles) > 0): ?>
                    <?php foreach ($highlight_articles as $article): ?>
                    <div class="slider-slide">
                        <div class="relative bg-gray-300 h-64 md:h-96 flex items-center justify-center rounded-lg overflow-hidden">
                            <!-- Gambar Utama -->
                            <img src="<?php echo htmlspecialchars($article['gambar_artikel']); ?>" alt="<?php echo htmlspecialchars($article['judul']); ?>" class="absolute inset-0 w-full h-full object-cover">
                            <!-- Dropdown Menu -->
                            <div class="absolute top-2 right-2 dropdown">
                                <button class="text-gray-600 hover:text-gray-800 focus:outline-none articleDropdownBtn p-2 rounded-full hover:bg-gray-200">
                                    <i class="fas fa-ellipsis-v fa-lg"></i>
                                </button>
                                <div class="dropdown-content mt-2">
                                    <a href="editArtikel.php?id_artikel=<?php echo htmlspecialchars($article['id_artikel']); ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit</a>
                                    <a href="#" class="block px-4 py-2 text-sm text-red-700 hover:bg-gray-100 hapusBtn" data-id="<?php echo htmlspecialchars($article['id_artikel']); ?>">Hapus</a>
                                </div>
                            </div>
                            <!-- Konten Teks di atas gambar -->
                            <div class="absolute bottom-0 left-0 right-0 p-4 bg-black bg-opacity-60 text-white rounded-lg">
                                <div class="flex items-center space-x-2 mb-2">
                                    <span class="bg-yellow-400 text-black px-2 py-1 text-xs uppercase font-bold rounded">
                                        <?php echo htmlspecialchars($article['tipe']); ?>
                                    </span>
                                </div>
                                <h2 class="text-xl font-bold line-clamp-2">
                                    <a href="isiArtikelAdmin.php?id_artikel=<?php echo htmlspecialchars($article['id_artikel']); ?>" class="block hover:text-yellow-400 transition-colors">
                                        <?php echo htmlspecialchars($article['judul']); ?>
                                    </a>
                                </h2>
                                <div class="mt-2 flex items-center text-sm">
                                    <span class="mr-2"><?php echo htmlspecialchars($article['sumber']); ?></span>
                                    <span class="text-gray-400">|</span>
                                    <span class="ml-2"><?php echo date("F d, Y", strtotime($article['tgl_publish'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="slider-slide">
                        <div class="flex items-center justify-center h-64 md:h-96 bg-gray-200 rounded-lg">
                            <span class="text-gray-500">Tidak ada artikel highlight.</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Flex Container: Recent Post dan Popular Post -->
        <div class="flex flex-col md:flex-row gap-4">

            <!-- Recent Post -->
            <div class="flex-1">
                <h4 class="text-2xl font-bold border-b-2 border-black pb-2 mb-6 uppercase">Recent Post</h4>

                <?php if (count($recent_articles) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <?php for ($i = 0; $i < 2 && $i < count($recent_articles); $i++): 
                            $article = $recent_articles[$i];
                        ?>
                        <!-- Kartu Recent Post Besar dengan Dropdown -->
                        <div class="group relative bg-white shadow-md rounded-lg overflow-hidden cursor-pointer transition-transform duration-300 hover:scale-105">
                            <!-- Dropdown Menu -->
                            <div class="absolute top-2 right-2 dropdown">
                                <button class="text-gray-600 hover:text-gray-800 focus:outline-none articleDropdownBtn p-2 rounded-full hover:bg-gray-200">
                                    <i class="fas fa-ellipsis-v fa-lg"></i>
                                </button>
                                <div class="dropdown-content mt-2">
                                    <a href="editArtikel.php?id_artikel=<?php echo htmlspecialchars($article['id_artikel']); ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit</a>
                                    <a href="#" class="block px-4 py-2 text-sm text-red-700 hover:bg-gray-100 hapusBtn" data-id="<?php echo htmlspecialchars($article['id_artikel']); ?>">Hapus</a>
                                </div>
                            </div>

                            <!-- Gambar Artikel -->
                            <div class="overflow-hidden relative">
                                <img src="<?php echo htmlspecialchars($article['gambar_artikel']); ?>" alt="<?php echo htmlspecialchars($article['judul']); ?>" class="w-full h-40 object-cover group-hover:scale-110 transform transition-transform duration-300">
                                <div class="absolute top-2 left-2 bg-yellow-400 text-black text-xs uppercase font-bold px-2 py-1 rounded">
                                    <?php echo htmlspecialchars($article['tipe']); ?>
                                </div>
                            </div>
                            <div class="p-4">
                                <h5 class="text-lg font-semibold text-gray-900 group-hover:text-yellow-700 transition-colors duration-300">
                                    <a href="isiArtikelAdmin.php?id_artikel=<?php echo htmlspecialchars($article['id_artikel']); ?>" class="block">
                                        <?php echo htmlspecialchars($article['judul']); ?>
                                    </a>
                                </h5>
                                <div class="mt-2 flex items-center text-gray-600 text-sm">
                                    <span class="mr-2"><?php echo htmlspecialchars($article['sumber']); ?></span>
                                    <span class="text-gray-400">|</span>
                                    <span class="ml-2"><?php echo date("F d, Y", strtotime($article['tgl_publish'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php for ($i = 2; $i < count($recent_articles); $i++): 
                            $article = $recent_articles[$i];
                        ?>
                        <!-- Kartu List Post Kecil dengan Dropdown -->
                        <div class="space-y-4">
                            <div class="flex items-stretch group cursor-pointer bg-white shadow-md rounded-lg overflow-hidden h-32">
                                <div class="w-32 h-32 flex-shrink-0 overflow-hidden relative">
                                    <img src="<?php echo htmlspecialchars($article['gambar_artikel']); ?>" alt="<?php echo htmlspecialchars($article['judul']); ?>" class="w-full h-full object-cover group-hover:scale-105 transform transition-transform duration-300 rounded-l">
                                    <div class="absolute top-2 left-2 bg-yellow-400 text-black text-xs uppercase font-bold px-2 py-1 rounded">
                                        <?php echo htmlspecialchars($article['tipe']); ?>
                                    </div>
                                </div>
                                <div class="bg-white p-4 rounded-r flex-1 flex flex-col justify-between">
                                    <div class="text-sm text-gray-700 mb-1">
                                        <?php echo htmlspecialchars($article['sumber']); ?> - <?php echo date("F d, Y", strtotime($article['tgl_publish'])); ?>
                                    </div>
                                    <h6 class="font-semibold text-gray-900 line-clamp-2">
                                        <a href="isiArtikel.php?id=<?php echo $article['id_artikel']; ?>" class="block hover:text-yellow-400 transition-colors">
                                            <?php echo htmlspecialchars($article['judul']); ?>
                                        </a>
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-gray-500">Tidak ada artikel terbaru.</div>
                <?php endif; ?>
            </div>

            <!-- Sidebar Popular Post -->
            <div class="w-full md:w-1/3 hidden md:block">
                <div class="sticky top-4 overflow-y-auto max-h-screen mt-4 md:mt-0">
                    <div class="bg-white shadow-md p-4 rounded">
                        <h4 class="text-2xl font-bold border-b-2 border-black pb-2 mb-6 uppercase">Popular Post</h4>
                        <?php if (count($popular_articles) > 0): ?>
                            <div class="space-y-4">
                                <?php foreach ($popular_articles as $index => $article): ?>
                                <div class="flex items-start space-x-4">
                                    <div class="w-8 h-8 aspect-square flex items-center justify-center bg-black text-white font-bold rounded-full">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div>
                                        <span class="inline-block bg-yellow-400 text-white text-xs uppercase font-bold px-2 py-1 rounded mb-1">
                                            <?php echo htmlspecialchars($article['tipe']); ?>
                                        </span>
                                        <h5 class="font-semibold text-sm">
                                            <a href="isiArtikelAdmin.php?id_artikel=<?php echo htmlspecialchars($article['id_artikel']); ?>" class="hover:text-yellow-400 transition-colors">
                                                <?php echo htmlspecialchars($article['judul']); ?>
                                            </a>
                                        </h5>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-gray-500">Tidak ada artikel populer.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    <?php endif; ?>

  </div>

  <!-- Pagination (Tailwind) -->
  <?php if ($search !== '' || $total_pages > 1): ?>
    <div class="flex justify-center mt-10 ml-0 md:ml-64">
      <nav class="flex space-x-1" aria-label="Pagination">
          <?php if ($page > 1): ?>
              <a href="?<?php echo http_build_query(['search' => $search, 'page' => $page - 1]); ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-200 hover:bg-gray-100 hover:text-gray-700 rounded-l-lg">
                  &laquo;
              </a>
          <?php else: ?>
              <span class="px-3 py-2 leading-tight text-gray-400 bg-white border border-gray-200 cursor-not-allowed rounded-l-lg">&laquo;</span>
          <?php endif; ?>

          <?php
              // Menentukan rentang halaman yang akan ditampilkan
              $range = 2; // Jumlah halaman sebelum dan sesudah halaman saat ini
              $start = max(1, $page - $range);
              $end = min($total_pages, $page + $range);

              // Jika start lebih dari 1, tampilkan ellipsis
              if ($start > 1) {
                  echo '<a href="?' . http_build_query(['search' => $search, 'page' => 1]) . '" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-200 hover:bg-gray-100 hover:text-gray-700">1</a>';
                  if ($start > 2) {
                      echo '<span class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-200">...</span>';
                  }
              }

              // Tampilkan halaman dalam rentang
              for ($i = $start; $i <= $end; $i++):
                  if ($i == $page):
          ?>
                      <span class="px-3 py-2 leading-tight text-white bg-blue-600 border border-blue-300 hover:bg-blue-700 hover:text-white"><?php echo $i; ?></span>
          <?php
                  else:
          ?>
                      <a href="?<?php echo http_build_query(['search' => $search, 'page' => $i]); ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-200 hover:bg-gray-100 hover:text-gray-700"><?php echo $i; ?></a>
          <?php
                  endif;
              endfor;

              // Jika end kurang dari total_pages, tampilkan ellipsis
              if ($end < $total_pages) {
                  if ($end < $total_pages - 1) {
                      echo '<span class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-200">...</span>';
                  }
                  echo '<a href="?' . http_build_query(['search' => $search, 'page' => $total_pages]) . '" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-200 hover:bg-gray-100 hover:text-gray-700">' . $total_pages . '</a>';
              }
          ?>

          <!-- Tombol Halaman Berikutnya -->
          <?php if ($page < $total_pages): ?>
              <a href="?<?php echo http_build_query(['search' => $search, 'page' => $page + 1]); ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-200 hover:bg-gray-100 hover:text-gray-700 rounded-r-lg">
                  &raquo;
              </a>
          <?php else: ?>
              <span class="px-3 py-2 leading-tight text-gray-400 bg-white border border-gray-200 cursor-not-allowed rounded-r-lg">&raquo;</span>
          <?php endif; ?>
      </nav>
    </div>
  <?php endif; ?>

  <!-- Footer -->
  <footer class="bg-white ml-64 mt-12">
        <div class="max-w-7xl mx-auto py-8 px-4 text-center">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <a href="dashboardAdmin.php" class="flex items-center">
                    <img src="../gambar/Banner2.png" class="h-8" alt="RefreshOil Logo" />
                </a>
                <span class="text-sm items-end text-gray-500">
                    © <?= date("Y"); ?> <a href="dashboardAdmin.php" class="hover:text-yellow-500">RefreshOil™</a>. All Rights Reserved.
                </span>
            </div>
        </div>
    </footer>

  <!-- Tombol Tambah Artikel -->
  <a href="tambahArtikel.php" class="fixed bottom-6 right-6 bg-yellow-400 text-white w-16 h-16 rounded-full flex items-center justify-center shadow-lg hover:bg-yellow-500 transition-colors">
      <i class="fas fa-plus fa-lg"></i>
  </a>

  <script>
    // Logout Modal
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutModal = document.getElementById('logoutModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const confirmBtn = document.getElementById('confirmBtn');

    function toggleModal(show = true) {
      logoutModal.classList.toggle('active', show);
    }

    if(logoutBtn) {
      logoutBtn.addEventListener('click', (e) => {
        e.preventDefault();
        toggleModal(true);
      });
    }

    const logoutBtnMobile = document.getElementById('logoutBtnMobile');
    if(logoutBtnMobile) {
      logoutBtnMobile.addEventListener('click', (e) => {
        e.preventDefault();
        toggleModal(true);
      });
    }

    const logoutBtnDesktop = document.getElementById('logoutBtnDesktop');
    if(logoutBtnDesktop) {
      logoutBtnDesktop.addEventListener('click', (e) => {
        e.preventDefault();
        toggleModal(true);
      });
    }

    cancelBtn.addEventListener('click', () => toggleModal(false));

    confirmBtn.addEventListener('click', () => {
      // Logika logout
      window.location.href = 'logout.php'; // Pastikan Anda memiliki script logout.php
    });

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

    // Dropdown untuk Artikel
    const articleDropdownButtons = document.querySelectorAll('.articleDropdownBtn');
    const dropdowns = document.querySelectorAll('.dropdown');

    articleDropdownButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation(); // Mencegah event bubbling

            // Tutup semua dropdown kecuali yang sedang diklik
            dropdowns.forEach(dropdown => {
                if (!dropdown.contains(button)) {
                    dropdown.classList.remove('show');
                }
            });

            // Toggle dropdown yang diklik
            const parentDropdown = button.parentElement;
            parentDropdown.classList.toggle('show');
        });
    });

    // Tutup dropdown ketika klik di luar
    document.addEventListener('click', () => {
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    });

    // Optionally, handle ESC key to close all dropdowns
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('show');
            });
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
        sliderWrapper.style.transform = translateX(${translateX}%);
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
    if (sliderContainer) {
      sliderContainer.addEventListener('mouseenter', stopSlider);
      sliderContainer.addEventListener('mouseleave', startSlider);
    }

    // Delete Confirmation Modal
    const deleteModal = document.getElementById('deleteModal');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const hapusButtons = document.querySelectorAll('.hapusBtn');
    
    let artikelIdToDelete = null;

    // Fungsi untuk membuka modal
    function openDeleteModal(id) {
        artikelIdToDelete = id;
        deleteModal.classList.add('active');
    }

    // Fungsi untuk menutup modal
    function closeDeleteModal() {
        artikelIdToDelete = null;
        deleteModal.classList.remove('active');
    }

    // Tambahkan event listener ke setiap tombol "Hapus"
    hapusButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const id = button.getAttribute('data-id');
            openDeleteModal(id);
        });
    });

    // Event listener untuk tombol "Tidak" di modal
    cancelDeleteBtn.addEventListener('click', () => {
        closeDeleteModal();
    });

    // Event listener untuk tombol "Iya" di modal
    confirmDeleteBtn.addEventListener('click', () => {
        if (artikelIdToDelete) {
            // Mengirimkan form POST secara dinamis
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'edukasiAdmin.php';

            // CSRF Token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?php echo $_SESSION['csrf_token']; ?>';
            form.appendChild(csrfInput);

            // ID Artikel
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id_artikel';
            idInput.value = artikelIdToDelete;
            form.appendChild(idInput);

            document.body.appendChild(form);
            form.submit();
        }
    });

    // Tutup modal jika klik di luar konten modal
    deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) {
            closeDeleteModal();
        }
    });

    // Tutup modal dengan tombol ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && deleteModal.classList.contains('active')) {
            closeDeleteModal();
        }
    });
  </script>
</body>
</html>