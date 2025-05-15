<?php
require_once '../../config/config.php';

// Cek apakah user adalah anggota
$user = checkRole(ROLE_ANGGOTA);

// Ambil ID cicilan dari parameter URL
$cicilanId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$cicilanId) {
    flashMessage('ID Cicilan tidak valid', 'error');
    redirect('/pages/anggota/dashboard.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Proses upload bukti pembayaran
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['bukti_pembayaran'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        $errors = [];
        
        // Validasi tipe file
        if (!in_array($file['type'], $allowedTypes)) {
            $errors[] = "Tipe file tidak didukung. Harap upload file gambar (JPG, PNG)";
        }
        
        // Validasi ukuran file
        if ($file['size'] > $maxSize) {
            $errors[] = "Ukuran file terlalu besar. Maksimal 5MB";
        }
        
        if (empty($errors)) {
            try {
                // Generate nama file unik
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $extension;
                $uploadDir = '../../uploads/bukti_pembayaran/';
                
                // Buat direktori jika belum ada
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Pindahkan file
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    // Update status cicilan
                    $stmt = $pdo->prepare("
                        UPDATE cicilan 
                        SET status = 'menunggu',
                            bukti_pembayaran = ? 
                        WHERE id = ? 
                        AND status = 'belum_bayar'
                    ");
                    
                    $buktiPath = '/uploads/bukti_pembayaran/' . $filename;
                    $stmt->execute([$buktiPath, $cicilanId]);
                    
                    flashMessage('Bukti pembayaran berhasil diupload', 'success');
                    redirect('/pages/anggota/detail_pinjaman.php?id=' . $cicilan['pinjaman_id']);
                    
                } else {
                    flashMessage('Gagal mengupload file', 'error');
                }
            } catch (PDOException $e) {
                flashMessage('Error: ' . $e->getMessage(), 'error');
            }
        } else {
            foreach ($errors as $error) {
                flashMessage($error, 'error');
            }
        }
    } else {
        flashMessage('Harap pilih file untuk diupload', 'error');
    }
}

try {
    // Ambil detail cicilan
    $stmt = $pdo->prepare("
        SELECT c.*, 
               p.jumlah_pinjaman,
               p.tenor,
               p.status as status_pinjaman
        FROM cicilan c 
        JOIN pinjaman p ON c.pinjaman_id = p.id 
        WHERE c.id = ? 
        AND p.user_id = ?
    ");
    $stmt->execute([$cicilanId, $user['id']]);
    $cicilan = $stmt->fetch();
    
    if (!$cicilan || $cicilan['status'] !== 'belum_bayar') {
        flashMessage('Cicilan tidak ditemukan atau sudah dibayar', 'error');
        redirect('/pages/anggota/dashboard.php');
    }

} catch (PDOException $e) {
    flashMessage('Error: ' . $e->getMessage(), 'error');
    redirect('/pages/anggota/dashboard.php');
}

require_once '../../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gold">Pembayaran Cicilan</h2>
        <a href="detail_pinjaman.php?id=<?= $cicilan['pinjaman_id'] ?>" 
           class="text-gray-400 hover:text-gold">
            &larr; Kembali
        </a>
    </div>

    <!-- Detail Cicilan -->
    <div class="bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <h3 class="text-xl font-semibold text-gold mb-4">Detail Pembayaran</h3>
        <div class="space-y-4">
            <div>
                <label class="block text-sm text-gray-400">Cicilan ke</label>
                <p class="text-white"><?= $cicilan['nomor_cicilan'] ?> dari <?= $cicilan['tenor'] ?></p>
            </div>
            <div>
                <label class="block text-sm text-gray-400">Jumlah yang Harus Dibayar</label>
                <p class="text-2xl font-bold text-white">
                    Rp <?= number_format($cicilan['jumlah_cicilan'], 0, ',', '.') ?>
                </p>
            </div>
            <div>
                <label class="block text-sm text-gray-400">Jatuh Tempo</label>
                <p class="text-white"><?= date('d F Y', strtotime($cicilan['tanggal_jatuh_tempo'])) ?></p>
            </div>
        </div>
    </div>

    <!-- Informasi Pembayaran -->
    <div class="bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <h3 class="text-xl font-semibold text-gold mb-4">Informasi Pembayaran</h3>
        <div class="space-y-4">
            <div>
                <p class="text-gray-300">Silakan transfer ke salah satu rekening berikut:</p>
                <div class="mt-2 space-y-2">
                    <div class="p-3 bg-gray-700 rounded">
                        <p class="text-sm text-gray-400">Bank BRI</p>
                        <p class="text-white font-mono">1234-5678-9012-3456</p>
                        <p class="text-sm text-gray-400">a.n. Koperasi Simpan Pinjam</p>
                    </div>
                    <div class="p-3 bg-gray-700 rounded">
                        <p class="text-sm text-gray-400">Bank BCA</p>
                        <p class="text-white font-mono">0987-6543-2109-8765</p>
                        <p class="text-sm text-gray-400">a.n. Koperasi Simpan Pinjam</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Upload -->
    <form method="POST" 
          enctype="multipart/form-data" 
          class="bg-gray-800 rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-semibold text-gold mb-4">Upload Bukti Pembayaran</h3>
        
        <div class="space-y-4">
            <div>
                <label for="bukti_pembayaran" class="block text-sm font-medium text-gray-300 mb-2">
                    Bukti Pembayaran <span class="text-red-500">*</span>
                </label>
                <input type="file" 
                       id="bukti_pembayaran" 
                       name="bukti_pembayaran" 
                       accept="image/jpeg,image/png,image/jpg"
                       required
                       class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
                <p class="mt-1 text-sm text-gray-400">
                    Format yang didukung: JPG, PNG. Maksimal 5MB
                </p>
            </div>

            <div class="flex justify-end space-x-4">
                <a href="detail_pinjaman.php?id=<?= $cicilan['pinjaman_id'] ?>" 
                   class="px-4 py-2 text-gray-400 hover:text-white">
                    Batal
                </a>
                <button type="submit" 
                        class="bg-gold text-gray-900 px-6 py-2 rounded hover:bg-yellow-500 transition-colors">
                    Upload Bukti Pembayaran
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
