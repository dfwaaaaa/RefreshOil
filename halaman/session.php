<?php
// Menampilkan lokasi penyimpanan session
echo 'Session save path: ' . session_save_path();

// Memulai session
session_start();

// Menampilkan ID session
echo '<br>ID Session: ' . session_id();
?>