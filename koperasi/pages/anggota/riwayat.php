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
    'tipe' => isset($_GET['tipe']) ? $_GET['tipe'] : '',
    'tanggal_mulai' => isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : '',
    'tanggal_akhir' => isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : ''
];

try {
    // Build query conditions
    $where = ["s.user_id = :user_id"];
    $params = [':user_id' => $user['id']];

    if ($filter['tipe']) {
        $where[] = "s.tipe = :tipe";
        $params[':tipe'] = $filter['tipe'];
    }

    if ($filter['tanggal_mulai']) {
        $where[] = "s.tanggal >= :tanggal_mulai";
        $params[':tanggal_mulai'] = $filter['tanggal_mulai'];
    }

    if ($filter['tanggal_akhir']) {
        $where[] = "s.tanggal <= :tanggal_akhir";
        $params[':tanggal_akhir'] = $filter['tanggal_akhir'];
    }

    $whereClause = implode(" AND ", $where);

    // Get total records for pagination
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM (
            SELECT 'simpanan' as tipe, 
                   tanggal, 
                   jumlah, 
                   jenis_simpanan as keterangan, 
                   NULL as status,
                   user_id
            FROM simpanan
            UNION ALL
            SELECT 'cicilan' as tipe, 
                   c.tanggal_pembayaran as tanggal, 
                   c.jumlah_cicilan as jumlah, 
                   CONCAT('Cicilan ke-', c.nomor_cicilan) as keterangan,
                   c.status,
                   p.user_id
            FROM cicilan c 
            JOIN pinjaman p ON c.pinjaman_id = p.id 
            WHERE c.status != 'belum_bayar'
        ) s 
        WHERE $whereClause
    ");
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);

    // Get transactions
    $query = "
        SELECT * FROM (
            SELECT 'simpanan' as tipe, 
                   tanggal, 
                   jumlah, 
                   jenis_simpanan as keterangan, 
                   NULL as status,
                   user_id
            FROM simpanan
            UNION ALL
            SELECT 'cicilan' as tipe, 
                   c.tanggal_pembayaran as tanggal, 
                   c.jumlah_cicilan as jumlah, 
                   CONCAT('Cicilan ke-', c.nomor_cicilan) as keterangan,
                   c.status,
                   p.user_id
            FROM cicilan c 
            JOIN pinjaman p ON c.pinjaman_id = p.id 
            WHERE c.status != 'belum_bayar'
        ) s 
        WHERE $whereClause 
        ORDER BY tanggal DESC 
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($query);
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;
    $stmt->execute($params);
    $transaksi = $stmt->fetchAll();

} catch (PDOException $e) {
    flashMessage('Error: ' . $e->getMessage(), 'error');
}

require_once '../../includes/header.php';
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gold">Riwayat Transaksi</h2>
        <a href="dashboard.php" 
           class="text-gray-400 hover:text-gold">
            &larr; Kembali ke Dashboard
        </a>
    </div>

    <!-- Filter Form -->
    <div class="bg-gray-800 p-4 rounded-lg">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Filter Tipe -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Tipe Transaksi</label>
                <select name="tipe" 
                        class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
                    <option value="">Semua Transaksi</option>
                    <option value="simpanan" <?= $filter['tipe'] == 'simpanan' ? 'selected' : '' ?>>Simpanan</option>
                    <option value="cicilan" <?= $filter['tipe'] == 'cicilan' ? 'selected' : '' ?>>Cicilan</option>
                </select>
            </div>

            <!-- Filter Tanggal -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Dari Tanggal</label>
                <input type="date" 
                       name="tanggal_mulai" 
                       value="<?= $filter['tanggal_mulai'] ?>"
                       class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Sampai Tanggal</label>
                <input type="date" 
                       name="tanggal_akhir" 
                       value="<?= $filter['tanggal_akhir'] ?>"
                       class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
            </div>

            <!-- Filter Buttons -->
            <div class="md:col-span-3 flex justify-end gap-4">
                <a href="riwayat.php" 
                   class="px-4 py-2 text-gray-400 hover:text-white">
                    Reset
                </a>
                <button type="submit" 
                        class="bg-gold text-gray-900 px-6 py-2 rounded hover:bg-yellow-500 transition-colors">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Transactions Table -->
    <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="text-left">
                    <tr class="text-gray-400 bg-gray-900">
                        <th class="p-4">Tanggal</th>
                        <th class="p-4">Tipe</th>
                        <th class="p-4">Keterangan</th>
                        <th class="p-4">Jumlah</th>
                        <th class="p-4">Status</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php if (empty($transaksi)): ?>
                    <tr>
                        <td colspan="5" class="p-4 text-center text-gray-500">
                            Tidak ada transaksi
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($transaksi as $t): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-4"><?= date('d/m/Y', strtotime($t['tanggal'])) ?></td>
                            <td class="p-4"><?= ucfirst($t['tipe']) ?></td>
                            <td class="p-4">
                                <?php if ($t['tipe'] === 'simpanan'): ?>
                                    Simpanan <?= ucfirst($t['keterangan']) ?>
                                <?php else: ?>
                                    <?= $t['keterangan'] ?>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">Rp <?= number_format($t['jumlah'], 0, ',', '.') ?></td>
                            <td class="p-4">
                                <?php if ($t['status']): ?>
                                    <span class="px-2 py-1 rounded text-xs
                                        <?php
                                        switch($t['status']) {
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
                                        <?= ucfirst(str_replace('_', ' ', $t['status'])) ?>
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

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="bg-gray-900 px-4 py-3 border-t border-gray-700 sm:px-6">
            <div class="flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&tipe=<?= $filter['tipe'] ?>&tanggal_mulai=<?= $filter['tanggal_mulai'] ?>&tanggal_akhir=<?= $filter['tanggal_akhir'] ?>" 
                           class="text-gold hover:text-yellow-400">
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page+1 ?>&tipe=<?= $filter['tipe'] ?>&tanggal_mulai=<?= $filter['tanggal_mulai'] ?>&tanggal_akhir=<?= $filter['tanggal_akhir'] ?>" 
                           class="text-gold hover:text-yellow-400">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-400">
                            Showing 
                            <span class="font-medium"><?= ($offset + 1) ?></span>
                            to 
                            <span class="font-medium">
                                <?= min($offset + $limit, $totalRecords) ?>
                            </span>
                            of 
                            <span class="font-medium"><?= $totalRecords ?></span>
                            results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" 
                             aria-label="Pagination">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?= $i ?>&tipe=<?= $filter['tipe'] ?>&tanggal_mulai=<?= $filter['tanggal_mulai'] ?>&tanggal_akhir=<?= $filter['tanggal_akhir'] ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-700 
                                          <?= $i === $page ? 'bg-gray-700 text-gold' : 'bg-gray-800 text-gray-400 hover:bg-gray-700' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
