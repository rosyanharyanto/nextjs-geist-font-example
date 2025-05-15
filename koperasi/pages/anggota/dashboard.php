<?php
require_once '../../config/config.php';

// Cek apakah user adalah anggota
$user = checkRole(ROLE_ANGGOTA);

try {
    // Ambil data simpanan
    $stmt = $pdo->prepare("
        SELECT jenis_simpanan, 
               SUM(jumlah) as total 
        FROM simpanan 
        WHERE user_id = ? 
        GROUP BY jenis_simpanan
    ");
    $stmt->execute([$user['id']]);
    $simpanan = [];
    while ($row = $stmt->fetch()) {
        $simpanan[$row['jenis_simpanan']] = $row['total'];
    }

    // Total simpanan
    $totalSimpanan = array_sum($simpanan);

    // Ambil pinjaman aktif
    $stmt = $pdo->prepare("
        SELECT * FROM pinjaman 
        WHERE user_id = ? 
        AND status IN ('pending', 'disetujui') 
        ORDER BY tanggal_pengajuan DESC
    ");
    $stmt->execute([$user['id']]);
    $pinjamanAktif = $stmt->fetchAll();

    // Ambil cicilan yang belum dibayar
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM cicilan c 
        JOIN pinjaman p ON c.pinjaman_id = p.id 
        WHERE p.user_id = ? 
        AND c.status IN ('belum_bayar', 'menunggu') 
        ORDER BY c.tanggal_jatuh_tempo ASC 
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $cicilanMenunggu = $stmt->fetchAll();

    // Ambil riwayat transaksi terakhir (simpanan dan cicilan)
    $stmt = $pdo->prepare("
        (SELECT 'simpanan' as tipe, 
                s.tanggal as tanggal, 
                s.jumlah, 
                s.jenis_simpanan as keterangan, 
                NULL as status
         FROM simpanan s 
         WHERE s.user_id = ?)
        UNION ALL
        (SELECT 'cicilan' as tipe, 
                c.tanggal_pembayaran as tanggal, 
                c.jumlah_cicilan as jumlah, 
                CONCAT('Cicilan ke-', c.nomor_cicilan) as keterangan,
                c.status
         FROM cicilan c 
         JOIN pinjaman p ON c.pinjaman_id = p.id 
         WHERE p.user_id = ? AND c.status != 'belum_bayar')
        ORDER BY tanggal DESC 
        LIMIT 5
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $riwayatTransaksi = $stmt->fetchAll();

} catch (PDOException $e) {
    flashMessage('Error: ' . $e->getMessage(), 'error');
}

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <h2 class="text-2xl font-bold text-gold">Dashboard Anggota</h2>

    <!-- Statistik Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Total Simpanan -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Total Simpanan</h3>
            <p class="text-3xl font-bold text-white">Rp <?= number_format($totalSimpanan, 0, ',', '.') ?></p>
            <div class="mt-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Pokok</span>
                    <span class="text-white">Rp <?= number_format($simpanan['pokok'] ?? 0, 0, ',', '.') ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Wajib</span>
                    <span class="text-white">Rp <?= number_format($simpanan['wajib'] ?? 0, 0, ',', '.') ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Sukarela</span>
                    <span class="text-white">Rp <?= number_format($simpanan['sukarela'] ?? 0, 0, ',', '.') ?></span>
                </div>
            </div>
        </div>

        <!-- Pinjaman Aktif -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Pinjaman Aktif</h3>
            <?php if (empty($pinjamanAktif)): ?>
                <p class="text-gray-400">Tidak ada pinjaman aktif</p>
            <?php else: ?>
                <?php foreach ($pinjamanAktif as $pinjaman): ?>
                    <div class="mb-4">
                        <p class="text-white">Rp <?= number_format($pinjaman['jumlah_pinjaman'], 0, ',', '.') ?></p>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Status</span>
                            <span class="px-2 py-1 rounded text-xs
                                <?php
                                echo $pinjaman['status'] === 'pending' ? 
                                    'bg-yellow-500 text-yellow-900' : 
                                    'bg-green-500 text-green-900';
                                ?>">
                                <?= ucfirst($pinjaman['status']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a href="pinjaman.php" class="text-sm text-gray-400 hover:text-gold">
                Ajukan pinjaman →
            </a>
        </div>

        <!-- Cicilan -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-gold text-lg font-semibold mb-2">Cicilan Menunggu</h3>
            <?php if (empty($cicilanMenunggu)): ?>
                <p class="text-gray-400">Tidak ada cicilan yang menunggu</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($cicilanMenunggu as $cicilan): ?>
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-white">Cicilan ke-<?= $cicilan['nomor_cicilan'] ?></p>
                                <p class="text-sm text-gray-400">
                                    Jatuh tempo: <?= date('d/m/Y', strtotime($cicilan['tanggal_jatuh_tempo'])) ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-white">Rp <?= number_format($cicilan['jumlah_cicilan'], 0, ',', '.') ?></p>
                                <span class="inline-block px-2 py-1 rounded text-xs
                                    <?= $cicilan['status'] === 'menunggu' ? 
                                        'bg-yellow-500 text-yellow-900' : 
                                        'bg-gray-500 text-white' ?>">
                                    <?= ucfirst(str_replace('_', ' ', $cicilan['status'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a href="cicilan.php" class="text-sm text-gray-400 hover:text-gold mt-4 inline-block">
                Lihat semua cicilan →
            </a>
        </div>
    </div>

    <!-- Riwayat Transaksi -->
    <div class="bg-gray-800 rounded-lg shadow-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gold">Riwayat Transaksi Terakhir</h3>
            <a href="riwayat.php" class="text-sm text-gray-400 hover:text-gold">
                Lihat semua →
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="text-left">
                    <tr class="text-gray-400">
                        <th class="p-2">Tanggal</th>
                        <th class="p-2">Tipe</th>
                        <th class="p-2">Keterangan</th>
                        <th class="p-2">Jumlah</th>
                        <th class="p-2">Status</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php if (empty($riwayatTransaksi)): ?>
                    <tr>
                        <td colspan="5" class="p-2 text-center text-gray-500">
                            Belum ada transaksi
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($riwayatTransaksi as $transaksi): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-2"><?= date('d/m/Y', strtotime($transaksi['tanggal'])) ?></td>
                            <td class="p-2"><?= ucfirst($transaksi['tipe']) ?></td>
                            <td class="p-2"><?= ucfirst($transaksi['keterangan']) ?></td>
                            <td class="p-2">Rp <?= number_format($transaksi['jumlah'], 0, ',', '.') ?></td>
                            <td class="p-2">
                                <?php if ($transaksi['status']): ?>
                                    <span class="px-2 py-1 rounded text-xs
                                        <?php
                                        switch($transaksi['status']) {
                                            case 'menunggu':
                                                echo 'bg-yellow-500 text-yellow-900';
                                                break;
                                            case 'dibayar':
                                                echo 'bg-green-500 text-green-900';
                                                break;
                                            case 'telat':
                                                echo 'bg-red-500 text-red-900';
                                                break;
                                        }
                                        ?>">
                                        <?= ucfirst(str_replace('_', ' ', $transaksi['status'])) ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
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
