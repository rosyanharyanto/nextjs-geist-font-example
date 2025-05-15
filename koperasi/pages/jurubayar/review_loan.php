<?php
require_once '../../config/config.php';

// Cek apakah user adalah jurubayar
$user = checkRole(ROLE_JURUBAYAR);

// Ambil ID pinjaman dari parameter URL
$pinjamanId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$pinjamanId) {
    flashMessage('ID Pinjaman tidak valid', 'error');
    redirect('/pages/jurubayar/dashboard.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];
    $keterangan = sanitizeInput($_POST['keterangan']);
    
    if (!in_array($status, ['disetujui', 'ditolak'])) {
        flashMessage('Status tidak valid', 'error');
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update status pinjaman
            $stmt = $pdo->prepare("
                UPDATE pinjaman 
                SET status = ?, 
                    keterangan = ?, 
                    tanggal_persetujuan = CURRENT_DATE,
                    approved_by = ?
                WHERE id = ? AND status = 'pending'
            ");
            
            $stmt->execute([$status, $keterangan, $user['id'], $pinjamanId]);
            
            // Jika disetujui, buat cicilan otomatis
            if ($status === 'disetujui') {
                // Ambil data pinjaman
                $stmt = $pdo->prepare("SELECT * FROM pinjaman WHERE id = ?");
                $stmt->execute([$pinjamanId]);
                $pinjaman = $stmt->fetch();
                
                // Buat cicilan untuk setiap bulan
                for ($i = 1; $i <= $pinjaman['tenor']; $i++) {
                    $tanggalJatuhTempo = date('Y-m-d', strtotime("+$i month", strtotime($pinjaman['tanggal_persetujuan'])));
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO cicilan (
                            pinjaman_id, 
                            nomor_cicilan, 
                            jumlah_cicilan, 
                            tanggal_jatuh_tempo, 
                            status
                        ) VALUES (?, ?, ?, ?, 'belum_bayar')
                    ");
                    
                    $stmt->execute([
                        $pinjamanId,
                        $i,
                        $pinjaman['jumlah_cicilan'],
                        $tanggalJatuhTempo
                    ]);
                }
            }
            
            $pdo->commit();
            flashMessage(
                $status === 'disetujui' ? 
                'Pinjaman berhasil disetujui dan cicilan telah dibuat' : 
                'Pinjaman telah ditolak',
                'success'
            );
            redirect('/pages/jurubayar/loan_approval.php');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            flashMessage('Error: ' . $e->getMessage(), 'error');
        }
    }
}

try {
    // Ambil detail pinjaman
    $stmt = $pdo->prepare("
        SELECT p.*, 
               u.nama as nama_anggota,
               u.no_telepon,
               u.alamat,
               (SELECT COUNT(*) FROM pinjaman p2 
                WHERE p2.user_id = p.user_id 
                AND p2.status = 'lunas') as total_pinjaman_lunas,
               (SELECT COUNT(*) FROM pinjaman p3 
                WHERE p3.user_id = p.user_id 
                AND p3.status = 'disetujui') as pinjaman_aktif
        FROM pinjaman p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$pinjamanId]);
    $pinjaman = $stmt->fetch();
    
    if (!$pinjaman || $pinjaman['status'] !== 'pending') {
        flashMessage('Pinjaman tidak ditemukan atau sudah diproses', 'error');
        redirect('/pages/jurubayar/dashboard.php');
    }

    // Ambil riwayat pinjaman sebelumnya
    $stmt = $pdo->prepare("
        SELECT * FROM pinjaman 
        WHERE user_id = ? AND id != ? 
        ORDER BY tanggal_pengajuan DESC
    ");
    $stmt->execute([$pinjaman['user_id'], $pinjamanId]);
    $riwayatPinjaman = $stmt->fetchAll();

} catch (PDOException $e) {
    flashMessage('Error: ' . $e->getMessage(), 'error');
    redirect('/pages/jurubayar/dashboard.php');
}

require_once '../../includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gold">Review Pengajuan Pinjaman</h2>
        <a href="loan_approval.php" 
           class="text-gray-400 hover:text-gold">
            &larr; Kembali
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Detail Peminjam -->
        <div class="bg-gray-800 rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold text-gold mb-4">Detail Peminjam</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-400">Nama Anggota</label>
                    <p class="text-white"><?= htmlspecialchars($pinjaman['nama_anggota']) ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">No. Telepon</label>
                    <p class="text-white"><?= htmlspecialchars($pinjaman['no_telepon'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Alamat</label>
                    <p class="text-white"><?= nl2br(htmlspecialchars($pinjaman['alamat'] ?? '-')) ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Total Pinjaman Lunas</label>
                    <p class="text-white"><?= $pinjaman['total_pinjaman_lunas'] ?> pinjaman</p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Pinjaman Aktif</label>
                    <p class="text-white"><?= $pinjaman['pinjaman_aktif'] ?> pinjaman</p>
                </div>
            </div>
        </div>

        <!-- Detail Pinjaman -->
        <div class="bg-gray-800 rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold text-gold mb-4">Detail Pinjaman</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-400">Jumlah Pinjaman</label>
                    <p class="text-white">Rp <?= number_format($pinjaman['jumlah_pinjaman'], 0, ',', '.') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Tenor</label>
                    <p class="text-white"><?= $pinjaman['tenor'] ?> bulan</p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Cicilan per Bulan</label>
                    <p class="text-white">Rp <?= number_format($pinjaman['jumlah_cicilan'], 0, ',', '.') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Total Bayar</label>
                    <p class="text-white">Rp <?= number_format($pinjaman['total_bayar'], 0, ',', '.') ?></p>
                </div>
                <div>
                    <label class="block text-sm text-gray-400">Tanggal Pengajuan</label>
                    <p class="text-white"><?= date('d F Y', strtotime($pinjaman['tanggal_pengajuan'])) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Pinjaman -->
    <?php if (!empty($riwayatPinjaman)): ?>
    <div class="mt-6 bg-gray-800 rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-semibold text-gold mb-4">Riwayat Pinjaman</h3>
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
                    <?php foreach ($riwayatPinjaman as $riwayat): ?>
                    <tr class="border-t border-gray-700">
                        <td class="p-2"><?= date('d/m/Y', strtotime($riwayat['tanggal_pengajuan'])) ?></td>
                        <td class="p-2">Rp <?= number_format($riwayat['jumlah_pinjaman'], 0, ',', '.') ?></td>
                        <td class="p-2">
                            <span class="px-2 py-1 rounded text-xs
                                <?php
                                switch($riwayat['status']) {
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
                                <?= ucfirst($riwayat['status']) ?>
                            </span>
                        </td>
                        <td class="p-2"><?= $riwayat['tenor'] ?> bulan</td>
                        <td class="p-2">Rp <?= number_format($riwayat['total_bayar'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form Persetujuan -->
    <form method="POST" class="mt-6 bg-gray-800 rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-semibold text-gold mb-4">Keputusan</h3>
        
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
                    <option value="disetujui">Setujui Pinjaman</option>
                    <option value="ditolak">Tolak Pinjaman</option>
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
                <a href="loan_approval.php" 
                   class="px-4 py-2 text-gray-400 hover:text-white">
                    Batal
                </a>
                <button type="submit" 
                        class="bg-gold text-gray-900 px-6 py-2 rounded hover:bg-yellow-500 transition-colors">
                    Simpan Keputusan
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
