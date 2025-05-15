<?php
require_once '../../config/config.php';

// Cek apakah user adalah admin
$user = checkRole(ROLE_ADMIN);

// Ambil ID anggota dari parameter URL
$memberId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$memberId) {
    flashMessage('ID Anggota tidak valid', 'error');
    redirect('/pages/admin/manage_members.php');
}

// Ambil data anggota
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'anggota'");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();
    
    if (!$member) {
        flashMessage('Anggota tidak ditemukan', 'error');
        redirect('/pages/admin/manage_members.php');
    }
} catch (PDOException $e) {
    flashMessage('Error: ' . $e->getMessage(), 'error');
    redirect('/pages/admin/manage_members.php');
}

// Proses form edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = sanitizeInput($_POST['nama']);
    $email = sanitizeInput($_POST['email']);
    $no_telepon = sanitizeInput($_POST['no_telepon']);
    $alamat = sanitizeInput($_POST['alamat']);
    $password = $_POST['password']; // Optional, hanya jika ingin mengubah password
    
    $errors = [];
    
    // Validasi input
    if (empty($nama)) {
        $errors[] = "Nama harus diisi";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid";
    }
    
    // Cek email unik jika diubah
    if (!empty($email) && $email !== $member['email']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $memberId]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email sudah digunakan";
        }
    }
    
    if (empty($errors)) {
        try {
            // Siapkan query update
            $updateFields = [
                'nama = ?',
                'email = ?',
                'no_telepon = ?',
                'alamat = ?'
            ];
            $params = [$nama, $email ?: null, $no_telepon ?: null, $alamat ?: null];
            
            // Jika password diisi, tambahkan ke query update
            if (!empty($password)) {
                $updateFields[] = 'password = ?';
                $params[] = generateHash($password);
            }
            
            // Tambahkan ID ke parameter
            $params[] = $memberId;
            
            $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            
            flashMessage('Data anggota berhasil diperbarui', 'success');
            redirect('/pages/admin/manage_members.php');
            
        } catch (PDOException $e) {
            flashMessage('Gagal memperbarui data: ' . $e->getMessage(), 'error');
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gold">Edit Anggota</h2>
        <a href="manage_members.php" 
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
        <!-- Username (readonly) -->
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">
                Username
            </label>
            <input type="text" 
                   value="<?= htmlspecialchars($member['username']) ?>"
                   readonly
                   class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-gray-400">
        </div>

        <!-- Password (optional) -->
        <div>
            <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                Password Baru (kosongkan jika tidak ingin mengubah)
            </label>
            <input type="password" 
                   id="password" 
                   name="password"
                   class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
        </div>

        <!-- Nama -->
        <div>
            <label for="nama" class="block text-sm font-medium text-gray-300 mb-2">
                Nama Lengkap <span class="text-red-500">*</span>
            </label>
            <input type="text" 
                   id="nama" 
                   name="nama" 
                   required
                   value="<?= htmlspecialchars($member['nama']) ?>"
                   class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
        </div>

        <!-- Email -->
        <div>
            <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                Email
            </label>
            <input type="email" 
                   id="email" 
                   name="email"
                   value="<?= htmlspecialchars($member['email'] ?? '') ?>"
                   class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
        </div>

        <!-- No Telepon -->
        <div>
            <label for="no_telepon" class="block text-sm font-medium text-gray-300 mb-2">
                No. Telepon
            </label>
            <input type="tel" 
                   id="no_telepon" 
                   name="no_telepon"
                   value="<?= htmlspecialchars($member['no_telepon'] ?? '') ?>"
                   class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
        </div>

        <!-- Alamat -->
        <div>
            <label for="alamat" class="block text-sm font-medium text-gray-300 mb-2">
                Alamat
            </label>
            <textarea id="alamat" 
                      name="alamat" 
                      rows="3"
                      class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold"><?= htmlspecialchars($member['alamat'] ?? '') ?></textarea>
        </div>

        <div class="flex items-center justify-end space-x-4">
            <a href="manage_members.php" 
               class="px-4 py-2 text-gray-400 hover:text-white">
                Batal
            </a>
            <button type="submit" 
                    class="bg-gold text-gray-900 px-6 py-2 rounded hover:bg-yellow-500 transition-colors">
                Simpan Perubahan
            </button>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
