<?php
require_once '../../config/config.php';

// Cek apakah user adalah jurubayar
$user = checkRole(ROLE_JURUBAYAR);

try {
    // Ambil pengajuan pinjaman yang pending
    $stmt = $pdo->query("
        SELECT p.*, u.nama as nama_anggota 
        FROM pinjaman p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.status = 'pending' 
        ORDER BY p.tanggal_pengajuan DESC 
        LIMIT 5
    ");
    $pinjamanPending = $stmt->fetchAll();

    // Ambil cicilan yang menunggu verifikasi
    $stmt = $pdo->query("
        SELECT c.*, p.jumlah_pinjaman, u.nama as nama_anggota 
        FROM cicilan c 
        JOIN pinjaman p ON c.pinjaman_id = p.id 
        JOIN users u ON p.user_id = u.id 
        WHERE c.status = 'menunggu' 
        ORDER BY c.tanggal_jatuh_tempo ASC 
        LIMIT 5
    ");
    $cicilanPending = $stmt->fetchAll();

    // Statistik
    // Total pinjaman pending
    $stmt = $pdo->query("SELECT COUNT(*) FROM pinjaman WHERE status = 'pending'");
    $totalPinjamanPending = $stmt->fetchColumn();

    // Total cicilan menunggu verifikasi
    $stmt = $pdo->query("SELECT COUNT(*) FROM cicilan WHERE status = 'menunggu'");
    $totalCicilanPending = $stmt->fetchColumn();

    // Total pinjaman disetujui bulan ini
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM pinjaman 
        WHERE status = 'disetujui' 
        AND MONTH(tanggal_persetujuan) = MONTH(CURRENT_DATE) 
        AND YEAR(tanggal_persetujuan) = YEAR(CURRENT_DATE)
    ");
    $pinjamanDisetujuiBulanIni = $stmt->fetchColumn();

    // Total cicilan diverifikasi bulan ini
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM cicilan 
        WHERE status = 'dibayar' 
        AND MONTH(tanggal_pembayaran) = MONTH(CURRENT_DATE) 
        AND YEAR(tanggal_pembayaran) = YEAR(CURRENT_DATE)
    ");
    $cicilanDibayarBulanIni = $stmt->fetchColumn();

} catch (PDOException $e) {
    flashMessage('Error: ' . $e->getMessage(), 'error');
}

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <h2 class="text-2xl font-bold text-gold">Dashboard Jurubayar</h2>

    <!-- Statistik Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Pinjaman Pending -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Pinjaman Pending</h3>
            <p class="text-3xl font-bold text-white"><?= number_format($totalPinjamanPending) ?></p>
            <p class="text-sm text-gray-400">Menunggu persetujuan</p>
        </div>

        <!-- Cicilan Pending -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Cicilan Pending</h3>
            <p class="text-3xl font-bold text-white"><?= number_format($totalCicilanPending) ?></p>
            <p class="text-sm text-gray-400">Menunggu verifikasi</p>
        </div>

        <!-- Pinjaman Disetujui Bulan Ini -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Pinjaman Disetujui</h3>
            <p class="text-3xl font-bold text-white"><?= number_format($pinjamanDisetujuiBulanIni) ?></p>
            <p class="text-sm text-gray-400">Bulan ini</p>
        </div>

        <!-- Cicilan Diverifikasi Bulan Ini -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Cicilan Diverifikasi</h3>
            <p class="text-3xl font-bold text-white"><?= number_format($cicilanDibayarBulanIni) ?></p>
            <p class="text-sm text-gray-400">Bulan ini</p>
        </div>
    </div>

    <!-- Pengajuan Pinjaman Terbaru -->
    <div class="bg-gray-800 rounded-lg shadow-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gold">Pengajuan Pinjaman Terbaru</h3>
            <a href="loan_approval.php" class="text-sm text-gray-400 hover:text-gold">
                Lihat semua →
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="text-left">
                    <tr class="text-gray-400">
                        <th class="p-2">Tanggal</th>
                        <th class="p-2">Anggota</th>
                        <th class="p-2">Jumlah</th>
                        <th class="p-2">Tenor</th>
                        <th class="p-2">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php if (empty($pinjamanPending)): ?>
                    <tr>
                        <td colspan="5" class="p-2 text-center text-gray-500">
                            Tidak ada pengajuan pinjaman yang pending
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($pinjamanPending as $pinjaman): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-2"><?= date('d/m/Y', strtotime($pinjaman['tanggal_pengajuan'])) ?></td>
                            <td class="p-2"><?= htmlspecialchars($pinjaman['nama_anggota']) ?></td>
                            <td class="p-2">Rp <?= number_format($pinjaman['jumlah_pinjaman'], 0, ',', '.') ?></td>
                            <td class="p-2"><?= $pinjaman['tenor'] ?> bulan</td>
                            <td class="p-2">
                                <a href="review_loan.php?id=<?= $pinjaman['id'] ?>" 
                                   class="text-gold hover:text-yellow-400">
                                    Review
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Cicilan Menunggu Verifikasi -->
    <div class="bg-gray-800 rounded-lg shadow-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gold">Cicilan Menunggu Verifikasi</h3>
            <a href="manage_installments.php" class="text-sm text-gray-400 hover:text-gold">
                Lihat semua →
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="text-left">
                    <tr class="text-gray-400">
                        <th class="p-2">Jatuh Tempo</th>
                        <th class="p-2">Anggota</th>
                        <th class="p-2">Cicilan ke</th>
                        <th class="p-2">Jumlah</th>
                        <th class="p-2">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php if (empty($cicilanPending)): ?>
                    <tr>
                        <td colspan="5" class="p-2 text-center text-gray-500">
                            Tidak ada cicilan yang menunggu verifikasi
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($cicilanPending as $cicilan): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-2"><?= date('d/m/Y', strtotime($cicilan['tanggal_jatuh_tempo'])) ?></td>
                            <td class="p-2"><?= htmlspecialchars($cicilan['nama_anggota']) ?></td>
                            <td class="p-2"><?= $cicilan['nomor_cicilan'] ?></td>
                            <td class="p-2">Rp <?= number_format($cicilan['jumlah_cicilan'], 0, ',', '.') ?></td>
                            <td class="p-2">
                                <a href="verify_installment.php?id=<?= $cicilan['id'] ?>" 
                                   class="text-gold hover:text-yellow-400">
                                    Verifikasi
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
