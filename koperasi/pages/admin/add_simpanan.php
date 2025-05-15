<?php
require_once '../../config/config.php';

// Cek apakah user adalah admin
$user = checkRole(ROLE_ADMIN);

// Ambil daftar anggota untuk dropdown
try {
    $stmt = $pdo->query("SELECT id, nama FROM users WHERE role = 'anggota' ORDER BY nama");
    $members = $stmt->fetchAll();
} catch (PDOException $e) {
    flashMessage('Error: ' . $e->getMessage(), 'error');
    redirect('/pages/admin/manage_simpanan.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_POST['user_id'];
    $jenis_simpanan = sanitizeInput($_POST['jenis_simpanan']);
    $jumlah = (float)str_replace([',', '.'], '', $_POST['jumlah']);
    $tanggal = $_POST['tanggal'];
    $keterangan = sanitizeInput($_POST['keterangan']);
    
    $errors = [];
    
    // Validasi input
    if (!$user_id) {
        $errors[] = "Anggota harus dipilih";
    }
    if (!in_array($jenis_simpanan, ['pokok', 'wajib', 'sukarela'])) {
        $errors[] = "Jenis simpanan tidak valid";
    }
    if ($jumlah <= 0) {
        $errors[] = "Jumlah simpanan harus lebih dari 0";
    }
    if (!$tanggal || !strtotime($tanggal)) {
        $errors[] = "Tanggal tidak valid";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO simpanan (user_id, jenis_simpanan, jumlah, tanggal, keterangan) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user_id,
                $jenis_simpanan,
                $jumlah,
                $tanggal,
                $keterangan
            ]);
            
            flashMessage('Simpanan berhasil ditambahkan', 'success');
            redirect('/pages/admin/manage_simpanan.php');
            
        } catch (PDOException $e) {
            flashMessage('Gagal menambahkan simpanan: ' . $e->getMessage(), 'error');
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gold">Tambah Simpanan</h2>
        <a href="manage_simpanan.php" 
           class="text-gray-400 hover:text-gold">
            &larr; Kembali
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-500 text-white p-4 rounded-lg mb-6">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-gray-800 rounded-lg shadow-lg p-6 space-y-6">
        <!-- Pilih Anggota -->
        <div>
            <label for="user_id" class="block text-sm font-medium text-gray-300 mb-2">
                Anggota <span class="text-red-500">*</span>
            </label>
            <select id="user_id" 
                    name="user_id" 
                    required
                    class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
                <option value="">Pilih Anggota</option>
                <?php foreach ($members as $member): ?>
                    <option value="<?= $member['id'] ?>" 
                            <?= isset($_POST['user_id']) && $_POST['user_id'] == $member['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($member['nama']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Jenis Simpanan -->
        <div>
            <label for="jenis_simpanan" class="block text-sm font-medium text-gray-300 mb-2">
                Jenis Simpanan <span class="text-red-500">*</span>
            </label>
            <select id="jenis_simpanan" 
                    name="jenis_simpanan" 
                    required
                    class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
                <option value="">Pilih Jenis Simpanan</option>
                <option value="pokok" <?= isset($_POST['jenis_simpanan']) && $_POST['jenis_simpanan'] == 'pokok' ? 'selected' : '' ?>>
                    Simpanan Pokok
                </option>
                <option value="wajib" <?= isset($_POST['jenis_simpanan']) && $_POST['jenis_simpanan'] == 'wajib' ? 'selected' : '' ?>>
                    Simpanan Wajib
                </option>
                <option value="sukarela" <?= isset($_POST['jenis_simpanan']) && $_POST['jenis_simpanan'] == 'sukarela' ? 'selected' : '' ?>>
                    Simpanan Sukarela
                </option>
            </select>
        </div>

        <!-- Jumlah -->
        <div>
            <label for="jumlah" class="block text-sm font-medium text-gray-300 mb-2">
                Jumlah (Rp) <span class="text-red-500">*</span>
            </label>
            <input type="text" 
                   id="jumlah" 
                   name="jumlah" 
                   required
                   pattern="[0-9,.]+"
                   value="<?= isset($_POST['jumlah']) ? htmlspecialchars($_POST['jumlah']) : '' ?>"
                   placeholder="Contoh: 1.000.000"
                   class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold"
                   oninput="this.value = this.value.replace(/[^0-9,.]/g, '').replace(/(\..*)\./g, '$1');">
        </div>

        <!-- Tanggal -->
        <div>
            <label for="tanggal" class="block text-sm font-medium text-gray-300 mb-2">
                Tanggal <span class="text-red-500">*</span>
            </label>
            <input type="date" 
                   id="tanggal" 
                   name="tanggal" 
                   required
                   value="<?= isset($_POST['tanggal']) ? htmlspecialchars($_POST['tanggal']) : date('Y-m-d') ?>"
                   class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
        </div>

        <!-- Keterangan -->
        <div>
            <label for="keterangan" class="block text-sm font-medium text-gray-300 mb-2">
                Keterangan
            </label>
            <textarea id="keterangan" 
                      name="keterangan" 
                      rows="3"
                      class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold"><?= isset($_POST['keterangan']) ? htmlspecialchars($_POST['keterangan']) : '' ?></textarea>
        </div>

        <div class="flex items-center justify-end space-x-4">
            <button type="reset" 
                    class="px-4 py-2 text-gray-400 hover:text-white">
                Reset
            </button>
            <button type="submit" 
                    class="bg-gold text-gray-900 px-6 py-2 rounded hover:bg-yellow-500 transition-colors">
                Simpan
            </button>
        </div>
    </form>
</div>

<script>
// Format input jumlah dengan pemisah ribuan
document.getElementById('jumlah').addEventListener('input', function(e) {
    let value = this.value.replace(/[^0-9]/g, '');
    if (value) {
        value = parseInt(value).toLocaleString('id-ID');
        this.value = value;
    }
});

// Sebelum submit, hapus pemisah ribuan
document.querySelector('form').addEventListener('submit', function(e) {
    let jumlahInput = document.getElementById('jumlah');
    jumlahInput.value = jumlahInput.value.replace(/[.,]/g, '');
});
</script>

<?php require_once '../../includes/footer.php'; ?>
