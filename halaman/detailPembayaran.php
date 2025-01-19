<?php
session_start();

// Koneksi ke database (hanya satu koneksi)
$host = 'localhost';
$db   = 'refresh_oil';
$user = 'root';
$pass = '';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Menampilkan error dengan eksepsi
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch sebagai array asosiatif
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Menonaktifkan emulasi prepare statement
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Jika koneksi gagal, tampilkan pesan dan log error
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.']);
    error_log("Koneksi database gagal: " . $e->getMessage());
    exit;
}

/**
 * Fungsi untuk mendapatkan potongan berdasarkan id_reward
 *
 * @param int $id_reward
 * @return int
 */
function getPotongan($id_reward) {
    switch ($id_reward) {
        case 1:
            return 1000;
        case 2:
            return 3000;
        case 3:
            return 5000;
        default:
            return 0;
    }
}

// Cek apakah permintaan adalah AJAX untuk memilih voucher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_voucher') {
    // Set header untuk JSON
    header('Content-Type: application/json');

    // Pastikan pengguna sudah login
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Anda harus login terlebih dahulu.']);
        error_log("User tidak login saat mencoba memilih voucher.");
        exit;
    }

    // Pastikan data penjemputan tersedia di session
    if (!isset($_SESSION['penjemputan_data']) || empty($_SESSION['penjemputan_data'])) {
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada data penjemputan yang ditemukan.']);
        error_log("Tidak ada data penjemputan di session untuk user_id=" . $_SESSION['user_id']);
        exit;
    }

    // Cek apakah id_reward dikirim via POST
    if (!isset($_POST['id_reward'])) {
        echo json_encode(['status' => 'error', 'message' => 'Voucher tidak valid.']);
        error_log("id_reward tidak dikirim saat memilih voucher oleh user_id=" . $_SESSION['user_id']);
        exit;
    }

    $id_reward = intval($_POST['id_reward']);

    // Validasi id_reward
    if (!in_array($id_reward, [1, 2, 3])) {
        echo json_encode(['status' => 'error', 'message' => 'Voucher tidak tersedia.']);
        error_log("id_reward tidak valid: $id_reward untuk user_id=" . $_SESSION['user_id']);
        exit;
    }

    // Cek apakah pengguna memiliki voucher tersebut di tabel penukaran dan ambil data dari rewards
    $stmt = $pdo->prepare("
        SELECT r.* 
        FROM penukaran p
        JOIN rewards r ON p.id_reward = r.id_reward
        WHERE p.id_reward = ? AND p.id_user = ?
    ");
    $stmt->execute([$id_reward, $_SESSION['user_id']]);
    $voucher = $stmt->fetch();

    if (!$voucher) {
        echo json_encode(['status' => 'error', 'message' => 'Voucher tidak ditemukan atau tidak dimiliki oleh Anda.']);
        error_log("Voucher tidak ditemukan: id_reward=$id_reward, id_user=" . $_SESSION['user_id']);
        exit;
    }

    // Tentukan potongan harga berdasarkan id_reward
    $potongan = getPotongan($id_reward);

    // Terapkan voucher hanya jika belum ada voucher yang diterapkan
    if (!isset($_SESSION['penjemputan_data']['voucher_applied'])) {
        $_SESSION['selected_voucher_id'] = $id_reward;

        // Update subtotal dan total biaya
        $penjemputan = $_SESSION['penjemputan_data'];
        $penjemputan['subtotal'] -= $potongan;
        $penjemputan['total_biaya'] -= $potongan;
        $_SESSION['penjemputan_data'] = $penjemputan;
        $_SESSION['penjemputan_data']['voucher_applied'] = true;
    } else {
        // Jika sudah ada voucher yang diterapkan, ganti dengan voucher yang lebih besar jika diperlukan
        $current_voucher_id = $_SESSION['selected_voucher_id'];
        $current_potongan = getPotongan($current_voucher_id);

        if ($potongan > $current_potongan) {
            // Kembalikan potongan sebelumnya
            $penjemputan = $_SESSION['penjemputan_data'];
            $penjemputan['subtotal'] += $current_potongan;
            $penjemputan['total_biaya'] += $current_potongan;

            // Terapkan potongan baru
            $_SESSION['selected_voucher_id'] = $id_reward;
            $penjemputan['subtotal'] -= $potongan;
            $penjemputan['total_biaya'] -= $potongan;
            $_SESSION['penjemputan_data'] = $penjemputan;
        } else {
            // Voucher baru tidak lebih besar, tidak diterapkan
            echo json_encode(['status' => 'error', 'message' => 'Anda sudah menerapkan voucher dengan potongan yang lebih besar atau sama.']);
            exit;
        }
    }

    // Kembalikan respons
    echo json_encode([
        'status' => 'success',
        'message' => 'Voucher berhasil diterapkan.',
        'potongan' => $potongan,
        'new_subtotal' => $_SESSION['penjemputan_data']['subtotal'],
        'new_total_biaya' => $_SESSION['penjemputan_data']['total_biaya'],
        'selected_voucher_id' => $_SESSION['selected_voucher_id']
    ]);
    exit;
}

// Jika bukan permintaan AJAX, lanjutkan untuk menampilkan halaman
// Pastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    echo "Anda harus login terlebih dahulu.";
    exit;
}

// Pastikan data penjemputan tersedia di session
if (!isset($_SESSION['penjemputan_data']) || empty($_SESSION['penjemputan_data'])) {
    echo "Tidak ada data penjemputan yang ditemukan. Silakan kembali ke halaman penjemputan.";
    exit;
}

// Ambil data penjemputan dari session
$penjemputan = $_SESSION['penjemputan_data'];

// Pastikan pemilik data adalah user yang sedang login
if ($penjemputan['id_user'] != $_SESSION['user_id']) {
    echo "Anda tidak memiliki izin untuk melihat data ini.";
    exit;
}

// Fungsi untuk mendapatkan potongan berdasarkan id_reward
function getPotonganFrontend($id_reward) {
    switch ($id_reward) {
        case 1:
            return 1000;
        case 2:
            return 2999;
        case 3:
            return 5000;
        default:
            return 0;
    }
}

// Cek apakah pengguna sudah memilih voucher
$selected_voucher = isset($_SESSION['selected_voucher_id']) ? $_SESSION['selected_voucher_id'] : null;
$voucher_info = null;
$potongan = 0;

if (!$selected_voucher) {
    // Otomatis memilih voucher dengan potongan terbesar
    $stmt = $pdo->prepare("
        SELECT r.* 
        FROM penukaran p
        JOIN rewards r ON p.id_reward = r.id_reward
        WHERE p.id_user = ?
        ORDER BY 
            CASE p.id_reward
                WHEN 3 THEN 3
                WHEN 2 THEN 2
                WHEN 1 THEN 1
                ELSE 0
            END DESC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $voucher_info = $stmt->fetch();

    if ($voucher_info) {
        $selected_voucher = $voucher_info['id_reward'];
        $potongan = getPotongan($selected_voucher);

        // Terapkan voucher
        $_SESSION['selected_voucher_id'] = $selected_voucher;
        $penjemputan['subtotal'] -= $potongan;
        $penjemputan['total_biaya'] -= $potongan;
        $penjemputan['voucher_applied'] = true;
        $_SESSION['penjemputan_data'] = $penjemputan;
    }
} else {
    // Jika voucher sudah dipilih sebelumnya
    $stmt = $pdo->prepare("
        SELECT r.* 
        FROM penukaran p
        JOIN rewards r ON p.id_reward = r.id_reward
        WHERE p.id_reward = ? AND p.id_user = ?
    ");
    $stmt->execute([$selected_voucher, $_SESSION['user_id']]);
    $voucher_info = $stmt->fetch();

    if ($voucher_info) {
        $potongan = getPotongan($selected_voucher);
    } else {
        // Voucher tidak valid atau tidak dimiliki
        unset($_SESSION['selected_voucher_id']);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pembayaran - RefreshOil</title>
    <link href="../gambar/logo.png" rel="shortcut icon">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom scrollbar for better aesthetics */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-thumb {
            background-color: rgba(107, 114, 128, 0.5);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background-color: rgba(107, 114, 128, 0.7);
        }

        /* Kelas kustom untuk latar belakang dengan gambar */
        .bg-custom {
            background-image: url('../gambar/banyak.png'); /* Sesuaikan path sesuai lokasi banyak.png */
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1; /* Agar berada di belakang konten */
        }
        /* Overlay semi-transparan hitam dengan opasitas 50% */
        .bg-custom::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 0;
        }
        /* Memastikan konten berada di atas overlay */
        .content-wrapper {
            position: relative;
            z-index: 1;
        }
        /* Penyesuaian Warna Teks untuk Kontras yang Lebih Baik */
        .text-gray-700 {
            color: #d1d5db;
        }
        .text-gray-900 {
            color: #f3f4f6;
        }
        .text-gray-800 {
            color: #e5e7eb;
        }
        .bg-white.bg-opacity-90 {
            background-color: rgba(255, 255, 255, 0.9);
        }
        /* Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.5); /* Black w/ opacity */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: #fefefe;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 500px; /* Could be more or less, depending on screen size */
            border-radius: 10px;
            position: relative;
        }
        .close {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }
        .voucher-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .voucher-info {
            display: flex;
            align-items: center;
        }
        .voucher-info img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 10px;
        }
    </style>
</head>
<body class="bg-custom font-poppins min-h-screen flex items-center justify-center">
    <div class="content-wrapper max-w-md w-full bg-white bg-opacity-90 rounded-3xl shadow-lg overflow-hidden p-6">
        <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 px-6 py-4 rounded-t-3xl">
            <div class="flex items-center justify-between">
                <h2 class="text-white text-2xl font-semibold">Detail Pembayaran</h2>
                <img src="../gambar/logo.png" alt="Logo RefreshOil" class="h-10 w-10">
            </div>
        </div>
        <div class="p-6">
            <!-- Detail Pembayaran -->
            <div class="space-y-4">
                <!-- Biaya Kirim -->
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-truck-moving text-yellow-500"></i>
                        <span class="text-gray-700">Biaya Kirim / 1 Liter</span>
                    </div>
                    <span class="text-gray-900 font-medium">
                        Rp <?php echo number_format($penjemputan['biaya_kirim'], 0, ',', '.'); ?>
                    </span>
                </div>
                <hr class="border-gray-200">

                <!-- Jumlah Liter -->
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-gas-pump text-yellow-500"></i>
                        <span class="text-gray-700">Jumlah Liter</span>
                    </div>
                    <span class="text-gray-900 font-medium">
                        <?php echo number_format($penjemputan['jumlah_liter'], 1, ',', '.'); ?> Liter
                    </span>
                </div>
                <hr class="border-gray-200">

                <!-- Subtotal -->
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-calculator text-yellow-500"></i>
                        <span class="text-gray-700">Subtotal</span>
                    </div>
                    <span class="text-gray-900 font-medium" id="subtotal">
                        Rp <?php echo number_format($penjemputan['subtotal'], 0, ',', '.'); ?>
                    </span>
                </div>
                <hr class="border-gray-200">

                <!-- Kode Unik -->
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-key text-yellow-500"></i>
                        <span class="text-gray-700">Kode Unik</span>
                    </div>
                    <span class="text-gray-900 font-medium">
                        <?php echo str_pad($penjemputan['kode_unik'], 2, '0', STR_PAD_LEFT); ?>
                    </span>
                </div>
                <hr class="border-gray-200">

                <!-- Total -->
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-wallet text-yellow-500"></i>
                        <span class="text-gray-800 font-semibold">Total</span>
                    </div>
                    <span class="text-yellow-700 font-bold text-lg" id="total_biaya">
                        Rp <?php echo number_format($penjemputan['total_biaya'], 0, ',', '.'); ?>
                    </span>
                </div>
            </div>

            <!-- Bagian Memasang Voucher (opsional) -->
            <div class="flex justify-between items-center bg-yellow-50 rounded-lg p-4 mt-6 transition-transform transform hover:scale-105">
                <span id="voucher-text" class="text-yellow-700">
                    <?php
                    if ($selected_voucher && $voucher_info) {
                        echo "Voucher Potongan Ongkir (Rp " . number_format($potongan, 0, ',', '.') . ")";
                    } else {
                        echo "Kamu bisa hemat nih...";
                    }
                    ?>
                </span>
                <button id="pasang-button" class="flex items-center text-yellow-500 hover:text-yellow-600 font-medium">
                    <?php
                    if ($selected_voucher && $voucher_info) {
                        echo '<i class="fa-solid fa-check-circle text-green-500"></i>';
                    } else {
                        echo '<i class="fa-solid fa-tag mr-2"></i> Pasang';
                    }
                    ?>
                </button>
            </div>

            <!-- Tombol Lanjut Ke pembayaran.php -->
            <a href="pembayaran.php"
               class="w-full bg-yellow-500 text-white font-semibold py-3 rounded-lg mt-6 hover:bg-yellow-600 transition-colors flex items-center justify-center">
                <i class="fa-solid fa-arrow-right mr-2"></i> Lanjut
            </a>
        </div>

        <!-- Footer -->
        <div class="bg-gray-100 px-6 py-4 text-center text-gray-500 text-sm">
            &copy; <?php echo date("Y"); ?> RefreshOil. All rights reserved.
        </div>
    </div>

    <!-- Modal Voucher -->
    <div id="voucherModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-xl font-semibold mb-4">Pilih Voucher</h2>
            <div id="voucher-list">
                <?php
                // Ambil voucher yang dimiliki pengguna dari tabel penukaran dan rewards
                $stmt = $pdo->prepare("
                    SELECT r.*, p.id_tukar
                    FROM penukaran p
                    JOIN rewards r ON p.id_reward = r.id_reward
                    WHERE p.id_reward IN (1, 2, 3) AND p.id_user = ?
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $vouchers = $stmt->fetchAll();

                if ($vouchers) {
                    foreach ($vouchers as $voucher) {
                        // Cek apakah voucher sudah dipasang
                        $isSelected = ($selected_voucher === $voucher['id_reward']) ? true : false;
                        echo '<div class="voucher-card">';
                        echo '<div class="voucher-info">';
                        echo '<img src="../gambar/' . htmlspecialchars($voucher['foto_reward']) . '" alt="' . htmlspecialchars($voucher['nama_reward']) . '">';
                        echo '<div>';
                        echo '<p class="font-medium">' . htmlspecialchars($voucher['nama_reward']) . '</p>';
                        echo '</div>';
                        echo '</div>';
                        // Jika voucher sudah dipasang, nonaktifkan tombol Tukar
                        if ($isSelected) {
                            echo '<button class="tukar-button bg-green-500 text-white px-3 py-1 rounded" disabled><i class="fa-solid fa-check"></i> Terpasang</button>';
                        } else {
                            echo '<button class="tukar-button bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600" data-id="' . $voucher['id_reward'] . '">Tukar</button>';
                        }
                        echo '</div>';
                    }
                } else {
                    echo '<p>Tidak ada voucher yang tersedia.</p>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Script JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById("voucherModal");
            var btn = document.getElementById("pasang-button");
            var span = document.getElementsByClassName("close")[0];
            var tukarButtons = document.getElementsByClassName("tukar-button");

            // Debugging: Pastikan elemen ditemukan
            console.log("Modal:", modal);
            console.log("Button Pasang:", btn);
            console.log("Button Close:", span);

            // Ketika pengguna mengklik tombol Pasang, buka modal
            btn.addEventListener('click', function() {
                console.log("Tombol Pasang diklik");
                modal.style.display = "flex"; // Gunakan flex untuk memanfaatkan align-items dan justify-content
            });

            // Ketika pengguna mengklik <span> (x), tutup modal
            span.addEventListener('click', function() {
                console.log("Tombol Close diklik");
                modal.style.display = "none";
            });

            // Ketika pengguna mengklik di luar modal, tutup modal
            window.addEventListener('click', function(event) {
                if (event.target == modal) {
                    console.log("Klik di luar modal");
                    modal.style.display = "none";
                }
            });

            // Tambahkan event listener ke setiap tombol Tukar
            Array.from(tukarButtons).forEach(function(button) {
                button.addEventListener('click', function() {
                    var id_reward = this.getAttribute('data-id');
                    console.log("Tombol Tukar diklik untuk id_reward:", id_reward);

                    // Kirim AJAX request ke detail_pembayaran.php
                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", "detailPembayaran.php", true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === XMLHttpRequest.DONE) {
                            if (xhr.status === 200) {
                                try {
                                    var response = JSON.parse(xhr.responseText);
                                    console.log("Respons AJAX:", response);
                                    if (response.status === 'success') {
                                        // Tutup modal
                                        modal.style.display = "none";

                                        // Perbarui teks voucher
                                        document.getElementById("voucher-text").innerText = "Voucher Potongan Ongkir (Rp " + formatNumber(response.potongan) + ")";

                                        // Ubah tombol Pasang menjadi centang hijau
                                        document.getElementById("pasang-button").innerHTML = '<i class="fa-solid fa-check-circle text-green-500"></i>';

                                        // Perbarui subtotal
                                        document.getElementById("subtotal").innerText = "Rp " + formatNumber(response.new_subtotal);

                                        // Perbarui total biaya
                                        document.getElementById("total_biaya").innerText = "Rp " + formatNumber(response.new_total_biaya);

                                        // Perbarui daftar voucher di modal
                                        updateVoucherList(response.selected_voucher_id);

                                    } else {
                                        alert("Error: " + response.message);
                                    }
                                } catch (e) {
                                    console.error("Parsing error:", e);
                                    alert("Terjadi kesalahan dalam memproses respons dari server.");
                                }
                            } else {
                                alert("Terjadi kesalahan saat mengirim permintaan.");
                            }
                        }
                    };
                    xhr.send("action=select_voucher&id_reward=" + encodeURIComponent(id_reward));
                });
            });

            // Fungsi untuk memformat angka dengan titik sebagai pemisah ribuan
            function formatNumber(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }

            // Fungsi untuk memperbarui daftar voucher di modal setelah pemilihan
            function updateVoucherList(selectedId) {
                var voucherList = document.getElementById("voucher-list");
                var vouchers = voucherList.getElementsByClassName("voucher-card");
                Array.from(vouchers).forEach(function(card) {
                    var button = card.getElementsByClassName("tukar-button")[0];
                    var dataId = button.getAttribute("data-id");
                    if (parseInt(dataId) === parseInt(selectedId)) {
                        button.className = "tukar-button bg-green-500 text-white px-3 py-1 rounded";
                        button.innerHTML = '<i class="fa-solid fa-check"></i> Terpasang';
                        button.disabled = true;
                    } else {
                        // Jika ada voucher lain yang sebelumnya terpasang, ubah kembali tombolnya
                        if (button.classList.contains("bg-green-500")) {
                            button.className = "tukar-button bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600";
                            button.innerHTML = 'Tukar';
                            button.disabled = false;
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>