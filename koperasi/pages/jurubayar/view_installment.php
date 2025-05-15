<?php
require_once '../../config/config.php';

// Cek apakah user adalah jurubayar
$user = checkRole(ROLE_JURUBAYAR);

// Ambil ID cicilan dari parameter URL
$cicilanId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$cicilanId) {
    flashMessage('ID Cicilan tidak valid', 'error');
    redirect('/pages/jurubayar/manage_installments.php');
}

try {
    // Ambil detail cicilan
    $stmt = $pdo->prepare("
        SELECT c.*, 
               p.jumlah_pinjaman,
               p.tenor,
               p.total_bayar,
               p.status as status_pinjaman,
               u.nama as nama_anggota,
               u.no_telepon,
               u.alamat
        FROM cicilan c 
        JOIN pinjaman p ON c.pinjaman_id = p.id 
        JOIN users u ON p.user_id = u.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$cicilanId]);
    $cicilan = $stmt->fetch();
    
    if (!$cicilan) {
        flashMessage('Cicilan tidak ditemukan', 'error');
        redirect('/pages/jurubayar/manage_installments.php');
    }

    // Ambil riwayat cicilan dari pinjaman yang sama
    $stmt = $pdo->prepare("
        SELECT * FROM cicilan 
        WHERE pinjaman_id = ? 
        ORDER BY nomor_cicilan
    ");
    $stmt->execute([$cicilan['pinjaman_id']]);
    $riwayatCicilan = $stmt->fetchAll();

} catch (PDOException $e) {
    flashMessage('Error: ' . $e->getMessage(), 'error');
    redirect('/pages/jurubayar/manage_installments.php');
}

require_once '../../includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gold">Detail Cicilan</h2>
        <a href="manage_installments.php" 
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
                    <p class="text-white"><?= htmlspecialchars($cicilan['nama_anggota']) ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">No. Telepon</label>
                    <p class="text-white"><?= htmlspecialchars($cicilan['no_telepon'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Alamat</label>
                    <p class="text-white"><?= nl2br(htmlspecialchars($cicilan['alamat'] ?? '-')) ?></p>
                </div>
            </div>
        </div>

        <!-- Detail Cicilan -->
        <div class="bg-gray-800 rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold text-gold mb-4">Detail Cicilan</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-400">Status Pinjaman</label>
                    <span class="inline-block px-2 py-1 rounded text-xs mt-1
                        <?php
                        switch($cicilan['status_pinjaman']) {
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
                        <?= ucfirst($cicilan['status_pinjaman']) ?>
                    </span>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Cicilan ke</label>
                    <p class="text-white"><?= $cicilan['nomor_cicilan'] ?> dari <?= $cicilan['tenor'] ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Jumlah Cicilan</label>
                    <p class="text-white">Rp <?= number_format($cicilan['jumlah_cicilan'], 0, ',', '.') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Status Cicilan</label>
                    <span class="inline-block px-2 py-1 rounded text-xs mt-1
                        <?php
                        switch($cicilan['status']) {
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
                        <?= ucfirst(str_replace('_', ' ', $cicilan['status'])) ?>
                    </span>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Tanggal Jatuh Tempo</label>
                    <p class="text-white"><?= date('d F Y', strtotime($cicilan['tanggal_jatuh_tempo'])) ?></p>
                </div>
                <?php if ($cicilan['tanggal_pembayaran']): ?>
                <div>
                    <label class="block text-sm text-gray-400">Tanggal Pembayaran</label>
                    <p class="text-white"><?= date('d F Y', strtotime($cicilan['tanggal_pembayaran'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($cicilan['keterangan']): ?>
                <div>
                    <label class="block text-sm text-gray-400">Keterangan</label>
                    <p class="text-white"><?= nl2br(htmlspecialchars($cicilan['keterangan'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($cicilan['bukti_pembayaran']): ?>
    <!-- Bukti Pembayaran -->
    <div class="mt-6 bg-gray-800 rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-semibold text-gold mb-4">Bukti Pembayaran</h3>
        <div class="aspect-w-16 aspect-h-9">
            <img src="<?= htmlspecialchars($cicilan['bukti_pembayaran']) ?>" 
                 alt="Bukti Pembayaran" 
                 class="rounded-lg object-contain">
        </div>
    </div>
    <?php endif; ?>

    <!-- Riwayat Cicilan -->
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
                    <?php foreach ($riwayatCicilan as $c): ?>
                    <tr class="border-t border-gray-700 <?= $c['id'] === $cicilan['id'] ? 'bg-gray-700' : '' ?>">
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
</div>

<?php require_once '../../includes/footer.php'; ?>
