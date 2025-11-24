<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if ($action === 'register') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already exists";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            if ($stmt->execute([$username, $hash])) {
                $success = "Account created! Please login.";
                $action = 'login'; // Switch to login view
            } else {
                $error = "Registration failed";
            }
        }
    } else {
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            redirect('dashboard.php');
        } else {
            $error = "Invalid credentials";
        }
    }
}

$isRegister = isset($_GET['mode']) && $_GET['mode'] === 'register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spend Calculator - Login</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .minimal-auth {
            width: 100%;
            max-width: 360px;
            padding: 2rem;
        }
        .minimal-input {
            background: transparent;
            border: none;
            border-bottom: 2px solid var(--surface-light);
            border-radius: 0;
            padding: 1rem 0;
            color: var(--text);
            font-size: 1rem;
        }
        .minimal-input:focus {
            box-shadow: none;
            border-color: var(--primary);
        }
        .minimal-btn {
            margin-top: 2rem;
            border-radius: 2rem;
        }
    </style>
</head>
<body>
    <div class="minimal-auth">
        <div style="margin-bottom: 3rem;">
            <h1 class="logo" style="font-size: 2.5rem;">SpendCalc</h1>
            <p style="color: var(--text-muted); margin-top: 0.5rem;"><?php echo $isRegister ? 'Create your account' : 'Sign in to continue'; ?></p>
        </div>

        <?php if ($error): ?>
            <div style="color: var(--danger); margin-bottom: 1.5rem; font-size: 0.9rem;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="color: var(--success); margin-bottom: 1.5rem; font-size: 0.9rem;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="?mode=<?php echo $isRegister ? 'register' : 'login'; ?>">
            <input type="hidden" name="action" value="<?php echo $isRegister ? 'register' : 'login'; ?>">
            
            <div class="form-group">
                <input type="text" name="username" class="form-control minimal-input" required placeholder="Username">
            </div>

            <div class="form-group">
                <input type="password" name="password" class="form-control minimal-input" required placeholder="Password">
            </div>

            <button type="submit" class="btn btn-primary minimal-btn">
                <?php echo $isRegister ? 'Sign Up' : 'Sign In'; ?>
            </button>
        </form>

        <div style="margin-top: 2rem;">
            <a href="?mode=<?php echo $isRegister ? 'login' : 'register'; ?>" style="color: var(--text-muted); font-size: 0.9rem; text-decoration: none;">
                <?php echo $isRegister ? 'Already have an account? Login' : 'Create an account'; ?>
            </a>
        </div>
    </div>
</body>
</html>
