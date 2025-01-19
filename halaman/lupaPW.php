<?php
// Memulai sesi untuk kebutuhan pesan kesalahan atau notifikasi
session_start();

// Konfigurasi koneksi ke database
$host     = "localhost";
$username = "root";
$password = "";
$dbname   = "refresh_oil";

// Membuat koneksi menggunakan MySQLi
$conn = new mysqli($host, $username, $password, $dbname);

// Memeriksa koneksi
if ($conn->connect_error) {
    $_SESSION['error'] = "Koneksi gagal: " . $conn->connect_error;
    // Redirect ke halaman yang sama untuk menampilkan modal error
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Inisialisasi variabel untuk menyimpan pesan error dan sukses
$error   = "";
$success = "";

// Cek apakah form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan sanitasi input
    $email_input          = trim($_POST['email']);
    $new_password         = trim($_POST['new_password']);
    $confirm_new_password = trim($_POST['confirm_new_password']);

    // Validasi input
    if (empty($email_input) || empty($new_password) || empty($confirm_new_password)) {
        $error = "Mohon isi semua field.";
    } elseif ($new_password !== $confirm_new_password) {
        $error = "Password baru dan konfirmasi tidak sama.";
    } else {
        // Cek apakah email ada di database
        $query  = "SELECT id FROM users WHERE email = ?";
        $stmt   = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $email_input);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                // Email ditemukan, ambil ID user
                $stmt->bind_result($id_admin);
                $stmt->fetch();

                // Hash password baru
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Update password di database
                $update_query = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt  = $conn->prepare($update_query);
                if ($update_stmt) {
                    $update_stmt->bind_param("si", $hashed_password, $id_admin);
                    if ($update_stmt->execute()) {
                        // Set pesan sukses
                        $success = "Password berhasil diubah.";
                    } else {
                        $error = "Gagal memperbarui password. Silakan coba lagi.";
                    }
                    $update_stmt->close();
                } else {
                    $error = "Gagal mempersiapkan pernyataan pembaruan.";
                }
            } else {
                // Email tidak ditemukan
                $error = "Email tidak terdaftar atau salah.";
            }

            $stmt->close();
        } else {
            $error = "Gagal mempersiapkan pernyataan.";
        }
    }

    // Menyimpan pesan ke session dan redirect untuk menghindari resubmission form
    if (!empty($error)) {
        $_SESSION['error'] = $error;
    }
    if (!empty($success)) {
        $_SESSION['success'] = $success;
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Menutup koneksi
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - RefreshOil</title>
    <link href="../gambar/logo.png" rel="shortcut icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Optional: Tailwind CSS forms plugin for better form styling -->
    <script>
        tailwind.config = {
            theme: {
                extend: {},
            },
            plugins: [],
        }
    </script>
</head>
<body class="min-h-screen flex justify-center items-center relative font-[Poppins] bg-[#f5f5f5]">
    <!-- Background Image dengan Overlay -->
    <div class="fixed inset-0 z-[-1]">
        <img src="../gambar/banyak.png" alt="RefreshOil Logo" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-black/50"></div>
    </div>

    <!-- Main Container -->
    <div class="bg-white p-8 md:p-10 rounded-2xl w-full max-w-[400px] mx-4 shadow-lg">
        <!-- Logo -->
        <div class="flex flex-col items-center mb-8">
            <img src="../gambar/Banner2.png" alt="RefreshOil Logo" class="w-3/5">
        </div>

        <!-- Form Lupa Password -->
        <form id="forgot-password-form" method="POST" action="" class="space-y-6">
            <h3 class="text-center font-semibold text-xl">Lupa Password</h3>

            <!-- Input Email -->
            <input
                type="email"
                name="email"
                placeholder="Masukkan Email Terdaftar"
                required
                class="w-full p-3 bg-gray-100 rounded-lg border-none focus:ring-2 focus:ring-yellow-400 focus:outline-none"
            >

            <!-- Input Password Baru dengan Toggle -->
            <div class="relative">
                <input
                    type="password"
                    name="new_password"
                    placeholder="Masukkan Password Baru"
                    id="new-password-field"
                    required
                    class="w-full p-3 bg-gray-100 rounded-lg border-none focus:ring-2 focus:ring-yellow-400 focus:outline-none"
                >
                <span
                    id="toggle-new-password"
                    class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-600 text-lg"
                >
                    <img src="../gambar/eye.png" alt="Open Eye Icon" id="eye-new-icon" style="width: 20px; height: 20px;">
                </span>
            </div>

            <!-- Input Konfirmasi Password Baru dengan Toggle -->
            <div class="relative">
                <input
                    type="password"
                    name="confirm_new_password"
                    placeholder="Konfirmasi Password Baru"
                    id="confirm-password-field"
                    required
                    class="w-full p-3 bg-gray-100 rounded-lg border-none focus:ring-2 focus:ring-yellow-400 focus:outline-none"
                >
                <span
                    id="toggle-confirm-password"
                    class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-600 text-lg"
                >
                    <img src="../gambar/eye.png" alt="Open Eye Icon" id="eye-confirm-icon" style="width: 20px; height: 20px;">
                </span>
            </div>

            <button
                type="submit"
                id="reset-button"
                class="w-full py-3 px-4 bg-yellow-400 text-white rounded-lg font-medium transition-colors duration-200
                       hover:bg-yellow-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                Reset Password
            </button>
        </form>
    </div>

    <!-- Modal Template -->
    <div id="modal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden">
        <div class="bg-white rounded-lg shadow-lg max-w-sm w-full p-6">
            <div id="modal-content" class="mb-4">
                <!-- Pesan akan diisi oleh JavaScript -->
            </div>
            <div class="flex justify-end">
                <button id="modal-ok-button" class="px-4 py-2 bg-yellow-400 text-white rounded hover:bg-yellow-500">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- Notifikasi error atau success via modal -->
    <?php if (isset($_SESSION['error']) && !empty($_SESSION['error'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const modal = document.getElementById("modal");
                const modalContent = document.getElementById("modal-content");
                const modalOkButton = document.getElementById("modal-ok-button");

                modalContent.innerHTML = `<p class="text-red-500"><?php echo htmlspecialchars($_SESSION['error']); ?></p>`;
                modal.classList.remove("hidden");

                modalOkButton.addEventListener("click", () => {
                    modal.classList.add("hidden");
                });
            });
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success']) && !empty($_SESSION['success'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const modal = document.getElementById("modal");
                const modalContent = document.getElementById("modal-content");
                const modalOkButton = document.getElementById("modal-ok-button");

                modalContent.innerHTML = `<p class="text-green-500"><?php echo htmlspecialchars($_SESSION['success']); ?></p>`;
                modal.classList.remove("hidden");

                modalOkButton.addEventListener("click", () => {
                    // Redirect ke login.php setelah klik OK
                    window.location.href = 'login.php';
                });
            });
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <script>
        // JavaScript untuk toggle password

        // Toggle untuk Kolom Password Baru
        const toggleNewPassword = document.getElementById("toggle-new-password");
        const newPasswordField = document.getElementById("new-password-field");
        const eyeNewIcon = document.getElementById("eye-new-icon");

        toggleNewPassword.addEventListener("click", () => {
            const type = newPasswordField.getAttribute("type") === "password" ? "text" : "password";
            newPasswordField.setAttribute("type", type);
            eyeNewIcon.src = type === "password" ? "../gambar/eye.png" : "../gambar/eyeoff.png";
        });

        // Toggle untuk Kolom Konfirmasi Password Baru
        const toggleConfirmPassword = document.getElementById("toggle-confirm-password");
        const confirmPasswordField = document.getElementById("confirm-password-field");
        const eyeConfirmIcon = document.getElementById("eye-confirm-icon");

        toggleConfirmPassword.addEventListener("click", () => {
            const type = confirmPasswordField.getAttribute("type") === "password" ? "text" : "password";
            confirmPasswordField.setAttribute("type", type);
            eyeConfirmIcon.src = type === "password" ? "../gambar/eye.png" : "../gambar/eyeoff.png";
        });

        // Referensi elemen form dan tombol reset
        const resetButton = document.getElementById("reset-button");
        const inputFields = document.querySelectorAll("input");

        // Fungsi untuk mengecek apakah semua input sudah terisi
        function checkInputs() {
            const allFilled = Array.from(inputFields).every(input => input.value.trim() !== "");
            resetButton.disabled = !allFilled;
            if (allFilled) {
                resetButton.classList.add("bg-yellow-500");
                resetButton.classList.remove("bg-yellow-400");
            } else {
                resetButton.classList.remove("bg-yellow-500");
                resetButton.classList.add("bg-yellow-400");
            }
        }

        // Cek setiap perubahan input untuk update tombol
        inputFields.forEach(input => {
            input.addEventListener("input", checkInputs);
        });

        // Set status tombol saat halaman dimuat
        checkInputs();
    </script>
</body>
</html>