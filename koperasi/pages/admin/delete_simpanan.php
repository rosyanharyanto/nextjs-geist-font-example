<?php
require_once '../../config/config.php';

// Cek apakah user adalah admin
$user = checkRole(ROLE_ADMIN);

// Cek method dan ID
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    flashMessage('Invalid request', 'error');
    redirect('/pages/admin/manage_simpanan.php');
}

$simpananId = (int)$_POST['id'];

try {
    // Cek apakah simpanan ada
    $stmt = $pdo->prepare("SELECT id FROM simpanan WHERE id = ?");
    $stmt->execute([$simpananId]);
    
    if (!$stmt->fetch()) {
        flashMessage('Data simpanan tidak ditemukan', 'error');
        redirect('/pages/admin/manage_simpanan.php');
    }

    // Hapus simpanan
    $stmt = $pdo->prepare("DELETE FROM simpanan WHERE id = ?");
    $stmt->execute([$simpananId]);

    flashMessage('Data simpanan berhasil dihapus', 'success');
    
} catch (PDOException $e) {
    flashMessage('Gagal menghapus data: ' . $e->getMessage(), 'error');
}

redirect('/pages/admin/manage_simpanan.php');
