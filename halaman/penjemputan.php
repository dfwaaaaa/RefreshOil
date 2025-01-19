<?php
session_start();

// Konfigurasi koneksi ke database
$host = "localhost";
$username = "root";
$password = "";
$dbname = "refresh_oil";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Mengatur atribut PDO untuk melemparkan exception saat terjadi error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

$error = "";

// Memeriksa apakah form telah disubmit menggunakan metode POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // isset adalah memeriksa apakah sebuah variabel telah diatur dan tidak bernilai null.
        // Jika session user id tidak diatur / tidak tersedia, maka bernilai true.
        // Maka akan dilempar ke throw
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('User tidak terautentikasi.');
        }
        $id_user = $_SESSION['user_id'];

        // Mengambil dan mengkonversi input 'literMinyak' menjadi float
        if (!isset($_POST['literMinyak']) || empty($_POST['literMinyak'])) {
            throw new Exception('Jumlah liter minyak tidak diisi.');
        }
        $jumlah_liter = floatval($_POST['literMinyak']);

        // Mengambil patokan lokasi jemput
        $patokan = isset($_POST['patokan']) ? trim($_POST['patokan']) : '';
        if (empty($patokan)) {
            throw new Exception('Patokan lokasi tidak diisi.');
        }

        // Mengambil slot waktu jemput
        $waktu_slot = isset($_POST['timeSlot']) ? trim($_POST['timeSlot']) : '';
        if (empty($waktu_slot)) {
            throw new Exception('Slot waktu jemput tidak dipilih.');
        }

        // Mendapatkan tanggal jemput (bisa diganti sesuai kebutuhan)
        $tanggal_jemput = date('Y-m-d');

        // Mendapatkan latitude dan longitude dari POST
        if (!isset($_POST['latitude']) || !isset($_POST['longitude'])) {
            throw new Exception('Koordinat lokasi jemput tidak tersedia.');
        }
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);

        // Validasi latitude dan longitude
        if ($latitude < -90 || $latitude > 90) {
            throw new Exception('Latitude tidak valid.');
        }
        if ($longitude < -180 || $longitude > 180) {
            throw new Exception('Longitude tidak valid.');
        }

        // Menangani unggahan file foto limbah
        $foto_limbah = '';
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            // Pastikan direktori upload ada
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            // Validasi tipe file
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($_FILES['foto']['type'], $allowed_types)) {
                throw new Exception('Tipe file foto tidak diizinkan. Hanya JPEG, PNG, dan GIF yang diperbolehkan.');
            }
            // Buat nama file unik (untuk foto), prefix 'foto_'
            $foto_limbah = $upload_dir . uniqid('foto_') . '_' . basename($_FILES['foto']['name']);
            // Pindahkan file yang diunggah
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $foto_limbah)) {
                throw new Exception('Gagal mengunggah foto.');
            }
        } else {
            throw new Exception('Foto limbah tidak diunggah atau terjadi kesalahan saat unggah.');
        }

        // Menghitung biaya kirim dan poin yang didapat
        $biaya_kirim = 2999; // Biaya per liter
        $subtotal    = $biaya_kirim; // Subtotal biaya berdasarkan jumlah liter
        $kode_unik   = rand(1, 50); // Kode unik antara 1 dan 50
        $total_biaya = $subtotal + $kode_unik; // Total biaya termasuk kode unik

        // Menghitung poin: setiap 1L = 5000 poin
        $poin_didapat = floor($jumlah_liter) * 5000;

        /*
         * ===========================================
         * INSERT langsung ke DB (pakai AUTO_INCREMENT)
         * ===========================================
         */
        $sql = "INSERT INTO penjemputan (
                    id_user,
                    jumlah_liter,
                    foto_limbah,
                    alamat_jemput,      -- Menyimpan latitude
                    detail_lokasi,      -- Menyimpan longitude
                    patokan,
                    waktu_slot,
                    tanggal_jemput,
                    biaya_kirim,
                    subtotal,
                    kode_unik,
                    total_biaya,
                    poin_didapat,
                    status_pembayaran
                ) VALUES (
                    :id_user,
                    :jumlah_liter,
                    :foto_limbah,
                    :alamat_jemput,
                    :detail_lokasi,
                    :patokan,
                    :waktu_slot,
                    :tanggal_jemput,
                    :biaya_kirim,
                    :subtotal,
                    :kode_unik,
                    :total_biaya,
                    :poin_didapat,
                    'pending'
                )";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_user'        => $id_user,
            ':jumlah_liter'   => $jumlah_liter,
            ':foto_limbah'    => $foto_limbah,
            ':alamat_jemput'  => $latitude,    // Menyimpan latitude
            ':detail_lokasi'  => $longitude,   // Menyimpan longitude
            ':patokan'        => $patokan,
            ':waktu_slot'     => $waktu_slot,
            ':tanggal_jemput' => $tanggal_jemput,
            ':biaya_kirim'    => $biaya_kirim,
            ':subtotal'       => $subtotal,
            ':kode_unik'      => $kode_unik,
            ':total_biaya'    => $total_biaya,
            ':poin_didapat'   => $poin_didapat
        ]);

        // Ambil id_penjemputan yang barusan di-insert (INT AUTO_INCREMENT)
        $last_id_penjemputan = $pdo->lastInsertId();

        /*
         * =============================
         * Simpan data ke SESSION
         * =============================
         */
        $_SESSION['penjemputan_data'] = [
            'id_penjemputan' => $last_id_penjemputan, // INT
            'id_user'        => $id_user,
            'jumlah_liter'   => $jumlah_liter,
            'foto_limbah'    => $foto_limbah,
            'alamat_jemput'  => $latitude,    // Menyimpan latitude
            'detail_lokasi'  => $longitude,   // Menyimpan longitude
            'patokan'        => $patokan,
            'waktu_slot'     => $waktu_slot,
            'tanggal_jemput' => $tanggal_jemput,
            'biaya_kirim'    => $biaya_kirim,
            'subtotal'       => $subtotal,
            'kode_unik'      => $kode_unik,
            'total_biaya'    => $total_biaya,
            'poin_didapat'   => $poin_didapat
        ];

        // Setelah menyimpan data ke session, arahkan user ke detailPembayaran.php (atau pembayaran.php)
        header("Location: detailPembayaran.php");
        exit;

    } catch (Exception $e) {
        // Menangani exception dan menyimpan pesan error
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjemputan - RefreshOil</title>
    <link href="../gambar/logo.png" rel="shortcut icon">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
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
        /* Responsive Iframe */
        .map-responsive {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 */
            padding-top: 25px;
            height: 0;
            border: 2px solid #e5e7eb; /* Tailwind gray-200 */
            border-radius: 0.5rem; /* Tailwind rounded-lg */
            overflow: hidden;
        }
        .map-responsive iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border:0;
        }
    </style>
</head>
<body class="bg-gray-50 font-[Poppins]">
    <div class="flex">
        <!-- Mobile Header with Hamburger -->
        <header class="md:hidden flex items-center justify-between p-4 bg-white shadow-md fixed top-0 left-0 right-0 z-50">
            <div class="flex items-center flex-shrink-0">
                <button id="mobileMenuBtn" class="text-gray-700 focus:outline-none">
                    <i class="fas fa-bars fa-2x"></i>
                </button>
                <!-- Logo -->
                <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="w-24 h-auto ml-3">
            </div>
            <div class="flex items-center space-x-4">
                <!-- Dropdown di mobile -->
                <div class="dropdown">
                    <img src="../gambar/profil.png" alt="User Avatar" class="w-8 h-8 rounded-full" id="mobileProfileBtn">
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
                        <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100 transition-colors">
                            <i class="fas fa-home mr-3"></i>Dashboard
                        </a>
                        <a href="penjemputan.php" class="flex items-center px-4 py-3 bg-yellow-400 text-gray-700 rounded-lg mb-2">
                            <i class="fas fa-truck mr-3"></i>Penjemputan
                        </a>
                        <a href="reward.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100 transition-colors">
                            <i class="fas fa-gift mr-3"></i>Reward
                        </a>
                        <a href="riwayat.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100 transition-colors">
                            <i class="fas fa-calendar-alt mr-3"></i>Riwayat
                        </a>
                        <a href="edukasi.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100 transition-colors">
                            <i class="fas fa-book mr-3"></i>Edukasi
                        </a>
                    </nav>
                </div>
                <div class="border-t border-gray-300 w-40 mx-auto my-4"></div>
                <div class="flex flex-col px-4">
                    <a href="faq.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100 transition-colors">
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
        <div class="ml-0 md:ml-64 p-6 pt-20 md:pt-5 transition-all duration-300 w-full">
            <!-- Informasi Operasional -->
            <div class="bg-yellow-100 text-yellow-800 p-4 rounded-lg mb-6 text-center md:text-left">
                Saat ini layanan hanya tersedia untuk wilayah Bojongsoang dan operasional pada Senin-Jum'at pukul 08.00 s/d 18.00
            </div>

            <!-- Price Card -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 mx-auto max-w-6xl justify-center justify-items-center">
                <!-- Biaya Kirim -->
                <div class="flex flex-row items-center justify-center bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition-shadow duration-300 w-full">
                    <i class="fas fa-truck fa-3x text-yellow-500 mr-4" aria-hidden="true" title="Biaya Kirim"></i>
                    <div class="text-center">
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">Biaya Kirim</h3>
                        <p class="text-2xl font-bold text-gray-900">Rp 2.999</p>
                    </div>
                </div>
                <!-- Poin per Liter dengan Lingkaran Hadiah -->
                <div class="flex flex-row items-center justify-center bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition-shadow duration-300 w-full relative">
                    <!-- Lingkaran Hadiah -->
                    <a href="reward.php" class="absolute -top-3 -right-3 bg-red-500 text-white rounded-full w-10 h-10 flex items-center justify-center shadow-lg hover:bg-red-600 transition-colors">
                        <i class="fas fa-gift"></i>
                    </a>
                    <!-- Konten Kartu -->
                    <i class="fas fa-coins fa-3x text-blue-500 mr-4" aria-hidden="true" title="Poin per Liter"></i>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gray-900">1 Liter = 5000 Poin</p>
                    </div>
                </div>
            </div>

            <!-- Form Penjemputan -->
            <form id="penjemputanForm" action="penjemputan.php" method="post" enctype="multipart/form-data">
                <!-- Liter Minyak dan Upload Foto -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Liter Minyak -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="mb-4">
                            <h5 class="font-medium text-lg mb-2">PENTING:</h5>
                            <p class="text-gray-500">Gunakan botol/wadah yang memiliki takaran liter jelas untuk memastikan takaran minyak yang tepat.</p>
                        </div>
                        <div class="">
                            <label class="block text-gray-700 mb-2" for="literMinyak">Liter Minyak</label>
                            <input type="number" id="literMinyak" name="literMinyak" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-yellow-400" placeholder="Cth: 1 atau 2" min="1" step="0.1" required oninput="validateForm()">
                        </div>
                    </div>

                    <!-- Upload Foto Limbah -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-center">
                            <label for="uploadFoto" class="relative flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-gray-400 transition duration-300">
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <img id="previewImage" src="" alt="" class="hidden w-full h-full object-cover rounded-lg">
                                </div>
                                <div id="uploadIcon" class="flex flex-col items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="text-gray-600 text-sm">Tambahkan Foto Limbah</span>
                                </div>
                            </label>
                            <input type="file" id="uploadFoto" name="foto" class="hidden" accept="image/*" required onchange="previewImageFunction()" oninput="validateForm()">
                        </div>
                    </div>
                </div>

                <!-- Alamat Penjemputan (Latitude dan Longitude) -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <h5 class="font-medium text-lg mb-4">Alamat Penjemputan</h5>
                    <div class="flex flex-col md:flex-row gap-6 mb-4">
                        <!-- Embedded Google Maps -->
                        <div class="flex-1">
                            <div class="map-responsive">
                                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d63362.89903856104!2d107.62723621229112!3d-6.987926814857641!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e68c2753c544847%3A0x401e8f1fc28c6f0!2sBojongsoang%2C%20Kec.%20Bojongsoang%2C%20Kabupaten%20Bandung%2C%20Jawa%20Barat!5e0!3m2!1sid!2sid!4v1736338767259!5m2!1sid!2sid" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                            </div>
                        </div>
                        <!-- Tombol Geolocation dan Patokan -->
                        <div class="flex-1 flex flex-col">
                            <button type="button" onclick="getLocation()" class="w-full bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600 transition-colors duration-200">
                                Dapatkan Lokasi Saya
                            </button>
                            <!-- Menampilkan Koordinat -->
                            <div id="locationDisplay" class="mt-4 text-gray-700">
                                <!-- Koordinat akan ditampilkan di sini -->
                            </div>
                            <!-- Hidden Inputs untuk Latitude dan Longitude -->
                            <input type="hidden" id="latitude" name="latitude" required>
                            <input type="hidden" id="longitude" name="longitude" required>

                            <!-- Patokan -->
                            <div class="mt-4">
                                <label class="block font-medium text-gray-700 mb-1" for="patokan">Patokan</label>
                                <input type="text" id="patokan" name="patokan" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-yellow-400" placeholder="Cth: Sebelah masjid/Depan warung" required oninput="validateForm()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slot Waktu Jemput -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <h5 class="font-medium text-lg mb-4">Slot Waktu Jemput</h5>
                    <div class="flex flex-col md:flex-row justify-around gap-4 mb-6">
                        <!-- Radio Button Pagi -->
                        <div class="relative">
                            <input type="radio" name="timeSlot" id="pagi" class="hidden peer" value="Pagi (08.00-11.00)" required onchange="validateForm()">
                            <label for="pagi" class="px-6 py-2 border border-gray-300 rounded-full cursor-pointer block peer-checked:bg-yellow-400 peer-checked:border-yellow-400 hover:bg-yellow-50 transition-all">
                                Pagi (08.00-11.00)
                            </label>
                        </div>
                        <!-- Radio Button Siang -->
                        <div class="relative">
                            <input type="radio" name="timeSlot" id="siang" class="hidden peer" value="Siang (13.00-15.00)" onchange="validateForm()">
                            <label for="siang" class="px-6 py-2 border border-gray-300 rounded-full cursor-pointer block peer-checked:bg-yellow-400 peer-checked:border-yellow-400 hover:bg-yellow-50 transition-all">
                                Siang (13.00-15.00)
                            </label>
                        </div>
                        <!-- Radio Button Sore -->
                        <div class="relative">
                            <input type="radio" name="timeSlot" id="sore" class="hidden peer" value="Sore (15.30-18.00)" onchange="validateForm()">
                            <label for="sore" class="px-6 py-2 border border-gray-300 rounded-full cursor-pointer block peer-checked:bg-yellow-400 peer-checked:border-yellow-400 hover:bg-yellow-50 transition-all">
                                Sore (15.30-18.00)
                            </label>
                        </div>
                    </div>
                    <button type="submit" id="submitBtn" disabled class="w-full p-3 rounded-md transition-all duration-300 bg-gray-300 text-gray-500 cursor-not-allowed hover:bg-gray-400">
                        Submit
                    </button>
                </div>

                <!-- Menampilkan Pesan Error jika ada -->
                <?php if ($error): ?>
                    <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
            </form>
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

        // Logout Modal
        const logoutBtnMobile = document.getElementById('logoutBtnMobile');
        const logoutModal = document.getElementById('logoutModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const confirmBtn = document.getElementById('confirmBtn');

        function toggleModal(show = true) {
            logoutModal.classList.toggle('active', show);
        }

        if (logoutBtnMobile) {
            logoutBtnMobile.addEventListener('click', (e) => {
                e.preventDefault();
                toggleModal(true);
            });
        }

        cancelBtn.addEventListener('click', () => toggleModal(false));
        confirmBtn.addEventListener('click', () => {
            // Redirect ke logout.php untuk menghapus sesi
            window.location.href = 'logout.php';
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

        // Dropdown for mobile
        const mobileProfileBtn = document.getElementById('mobileProfileBtn');
        const dropdowns = document.querySelectorAll('.dropdown');

        function closeAllDropdowns() {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('show');
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
        dropdowns.forEach(dropdown => {
            const dropdownContent = dropdown.querySelector('.dropdown-content');
            if (dropdownContent) {
                dropdownContent.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            }
        });
        document.addEventListener('click', () => {
            closeAllDropdowns();
        });

        // Preview Foto Limbah
        function previewImageFunction() {
            const file = document.getElementById('uploadFoto').files[0];
            if (file) {
                const reader = new FileReader();
                const previewImage = document.getElementById('previewImage');
                const uploadIcon = document.getElementById('uploadIcon');

                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewImage.classList.remove('hidden');
                    uploadIcon.classList.add('hidden');
                }
                reader.readAsDataURL(file);
            }
            validateForm();
        }

        // Geolocation Functionality
        function getLocation() {
            const locationDisplay = document.getElementById('locationDisplay');
            const latitudeInput = document.getElementById('latitude');
            const longitudeInput = document.getElementById('longitude');

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(showPosition, showError);
            } else { 
                locationDisplay.innerHTML = "Geolocation is not supported by this browser.";
            }

            function showPosition(position) {
                const latitude = position.coords.latitude.toFixed(6);
                const longitude = position.coords.longitude.toFixed(6);
                latitudeInput.value = latitude;
                longitudeInput.value = longitude;

                // PERBAIKAN DI SINI (gunakan backtick)
                locationDisplay.innerHTML = `Latitude: ${latitude} <br> Longitude: ${longitude}`;

                validateForm();
            }

            function showError(error) {
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        locationDisplay.innerHTML = "User denied the request for Geolocation.";
                        break;
                    case error.POSITION_UNAVAILABLE:
                        locationDisplay.innerHTML = "Location information is unavailable.";
                        break;
                    case error.TIMEOUT:
                        locationDisplay.innerHTML = "The request to get user location timed out.";
                        break;
                    case error.UNKNOWN_ERROR:
                        locationDisplay.innerHTML = "An unknown error occurred.";
                        break;
                }
            }
        }

        // Validasi Form
        function validateForm() {
            const literMinyak = document.getElementById('literMinyak').value;
            const uploadFoto = document.getElementById('uploadFoto').files.length;
            const latitude = document.getElementById('latitude').value;
            const longitude = document.getElementById('longitude').value;
            const patokan = document.getElementById('patokan').value;
            const timeSlot = document.querySelector('input[name="timeSlot"]:checked');

            const submitBtn = document.getElementById('submitBtn');

            if (literMinyak && uploadFoto && latitude && longitude && patokan && timeSlot) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
                submitBtn.classList.add('bg-yellow-400', 'text-black', 'hover:bg-yellow-500');
            } else {
                submitBtn.disabled = true;
                submitBtn.classList.add('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
                submitBtn.classList.remove('bg-yellow-400', 'text-black', 'hover:bg-yellow-500');
            }
        }
    </script>
</body>
</html>