<?php
session_start();

// Set header untuk JSON response
header('Content-Type: application/json');

// Cek apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Anda belum login.',
        'icon' => 'error'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Cek apakah permintaan adalah POST dan memiliki parameter 'id_voucher' dan 'action'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_voucher']) && isset($_POST['action'])) {
    $id_voucher = $_POST['id_voucher'];
    $action = $_POST['action'];
    
    // Validasi action
    if ($action !== 'dipakai') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Aksi tidak dikenali.',
            'icon' => 'error'
        ]);
        exit;
    }
    
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
            'message' => 'Koneksi database gagal: ' . $e->getMessage(),
            'icon' => 'error'
        ]);
        exit;
    }
    
    try {
        // Cek apakah voucher milik user dan statusnya 'Tersedia'
        $queryCheck = "SELECT penukaran.id_tukar, rewards.id_reward, rewards.point_cost 
                       FROM penukaran 
                       JOIN rewards ON penukaran.id_reward = rewards.id_reward 
                       WHERE penukaran.id_user = :id_user 
                       AND penukaran.id_tukar = :id_voucher 
                       AND rewards.tipe_reward = 'voucher' 
                       AND penukaran.status = 'Tersedia' 
                       LIMIT 1";
        $stmtCheck = $conn->prepare($queryCheck);
        $stmtCheck->execute([
            ':id_user' => $user_id,
            ':id_voucher' => $id_voucher
        ]);
        $voucher = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($voucher) {
            // Ambil poin yang diperlukan untuk voucher
            $point_cost = $voucher['point_cost'];
            
            // Ambil poin pengguna
            $queryUser = "SELECT poin, ttl_voucher FROM users WHERE id = :id_user LIMIT 1";
            $stmtUser = $conn->prepare($queryUser);
            $stmtUser->execute([':id_user' => $user_id]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'User tidak ditemukan.',
                    'icon' => 'error'
                ]);
                exit;
            }
            
            // Cek apakah pengguna memiliki poin yang cukup
            if ($user['poin'] < $point_cost) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Poin Anda tidak cukup untuk menggunakan voucher ini.',
                    'icon' => 'error'
                ]);
                exit;
            }
            
            // Mulai transaksi
            $conn->beginTransaction();
            try {
                // Update status menjadi 'Dipakai'
                $queryUpdate = "UPDATE penukaran 
                                SET status = 'Dipakai' 
                                WHERE id_tukar = :id_voucher";
                $stmtUpdate = $conn->prepare($queryUpdate);
                $stmtUpdate->execute([
                    ':id_voucher' => $id_voucher
                ]);
                
                // Kurangi ttl_voucher di tabel users
                $queryKurangiTtl = "UPDATE users 
                                     SET ttl_voucher = ttl_voucher - 1, 
                                         poin = poin - :point_cost 
                                     WHERE id = :id_user";
                $stmtKurangiTtl = $conn->prepare($queryKurangiTtl);
                $stmtKurangiTtl->execute([
                    ':point_cost' => $point_cost,
                    ':id_user' => $user_id
                ]);
                
                // Commit transaksi
                $conn->commit();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Voucher berhasil digunakan. Poin Anda berkurang sebesar ' . number_format($point_cost, 0, ',', '.') . '.',
                    'icon' => 'check_circle' // Material Icons name for green check
                ]);
                exit;
            } catch (Exception $ex) {
                // Rollback jika ada error
                $conn->rollBack();
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Terjadi kesalahan saat menggunakan voucher: ' . $ex->getMessage(),
                    'icon' => 'error' // Material Icons name for red cross
                ]);
                exit;
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Voucher tidak ditemukan atau sudah digunakan.',
                'icon' => 'error' // Material Icons name for red cross
            ]);
            exit;
        }
        
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Terjadi kesalahan saat memproses voucher: ' . $e->getMessage(),
            'icon' => 'error' // Material Icons name for red cross
        ]);
        exit;
    }
    
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Permintaan tidak valid.',
        'icon' => 'error'
    ]);
    exit;
}
?>