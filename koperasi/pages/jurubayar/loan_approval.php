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
    'status' => isset($_GET['status']) ? $_GET['status'] : 'pending',
    'tanggal_mulai' => isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : '',
    'tanggal_akhir' => isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : ''
];

// Build query
$where = [];
$params = [];

if ($filter['status']) {
    $where[] = "p.status = ?";
    $params[] = $filter['status'];
}

if ($filter['tanggal_mulai']) {
    $where[] = "p.tanggal_pengajuan >= ?";
    $params[] = $filter['tanggal_mulai'];
}

if ($filter['tanggal_akhir']) {
    $where[] = "p.tanggal_pengajuan <= ?";
    $params[] = $filter['tanggal_akhir'];
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total records for pagination
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM pinjaman p 
    $whereClause
");
$stmt->execute($params);
$totalRecords = $stmt->fetch()['total'];
$totalPages = ceil($totalRecords / $limit);

// Get pinjaman data
$query = "
    SELECT p.*, u.nama as nama_anggota 
    FROM pinjaman p 
    JOIN users u ON p.user_id = u.id 
    $whereClause 
    ORDER BY p.tanggal_pengajuan DESC 
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pinjaman = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gold">Persetujuan Pinjaman</h2>
    </div>

    <!-- Filter Form -->
    <div class="bg-gray-800 p-4 rounded-lg">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Filter Status -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                <select name="status" 
                        class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
                    <option value="pending" <?= $filter['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="disetujui" <?= $filter['status'] == 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                    <option value="ditolak" <?= $filter['status'] == 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                    <option value="lunas" <?= $filter['status'] == 'lunas' ? 'selected' : '' ?>>Lunas</option>
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
                <a href="loan_approval.php" 
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

    <!-- Pinjaman Table -->
    <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="text-left">
                    <tr class="text-gray-400 bg-gray-900">
                        <th class="p-4">Tanggal</th>
                        <th class="p-4">Anggota</th>
                        <th class="p-4">Jumlah</th>
                        <th class="p-4">Tenor</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php if (empty($pinjaman)): ?>
                    <tr>
                        <td colspan="6" class="p-4 text-center text-gray-500">
                            Tidak ada data pinjaman
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($pinjaman as $p): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-4"><?= date('d/m/Y', strtotime($p['tanggal_pengajuan'])) ?></td>
                            <td class="p-4"><?= htmlspecialchars($p['nama_anggota']) ?></td>
                            <td class="p-4">Rp <?= number_format($p['jumlah_pinjaman'], 0, ',', '.') ?></td>
                            <td class="p-4"><?= $p['tenor'] ?> bulan</td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-xs
                                    <?php
                                    switch($p['status']) {
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
                                    <?= ucfirst($p['status']) ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <?php if ($p['status'] === 'pending'): ?>
                                    <a href="review_loan.php?id=<?= $p['id'] ?>" 
                                       class="text-gold hover:text-yellow-400">
                                        Review
                                    </a>
                                <?php else: ?>
                                    <a href="view_loan.php?id=<?= $p['id'] ?>" 
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
