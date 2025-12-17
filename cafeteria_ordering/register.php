<?php
session_start();
require_once __DIR__ . '/db.php';

// Prevent open redirects
function safe_redirect_path(string $path): string
{
    if ($path === '' || str_starts_with($path, 'http')) {
        return 'index.php';
    }
    return ltrim($path, '/');
}

$redirect = safe_redirect_path($_GET['redirect'] ?? 'index.php');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($username === '' || $password === '' || $passwordConfirm === '') {
        $errors[] = 'Username and password are required.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        // check existing
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already exists. Please choose another.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO users (username, password_hash, display_name, role) VALUES (:u, :p, :d, :r)');
            $insert->execute([
                ':u' => $username,
                ':p' => $hash,
                ':d' => $displayName ?: $username,
                ':r' => 'user',
            ]);
            $newId = (int)$pdo->lastInsertId();
            $_SESSION['user_id'] = $newId;
            $_SESSION['username'] = $username;
            $_SESSION['display_name'] = $displayName ?: $username;
            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account - TAR UMT Cafeteria</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-content">
        <h1 class="logo">Create Account</h1>
    </div>
</header>

<main class="container">
    <section class="menu-section">
        <?php if (!empty($errors)): ?>
            <ul style="color: #b91c1c; font-weight: 600; padding-left: 1rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" class="checkout-form" action="register.php?redirect=<?= htmlspecialchars($redirect); ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="display_name">Display name (optional)</label>
                <input type="text" id="display_name" name="display_name">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm password</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            <button type="submit" class="btn btn-primary">Create account</button>
            <p style="margin-top:0.5rem;">Already have an account? <a href="login.php">Log in</a></p>
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

