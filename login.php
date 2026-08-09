<?php
require 'config.php';
if (isLoggedIn()) {
    header("Location: " . (isAdmin() ? "admin.php" : "index.php"));
    exit();
}

$login_err = '';
$reg_err = '';
$reg_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $login_err = "CSRF token validation failed.";
    } elseif (isset($_POST['login'])) {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 1) {
            $user = $res->fetch_assoc();
            if ($user['is_active'] == 0) {
                $login_err = "Account is disabled.";
            } else {
                $is_valid = password_verify($password, $user['password']);
                
                if ($is_valid) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'];
                    
                    $conn->query("UPDATE users SET last_login = NOW() WHERE id = " . $user['id']);
                    
                    header("Location: " . ($user['role'] === 'admin' ? "admin.php" : "index.php"));
                    exit();
                } else {
                    $login_err = "Invalid credentials.";
                }
            }
        } else {
            $login_err = "Invalid credentials.";
        }
    } elseif (isset($_POST['register'])) {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $reg_err = "CSRF token validation failed.";
        } else {
            $name = trim(htmlspecialchars($_POST['name'] ?? ''));
            $email = trim(htmlspecialchars($_POST['email'] ?? ''));
            $phone = trim(htmlspecialchars($_POST['phone'] ?? ''));
            $city = trim(htmlspecialchars($_POST['city'] ?? ''));
            $password = $_POST['password'] ?? '';
            $cpassword = $_POST['cpassword'] ?? '';
            
            if (strlen($password) < 6) {
                $reg_err = "Password must be at least 6 characters.";
            } elseif ($password !== $cpassword) {
                $reg_err = "Passwords do not match.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, city, password, role) VALUES (?, ?, ?, ?, ?, 'user')");
                $stmt->bind_param("sssss", $name, $email, $phone, $city, $hash);
                if ($stmt->execute()) {
                    $reg_success = "Registration successful. Please log in.";
                    $_POST = array(); // clear post array
                } else {
                    $reg_err = "Email already registered.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - PACKED FOOD FRAUD DETECTOR</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="split-layout">
    <div class="split-left">
        <div>
            <h1 class="font-serif" style="font-size: 3rem; margin-bottom: 20px;">Protect Our Food Together</h1>
            <p style="font-size: 1.1rem; opacity: 0.9; margin-bottom: 40px; max-width: 400px;">Join thousands of citizen watchers across India using technology to detect and report unsafe food products.</p>
            
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 1.5rem;">🔒</span> Security & Anonymity
                </li>
                <li style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 1.5rem;">⚡</span> Instant Verification
                </li>
                <li style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 1.5rem;">🏛️</span> Direct Authority Action
                </li>
            </ul>

            <div style="margin-top: 60px;">
                <a href="public.php" style="color: var(--white); text-decoration: underline;">&larr; Back to Public Portal</a>
            </div>
        </div>
    </div>
    
    <div class="split-right">
        <div class="auth-box">
            
            <div style="display: flex; gap: 10px; margin-bottom: 30px;">
                <button id="tab-login" class="btn <?php echo isset($_POST['register']) ? 'btn-outline' : 'btn-primary'; ?>" style="flex: 1;">Login</button>
                <button id="tab-register" class="btn <?php echo isset($_POST['register']) ? 'btn-primary' : 'btn-outline'; ?>" style="flex: 1;">Register</button>
            </div>

            <!-- Login Form -->
            <form id="form-login" method="POST" action="" style="display: <?php echo isset($_POST['register']) ? 'none' : 'block'; ?>;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="login" value="1">
                <h3 class="font-serif" style="margin-bottom: 24px; font-size: 1.5rem;">Sign in to your account</h3>
                
                <?php if($login_err): ?>
                    <div class="alert alert-danger" style="display:block; padding: 10px; margin-bottom: 15px;"><?php echo $login_err; ?></div>
                <?php endif; ?>
                <?php if($reg_success): ?>
                    <div class="alert alert-success" style="display:block; padding: 10px; margin-bottom: 15px;"><?php echo $reg_success; ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="name@domain.in">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 1.1rem;">Sign In</button>
            </form>

            <!-- Register Form -->
            <form id="form-register" method="POST" action="" style="display: <?php echo isset($_POST['register']) ? 'block' : 'none'; ?>;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="register" value="1">
                <h3 class="font-serif" style="margin-bottom: 24px; font-size: 1.5rem;">Create a Watcher Account</h3>
                
                <?php if($reg_err): ?>
                    <div class="alert alert-danger" style="display:block; padding: 10px; margin-bottom: 15px;"><?php echo $reg_err; ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="grid grid-2" style="gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" minlength="6" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="cpassword" class="form-control" minlength="6" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 1.1rem;">Register</button>
            </form>
            
        </div>
    </div>
</div>

<script src="script.js"></script>
</body>
</html>
