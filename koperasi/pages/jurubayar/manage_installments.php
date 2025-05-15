<?php
require_once '../../config/config.php';

// Cek apakah user adalah jurubayar
$user = checkRole(ROLE_JURUBAYAR);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter
$filter = [
    'status' => isset($_GET['status']) ? $_GET['status'] : 'menunggu',
    'tanggal_mulai' => isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : '',
    'tanggal_akhir' => isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : ''
];

// Build query
$where = [];
$params = [];

if ($filter['status']) {
    $where[] = "c.status = ?";
    $params[] = $filter['status'];
}

if ($filter['tanggal_mulai']) {
    $where[] = "c.tanggal_jatuh_tempo >= ?";
    $params[] = $filter['tanggal_mulai'];
}

if ($filter['tanggal_akhir']) {
    $where[] = "c.tanggal_jatuh_tempo <= ?";
    $params[] = $filter['tanggal_akhir'];
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total records for pagination
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM cicilan c 
    JOIN pinjaman p ON c.pinjaman_id = p.id 
    JOIN users u ON p.user_id = u.id 
    $whereClause
");
$stmt->execute($params);
$totalRecords = $stmt->fetch()['total'];
$totalPages = ceil($totalRecords / $limit);

// Get cicilan data
$query = "
    SELECT c.*, 
           p.jumlah_pinjaman,
           p.tenor,
           u.nama as nama_anggota 
    FROM cicilan c 
    JOIN pinjaman p ON c.pinjaman_id = p.id 
    JOIN users u ON p.user_id = u.id 
    $whereClause 
    ORDER BY c.tanggal_jatuh_tempo ASC, c.nomor_cicilan ASC 
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$cicilan = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gold">Manajemen Cicilan</h2>
    </div>

    <!-- Filter Form -->
    <div class="bg-gray-800 p-4 rounded-lg">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Filter Status -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                <select name="status" 
                        class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
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
                <a href="manage_installments.php" 
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

    <!-- Cicilan Table -->
    <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="text-left">
                    <tr class="text-gray-400 bg-gray-900">
                        <th class="p-4">Jatuh Tempo</th>
                        <th class="p-4">Anggota</th>
                        <th class="p-4">Cicilan ke</th>
                        <th class="p-4">Jumlah</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Tgl Bayar</th>
                        <th class="p-4">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php if (empty($cicilan)): ?>
                    <tr>
                        <td colspan="7" class="p-4 text-center text-gray-500">
                            Tidak ada data cicilan
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($cicilan as $c): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-4"><?= date('d/m/Y', strtotime($c['tanggal_jatuh_tempo'])) ?></td>
                            <td class="p-4"><?= htmlspecialchars($c['nama_anggota']) ?></td>
                            <td class="p-4"><?= $c['nomor_cicilan'] ?> dari <?= $c['tenor'] ?></td>
                            <td class="p-4">Rp <?= number_format($c['jumlah_cicilan'], 0, ',', '.') ?></td>
                            <td class="p-4">
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
                            <td class="p-4">
                                <?= $c['tanggal_pembayaran'] ? date('d/m/Y', strtotime($c['tanggal_pembayaran'])) : '-' ?>
                            </td>
                            <td class="p-4">
                                <?php if ($c['status'] === 'menunggu'): ?>
                                    <a href="verify_installment.php?id=<?= $c['id'] ?>" 
                                       class="text-gold hover:text-yellow-400">
                                        Verifikasi
                                    </a>
                                <?php else: ?>
                                    <a href="view_installment.php?id=<?= $c['id'] ?>" 
                                       class="text-gold hover:text-yellow-400">
                                        Detail
                                    </a>
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
                        <a href="?page=<?= $page-1 ?>&status=<?= $filter['status'] ?>&tanggal_mulai=<?= $filter['tanggal_mulai'] ?>&tanggal_akhir=<?= $filter['tanggal_akhir'] ?>" 
                           class="text-gold hover:text-yellow-400">
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page+1 ?>&status=<?= $filter['status'] ?>&tanggal_mulai=<?= $filter['tanggal_mulai'] ?>&tanggal_akhir=<?= $filter['tanggal_akhir'] ?>" 
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
                                <a href="?page=<?= $i ?>&status=<?= $filter['status'] ?>&tanggal_mulai=<?= $filter['tanggal_mulai'] ?>&tanggal_akhir=<?= $filter['tanggal_akhir'] ?>" 
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
