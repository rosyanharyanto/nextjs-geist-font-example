-- Buat database jika belum ada
CREATE DATABASE IF NOT EXISTS koperasi_db;
USE koperasi_db;

-- Tabel users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    role ENUM('admin', 'jurubayar', 'anggota') NOT NULL,
    email VARCHAR(100) UNIQUE,
    no_telepon VARCHAR(15),
    alamat TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel simpanan
CREATE TABLE IF NOT EXISTS simpanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    jenis_simpanan ENUM('pokok', 'wajib', 'sukarela') NOT NULL,
    jumlah DECIMAL(15,2) NOT NULL,
    tanggal DATE NOT NULL,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabel pinjaman
CREATE TABLE IF NOT EXISTS pinjaman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    jumlah_pinjaman DECIMAL(15,2) NOT NULL,
    jumlah_cicilan DECIMAL(15,2) NOT NULL,
    tenor INT NOT NULL COMMENT 'Lama cicilan dalam bulan',
    bunga DECIMAL(5,2) NOT NULL COMMENT 'Persentase bunga',
    total_bayar DECIMAL(15,2) NOT NULL,
    status ENUM('pending', 'disetujui', 'ditolak', 'lunas') NOT NULL DEFAULT 'pending',
    tanggal_pengajuan DATE NOT NULL,
    tanggal_persetujuan DATE,
    keterangan TEXT,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabel cicilan
CREATE TABLE IF NOT EXISTS cicilan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pinjaman_id INT NOT NULL,
    nomor_cicilan INT NOT NULL COMMENT 'Cicilan ke-n',
    jumlah_cicilan DECIMAL(15,2) NOT NULL,
    tanggal_jatuh_tempo DATE NOT NULL,
    status ENUM('belum_bayar', 'menunggu', 'dibayar', 'telat') NOT NULL DEFAULT 'belum_bayar',
    tanggal_pembayaran DATE,
    bukti_pembayaran VARCHAR(255),
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pinjaman_id) REFERENCES pinjaman(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Insert default admin user
INSERT INTO users (username, password, nama, role, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 'admin@koperasi.com');

-- Insert default jurubayar
INSERT INTO users (username, password, nama, role, email) VALUES 
('jurubayar', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juru Bayar', 'jurubayar', 'jurubayar@koperasi.com');

-- Insert sample anggota
INSERT INTO users (username, password, nama, role, email, no_telepon, alamat) VALUES 
('anggota1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Anggota Satu', 'anggota', 'anggota1@mail.com', '08123456789', 'Jl. Contoh No. 1'),
('anggota2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Anggota Dua', 'anggota', 'anggota2@mail.com', '08234567890', 'Jl. Contoh No. 2');

-- Note: Password default untuk semua user adalah 'password'
