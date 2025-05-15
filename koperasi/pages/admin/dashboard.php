<?php
require_once '../../config/config.php';

// Cek apakah user adalah admin
$user = checkRole(ROLE_ADMIN);

// Ambil statistik untuk dashboard
try {
    // Total Anggota
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'anggota'");
    $totalAnggota = $stmt->fetch()['total'];

    // Total Simpanan
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM simpanan");
    $totalSimpanan = $stmt->fetch()['total'];

    // Total Pinjaman Aktif
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pinjaman WHERE status = 'disetujui'");
    $totalPinjamanAktif = $stmt->fetch()['total'];

    // Total Pinjaman Pending
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pinjaman WHERE status = 'pending'");
    $totalPinjamanPending = $stmt->fetch()['total'];

    // Pinjaman Terbaru (5 terakhir)
    $stmt = $pdo->query("
        SELECT p.*, u.nama as nama_anggota 
        FROM pinjaman p 
        JOIN users u ON p.user_id = u.id 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $pinjamanTerbaru = $stmt->fetchAll();

} catch (PDOException $e) {
    flashMessage('Terjadi kesalahan saat mengambil data: ' . $e->getMessage(), 'error');
}

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <!-- Statistik Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Anggota -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Total Anggota</h3>
            <p class="text-3xl font-bold text-white"><?= number_format($totalAnggota) ?></p>
            <a href="manage_members.php" class="text-sm text-gray-400 hover:text-gold">Lihat detail →</a>
        </div>

        <!-- Total Simpanan -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Total Simpanan</h3>
            <p class="text-3xl font-bold text-white">Rp <?= number_format($totalSimpanan, 0, ',', '.') ?></p>
            <a href="manage_simpanan.php" class="text-sm text-gray-400 hover:text-gold">Lihat detail →</a>
        </div>

        <!-- Pinjaman Aktif -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Pinjaman Aktif</h3>
            <p class="text-3xl font-bold text-white"><?= number_format($totalPinjamanAktif) ?></p>
            <span class="text-sm text-gray-400">Sedang berjalan</span>
        </div>

        <!-- Pinjaman Pending -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Pinjaman Pending</h3>
            <p class="text-3xl font-bold text-white"><?= number_format($totalPinjamanPending) ?></p>
            <span class="text-sm text-gray-400">Menunggu persetujuan</span>
        </div>
    </div>

    <!-- Pinjaman Terbaru -->
    <div class="bg-gray-800 rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-semibold text-gold mb-4">Pinjaman Terbaru</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="text-gold">
                    <tr>
                        <th class="p-3">Anggota</th>
                        <th class="p-3">Jumlah</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Tanggal</th>
                        <th class="p-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php foreach ($pinjamanTerbaru as $pinjaman): ?>
                    <tr class="border-t border-gray-700">
                        <td class="p-3"><?= htmlspecialchars($pinjaman['nama_anggota']) ?></td>
                        <td class="p-3">Rp <?= number_format($pinjaman['jumlah_pinjaman'], 0, ',', '.') ?></td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded text-xs
                                <?php
                                switch($pinjaman['status']) {
                                    case 'pending':
                                        echo 'bg-yellow-500 text-yellow-900';
                                        break;
                                    case 'disetujui':
                                        echo 'bg-green-500 text-green-900';
                                        break;
                                    case 'ditolak':
                                        echo 'bg-red-500 text-red-900';
                                        break;
                                    case 'lunas':
                                        echo 'bg-blue-500 text-blue-900';
                                        break;
                                }
                                ?>">
                                <?= ucfirst($pinjaman['status']) ?>
                            </span>
                        </td>
                        <td class="p-3"><?= date('d/m/Y', strtotime($pinjaman['tanggal_pengajuan'])) ?></td>
                        <td class="p-3">
                            <a href="view_loan.php?id=<?= $pinjaman['id'] ?>" 
                               class="text-gold hover:text-yellow-400">Detail</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="add_member.php" 
           class="bg-gray-800 p-6 rounded-lg shadow-lg hover:bg-gray-700 transition-colors">
            <h3 class="text-gold font-semibold mb-2">Tambah Anggota Baru</h3>
            <p class="text-gray-400">Daftarkan anggota baru ke sistem</p>
        </a>

        <a href="add_simpanan.php" 
           class="bg-gray-800 p-6 rounded-lg shadow-lg hover:bg-gray-700 transition-colors">
            <h3 class="text-gold font-semibold mb-2">Catat Simpanan</h3>
            <p class="text-gray-400">Tambah data simpanan anggota</p>
        </a>

        <a href="view_reports.php" 
           class="bg-gray-800 p-6 rounded-lg shadow-lg hover:bg-gray-700 transition-colors">
            <h3 class="text-gold font-semibold mb-2">Lihat Laporan</h3>
            <p class="text-gray-400">Akses laporan dan statistik</p>
        </a>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
