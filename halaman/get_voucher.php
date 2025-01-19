<?php
session_start();

// Set header untuk JSON response
header('Content-Type: application/json');

// Cek apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Anda belum login.'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Koneksi ke database
$host     = "localhost";    // Sesuaikan
$username = "root";         // Sesuaikan
$password = "";             // Sesuaikan
$dbname   = "refresh_oil";  // Sesuaikan

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Setel mode error
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Koneksi database gagal: ' . $e->getMessage()
    ]);
    exit;
}

try {
    // Query untuk mengambil voucher yang dimiliki pengguna dengan status 'Tersedia'
    $query = "SELECT penukaran.id_tukar, rewards.nama_reward, rewards.foto_reward, rewards.point_cost, penukaran.tgl_tukar 
              FROM penukaran 
              JOIN rewards ON penukaran.id_reward = rewards.id_reward 
              WHERE penukaran.id_user = :id_user 
              AND rewards.tipe_reward = 'voucher' 
              AND penukaran.status = 'Tersedia'";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id_user' => $user_id]);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'vouchers' => $vouchers
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan saat mengambil data voucher: ' . $e->getMessage()
    ]);
    exit;
}
?>