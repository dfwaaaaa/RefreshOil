<?php
session_start();

/**
 * -------------------------
 * KONFIGURASI KONEKSI DB
 * -------------------------
 */
$host = "localhost";
$username = "root";
$password = "";
$dbname = "refresh_oil";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Mengatur agar PDO melempar exception bila terjadi error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Koneksi ke database gagal.'
        ]);
    } else {
        echo "Koneksi ke database gagal.";
    }
    exit;
}

/**
 * ---------------------------------------
 * 1) Pastikan user login
 * 2) Pastikan data penjemputan ada di SESSION
 * ---------------------------------------
 */
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Anda harus login terlebih dahulu.'
        ]);
    } else {
        echo "Anda harus login terlebih dahulu.";
    }
    exit;
}

// Cek apakah 'penjemputan_data' sudah ada di session
if (!isset($_SESSION['penjemputan_data']) || empty($_SESSION['penjemputan_data'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Tidak ada data penjemputan yang ditemukan. Silakan kembali ke halaman penjemputan.'
        ]);
    } else {
        echo "Tidak ada data penjemputan yang ditemukan. Silakan kembali ke halaman penjemputan.";
    }
    exit;
}

// Ambil data dari session
$penjemputanData = $_SESSION['penjemputan_data'];
$id_penjemputan  = isset($penjemputanData['id_penjemputan']) ? (int)$penjemputanData['id_penjemputan'] : 0;
$total_biaya_raw = isset($penjemputanData['total_biaya']) ? $penjemputanData['total_biaya'] : 0;
$total_biaya     = is_numeric($total_biaya_raw) ? (float)$total_biaya_raw : 0;

// Validasi ID penjemputan
if ($id_penjemputan <= 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode([
            'status'  => 'error',
            'message' => 'ID penjemputan tidak valid. Pastikan Anda sudah memesan penjemputan.'
        ]);
    } else {
        echo "ID penjemputan tidak valid. Pastikan Anda sudah memesan penjemputan.";
    }
    exit;
}

/**
 * -----------------------------------------------------
 * 1.1) Update total_biaya di tabel penjemputan
 * -----------------------------------------------------
 */
try {
    $sqlUpdateTotal = "UPDATE penjemputan
                       SET total_biaya = :total_biaya
                       WHERE id_penjemputan = :id_penjemputan";
    $stmtUpdateTotal = $pdo->prepare($sqlUpdateTotal);
    $stmtUpdateTotal->execute([
        ':total_biaya'    => $total_biaya,
        ':id_penjemputan' => $id_penjemputan
    ]);

    // Opsional: Periksa apakah update berhasil
    if ($stmtUpdateTotal->rowCount() === 0) {
        error_log("pembayaran.php: Tidak ada perubahan pada total_biaya untuk id_penjemputan = " . $id_penjemputan);
    } else {
        error_log("pembayaran.php: total_biaya berhasil diperbarui untuk id_penjemputan = " . $id_penjemputan);
    }
} catch (PDOException $e) {
    error_log("Error updating total_biaya: " . $e->getMessage());
    // Anda bisa memilih untuk menghentikan eksekusi atau melanjutkan
    // Di sini saya akan memberikan respons error jika metode POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Terjadi kesalahan saat memperbarui total biaya.'
        ]);
        exit;
    } else {
        echo "Terjadi kesalahan saat memperbarui total biaya.";
        exit;
    }
}

/**
 * -----------------------------------------------------
 * 2) KONFIRMASI PEMBAYARAN
 * -----------------------------------------------------
 * Alur: 
 * - AJAX dari browser mengirim 'confirm=1' via POST 
 * - Update status_pembayaran jadi 'paid'
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] == 1) {
    try {
        error_log("pembayaran.php: Memproses pembayaran untuk id_penjemputan = " . $id_penjemputan);

        // Update status pembayaran => paid
        $sqlUpdate = "UPDATE penjemputan
                      SET status_pembayaran = 'paid'
                      WHERE id_penjemputan = :id_penjemputan";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([':id_penjemputan' => $id_penjemputan]);

        // Hapus data di session agar tidak double
        unset($_SESSION['penjemputan_data']);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Pembayaran berhasil, status penjemputan jadi paid.'
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Error in pembayaran.php: " . $e->getMessage());

        echo json_encode([
            'status'  => 'error',
            'message' => 'Terjadi kesalahan saat memproses pembayaran: '.$e->getMessage()
        ]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bayar Biaya Kirim</title>
    <link href="../gambar/logo.png" rel="shortcut icon">

    <!-- Tailwind CSS dari CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* Animasi untuk border nominal */
        @keyframes pulse-border {
            0% { border-color: rgba(251, 191, 36, 0.4); }
            50% { border-color: rgba(251, 191, 36, 1); }
            100% { border-color: rgba(251, 191, 36, 0.4); }
        }
        .animate-pulse-border {
            animation: pulse-border 2s infinite;
        }

        /* Background Custom */
        .bg-custom {
            background-image: url('../gambar/banyak.png'); 
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
        }
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
        .content-wrapper {
            position: relative;
            z-index: 1;
        }
        /* Penyesuaian Warna */
        .text-gray-700 { color: #d1d5db; }
        .text-gray-900 { color: #f3f4f6; }
        .text-gray-800 { color: #e5e7eb; }
        .bg-white.bg-opacity-90 { background-color: rgba(255, 255, 255, 0.9); }
    </style>
</head>
<body class="bg-custom font-sans min-h-screen flex items-center justify-center">
<div class="content-wrapper w-full p-8 bg-white shadow-lg relative overflow-hidden">
    <!-- Judul Halaman -->
    <div class="relative text-center">
        <h1 class="text-4xl font-bold mb-2">
            Bayar Biaya Kirim
        </h1>
    </div>

    <!-- Timer (5 Menit) -->
    <div class="text-center mb-6 rounded-xl">
        <p class="text-gray-600 font-medium mb-2">Waktu Pembayaran:</p>
        <div class="flex justify-center items-center gap-2">
            <div class="bg-white px-4 py-2 rounded-lg shadow-inner">
                <span class="text-2xl font-bold text-yellow-600" id="timer">05:00</span>
                <span class="text-gray-600 ml-1">menit</span>
            </div>
        </div>
    </div>

    <!-- Konten Utama: 2 Kolom -->
    <div class="flex flex-col md:flex-row gap-8">
        <!-- Kiri: Nominal, Cara Pembayaran, Kendala -->
        <div class="flex-1 md:w-1/2 flex flex-col justify-between">
            <div class="space-y-8">
                <!-- Nominal -->
                <div class="text-center">
                    <div class="bg-yellow-300 p-6 rounded-xl min-h-[120px] flex items-center justify-center animate-pulse-border border-4 border-yellow-400">
                        <h2 class="text-5xl font-bold text-gray-800" id="harga">
                            Rp <?php echo number_format($total_biaya, 0, ',', '.'); ?>
                        </h2>
                    </div>
                </div>
                <!-- Cara Pembayaran -->
                <div class="bg-gray-100 rounded-xl p-6 min-h-[250px] flex flex-col">
                    <h3 class="font-bold text-lg mb-4 text-gray-800 flex items-center">
                        <!-- Icon -->
                        <svg class="w-6 h-6 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Cara Pembayaran
                    </h3>
                    <ol class="space-y-4 flex-1">
                        <li class="flex items-center bg-white p-3 rounded-lg">
                            <span class="bg-yellow-500 text-white w-6 h-6 rounded-full flex items-center justify-center mr-3 font-bold">1</span>
                            <span class="text-gray-700">Scan QR Code menggunakan HP</span>
                        </li>
                        <li class="flex items-center bg-white p-3 rounded-lg">
                            <span class="bg-yellow-500 text-white w-6 h-6 rounded-full flex items-center justify-center mr-3 font-bold">2</span>
                            <span class="text-gray-700">Link mengarah ke halaman verifikasi "paid"</span>
                        </li>
                        <li class="flex items-center bg-white p-3 rounded-lg">
                            <span class="bg-yellow-500 text-white w-6 h-6 rounded-full flex items-center justify-center mr-3 font-bold">3</span>
                            <span class="text-gray-700">Pop-up "Pembayaran Berhasil" akan muncul di komputer</span>
                        </li>
                    </ol>
                </div>
            </div>

            <!-- Ada Kendala -->
            <button 
                onclick="window.open('https://wa.me/6283872839074?text=Halo,%20saya%20mengalami%20kendala%20dalam%20proses%20pembayaran.%20Dapatkah%20anda%20membantu?', '_blank')" 
                class="flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white text-lg font-bold py-4 px-6 rounded-xl shadow-lg transform transition-all duration-300 hover:scale-105 mt-8 w-full"
            >
                <!-- Icon -->
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                Ada Kendala?
            </button>
        </div>

        <!-- Kanan: QR Code dan Informasi -->
        <div class="flex-1 md:w-1/2 flex flex-col justify-between">
            <div class="space-y-8">
                <!-- QR Code -->
                <div class="rounded-xl min-h-[250px] flex flex-col items-center justify-center">
                    <div class="relative w-72 md:w-72">
                        <img 
                            src="../gambar/qris.png" 
                            alt="QR Code Pembayaran" 
                            class="w-full h-auto rounded-lg"
                        />
                    </div>
                </div>
            </div>

            <!-- Tombol Download QR Code -->
            <button 
                id="downloadQRButton"
                class="flex items-center justify-center gap-2 bg-gradient-to-r from-yellow-400 to-yellow-500 hover:from-yellow-500 hover:to-yellow-600 text-white text-lg font-bold py-4 px-6 rounded-xl shadow-lg transform transition-all duration-300 hover:scale-105 mt-8 w-full"
            >
                <!-- Icon Download -->
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                DOWNLOAD QR CODE
            </button>
        </div>
    </div>

    <!-- Popup Pesanan Gagal -->
    <div id="popupGagal" class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-lg p-8 w-80 text-center">
            <h2 class="text-xl font-bold mb-4 text-red-600">Pesanan Gagal</h2>
            <p class="text-gray-700 mb-6">Waktu pembayaran telah habis. Pesanan Anda tidak dapat diproses.</p>
            <button onclick="redirectToPage()" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">
                Oke
            </button>
        </div>
    </div>

    <!-- Popup Pembayaran Berhasil -->
    <div id="popupBerhasil" class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-lg p-8 w-80 text-center">
            <h2 class="text-xl font-bold mb-4 text-green-600">Pembayaran Berhasil</h2>
            <p class="text-gray-700 mb-6">Transaksi Anda telah berhasil diproses.</p>
            <button onclick="closeBerhasilPopup()" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">
                Oke
            </button>
        </div>
    </div>
</div>

<!-- SCRIPT TIMER DAN CEK PEMBAYARAN -->
<script>
    let paymentCheckInterval;

    // Fungsi untuk memeriksa status pembayaran (opsional, jika Anda pakai cekBayar.php)
    function checkPaymentStatus() {
        fetch('cekBayar.php', {
            credentials: 'same-origin'
        }) 
            .then(response => response.json())
            .then(data => {
                // Jika data.status_pembayaran terdeteksi 'paid', maka konfirmasi
                if (data.status_pembayaran === 'paid') {
                    stopPaymentCheck();
                    confirmPayment();
                }
            })
            .catch(error => {
                console.error('Error checking payment status:', error);
            });
    }

    // Mulai interval 10 detik
    function startPaymentCheck() {
        paymentCheckInterval = setInterval(checkPaymentStatus, 10000);
    }

    // Hentikan interval
    function stopPaymentCheck() {
        clearInterval(paymentCheckInterval);
    }

    // Setelah status 'paid', panggil POST ke pembayaran.php (confirm=1)
    function confirmPayment() {
        fetch('pembayaran.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'same-origin',
            body: 'confirm=1'
        })
        .then(response => response.json())
        .then(result => {
            console.log('Confirm Payment Response:', result);
            if (result.status === 'success') {
                alert('Pembayaran berhasil! Data berhasil disimpan.');
                window.location.href = 'riwayat.php';
            } else {
                alert('Terjadi kesalahan: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error confirming payment:', error);
        });
    }

    // Timer 5 menit
    function startTimer(duration, display) {
        let timer = duration, minutes, seconds;
        const countdown = setInterval(() => {
            minutes = parseInt(timer / 60, 10);
            seconds = parseInt(timer % 60, 10);

            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;

            display.textContent = minutes + ":" + seconds;

            if (--timer < 0) {
                clearInterval(countdown);
                display.textContent = "00:00";
                showPopupGagal();
                stopPaymentCheck();
            }
        }, 1000);
    }

    function showPopupGagal() {
        document.getElementById('popupGagal').classList.remove('hidden');
    }

    function redirectToPage() {
        window.location.href = 'penjemputan.php';
    }

    // Popup Berhasil
    function showPopupBerhasil() {
        document.getElementById('popupBerhasil').classList.remove('hidden');
    }
    function closeBerhasilPopup() {
        document.getElementById('popupBerhasil').classList.add('hidden');
        window.location.href = 'riwayat.php';
    }

    // Download QR
    function triggerDownload() {
        const link = document.createElement('a');
        link.href = '../gambar/qr_code.png';
        link.download = 'QR_Code.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Klik tombol "DOWNLOAD QR CODE"
    document.getElementById('downloadQRButton').addEventListener('click', function() {
        // Begitu tombol di-klik, langsung anggap user telah "membayar"
        fetch('pembayaran.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'same-origin',
            body: 'confirm=1'
        })
        .then(response => response.json())
        .then(result => {
            console.log('Download QR Confirm Payment Response:', result);
            if (result.status === 'success') {
                // Tampilkan popup "Pembayaran Berhasil"
                showPopupBerhasil();
                // Lakukan download QR
                triggerDownload();
            } else {
                alert('Terjadi kesalahan: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error confirming payment:', error);
            alert('Terjadi kesalahan saat memproses pembayaran.');
        });
    });

    window.onload = function() {
        const fiveMinutes = 5 * 60; 
        const display = document.querySelector('#timer');
        startTimer(fiveMinutes, display);

        // Jika Anda ingin polling status pembayaran dari server
        startPaymentCheck();
        checkPaymentStatus();
    };
</script>
</body>
</html>