<?php
session_start();

// Memeriksa apakah admin sudah login
if (!isset($_SESSION['id_admin'])) {
    header("Location: loginadmin.php"); // Redirect ke halaman login admin jika belum login
    exit();
}

$host = "localhost";
$username = "root";
$password = "";
$dbname = "refresh_oil";

try {
    // Koneksi ke database menggunakan MySQLi
    $conn = new mysqli($host, $username, $password, $dbname);
    
    // Memeriksa koneksi
    if ($conn->connect_error) {
        throw new Exception("Koneksi gagal: " . $conn->connect_error);
    }

    // Mendapatkan data admin saat ini
    $stmt = $conn->prepare("SELECT * FROM admin WHERE id_admin = ?");
    $stmt->bind_param("i", $_SESSION['id_admin']);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if (!$admin) {
        throw new Exception("Admin tidak ditemukan.");
    }

    // Menangani pembaruan data admin
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['change_password'])) {
            // Menangani perubahan password
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            // Memeriksa apakah password saat ini cocok
            if (!password_verify($current_password, $admin['password'])) {
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
            $stmt_update_password = $conn->prepare("UPDATE admin SET password = ? WHERE id_admin = ?");
            $stmt_update_password->bind_param("si", $hashed_password, $_SESSION['id_admin']);
            $stmt_update_password->execute();
            $stmt_update_password->close();

            $success_message = "Password berhasil diperbarui.";

            // Memperbarui data admin setelah perubahan
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();
        } else {
            // Menangani pembaruan profil
            $username_input = trim($_POST['username']); // Ubah 'name' menjadi 'username' untuk konsistensi
            if (empty($username_input)) {
                throw new Exception('Username tidak boleh kosong.');
            }

            // Memperbarui username di database
            $stmt_update = $conn->prepare("UPDATE admin SET username = ? WHERE id_admin = ?");
            $stmt_update->bind_param("si", $username_input, $_SESSION['id_admin']);
            $stmt_update->execute();
            $stmt_update->close();

            $success_message = "Profil berhasil diperbarui.";

            // Memperbarui data admin setelah perubahan
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();
        }
    }

    // Jika username kosong, generate nama dari email
    if (empty($admin['username'])) {
        $email = $admin['email'];
        $parts = explode("@", $email);
        $username_generated = $parts[0];
        $username_generated = str_replace([".", "-"], " ", $username_generated);
        $admin['username'] = ucwords($username_generated);
    }

} catch (Exception $e) {
    // Menampilkan pesan error
    $error_message = "Error: " . $e->getMessage();
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn) && $conn->ping()) $conn->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Admin - RefreshOil</title>
    <link href="../gambar/logo.png" rel="shortcut icon">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js"></script>
    <!-- Tambahkan Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Tambahkan CSS khusus jika diperlukan */
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
    </style>
</head>
<body class="bg-gray-50 font-[Poppins]">
<div class="flex h-screen overflow-hidden">
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
                    <a href="riwayatAdmin.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
                        <i class="fas fa-calendar-alt mr-3"></i>Riwayat
                    </a>
                    <a href="edukasiAdmin.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg mb-2 hover:bg-yellow-100">
                        <i class="fas fa-book mr-3"></i>Edukasi
                    </a>
                    <a href="profilAdmin.php" class="flex items-center px-4 py-3 bg-yellow-400 text-gray-700 rounded-lg mb-2">
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
    <!-- Overlay untuk sidebar mobile -->
    <div id="overlay" class="fixed inset-0 bg-black opacity-50 hidden z-40"></div>

    <!-- Content -->
    <div class="ml-0 md:ml-64 p-8 pt-20 md:pt-5 transition-all duration-300">
        <!-- Tombol Menu Mobile -->
        <button id="mobileMenuBtn" class="md:hidden mb-4 text-gray-700 focus:outline-none">
            <i class="fas fa-bars fa-2x"></i>
        </button>

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

        <div id="profileInfo" class="bg-white p-8 rounded-lg shadow-md">
            <div class="flex items-center mb-6">
                <div class="flex-shrink-0">
                    <img src="../gambar/profil.png" alt="Profile Picture" class="w-32 h-32 rounded-full">
                </div>
                <div class="ml-8">
                    <div>
                        <h2 class="text-3xl font-semibold">
                            <?php echo htmlspecialchars($admin['username']); ?>
                        </h2>
                        <span class="text-gray-400 text-lg">Admin</span>
                    </div>
                    <button id="editProfileBtn" class="mt-4 px-4 py-2 bg-yellow-500 text-white rounded-lg">Edit Profil</button>
                    <button id="changePasswordBtn" class="mt-4 px-4 py-2 bg-blue-500 text-white rounded-lg">Ubah Password</button>
                </div>
            </div>
        </div>

        <div id="editProfileForm" class="hidden bg-white p-8 rounded-lg shadow-md">
            <form method="POST">
                <div class="mb-4">
                    <label for="username" class="block text-sm font-semibold">Username</label>
                    <input type="text" id="username" name="username" class="mt-2 w-full p-3 border rounded-lg" 
                           value="<?php echo htmlspecialchars($admin['username']); ?>" required>
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

        <!-- Tambahkan konten khusus admin di sini, misalnya statistik, manajemen pengguna, dll. -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-10">
            <div class="bg-white rounded-2xl p-6 shadow-lg flex flex-col justify-center items-center h-60">
                <p class="text-m text-gray-600 text-center mb-4">Statistik Mingguan (L)</p>
                <div class="flex-grow w-full max-w-xs">
                    <canvas id="minyakChart"></canvas>
                </div>
            </div>

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

<!-- Logout Modal -->
<div id="logoutModal" class="modal fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white rounded-lg p-6 w-80">
        <h2 class="text-xl font-semibold mb-4">Konfirmasi Logout</h2>
        <p class="mb-6">Apakah Anda yakin ingin logout?</p>
        <div class="flex justify-end gap-4">
            <button id="cancelBtn" class="px-4 py-2 bg-gray-400 text-white rounded-lg">Batal</button>
            <button id="confirmBtn" class="px-4 py-2 bg-red-500 text-white rounded-lg">Logout</button>
        </div>
    </div>
</div>

<!-- Footer -->
    <footer class="bg-white mt-4 shadow-lg ml-0 md:ml-64 transition-all duration-300">
        <div class="w-full p-8">
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

    mobileMenuBtn?.addEventListener('click', () => {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    });

    overlay?.addEventListener('click', () => {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    });

    const editProfileBtn = document.getElementById('editProfileBtn');
    const editProfileForm = document.getElementById('editProfileForm');
    const profileInfo = document.getElementById('profileInfo');
    const cancelEditBtn = document.getElementById('cancelEditBtn');

    const changePasswordBtn = document.getElementById('changePasswordBtn');
    const changePasswordForm = document.getElementById('changePasswordForm');
    const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');

    editProfileBtn?.addEventListener('click', () => {
        profileInfo.classList.add('hidden');
        editProfileForm.classList.remove('hidden');
        changePasswordForm.classList.add('hidden');
    });

    cancelEditBtn?.addEventListener('click', () => {
        profileInfo.classList.remove('hidden');
        editProfileForm.classList.add('hidden');
    });

    changePasswordBtn?.addEventListener('click', () => {
        profileInfo.classList.add('hidden');
        editProfileForm.classList.add('hidden');
        changePasswordForm.classList.remove('hidden');
    });

    cancelPasswordBtn?.addEventListener('click', () => {
        profileInfo.classList.remove('hidden');
        editProfileForm.classList.add('hidden');
        changePasswordForm.classList.add('hidden');
    });

    // Validasi Form Ubah Password di Frontend
    document.getElementById('passwordForm')?.addEventListener('submit', function(event) {
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

    // Chart Configuration (Jika Diperlukan)
    document.addEventListener('DOMContentLoaded', function() {
        const minyakChart = document.getElementById('minyakChart');
        if (minyakChart) {
            new Chart(minyakChart.getContext('2d'), {
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
            });
        }
    });

    // Logout Modal Functionality
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutModal = document.getElementById('logoutModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const confirmBtn = document.getElementById('confirmBtn');

    function toggleModal(show = true) {
        logoutModal.classList.toggle('active', show);
    }

    logoutBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        toggleModal(true);
    });

    cancelBtn?.addEventListener('click', () => toggleModal(false));

    confirmBtn?.addEventListener('click', () => {
        // Logika logout
        window.location.href = 'logout.php'; // Pastikan file ini ada dan benar
    });

    logoutModal?.addEventListener('click', (e) => {
        if (e.target === logoutModal) {
            toggleModal(false);
        }
    });

    // Add keyboard support for modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && logoutModal?.classList.contains('active')) {
            toggleModal(false);
        }
    });
</script>
</body>
</html>