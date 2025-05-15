<?php
require_once '../../config/config.php';

// Cek apakah user adalah admin
$user = checkRole(ROLE_ADMIN);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter
$filter = [
    'user_id' => isset($_GET['user_id']) ? (int)$_GET['user_id'] : null,
    'jenis' => isset($_GET['jenis']) ? $_GET['jenis'] : null,
    'tanggal_mulai' => isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : null,
    'tanggal_akhir' => isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : null
];

// Build query
$where = [];
$params = [];

if ($filter['user_id']) {
    $where[] = "s.user_id = ?";
    $params[] = $filter['user_id'];
}

if ($filter['jenis']) {
    $where[] = "s.jenis_simpanan = ?";
    $params[] = $filter['jenis'];
}

if ($filter['tanggal_mulai']) {
    $where[] = "s.tanggal >= ?";
    $params[] = $filter['tanggal_mulai'];
}

if ($filter['tanggal_akhir']) {
    $where[] = "s.tanggal <= ?";
    $params[] = $filter['tanggal_akhir'];
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total records for pagination
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM simpanan s 
    $whereClause
");
$stmt->execute($params);
$totalRecords = $stmt->fetch()['total'];
$totalPages = ceil($totalRecords / $limit);

// Get simpanan data
$query = "
    SELECT s.*, u.nama as nama_anggota 
    FROM simpanan s 
    JOIN users u ON s.user_id = u.id 
    $whereClause 
    ORDER BY s.tanggal DESC, s.created_at DESC 
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$simpanan = $stmt->fetchAll();

// Get all members for filter
$stmt = $pdo->query("SELECT id, nama FROM users WHERE role = 'anggota' ORDER BY nama");
$members = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gold">Manajemen Simpanan</h2>
        <a href="add_simpanan.php" 
           class="bg-gold text-gray-900 px-4 py-2 rounded hover:bg-yellow-500 transition-colors">
            Tambah Simpanan
        </a>
    </div>

    <!-- Filter Form -->
    <div class="bg-gray-800 p-4 rounded-lg">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Filter Anggota -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Anggota</label>
                <select name="user_id" 
                        class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
                    <option value="">Semua Anggota</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?= $member['id'] ?>" 
                                <?= $filter['user_id'] == $member['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($member['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Jenis Simpanan -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Jenis Simpanan</label>
                <select name="jenis" 
                        class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
                    <option value="">Semua Jenis</option>
                    <option value="pokok" <?= $filter['jenis'] == 'pokok' ? 'selected' : '' ?>>Pokok</option>
                    <option value="wajib" <?= $filter['jenis'] == 'wajib' ? 'selected' : '' ?>>Wajib</option>
                    <option value="sukarela" <?= $filter['jenis'] == 'sukarela' ? 'selected' : '' ?>>Sukarela</option>
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
            <div class="md:col-span-2 lg:col-span-4 flex justify-end gap-4">
                <a href="manage_simpanan.php" 
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

    <!-- Simpanan Table -->
    <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="text-gold bg-gray-900">
                    <tr>
                        <th class="p-4">Tanggal</th>
                        <th class="p-4">Anggota</th>
                        <th class="p-4">Jenis</th>
                        <th class="p-4">Jumlah</th>
                        <th class="p-4">Keterangan</th>
                        <th class="p-4">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php if (empty($simpanan)): ?>
                    <tr>
                        <td colspan="6" class="p-4 text-center text-gray-500">
                            Tidak ada data simpanan
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($simpanan as $s): ?>
                        <tr class="border-t border-gray-700 hover:bg-gray-700">
                            <td class="p-4"><?= date('d/m/Y', strtotime($s['tanggal'])) ?></td>
                            <td class="p-4"><?= htmlspecialchars($s['nama_anggota']) ?></td>
                            <td class="p-4"><?= ucfirst($s['jenis_simpanan']) ?></td>
                            <td class="p-4">Rp <?= number_format($s['jumlah'], 0, ',', '.') ?></td>
                            <td class="p-4"><?= htmlspecialchars($s['keterangan'] ?? '-') ?></td>
                            <td class="p-4">
                                <div class="flex gap-2">
                                    <a href="edit_simpanan.php?id=<?= $s['id'] ?>" 
                                       class="text-blue-400 hover:text-blue-300">Edit</a>
                                    <form action="delete_simpanan.php" method="POST" class="inline" 
                                          onsubmit="return confirm('Yakin ingin menghapus data simpanan ini?')">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button type="submit" 
                                                class="text-red-400 hover:text-red-300">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
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
                        <a href="?page=<?= $page-1 ?>&user_id=<?= $filter['user_id'] ?>&jenis=<?= $filter['jenis'] ?>&tanggal_mulai=<?= $filter['tanggal_mulai'] ?>&tanggal_akhir=<?= $filter['tanggal_akhir'] ?>" 
                           class="text-gold hover:text-yellow-400">
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page+1 ?>&user_id=<?= $filter['user_id'] ?>&jenis=<?= $filter['jenis'] ?>&tanggal_mulai=<?= $filter['tanggal_mulai'] ?>&tanggal_akhir=<?= $filter['tanggal_akhir'] ?>" 
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
                                <a href="?page=<?= $i ?>&user_id=<?= $filter['user_id'] ?>&jenis=<?= $filter['jenis'] ?>&tanggal_mulai=<?= $filter['tanggal_mulai'] ?>&tanggal_akhir=<?= $filter['tanggal_akhir'] ?>" 
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
