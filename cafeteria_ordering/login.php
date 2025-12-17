<?php
session_start();
require_once __DIR__ . '/db.php';

// Simple relative redirect whitelist to avoid open redirects.
function safe_redirect_path(string $path): string
{
    if ($path === '' || str_starts_with($path, 'http')) {
        return 'index.php';
    }
    // Strip leading slash to keep it relative.
    return ltrim($path, '/');
}

$redirect = safe_redirect_path($_GET['redirect'] ?? 'index.php');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password_hash, display_name, role FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $role = $user['role'] ?? '';
            if ($role === '' && $user['username'] === 'admin') {
                $role = 'admin';
            }
            if ($role === '') {
                $role = 'user';
            }

            // Admins land on orders dashboard by default
            if ($role === 'admin' && ($redirect === '' || $redirect === 'index.php')) {
                $redirect = 'orders.php';
            }

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['display_name'] = $user['display_name'] ?: $user['username'];
            $_SESSION['role'] = $role;
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - TAR UMT Cafeteria</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-content">
            <h1 class="logo">Login</h1>
        </div>
    </header>

    <main class="container">
        <section class="menu-section">
            <?php if ($error): ?>
                <p style="color: #b91c1c; font-weight: 600;"><?= htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="post" class="checkout-form" action="login.php?redirect=<?= htmlspecialchars($redirect); ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
            <p style="margin-top:0.5rem;">No account yet? <a href="register.php">Create one</a></p>
            </form>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>© <?= date('Y'); ?> TAR UMT Cafeteria Online Ordering POC</p>
        </div>
    </footer>
</body>
</html>

