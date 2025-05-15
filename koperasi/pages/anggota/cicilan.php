<?php
require_once '../../config/config.php';

// Cek apakah user adalah anggota
$user = checkRole(ROLE_ANGGOTA);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter
$filter = [
    'status' => isset($_GET['status']) ? $_GET['status'] : '',
    'tanggal_mulai' => isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : '',
    'tanggal_akhir' => isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : ''
];

try {
    // Build query conditions
    $where = ["p.user_id = :user_id"];
    $params = [':user_id' => $user['id']];

    if ($filter['status']) {
        $where[] = "c.status = :status";
        $params[':status'] = $filter['status'];
    }

    if ($filter['tanggal_mulai']) {
        $where[] = "c.tanggal_jatuh_tempo >= :tanggal_mulai";
        $params[':tanggal_mulai'] = $filter['tanggal_mulai'];
    }

    if ($filter['tanggal_akhir']) {
        $where[] = "c.tanggal_jatuh_tempo <= :tanggal_akhir";
        $params[':tanggal_akhir'] = $filter['tanggal_akhir'];
    }

    $whereClause = implode(" AND ", $where);

    // Get total records for pagination
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM cicilan c 
        JOIN pinjaman p ON c.pinjaman_id = p.id 
        WHERE $whereClause
    ");
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);

    // Get cicilan data
    $query = "
        SELECT c.*, 
               p.jumlah_pinjaman,
               p.tenor,
               p.status as status_pinjaman
        FROM cicilan c 
        JOIN pinjaman p ON c.pinjaman_id = p.id 
        WHERE $whereClause 
        ORDER BY c.tanggal_jatuh_tempo ASC, c.nomor_cicilan ASC 
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($query);
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;
    $stmt->execute($params);
    $cicilan = $stmt->fetchAll();

    // Get summary statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_cicilan,
            SUM(CASE WHEN status = 'dibayar' THEN 1 ELSE 0 END) as cicilan_dibayar,
            SUM(CASE WHEN status = 'belum_bayar' THEN 1 ELSE 0 END) as cicilan_belum_bayar,
            SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) as cicilan_menunggu,
            SUM(CASE WHEN status = 'telat' THEN 1 ELSE 0 END) as cicilan_telat,
            SUM(CASE WHEN status = 'dibayar' THEN jumlah_cicilan ELSE 0 END) as total_dibayar
        FROM cicilan c 
        JOIN pinjaman p ON c.pinjaman_id = p.id 
        WHERE p.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $summary = $stmt->fetch();

} catch (PDOException $e) {
    flashMessage('Error: ' . $e->getMessage(), 'error');
}

require_once '../../includes/header.php';
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gold">Cicilan Saya</h2>
        <a href="dashboard.php" class="text-gray-400 hover:text-gold">
            &larr; Kembali ke Dashboard
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <!-- Total Cicilan -->
        <div class="bg-gray-800 p-4 rounded-lg">
            <p class="text-sm text-gray-400">Total Cicilan</p>
            <p class="text-2xl font-bold text-white"><?= number_format($summary['total_cicilan']) ?></p>
        </div>

        <!-- Cicilan Dibayar -->
        <div class="bg-gray-800 p-4 rounded-lg">
            <p class="text-sm text-gray-400">Sudah Dibayar</p>
            <p class="text-2xl font-bold text-green-500"><?= number_format($summary['cicilan_dibayar']) ?></p>
        </div>

        <!-- Cicilan Belum Bayar -->
        <div class="bg-gray-800 p-4 rounded-lg">
            <p class="text-sm text-gray-400">Belum Bayar</p>
            <p class="text-2xl font-bold text-gray-300"><?= number_format($summary['cicilan_belum_bayar']) ?></p>
        </div>

        <!-- Cicilan Menunggu -->
        <div class="bg-gray-800 p-4 rounded-lg">
            <p class="text-sm text-gray-400">Menunggu Verifikasi</p>
            <p class="text-2xl font-bold text-yellow-500"><?= number_format($summary['cicilan_menunggu']) ?></p>
        </div>

        <!-- Cicilan Telat -->
        <div class="bg-gray-800 p-4 rounded-lg">
            <p class="text-sm text-gray-400">Telat</p>
            <p class="text-2xl font-bold text-red-500"><?= number_format($summary['cicilan_telat']) ?></p>
        </div>

        <!-- Total Dibayar -->
        <div class="bg-gray-800 p-4 rounded-lg">
            <p class="text-sm text-gray-400">Total Dibayar</p>
            <p class="text-2xl font-bold text-white">Rp <?= number_format($summary['total_dibayar'], 0, ',', '.') ?></p>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="bg-gray-800 p-4 rounded-lg">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Filter Status -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
                    <option value="">Semua Status</option>
                    <option value="belum_bayar" <?= $filter['status'] == 'belum_bayar' ? 'selected' : '' ?>>Belum Bayar</option>
                    <option value="menunggu" <?= $filter['status'] == 'menunggu' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                    <option value="dibayar" <?= $filter['status'] == 'dibayar' ? 'selected' : '' ?>>Sudah Dibayar</option>
                    <option value="telat" <?= $filter['status'] == 'telat' ? 'selected' : '' ?>>Telat</option>
                </select>
            </div>

            <!-- Filter Tanggal -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Dari Tanggal</label>
                <input type="date" name="tanggal_mulai" value="<?= $filter['tanggal_mulai'] ?>"
                       class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Sampai Tanggal</label>
                <input type="date" name="tanggal_akhir" value="<?= $filter['tanggal_akhir'] ?>"
                       class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
            </div>

            <!-- Filter Buttons -->
            <div class="md:col-span-3 flex justify-end gap-4">
                <a href="cicilan.php" class="px-4 py-2 text-gray-400 hover:text-white">Reset</a>
                <button type="submit" class="bg-gold text-gray-900 px-6 py-2 rounded hover:bg-yellow-500 transition-colors">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Cicilan Table -->
    <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="text-left">
                    <tr class="text-gray-400 bg-gray-900">
                        <th class="p-4">Cicilan ke</th>
                        <th class="p-4">Jatuh Tempo</th>
                        <th class="p-4">Jumlah</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Tanggal Bayar</th>
                        <th class="p-4">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php if (empty($cicilan)): ?>
                    <tr>
                        <td colspan="6" class="p-4 text-center text-gray-500">
                            Tidak ada data cicilan
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($cicilan as $c): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-4"><?= $c['nomor_cicilan'] ?> dari <?= $c['tenor'] ?></td>
                            <td class="p-4"><?= date('d/m/Y', strtotime($c['tanggal_jatuh_tempo'])) ?></td>
                            <td class="p-4">Rp <?= number_format($c['jumlah_cicilan'], 0, ',', '.') ?></td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-xs <?php
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
                            <td class="p-4">
                                <?= $c['tanggal_pembayaran'] ? date('d/m/Y', strtotime($c['tanggal_pembayaran'])) : '-' ?>
                            </td>
                            <td class="p-4">
                                <?php if ($c['status'] === 'belum_bayar'): ?>
                                    <a href="bayar_cicilan.php?id=<?= $c['id'] ?>" class="text-gold hover:text-yellow-400">
                                        Bayar
                                    </a>
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

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="bg-gray-900 px-4 py-3 border-t border-gray-700">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-400">
                    Showing <?= ($offset + 1) ?> to <?= min($offset + $limit, $totalRecords) ?> 
                    of <?= $totalRecords ?> results
                </div>
                <div class="flex space-x-1">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>&status=<?= $filter['status'] ?>&tanggal_mulai=<?= $filter['tanggal_mulai'] ?>&tanggal_akhir=<?= $filter['tanggal_akhir'] ?>" 
                           class="px-3 py-1 rounded <?= $i === $page ? 'bg-gold text-gray-900' : 'text-gray-400 hover:text-white' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
