<?php
require_once '../../config/config.php';

// Cek apakah user adalah admin
$user = checkRole(ROLE_ADMIN);

// Ambil ID anggota dari parameter URL
$memberId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$memberId) {
    flashMessage('ID Anggota tidak valid', 'error');
    redirect('/pages/admin/manage_members.php');
}

try {
    // Ambil data anggota
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE id = ? AND role = 'anggota'
    ");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();

    if (!$member) {
        flashMessage('Anggota tidak ditemukan', 'error');
        redirect('/pages/admin/manage_members.php');
    }

    // Ambil total simpanan
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN jenis_simpanan = 'pokok' THEN jumlah ELSE 0 END) as total_pokok,
            SUM(CASE WHEN jenis_simpanan = 'wajib' THEN jumlah ELSE 0 END) as total_wajib,
            SUM(CASE WHEN jenis_simpanan = 'sukarela' THEN jumlah ELSE 0 END) as total_sukarela
        FROM simpanan 
        WHERE user_id = ?
    ");
    $stmt->execute([$memberId]);
    $simpanan = $stmt->fetch();

    // Ambil riwayat simpanan terakhir
    $stmt = $pdo->prepare("
        SELECT * FROM simpanan 
        WHERE user_id = ? 
        ORDER BY tanggal DESC 
        LIMIT 5
    ");
    $stmt->execute([$memberId]);
    $riwayatSimpanan = $stmt->fetchAll();

    // Ambil data pinjaman aktif
    $stmt = $pdo->prepare("
        SELECT * FROM pinjaman 
        WHERE user_id = ? AND status IN ('pending', 'disetujui') 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$memberId]);
    $pinjamanAktif = $stmt->fetchAll();

} catch (PDOException $e) {
    flashMessage('Error: ' . $e->getMessage(), 'error');
    redirect('/pages/admin/manage_members.php');
}

require_once '../../includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gold">Detail Anggota</h2>
        <div class="space-x-4">
            <a href="edit_member.php?id=<?= $member['id'] ?>" 
               class="text-blue-400 hover:text-blue-300">
                Edit Anggota
            </a>
            <a href="manage_members.php" 
               class="text-gray-400 hover:text-gold">
                &larr; Kembali
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Informasi Pribadi -->
        <div class="bg-gray-800 rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold text-gold mb-4">Informasi Pribadi</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-400">Username</label>
                    <p class="text-white"><?= htmlspecialchars($member['username']) ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Nama Lengkap</label>
                    <p class="text-white"><?= htmlspecialchars($member['nama']) ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Email</label>
                    <p class="text-white"><?= htmlspecialchars($member['email'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">No. Telepon</label>
                    <p class="text-white"><?= htmlspecialchars($member['no_telepon'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Alamat</label>
                    <p class="text-white"><?= nl2br(htmlspecialchars($member['alamat'] ?? '-')) ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Tanggal Bergabung</label>
                    <p class="text-white"><?= date('d F Y', strtotime($member['created_at'])) ?></p>
                </div>
            </div>
        </div>

        <!-- Ringkasan Simpanan -->
        <div class="bg-gray-800 rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold text-gold mb-4">Ringkasan Simpanan</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-400">Simpanan Pokok</label>
                    <p class="text-white">Rp <?= number_format($simpanan['total_pokok'] ?? 0, 0, ',', '.') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Simpanan Wajib</label>
                    <p class="text-white">Rp <?= number_format($simpanan['total_wajib'] ?? 0, 0, ',', '.') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Simpanan Sukarela</label>
                    <p class="text-white">Rp <?= number_format($simpanan['total_sukarela'] ?? 0, 0, ',', '.') ?></p>
                </div>
                <div class="pt-2 border-t border-gray-700">
                    <label class="block text-sm text-gray-400">Total Simpanan</label>
                    <p class="text-xl font-bold text-gold">
                        Rp <?= number_format(($simpanan['total_pokok'] ?? 0) + ($simpanan['total_wajib'] ?? 0) + ($simpanan['total_sukarela'] ?? 0), 0, ',', '.') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Simpanan -->
    <div class="mt-6 bg-gray-800 rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-semibold text-gold mb-4">Riwayat Simpanan Terakhir</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="text-left">
                    <tr class="text-gray-400">
                        <th class="p-2">Tanggal</th>
                        <th class="p-2">Jenis</th>
                        <th class="p-2">Jumlah</th>
                        <th class="p-2">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php if (empty($riwayatSimpanan)): ?>
                    <tr>
                        <td colspan="4" class="p-2 text-center text-gray-500">
                            Belum ada data simpanan
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($riwayatSimpanan as $simpanan): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-2"><?= date('d/m/Y', strtotime($simpanan['tanggal'])) ?></td>
                            <td class="p-2"><?= ucfirst($simpanan['jenis_simpanan']) ?></td>
                            <td class="p-2">Rp <?= number_format($simpanan['jumlah'], 0, ',', '.') ?></td>
                            <td class="p-2"><?= htmlspecialchars($simpanan['keterangan'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pinjaman Aktif -->
    <div class="mt-6 bg-gray-800 rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-semibold text-gold mb-4">Pinjaman Aktif</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="text-left">
                    <tr class="text-gray-400">
                        <th class="p-2">Tanggal</th>
                        <th class="p-2">Jumlah</th>
                        <th class="p-2">Status</th>
                        <th class="p-2">Tenor</th>
                        <th class="p-2">Total Bayar</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php if (empty($pinjamanAktif)): ?>
                    <tr>
                        <td colspan="5" class="p-2 text-center text-gray-500">
                            Tidak ada pinjaman aktif
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($pinjamanAktif as $pinjaman): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-2"><?= date('d/m/Y', strtotime($pinjaman['tanggal_pengajuan'])) ?></td>
                            <td class="p-2">Rp <?= number_format($pinjaman['jumlah_pinjaman'], 0, ',', '.') ?></td>
                            <td class="p-2">
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
                            <td class="p-2"><?= $pinjaman['tenor'] ?> bulan</td>
                            <td class="p-2">Rp <?= number_format($pinjaman['total_bayar'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
