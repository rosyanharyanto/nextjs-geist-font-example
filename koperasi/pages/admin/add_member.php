<?php
require_once '../../config/config.php';

// Cek apakah user adalah admin
$user = checkRole(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $nama = sanitizeInput($_POST['nama']);
    $email = sanitizeInput($_POST['email']);
    $no_telepon = sanitizeInput($_POST['no_telepon']);
    $alamat = sanitizeInput($_POST['alamat']);
    
    $errors = [];
    
    // Validasi input
    if (empty($username)) {
        $errors[] = "Username harus diisi";
    }
    if (empty($password)) {
        $errors[] = "Password harus diisi";
    }
    if (empty($nama)) {
        $errors[] = "Nama harus diisi";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid";
    }
    
    // Cek username unik
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Username sudah digunakan";
    }
    
    // Cek email unik jika diisi
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email sudah digunakan";
        }
    }
    
    if (empty($errors)) {
        try {
            $hashedPassword = generateHash($password);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, nama, email, no_telepon, alamat, role) 
                VALUES (?, ?, ?, ?, ?, ?, 'anggota')
            ");
            
            $stmt->execute([
                $username,
                $hashedPassword,
                $nama,
                $email ?: null,
                $no_telepon ?: null,
                $alamat ?: null
            ]);
            
            flashMessage('Anggota baru berhasil ditambahkan', 'success');
            redirect('/pages/admin/manage_members.php');
            
        } catch (PDOException $e) {
            flashMessage('Gagal menambahkan anggota: ' . $e->getMessage(), 'error');
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gold">Tambah Anggota Baru</h2>
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
        <!-- Username -->
        <div>
            <label for="username" class="block text-sm font-medium text-gray-300 mb-2">
                Username <span class="text-red-500">*</span>
            </label>
            <input type="text" 
                   id="username" 
                   name="username" 
                   required
                   value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                   class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                Password <span class="text-red-500">*</span>
            </label>
            <input type="password" 
                   id="password" 
                   name="password" 
                   required
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
                   value="<?= isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : '' ?>"
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
                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
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
                   value="<?= isset($_POST['no_telepon']) ? htmlspecialchars($_POST['no_telepon']) : '' ?>"
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
                      class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold"><?= isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : '' ?></textarea>
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

<?php require_once '../../includes/footer.php'; ?>
