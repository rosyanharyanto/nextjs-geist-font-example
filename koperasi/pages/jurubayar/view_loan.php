<?php
require_once '../../config/config.php';

// Cek apakah user adalah jurubayar
$user = checkRole(ROLE_JURUBAYAR);

// Ambil ID pinjaman dari parameter URL
$pinjamanId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$pinjamanId) {
    flashMessage('ID Pinjaman tidak valid', 'error');
    redirect('/pages/jurubayar/loan_approval.php');
}

try {
    // Ambil detail pinjaman
    $stmt = $pdo->prepare("
        SELECT p.*, 
               u.nama as nama_anggota,
               u.no_telepon,
               u.alamat,
               u2.nama as approved_by_name
        FROM pinjaman p 
        JOIN users u ON p.user_id = u.id 
        LEFT JOIN users u2 ON p.approved_by = u2.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pinjamanId]);
    $pinjaman = $stmt->fetch();
    
    if (!$pinjaman) {
        flashMessage('Pinjaman tidak ditemukan', 'error');
        redirect('/pages/jurubayar/loan_approval.php');
    }

    // Ambil data cicilan
    $stmt = $pdo->prepare("
        SELECT * FROM cicilan 
        WHERE pinjaman_id = ? 
        ORDER BY nomor_cicilan
    ");
    $stmt->execute([$pinjamanId]);
    $cicilan = $stmt->fetchAll();

} catch (PDOException $e) {
    flashMessage('Error: ' . $e->getMessage(), 'error');
    redirect('/pages/jurubayar/loan_approval.php');
}

require_once '../../includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gold">Detail Pinjaman</h2>
        <a href="loan_approval.php" 
           class="text-gray-400 hover:text-gold">
            &larr; Kembali
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Detail Peminjam -->
        <div class="bg-gray-800 rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold text-gold mb-4">Detail Peminjam</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-400">Nama Anggota</label>
                    <p class="text-white"><?= htmlspecialchars($pinjaman['nama_anggota']) ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">No. Telepon</label>
                    <p class="text-white"><?= htmlspecialchars($pinjaman['no_telepon'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Alamat</label>
                    <p class="text-white"><?= nl2br(htmlspecialchars($pinjaman['alamat'] ?? '-')) ?></p>
                </div>
            </div>
        </div>

        <!-- Detail Pinjaman -->
        <div class="bg-gray-800 rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold text-gold mb-4">Detail Pinjaman</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-400">Jumlah Pinjaman</label>
                    <p class="text-white">Rp <?= number_format($pinjaman['jumlah_pinjaman'], 0, ',', '.') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Status</label>
                    <span class="inline-block px-2 py-1 rounded text-xs mt-1
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
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Tenor</label>
                    <p class="text-white"><?= $pinjaman['tenor'] ?> bulan</p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Cicilan per Bulan</label>
                    <p class="text-white">Rp <?= number_format($pinjaman['jumlah_cicilan'], 0, ',', '.') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Total Bayar</label>
                    <p class="text-white">Rp <?= number_format($pinjaman['total_bayar'], 0, ',', '.') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Tanggal Pengajuan</label>
                    <p class="text-white"><?= date('d F Y', strtotime($pinjaman['tanggal_pengajuan'])) ?></p>
                </div>
                <?php if ($pinjaman['tanggal_persetujuan']): ?>
                <div>
                    <label class="block text-sm text-gray-400">Tanggal Persetujuan</label>
                    <p class="text-white"><?= date('d F Y', strtotime($pinjaman['tanggal_persetujuan'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($pinjaman['approved_by_name']): ?>
                <div>
                    <label class="block text-sm text-gray-400">Disetujui/Ditolak Oleh</label>
                    <p class="text-white"><?= htmlspecialchars($pinjaman['approved_by_name']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($pinjaman['keterangan']): ?>
                <div>
                    <label class="block text-sm text-gray-400">Keterangan</label>
                    <p class="text-white"><?= nl2br(htmlspecialchars($pinjaman['keterangan'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Riwayat Cicilan -->
    <?php if (!empty($cicilan)): ?>
    <div class="mt-6 bg-gray-800 rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-semibold text-gold mb-4">Riwayat Cicilan</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="text-left">
                    <tr class="text-gray-400">
                        <th class="p-2">Cicilan ke</th>
                        <th class="p-2">Jatuh Tempo</th>
                        <th class="p-2">Jumlah</th>
                        <th class="p-2">Status</th>
                        <th class="p-2">Tanggal Bayar</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php foreach ($cicilan as $c): ?>
                    <tr class="border-t border-gray-700">
                        <td class="p-2"><?= $c['nomor_cicilan'] ?></td>
                        <td class="p-2"><?= date('d/m/Y', strtotime($c['tanggal_jatuh_tempo'])) ?></td>
                        <td class="p-2">Rp <?= number_format($c['jumlah_cicilan'], 0, ',', '.') ?></td>
                        <td class="p-2">
                            <span class="px-2 py-1 rounded text-xs
                                <?php
                                switch($c['status']) {
                                    case 'belum_bayar':
                                        echo 'bg-gray-500 text-white';
                                        break;
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
                                <?= ucfirst(str_replace('_', ' ', $c['status'])) ?>
                            </span>
                        </td>
                        <td class="p-2">
                            <?= $c['tanggal_pembayaran'] ? date('d/m/Y', strtotime($c['tanggal_pembayaran'])) : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
