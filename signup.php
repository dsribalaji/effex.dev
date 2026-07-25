<?php
session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// Fetch active roles
$roles = [];
require_once __DIR__ . '/lib/db.php';
try {
    $stmt = db()->query('SELECT id, name, description FROM roles WHERE is_active = 1 ORDER BY name');
    $roles = $stmt->fetchAll();
} catch (Exception $e) {
    // Table may not exist yet
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/lib/auth.php';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $roleId = (int)($_POST['role_id'] ?? 0);

    if ($username === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($roleId <= 0) {
        $error = 'Please select a role.';
    } else {
        $id = create_user($username, $email, $password, $roleId);
        if ($id === null) {
            $error = 'Username or email already taken.';
        } else {
            login_user($username, $password);
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - SkillApp</title>
    <script>if(localStorage.getItem("daynight-theme")==="carbon"){document.documentElement.classList.add("carbon");}</script>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .form-description { font-size:0.8rem; color:var(--text-secondary); margin-top:0.25rem; line-height:1.4; }
        .role-select { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 0.75rem center; padding-right:2.25rem; }
        .carbon .role-select { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); }
    </style>
</head>
<body>
    <div class="login-theme-toggle">
        <div class="theme-toggle">
            <button class="theme-btn theme-btn-snow active" onclick="setTheme('snow')" title="Snow Edition">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="5"/>
                    <line x1="12" y1="1" x2="12" y2="3"/>
                    <line x1="12" y1="21" x2="12" y2="23"/>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                    <line x1="1" y1="12" x2="3" y2="12"/>
                    <line x1="21" y1="12" x2="23" y2="12"/>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
            </button>
            <button class="theme-btn theme-btn-carbon" onclick="setTheme('carbon')" title="Carbon Edition">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="login-page">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <div class="login-logo">
                        <div class="logo-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                            </svg>
                        </div>
                        <span>SkillApp</span>
                    </div>
                    <h1 class="login-title">Create account</h1>
                    <p class="login-subtitle">Get started with SkillApp</p>
                </div>

                <?php if ($error): ?>
                    <p style="color:var(--danger);font-size:0.875rem;margin-bottom:1rem;text-align:center"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <form class="login-form" method="post">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input" placeholder="Choose a username" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" placeholder="you@example.com" required>
                    </div>

                    <?php if (!empty($roles)): ?>
                    <div class="form-group">
                        <label class="form-label">Choose your role</label>
                        <select name="role_id" class="form-input role-select" required>
                            <option value="">— Select a role —</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-description">
                            <?php foreach ($roles as $r): ?>
                                <div style="margin-top:0.25rem"><strong><?= htmlspecialchars($r['name']) ?>:</strong> <?= htmlspecialchars($r['description']) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input" placeholder="At least 6 characters" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-input" placeholder="Repeat your password" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Create Account</button>
                </form>

                <p class="login-footer">
                    Already have an account? <a href="login.php">Sign in</a>
                </p>
            </div>

            <p style="text-align:center;margin-top:1.5rem;font-size:0.8125rem;color:var(--text-secondary);">
                &copy; 2026 SkillApp
            </p>
        </div>
    </div>

    <script src="assets/js/theme.js"></script>
</body>
</html>
