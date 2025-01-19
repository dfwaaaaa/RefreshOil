<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "refresh_oil";

try {
    // Koneksi ke database dengan charset yang benar
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Mendapatkan data pengguna saat ini
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Jika user tidak ditemukan, logout
        header("Location: logout.php");
        exit();
    }

    // Jika nama kosong, generate nama dari email
    if (empty($user['nama'])) {
        $email = $user['email'];
        $parts = explode("@", $email);
        $nama = $parts[0];
        $nama = str_replace([".", "-"], " ", $nama);
        $user['nama'] = ucwords($nama);
    }

    // Define profile images with fallback
    $defaultProfileImg = '../gambar/profil.png'; // Path ke gambar default
    $profileImg = !empty($user['profil_img']) ? htmlspecialchars($user['profil_img']) : $defaultProfileImg;
    $profileImg = htmlspecialchars($profileImg); // Sanitasi lagi jika diperlukan

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['change_password'])) {
            // Menangani perubahan password
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // Memeriksa apakah password saat ini cocok
            if (!password_verify($current_password, $user['password'])) {
                throw new Exception('Password saat ini salah.');
            }

            // Memeriksa apakah password baru dan konfirmasi cocok
            if ($new_password !== $confirm_password) {
                throw new Exception('Password baru dan konfirmasi tidak cocok.');
            }

            // Memeriksa kekuatan password baru (opsional)
            if (strlen($new_password) < 6) {
                throw new Exception('Password baru harus minimal 6 karakter.');
            }

            // Meng-hash password baru
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

            // Memperbarui password di database
            $stmt_update_password = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_update_password->execute([$hashed_password, $_SESSION['user_id']]);

            $success_message = "Password berhasil diperbarui.";

            // Memperbarui data pengguna setelah perubahan
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Update profile image variable if changed
            $profileImg = !empty($user['profil_img']) ? htmlspecialchars($user['profil_img']) : $defaultProfileImg;
        } else {
            // Menangani pembaruan profil
            $nama = trim($_POST['name'] ?? '');
            if (empty($nama)) {
                throw new Exception('Nama tidak boleh kosong.');
            }

            $profil_img = null;

            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $file_type = mime_content_type($_FILES['foto']['tmp_name']);
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception('Jenis file tidak didukung. Hanya JPG, PNG, dan GIF yang diperbolehkan.');
                }

                // Validasi gambar
                $check = getimagesize($_FILES['foto']['tmp_name']);
                if ($check === false) {
                    throw new Exception('File yang diunggah bukan gambar yang valid.');
                }

                // Tentukan ekstensi berdasarkan tipe MIME
                switch ($file_type) {
                    case 'image/jpeg':
                        $ext = 'jpg';
                        break;
                    case 'image/png':
                        $ext = 'png';
                        break;
                    case 'image/gif':
                        $ext = 'gif';
                        break;
                    default:
                        throw new Exception('Jenis file tidak didukung.');
                }

                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        throw new Exception('Gagal membuat direktori upload.');
                    }
                }

                // Menghasilkan nama file unik
                $unique_name = uniqid('profil_img_', true) . '.' . $ext;
                $profil_img_path = $upload_dir . $unique_name;
                $profil_img_url = 'uploads/' . $unique_name; // Path relatif untuk disimpan di database

                if (!move_uploaded_file($_FILES['foto']['tmp_name'], $profil_img_path)) {
                    throw new Exception('Gagal mengunggah foto.');
                }

                // Hapus foto lama jika ada dan bukan gambar default
                if (!empty($user['profil_img']) && $user['profil_img'] !== 'https://via.placeholder.com/150' && $user['profil_img'] !== '../gambar/profil.png') {
                    $old_img_path = __DIR__ . '/' . $user['profil_img'];
                    if (file_exists($old_img_path)) {
                        unlink($old_img_path);
                    }
                }

                $profil_img = $profil_img_url;
            }

            if ($profil_img) {
                $stmt_update = $conn->prepare("UPDATE users SET nama = ?, profil_img = ? WHERE id = ?");
                $stmt_update->execute([$nama, $profil_img, $_SESSION['user_id']]);
            } else {
                $stmt_update = $conn->prepare("UPDATE users SET nama = ? WHERE id = ?");
                $stmt_update->execute([$nama, $_SESSION['user_id']]);
            }

            $success_message = "Profil berhasil diperbarui.";

            // Memperbarui data pengguna setelah perubahan
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Update profile image variable if changed
            $profileImg = !empty($user['profil_img']) ? htmlspecialchars($user['profil_img']) : $defaultProfileImg;
        }
    }

} catch (PDOException $e) {
    // Untuk debugging, Anda bisa mengubah ini sementara
    // $error_message = "Terjadi kesalahan pada sistem. Silakan coba beberapa saat lagi.";
    $error_message = "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <!-- Head content -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - RefreshOil</title>
    <link href="../gambar/logo.png" rel="shortcut icon">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
<body class="bg-gray-50 font-[Poppins]">
<div class="flex h-screen overflow-hidden">
    <!-- Mobile Header with Hamburger -->
    <header class="md:hidden flex items-center justify-between p-4 bg-white shadow-md fixed top-0 left-0 right-0 z-50 flex-nowrap">
        <div class="flex items-center flex-shrink-0">
            <button id="mobileMenuBtn" class="text-gray-700 focus:outline-none">
                <i class="fas fa-bars fa-2x"></i>
            </button>
            <!-- Logo dengan ukuran yang lebih besar namun tetap proporsional dan jarak ke ikon profil -->
            <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="w-24 h-auto ml-3 flex-shrink-0">
        </div>
        <div class="flex items-center space-x-4 flex-shrink-0">
            <div class="dropdown">
                <button id="mobileProfileBtn" class="focus:outline-none">
                    <img src="<?php echo $profileImg; ?>" alt="User Avatar" class="w-8 h-8 rounded-full" onerror="this.onerror=null; this.src='<?php echo $defaultProfileImg; ?>';">
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

    <!-- Content -->
    <div class="ml-0 md:ml-64 p-8 pt-20 md:pt-5 transition-all duration-300">
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Profile Information -->
        <div id="profileInfo" class="bg-white p-8 rounded-lg shadow-md">
            <div class="flex items-center mb-6">
                <div class="flex-shrink-0">
                    <img src="<?php echo $profileImg; ?>" 
                         alt="User Avatar" 
                         class="w-32 h-32 rounded-full border-4 border-yellow-500" 
                         onerror="this.onerror=null; this.src='<?php echo $defaultProfileImg; ?>';">
                </div>
                <div class="ml-8">
                    <div>
                        <h2 class="text-3xl font-semibold">
                            <?php echo htmlspecialchars($user['nama']); ?>
                        </h2>
                        <span class="text-gray-400 text-lg">
                            <?php echo isset($user['category']) ? htmlspecialchars($user['category']) : 'Kategori Tidak Diketahui'; ?>
                        </span>
                    </div>
                    <button id="editProfileBtn" class="mt-4 px-4 py-2 bg-yellow-500 text-white rounded-lg">Edit Profil</button>
                    <button id="changePasswordBtn" class="mt-4 px-4 py-2 bg-blue-500 text-white rounded-lg">Ubah Password</button>
                </div>
            </div>
        </div>

        <!-- Form Edit Profil -->
        <div id="editProfileForm" class="hidden bg-white p-8 rounded-lg shadow-md">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="name" class="block text-sm font-semibold">Nama Lengkap</label>
                    <input type="text" id="name" name="name" class="mt-2 w-full p-3 border rounded-lg" 
                           value="<?php echo htmlspecialchars($user['nama']); ?>" required>
                </div>
                <div class="mb-4">
                    <label for="uploadFoto" class="block text-sm font-semibold">Foto Profil</label>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-center">
                            <label for="uploadFotoInput" class="relative flex flex-col items-center justify-center w-48 h-48 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-gray-400 transition duration-300">
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <img id="previewImage" src="<?php echo htmlspecialchars($user['profil_img'] ?? ''); ?>" alt="Preview" class="<?php echo !empty($user['profil_img']) ? '' : 'hidden'; ?> w-full h-full object-cover rounded-lg" onerror="this.onerror=null; this.src='<?php echo $defaultProfileImg; ?>';">
                                </div>
                                <div id="uploadIcon" class="<?php echo !empty($user['profil_img']) ? 'hidden' : 'flex'; ?> flex-col items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="text-gray-600 text-sm">Tambahkan Foto Profil</span>
                                </div>
                            </label>
                            <input type="file" id="uploadFotoInput" name="foto" class="hidden" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="flex gap-4 mt-6">
                    <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-lg">Simpan</button>
                    <button type="button" id="cancelEditBtn" class="px-4 py-2 bg-gray-400 text-white rounded-lg">Batal</button>
                </div>
            </form>
        </div>

        <!-- Form Ubah Password -->
        <div id="changePasswordForm" class="hidden bg-white p-8 rounded-lg shadow-md mt-8">
            <h3 class="text-2xl font-semibold mb-6">Ubah Password</h3>
            <form method="POST" id="passwordForm">
                <div class="mb-4">
                    <label for="current_password" class="block text-sm font-semibold">Password Saat Ini</label>
                    <input type="password" id="current_password" name="current_password" class="mt-2 w-full p-3 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="new_password" class="block text-sm font-semibold">Password Baru</label>
                    <input type="password" id="new_password" name="new_password" class="mt-2 w-full p-3 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="confirm_password" class="block text-sm font-semibold">Konfirmasi Password Baru</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="mt-2 w-full p-3 border rounded-lg" required>
                </div>
                <div class="flex gap-4 mt-6">
                    <button type="submit" name="change_password" class="px-4 py-2 bg-blue-500 text-white rounded-lg">Ubah Password</button>
                    <button type="button" id="cancelPasswordBtn" class="px-4 py-2 bg-gray-400 text-white rounded-lg">Batal</button>
                </div>
            </form>
        </div>

        <!-- Statistik dan Konten Lainnya -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-10">
            <!-- Statistik Pekan Ini -->
            <div class="bg-white rounded-2xl p-6 shadow-lg flex flex-col justify-center items-center h-60">
                <p class="text-m text-gray-600 text-center mb-4">Statistik Pekan Ini (L)</p>
                <div class="flex-grow w-full max-w-xs">
                    <canvas id="minyakChart"></canvas>
                </div>
            </div>

            <!-- Total Poin -->
            <div class="bg-white rounded-2xl p-6 shadow-lg flex flex-col justify-center items-center h-60">
                <h3 class="text-xl font-semibold text-center text-gray-800 mb-4">Total Poin</h3>
                <div class="flex justify-center items-center mb-4">
                    <p class="text-4xl font-bold text-yellow-600 mr-4">15.000</p>
                    <a href="penjemputan.php" class="bg-yellow-400 text-black font-semibold text-center rounded-full px-4 py-4 hover:bg-yellow-600 transition-colors duration-300 flex items-center justify-center w-6 h-6">
                        <span class="text-xl">+</span>
                    </a>
                </div>
                <a href="#" class="block w-full bg-yellow-400 text-gray-800 font-semibold text-center rounded-full px-4 py-2 hover:bg-yellow-300 transition-colors duration-300">
                    Tukar
                </a>
            </div>

            <!-- Total Minyak -->
            <div class="bg-white rounded-2xl p-6 shadow-lg flex flex-col justify-center items-center h-60">
                <div class="mb-6">
                    <h3 class="text-xl font-semibold text-gray-800 text-center">Total Minyak</h3>
                </div>
                <div class="flex items-center justify-center mb-6 w-full relative">
                    <div class="flex-grow h-2 bg-gray-200 rounded-full">
                        <div class="h-full bg-yellow-400 rounded-full relative" style="width: 26%;">
                            <span class="absolute right-0 top-1/2 transform translate-x-1/2 -translate-y-1/2 bg-white text-xs font-semibold text-black px-2 py-0.5 rounded-full shadow">26 L</span>
                        </div>
                    </div>
                    <span class="text-sm font-semibold text-gray-500 ml-4">100 Liter</span>
                </div>
                <a href="#" class="block w-full bg-yellow-400 text-gray-800 font-semibold text-center rounded-full px-4 py-2 hover:bg-yellow-300 transition-colors duration-300">
                    Penjemputan
                </a>
            </div>
        </div>
    </div>
</div>
<!-- Footer -->
<footer class="bg-white shadow-lg mt-10 ml-0 md:ml-64 transition-all duration-300">
    <div class="w-full p-8">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <a href="dashboard.php" class="flex items-center">
                <img src="../gambar/Banner2.png" class="h-8" alt="RefreshOil Logo" />
            </a>
            <span class="text-sm items-end text-gray-500">
                © 2024 <a href="dashboard.php" class="hover:text-yellow-500">RefreshOil™</a>. All Rights Reserved.
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

    // Edit Profile Toggle
    const editProfileBtn = document.getElementById('editProfileBtn');
    const editProfileForm = document.getElementById('editProfileForm');
    const profileInfo = document.getElementById('profileInfo');
    const cancelEditBtn = document.getElementById('cancelEditBtn');

    const changePasswordBtn = document.getElementById('changePasswordBtn');
    const changePasswordForm = document.getElementById('changePasswordForm');
    const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');

    editProfileBtn.addEventListener('click', () => {
        profileInfo.classList.add('hidden');
        editProfileForm.classList.remove('hidden');
        changePasswordForm.classList.add('hidden');
    });

    cancelEditBtn.addEventListener('click', () => {
        profileInfo.classList.remove('hidden');
        editProfileForm.classList.add('hidden');
    });

    changePasswordBtn.addEventListener('click', () => {
        profileInfo.classList.add('hidden');
        editProfileForm.classList.add('hidden');
        changePasswordForm.classList.remove('hidden');
    });

    cancelPasswordBtn.addEventListener('click', () => {
        profileInfo.classList.remove('hidden');
        editProfileForm.classList.add('hidden');
        changePasswordForm.classList.add('hidden');
    });

    // Preview Image for Edit Profile
    document.getElementById('uploadFotoInput').addEventListener('change', function(event) {
        const file = event.target.files[0];
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
    });

    // Validasi Form Ubah Password di Frontend
    document.getElementById('passwordForm').addEventListener('submit', function(event) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (newPassword !== confirmPassword) {
            event.preventDefault();
            alert('Password baru dan konfirmasi tidak cocok.');
        }

        if (newPassword.length < 6) {
            event.preventDefault();
            alert('Password baru harus minimal 6 karakter.');
        }
    });

    // Chart Configuration
    document.addEventListener('DOMContentLoaded', function() {
        const minyakChart = new Chart(
            document.getElementById('minyakChart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Ming'],
                    datasets: [{
                        label: 'Minyak Jelantah (L)',
                        data: [3, 5, 7, 4, 2, 3, 2],
                        backgroundColor: '#f3ce49',
                        borderRadius: 5,
                        barThickness: 13,
                    }]
                },
                options: {
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
                }
            }
        );
    });

    // Logout Modal Functionality
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutBtnMobile = document.getElementById('logoutBtnMobile');
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

    if(logoutBtnMobile) {
        logoutBtnMobile.addEventListener('click', (e) => {
            e.preventDefault();
            toggleModal(true);
        });
    }

    cancelBtn.addEventListener('click', () => toggleModal(false));

    confirmBtn.addEventListener('click', () => {
        // Logika logout
        window.location.href = 'logout.php';
    });

    logoutModal.addEventListener('click', (e) => {
        if (e.target === logoutModal) {
            toggleModal(false);
        }
    });

    // Add keyboard support for modal
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