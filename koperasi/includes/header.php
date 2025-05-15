<?php
require_once __DIR__ . '/../config/config.php';
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koperasi Sejahtera</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        gold: '#d4af37',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="min-h-screen dark:bg-gray-900 dark:text-gray-100">
    <header class="bg-gray-800 shadow-lg">
        <nav class="container mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="<?= BASE_URL ?>" class="text-gold text-xl font-bold">Koperasi Sejahtera</a>
                </div>
                
                <?php if (isLoggedIn()): ?>
                <div class="flex items-center space-x-6">
                    <?php if ($currentUser['role'] === ROLE_ADMIN): ?>
                        <a href="<?= BASE_URL ?>/pages/admin/dashboard.php" class="text-gray-300 hover:text-gold">Dashboard</a>
                        <a href="<?= BASE_URL ?>/pages/admin/manage_members.php" class="text-gray-300 hover:text-gold">Anggota</a>
                        <a href="<?= BASE_URL ?>/pages/admin/manage_simpanan.php" class="text-gray-300 hover:text-gold">Simpanan</a>
                        <a href="<?= BASE_URL ?>/pages/admin/view_reports.php" class="text-gray-300 hover:text-gold">Laporan</a>
                    <?php elseif ($currentUser['role'] === ROLE_JURUBAYAR): ?>
                        <a href="<?= BASE_URL ?>/pages/jurubayar/dashboard.php" class="text-gray-300 hover:text-gold">Dashboard</a>
                        <a href="<?= BASE_URL ?>/pages/jurubayar/loan_approval.php" class="text-gray-300 hover:text-gold">Pinjaman</a>
                        <a href="<?= BASE_URL ?>/pages/jurubayar/manage_installments.php" class="text-gray-300 hover:text-gold">Cicilan</a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/pages/user/dashboard.php" class="text-gray-300 hover:text-gold">Dashboard</a>
                        <a href="<?= BASE_URL ?>/pages/user/loan_apply.php" class="text-gray-300 hover:text-gold">Ajukan Pinjaman</a>
                        <a href="<?= BASE_URL ?>/pages/user/loan_history.php" class="text-gray-300 hover:text-gold">Riwayat</a>
                    <?php endif; ?>
                    
                    <div class="relative ml-3">
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-300"><?= htmlspecialchars($currentUser['nama']) ?></span>
                            <a href="<?= BASE_URL ?>/logout.php" class="text-red-500 hover:text-red-400">Logout</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main class="container mx-auto px-4 py-8">
        <?php
        $flash = getFlashMessage();
        if ($flash): 
        ?>
        <div class="mb-4 p-4 rounded <?= $flash['type'] === 'success' ? 'bg-green-500' : 'bg-red-500' ?> text-white">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>
