<?php
require_once '../../config/config.php';

// Cek apakah user adalah anggota
$user = checkRole(ROLE_ANGGOTA);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jumlah_pinjaman = (float)str_replace([',', '.'], '', $_POST['jumlah_pinjaman']);
    $tenor = (int)$_POST['tenor'];
    $tujuan = sanitizeInput($_POST['tujuan']);
    
    $errors = [];
    
    // Validasi input
    if ($jumlah_pinjaman <= 0) {
        $errors[] = "Jumlah pinjaman harus lebih dari 0";
    }
    if ($tenor < 1 || $tenor > 36) {
        $errors[] = "Tenor harus antara 1-36 bulan";
    }
    if (empty($tujuan)) {
        $errors[] = "Tujuan pinjaman harus diisi";
    }
    
    // Cek apakah masih ada pinjaman aktif
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM pinjaman 
        WHERE user_id = ? AND status IN ('pending', 'disetujui')
    ");
    $stmt->execute([$user['id']]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Anda masih memiliki pinjaman yang aktif atau dalam proses pengajuan";
    }
    
    if (empty($errors)) {
        try {
            // Hitung jumlah cicilan dan total bayar (dengan bunga 1% per bulan)
            $bunga = $jumlah_pinjaman * 0.01 * $tenor;
            $total_bayar = $jumlah_pinjaman + $bunga;
            $jumlah_cicilan = ceil($total_bayar / $tenor);
            
            $stmt = $pdo->prepare("
                INSERT INTO pinjaman (
                    user_id, 
                    jumlah_pinjaman, 
                    tenor, 
                    jumlah_cicilan,
                    total_bayar,
                    tujuan,
                    tanggal_pengajuan,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE, 'pending')
            ");
            
            $stmt->execute([
                $user['id'],
                $jumlah_pinjaman,
                $tenor,
                $jumlah_cicilan,
                $total_bayar,
                $tujuan
            ]);
            
            flashMessage('Pengajuan pinjaman berhasil dikirim', 'success');
            redirect('/pages/anggota/dashboard.php');
            
        } catch (PDOException $e) {
            flashMessage('Error: ' . $e->getMessage(), 'error');
        }
    }
}

// Ambil riwayat pinjaman
try {
    $stmt = $pdo->prepare("
        SELECT * FROM pinjaman 
        WHERE user_id = ? 
        ORDER BY tanggal_pengajuan DESC
    ");
    $stmt->execute([$user['id']]);
    $riwayatPinjaman = $stmt->fetchAll();
} catch (PDOException $e) {
    flashMessage('Error: ' . $e->getMessage(), 'error');
}

require_once '../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gold">Pengajuan Pinjaman</h2>
        <a href="dashboard.php" 
           class="text-gray-400 hover:text-gold">
            &larr; Kembali ke Dashboard
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-500 text-white p-4 rounded-lg">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Form Pengajuan -->
    <div class="bg-gray-800 rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-semibold text-gold mb-4">Form Pengajuan Pinjaman</h3>
        
        <form method="POST" class="space-y-6">
            <div>
                <label for="jumlah_pinjaman" class="block text-sm font-medium text-gray-300 mb-2">
                    Jumlah Pinjaman (Rp) <span class="text-red-500">*</span>
                </label>
                <input type="text" 
                       id="jumlah_pinjaman" 
                       name="jumlah_pinjaman" 
                       required
                       pattern="[0-9,.]+"
                       value="<?= isset($_POST['jumlah_pinjaman']) ? htmlspecialchars($_POST['jumlah_pinjaman']) : '' ?>"
                       placeholder="Contoh: 1.000.000"
                       class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold"
                       oninput="this.value = this.value.replace(/[^0-9,.]/g, '').replace(/(\..*)\./g, '$1');">
            </div>

            <div>
                <label for="tenor" class="block text-sm font-medium text-gray-300 mb-2">
                    Tenor (Bulan) <span class="text-red-500">*</span>
                </label>
                <select id="tenor" 
                        name="tenor" 
                        required
                        class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
                    <option value="">Pilih Tenor</option>
                    <?php for ($i = 1; $i <= 36; $i++): ?>
                        <option value="<?= $i ?>" 
                                <?= isset($_POST['tenor']) && $_POST['tenor'] == $i ? 'selected' : '' ?>>
                            <?= $i ?> bulan
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div>
                <label for="tujuan" class="block text-sm font-medium text-gray-300 mb-2">
                    Tujuan Pinjaman <span class="text-red-500">*</span>
                </label>
                <textarea id="tujuan" 
                          name="tujuan" 
                          required
                          rows="3"
                          class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold"><?= isset($_POST['tujuan']) ? htmlspecialchars($_POST['tujuan']) : '' ?></textarea>
            </div>

            <div class="bg-gray-900 p-4 rounded">
                <h4 class="text-gold font-medium mb-2">Informasi Bunga</h4>
                <ul class="text-sm text-gray-400 list-disc list-inside space-y-1">
                    <li>Bunga pinjaman sebesar 1% per bulan</li>
                    <li>Pembayaran cicilan dilakukan setiap bulan</li>
                    <li>Keterlambatan pembayaran akan dikenakan denda</li>
                </ul>
            </div>

            <div class="flex justify-end space-x-4">
                <button type="reset" 
                        class="px-4 py-2 text-gray-400 hover:text-white">
                    Reset
                </button>
                <button type="submit" 
                        class="bg-gold text-gray-900 px-6 py-2 rounded hover:bg-yellow-500 transition-colors">
                    Ajukan Pinjaman
                </button>
            </div>
        </form>
    </div>

    <!-- Riwayat Pinjaman -->
    <div class="bg-gray-800 rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-semibold text-gold mb-4">Riwayat Pinjaman</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="text-left">
                    <tr class="text-gray-400">
                        <th class="p-2">Tanggal</th>
                        <th class="p-2">Jumlah</th>
                        <th class="p-2">Tenor</th>
                        <th class="p-2">Status</th>
                        <th class="p-2">Total Bayar</th>
                        <th class="p-2">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php if (empty($riwayatPinjaman)): ?>
                    <tr>
                        <td colspan="6" class="p-2 text-center text-gray-500">
                            Belum ada riwayat pinjaman
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($riwayatPinjaman as $pinjaman): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-2"><?= date('d/m/Y', strtotime($pinjaman['tanggal_pengajuan'])) ?></td>
                            <td class="p-2">Rp <?= number_format($pinjaman['jumlah_pinjaman'], 0, ',', '.') ?></td>
                            <td class="p-2"><?= $pinjaman['tenor'] ?> bulan</td>
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
                            <td class="p-2">Rp <?= number_format($pinjaman['total_bayar'], 0, ',', '.') ?></td>
                            <td class="p-2">
                                <a href="detail_pinjaman.php?id=<?= $pinjaman['id'] ?>" 
                                   class="text-gold hover:text-yellow-400">
                                    Detail
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Format input jumlah pinjaman dengan pemisah ribuan
document.getElementById('jumlah_pinjaman').addEventListener('input', function(e) {
    let value = this.value.replace(/[^0-9]/g, '');
    if (value) {
        value = parseInt(value).toLocaleString('id-ID');
        this.value = value;
    }
});

// Sebelum submit, hapus pemisah ribuan
document.querySelector('form').addEventListener('submit', function(e) {
    let jumlahInput = document.getElementById('jumlah_pinjaman');
    jumlahInput.value = jumlahInput.value.replace(/[.,]/g, '');
});
</script>

<?php require_once '../../includes/footer.php'; ?>
