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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];
    $keterangan = sanitizeInput($_POST['keterangan']);
    
    if (!in_array($status, ['dibayar', 'telat'])) {
        flashMessage('Status tidak valid', 'error');
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update status cicilan
            $stmt = $pdo->prepare("
                UPDATE cicilan 
                SET status = ?, 
                    keterangan = ?, 
                    tanggal_pembayaran = CURRENT_DATE
                WHERE id = ? AND status = 'menunggu'
            ");
            
            $stmt->execute([$status, $keterangan, $cicilanId]);
            
            // Jika ini cicilan terakhir dan sudah dibayar, update status pinjaman menjadi lunas
            if ($status === 'dibayar') {
                $stmt = $pdo->prepare("
                    SELECT c.pinjaman_id, p.tenor, 
                           COUNT(c2.id) as total_dibayar
                    FROM cicilan c
                    JOIN pinjaman p ON c.pinjaman_id = p.id
                    LEFT JOIN cicilan c2 ON c2.pinjaman_id = p.id 
                        AND c2.status = 'dibayar'
                    WHERE c.id = ?
                    GROUP BY c.pinjaman_id, p.tenor
                ");
                $stmt->execute([$cicilanId]);
                $result = $stmt->fetch();
                
                if ($result && $result['total_dibayar'] == $result['tenor']) {
                    $stmt = $pdo->prepare("
                        UPDATE pinjaman 
                        SET status = 'lunas' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$result['pinjaman_id']]);
                }
            }
            
            $pdo->commit();
            flashMessage(
                $status === 'dibayar' ? 
                'Pembayaran cicilan berhasil diverifikasi' : 
                'Cicilan ditandai sebagai telat',
                'success'
            );
            redirect('/pages/jurubayar/manage_installments.php');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            flashMessage('Error: ' . $e->getMessage(), 'error');
        }
    }
}

try {
    // Ambil detail cicilan
    $stmt = $pdo->prepare("
        SELECT c.*, 
               p.jumlah_pinjaman,
               p.tenor,
               p.total_bayar,
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
    
    if (!$cicilan || $cicilan['status'] !== 'menunggu') {
        flashMessage('Cicilan tidak ditemukan atau sudah diverifikasi', 'error');
        redirect('/pages/jurubayar/manage_installments.php');
    }

} catch (PDOException $e) {
    flashMessage('Error: ' . $e->getMessage(), 'error');
    redirect('/pages/jurubayar/manage_installments.php');
}

require_once '../../includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gold">Verifikasi Pembayaran Cicilan</h2>
        <a href="manage_installments.php" 
           class="text-gray-400 hover:text-gold">
            &larr; Kembali
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
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
                    <label class="block text-sm text-gray-400">Cicilan ke</label>
                    <p class="text-white"><?= $cicilan['nomor_cicilan'] ?> dari <?= $cicilan['tenor'] ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Jumlah Cicilan</label>
                    <p class="text-white">Rp <?= number_format($cicilan['jumlah_cicilan'], 0, ',', '.') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Tanggal Jatuh Tempo</label>
                    <p class="text-white"><?= date('d F Y', strtotime($cicilan['tanggal_jatuh_tempo'])) ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Total Pinjaman</label>
                    <p class="text-white">Rp <?= number_format($cicilan['jumlah_pinjaman'], 0, ',', '.') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Total yang Harus Dibayar</label>
                    <p class="text-white">Rp <?= number_format($cicilan['total_bayar'], 0, ',', '.') ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($cicilan['bukti_pembayaran']): ?>
    <!-- Bukti Pembayaran -->
    <div class="bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <h3 class="text-xl font-semibold text-gold mb-4">Bukti Pembayaran</h3>
        <div class="aspect-w-16 aspect-h-9">
            <img src="<?= htmlspecialchars($cicilan['bukti_pembayaran']) ?>" 
                 alt="Bukti Pembayaran" 
                 class="rounded-lg object-contain">
        </div>
    </div>
    <?php endif; ?>

    <!-- Form Verifikasi -->
    <form method="POST" class="bg-gray-800 rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-semibold text-gold mb-4">Verifikasi Pembayaran</h3>
        
        <div class="space-y-4">
            <div>
                <label for="status" class="block text-sm font-medium text-gray-300 mb-2">
                    Status <span class="text-red-500">*</span>
                </label>
                <select id="status" 
                        name="status" 
                        required
                        class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
                    <option value="">Pilih Status</option>
                    <option value="dibayar">Terverifikasi (Dibayar)</option>
                    <option value="telat">Telat Bayar</option>
                </select>
            </div>

            <div>
                <label for="keterangan" class="block text-sm font-medium text-gray-300 mb-2">
                    Keterangan
                </label>
                <textarea id="keterangan" 
                          name="keterangan" 
                          rows="3"
                          class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold"></textarea>
            </div>

            <div class="flex items-center justify-end space-x-4">
                <a href="manage_installments.php" 
                   class="px-4 py-2 text-gray-400 hover:text-white">
                    Batal
                </a>
                <button type="submit" 
                        class="bg-gold text-gray-900 px-6 py-2 rounded hover:bg-yellow-500 transition-colors">
                    Simpan Verifikasi
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
