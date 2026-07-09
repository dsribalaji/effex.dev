<?php
session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/lib/auth.php';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } elseif (login_user($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SkillApp</title>
    <script>if(localStorage.getItem("daynight-theme")==="carbon"){document.documentElement.classList.add("carbon");}</script>
    <link rel="stylesheet" href="assets/css/app.css">
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
                    <h1 class="login-title">Welcome back</h1>
                    <p class="login-subtitle">Sign in to your account to continue</p>
                </div>

                <?php if ($error): ?>
                    <p style="color:var(--danger);font-size:0.875rem;margin-bottom:1rem;text-align:center"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <form class="login-form" method="post">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input" placeholder="Enter your username" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Sign In</button>
                </form>

                <p class="login-footer">
                    Don't have an account? <a href="signup.php">Sign up for free</a>
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
