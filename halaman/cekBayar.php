<?php
session_start();

// Opsional: Aktifkan CORS jika frontend dan backend beda domain
// header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

/**
 * Konfigurasi koneksi ke database
 */
$host   = "localhost";
$user   = "root";
$pass   = "";
$dbname = "refresh_oil";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Jika koneksi gagal, kembalikan JSON error
    echo json_encode([
        'status_pembayaran' => 'error',
        'message'           => 'Connection failed: ' . $e->getMessage()
    ]);
    exit;
}

/**
 * Pastikan user sudah login (opsional, bergantung pada alur aplikasi Anda).
 */
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status_pembayaran' => 'error_not_authenticated']);
    exit;
}

/**
 * Pastikan kita punya id_penjemputan di session penjemputan_data
 * (Diset di penjemputan.php / detailPembayaran.php / dsb.)
 */
if (!isset($_SESSION['penjemputan_data']['id_penjemputan'])) {
    echo json_encode(['status_pembayaran' => 'error_no_penjemputan_id']);
    exit;
}

// Ambil ID penjemputan dari session
$id_penjemputan = (int) $_SESSION['penjemputan_data']['id_penjemputan'];

/**
 * Ambil status_pembayaran dari tabel penjemputan
 * Pastikan tabel penjemputan memiliki kolom 'status_pembayaran'
 * (default: 'unpaid', atau diisi sesuai kebutuhan).
 */
try {
    $stmt = $pdo->prepare("
        SELECT status_pembayaran
        FROM penjemputan
        WHERE id_penjemputan = :id_penjemputan
        LIMIT 1
    ");
    $stmt->bindValue(':id_penjemputan', $id_penjemputan, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Jika data penjemputan ditemukan di DB
    if ($row) {
        // Cek kolom status_pembayaran
        $status = $row['status_pembayaran'] ?: 'unpaid';
        
        // Kembalikan status ke JavaScript
        echo json_encode(['status_pembayaran' => $status]);
    } else {
        // ID penjemputan tidak ditemukan di database
        echo json_encode(['status_pembayaran' => 'error_data_not_found']);
    }
} catch (Exception $e) {
    // Jika terjadi error saat query
    echo json_encode([
        'status_pembayaran' => 'error',
        'message'           => $e->getMessage()
    ]);
}