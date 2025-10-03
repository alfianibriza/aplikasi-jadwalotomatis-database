<?php
// Pengaturan koneksi database
$host = 'localhost'; // Host database Anda, biasanya 'localhost' untuk XAMPP
$username = 'root'; // Username database, default 'root' untuk XAMPP
$password = ''; // Password database, default kosong untuk XAMPP
$database = 'penjadwalan_otomatis'; // Nama database yang sudah Anda buat

// Membuat koneksi ke database
$conn = new mysqli($host, $username, $password, $database);

// Memeriksa koneksi
if ($conn->connect_error) {
    die("Koneksi Gagal: " . $conn->connect_error);
}

// Mengatur charset ke utf8mb4 untuk mendukung karakter yang lebih luas
$conn->set_charset("utf8mb4");
?>
