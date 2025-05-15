<?php
require_once '../../config/config.php';

// Cek apakah user adalah admin
$user = checkRole(ROLE_ADMIN);

// Handle delete action
if (isset($_POST['delete']) && isset($_POST['user_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'anggota'");
        $stmt->execute([$_POST['user_id']]);
        flashMessage('Anggota berhasil dihapus', 'success');
        header("Location: manage_members.php");
        exit;
    } catch (PDOException $e) {
        flashMessage('Gagal menghapus anggota: ' . $e->getMessage(), 'error');
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchWhere = '';
$params = [];

if ($search) {
    $searchWhere = "WHERE (nama LIKE ? OR username LIKE ? OR email LIKE ?) AND role = 'anggota'";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam];
} else {
    $searchWhere = "WHERE role = 'anggota'";
}

// Get total records for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users $searchWhere");
$stmt->execute($params);
$totalRecords = $stmt->fetch()['total'];
$totalPages = ceil($totalRecords / $limit);

// Get members
$query = "SELECT id, username, nama, email, no_telepon, created_at 
          FROM users 
          $searchWhere 
          ORDER BY nama ASC 
          LIMIT ? OFFSET ?";
$params = array_merge($params, [$limit, $offset]);
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$members = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gold">Manajemen Anggota</h2>
        <a href="add_member.php" 
           class="bg-gold text-gray-900 px-4 py-2 rounded hover:bg-yellow-500 transition-colors">
            Tambah Anggota
        </a>
    </div>

    <!-- Search Form -->
    <div class="bg-gray-800 p-4 rounded-lg">
        <form action="" method="GET" class="flex gap-4">
            <input type="text" 
                   name="search" 
                   value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Cari anggota..." 
                   class="flex-1 px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
            <button type="submit" 
                    class="bg-gold text-gray-900 px-6 py-2 rounded hover:bg-yellow-500 transition-colors">
                Cari
            </button>
        </form>
    </div>

    <!-- Members Table -->
    <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="text-gold bg-gray-900">
                    <tr>
                        <th class="p-4">Nama</th>
                        <th class="p-4">Username</th>
                        <th class="p-4">Email</th>
                        <th class="p-4">No. Telepon</th>
                        <th class="p-4">Tgl Daftar</th>
                        <th class="p-4">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="6" class="p-4 text-center text-gray-500">
                            Tidak ada data anggota
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($members as $member): ?>
                        <tr class="border-t border-gray-700 hover:bg-gray-700">
                            <td class="p-4"><?= htmlspecialchars($member['nama']) ?></td>
                            <td class="p-4"><?= htmlspecialchars($member['username']) ?></td>
                            <td class="p-4"><?= htmlspecialchars($member['email']) ?></td>
                            <td class="p-4"><?= htmlspecialchars($member['no_telepon']) ?></td>
                            <td class="p-4"><?= date('d/m/Y', strtotime($member['created_at'])) ?></td>
                            <td class="p-4">
                                <div class="flex gap-2">
                                    <a href="edit_member.php?id=<?= $member['id'] ?>" 
                                       class="text-blue-400 hover:text-blue-300">Edit</a>
                                    <form action="" method="POST" class="inline" 
                                          onsubmit="return confirm('Yakin ingin menghapus anggota ini?')">
                                        <input type="hidden" name="user_id" value="<?= $member['id'] ?>">
                                        <button type="submit" name="delete" 
                                                class="text-red-400 hover:text-red-300">
                                            Hapus
                                        </button>
                                    </form>
                                    <a href="view_member.php?id=<?= $member['id'] ?>" 
                                       class="text-gold hover:text-yellow-400">Detail</a>
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
                        <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>" 
                           class="text-gold hover:text-yellow-400">
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>" 
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
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" 
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
