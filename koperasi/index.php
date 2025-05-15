<?php
require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    switch ($user['role']) {
        case ROLE_ADMIN:
            redirect('/pages/admin/dashboard.php');
            break;
        case ROLE_JURUBAYAR:
            redirect('/pages/jurubayar/dashboard.php');
            break;
        case ROLE_USER:
            redirect('/pages/user/dashboard.php');
            break;
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        flashMessage('Username dan password harus diisi', 'error');
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                
                // Redirect based on role
                switch ($user['role']) {
                    case ROLE_ADMIN:
                        redirect('/pages/admin/dashboard.php');
                        break;
                    case ROLE_JURUBAYAR:
                        redirect('/pages/jurubayar/dashboard.php');
                        break;
                    case ROLE_USER:
                        redirect('/pages/user/dashboard.php');
                        break;
                }
            } else {
                flashMessage('Username atau password salah', 'error');
            }
        } catch (PDOException $e) {
            flashMessage('Terjadi kesalahan sistem', 'error');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-gray-800 p-8 rounded-lg shadow-lg">
            <h2 class="text-2xl font-bold text-gold text-center mb-6">Login Koperasi</h2>
            
            <form method="POST" action="" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-300 mb-2">Username</label>
                    <input type="text" id="username" name="username" required 
                           class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                    <input type="password" id="password" name="password" required 
                           class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 text-white focus:outline-none focus:border-gold">
                </div>

                <button type="submit" 
                        class="w-full bg-gold text-gray-900 py-2 px-4 rounded font-medium hover:bg-yellow-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gold">
                    Masuk
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
