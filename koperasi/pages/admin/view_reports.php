<?php
require_once '../../config/config.php';

// Cek apakah user adalah admin
$user = checkRole(ROLE_ADMIN);

try {
    // Statistik Umum
    $stats = [
        'total_anggota' => 0,
        'total_simpanan' => 0,
        'total_pinjaman' => 0,
        'total_pinjaman_aktif' => 0
    ];

    // Total Anggota
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'anggota'");
    $stats['total_anggota'] = $stmt->fetchColumn();

    // Total Simpanan
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) FROM simpanan");
    $stats['total_simpanan'] = $stmt->fetchColumn();

    // Total Pinjaman
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah_pinjaman), 0) FROM pinjaman WHERE status = 'disetujui'");
    $stats['total_pinjaman'] = $stmt->fetchColumn();

    // Total Pinjaman Aktif
    $stmt = $pdo->query("SELECT COUNT(*) FROM pinjaman WHERE status = 'disetujui'");
    $stats['total_pinjaman_aktif'] = $stmt->fetchColumn();

    // Rincian Simpanan per Jenis
    $stmt = $pdo->query("
        SELECT jenis_simpanan, 
               COUNT(*) as total_transaksi,
               COALESCE(SUM(jumlah), 0) as total_jumlah 
        FROM simpanan 
        GROUP BY jenis_simpanan
    ");
    $simpananPerJenis = $stmt->fetchAll();

    // Rincian Pinjaman per Status
    $stmt = $pdo->query("
        SELECT status, 
               COUNT(*) as total,
               COALESCE(SUM(jumlah_pinjaman), 0) as total_jumlah 
        FROM pinjaman 
        GROUP BY status
    ");
    $pinjamanPerStatus = $stmt->fetchAll();

    // Top 5 Anggota dengan Simpanan Terbesar
    $stmt = $pdo->query("
        SELECT u.nama, 
               COALESCE(SUM(s.jumlah), 0) as total_simpanan 
        FROM users u 
        LEFT JOIN simpanan s ON u.id = s.user_id 
        WHERE u.role = 'anggota' 
        GROUP BY u.id, u.nama 
        ORDER BY total_simpanan DESC 
        LIMIT 5
    ");
    $topSimpanan = $stmt->fetchAll();

    // Top 5 Anggota dengan Pinjaman Terbesar
    $stmt = $pdo->query("
        SELECT u.nama, 
               COALESCE(SUM(p.jumlah_pinjaman), 0) as total_pinjaman 
        FROM users u 
        LEFT JOIN pinjaman p ON u.id = p.user_id 
        WHERE u.role = 'anggota' AND p.status = 'disetujui' 
        GROUP BY u.id, u.nama 
        ORDER BY total_pinjaman DESC 
        LIMIT 5
    ");
    $topPinjaman = $stmt->fetchAll();

} catch (PDOException $e) {
    flashMessage('Error mengambil data laporan: ' . $e->getMessage(), 'error');
}

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <h2 class="text-2xl font-bold text-gold">Laporan Koperasi</h2>

    <!-- Statistik Umum -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Anggota -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Total Anggota</h3>
            <p class="text-3xl font-bold text-white"><?= number_format($stats['total_anggota']) ?></p>
            <p class="text-sm text-gray-400">Anggota aktif</p>
        </div>

        <!-- Total Simpanan -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Total Simpanan</h3>
            <p class="text-3xl font-bold text-white">Rp <?= number_format($stats['total_simpanan'], 0, ',', '.') ?></p>
            <p class="text-sm text-gray-400">Semua jenis simpanan</p>
        </div>

        <!-- Total Pinjaman -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Total Pinjaman</h3>
            <p class="text-3xl font-bold text-white">Rp <?= number_format($stats['total_pinjaman'], 0, ',', '.') ?></p>
            <p class="text-sm text-gray-400">Pinjaman disetujui</p>
        </div>

        <!-- Pinjaman Aktif -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Pinjaman Aktif</h3>
            <p class="text-3xl font-bold text-white"><?= number_format($stats['total_pinjaman_aktif']) ?></p>
            <p class="text-sm text-gray-400">Sedang berjalan</p>
        </div>
    </div>

    <!-- Rincian Simpanan dan Pinjaman -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Rincian Simpanan per Jenis -->
        <div class="bg-gray-800 rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold text-gold mb-4">Rincian Simpanan per Jenis</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="text-left">
                        <tr class="text-gray-400">
                            <th class="p-2">Jenis</th>
                            <th class="p-2">Transaksi</th>
                            <th class="p-2">Total</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php foreach ($simpananPerJenis as $s): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-2"><?= ucfirst($s['jenis_simpanan']) ?></td>
                            <td class="p-2"><?= number_format($s['total_transaksi']) ?></td>
                            <td class="p-2">Rp <?= number_format($s['total_jumlah'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Rincian Pinjaman per Status -->
        <div class="bg-gray-800 rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold text-gold mb-4">Rincian Pinjaman per Status</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="text-left">
                        <tr class="text-gray-400">
                            <th class="p-2">Status</th>
                            <th class="p-2">Jumlah</th>
                            <th class="p-2">Total</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php foreach ($pinjamanPerStatus as $p): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-2"><?= ucfirst($p['status']) ?></td>
                            <td class="p-2"><?= number_format($p['total']) ?></td>
                            <td class="p-2">Rp <?= number_format($p['total_jumlah'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Anggota -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Top 5 Simpanan -->
        <div class="bg-gray-800 rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold text-gold mb-4">Top 5 Simpanan Terbesar</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="text-left">
                        <tr class="text-gray-400">
                            <th class="p-2">Anggota</th>
                            <th class="p-2">Total Simpanan</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php foreach ($topSimpanan as $ts): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-2"><?= htmlspecialchars($ts['nama']) ?></td>
                            <td class="p-2">Rp <?= number_format($ts['total_simpanan'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top 5 Pinjaman -->
        <div class="bg-gray-800 rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold text-gold mb-4">Top 5 Pinjaman Terbesar</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="text-left">
                        <tr class="text-gray-400">
                            <th class="p-2">Anggota</th>
                            <th class="p-2">Total Pinjaman</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php foreach ($topPinjaman as $tp): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-2"><?= htmlspecialchars($tp['nama']) ?></td>
                            <td class="p-2">Rp <?= number_format($tp['total_pinjaman'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
