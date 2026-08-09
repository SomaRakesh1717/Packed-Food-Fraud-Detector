<?php
require 'config.php';
$msg = '';
$msgClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = "CSRF token validation failed.";
        $msgClass = "alert-danger";
    } else {
        $name = trim(htmlspecialchars($_POST['name'] ?? ''));
        $email = trim(htmlspecialchars($_POST['email'] ?? ''));
        $subject = trim(htmlspecialchars($_POST['subject'] ?? ''));
        $message = trim(htmlspecialchars($_POST['message'] ?? ''));
        
        if ($name && $email && $subject && $message) {
            $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $subject, $message);
            if ($stmt->execute()) {
                $msg = "Message sent successfully. An FSSAI representative will get back to you.";
                $msgClass = "alert-success";
            } else {
                $msg = "Error sending message. Please try again later.";
                $msgClass = "alert-danger";
            }
        } else {
            $msg = "All fields are required.";
            $msgClass = "alert-danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact FSSAI - Food Fraud Watch</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="background: var(--bg); min-height: 100vh; display: flex; flex-direction: column;">

<nav class="navbar" style="border-bottom: 1px solid var(--border);">
    <div class="container flex justify-between items-center">
        <a href="public.php" style="font-size: 1.5rem; font-weight: 800; color: var(--blue);">FSSAI Fraud Watch</a>
        <div class="nav-links">
            <a href="public.php#alerts">Alerts</a>
            <a href="public.php#active-cases">Active Cases</a>
            <a href="public.php#solved-cases">Solved Cases</a>
            <a href="contact.php" style="color: var(--blue);">Contact</a>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo isAdmin() ? 'admin.php' : 'index.php'; ?>" class="btn btn-primary">Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">Sign In / Report</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container" style="flex: 1; padding: 60px 24px;">
    <div class="grid grid-2" style="background: var(--white); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border); overflow: hidden;">
        
        <div style="background: var(--ink); color: var(--white); padding: 50px;">
            <h2 class="font-serif" style="margin-bottom: 20px; font-size: 2.5rem;">Get in Touch</h2>
            <p style="opacity: 0.8; margin-bottom: 50px; font-size: 1.05rem;">Have specific evidence, corporate whistleblower information, or other confidential queries? Contact our dedicated FSSAI compliance desk directly.</p>
            
            <div style="margin-bottom: 30px;">
                <h4 style="color: var(--blue); margin-bottom: 5px; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;">National Toll-Free Helpline</h4>
                <p style="font-size: 1.2rem; font-weight: 500;">1800-11-2100</p>
            </div>
            <div style="margin-bottom: 30px;">
                <h4 style="color: var(--blue); margin-bottom: 5px; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;">Email Support</h4>
                <p style="font-size: 1.2rem; font-weight: 500;">fraudwatch@fssai.gov.in</p>
            </div>
            <div style="margin-bottom: 30px;">
                <h4 style="color: var(--blue); margin-bottom: 5px; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;">Headquarters Address</h4>
                <p style="font-size: 1.1rem;">FDA Bhawan near Bal Bhavan,<br>Kotla Road, New Delhi - 110002</p>
            </div>
            <div style="margin-bottom: 30px;">
                <h4 style="color: var(--blue); margin-bottom: 5px; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;">Website</h4>
                <p style="font-size: 1.1rem;">www.fssai.gov.in</p>
            </div>
        </div>
        
        <div style="padding: 50px;">
            <h3 class="font-serif" style="margin-bottom: 30px; font-size: 1.75rem;">Send a Secure Message</h3>
            
            <?php if($msg): ?>
                <div class="alert <?php echo $msgClass; ?>" style="display:block;"><?php echo $msg; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="Dr. John Doe">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="john@domain.in">
                </div>
                <div class="form-group">
                    <label class="form-label">Subject Category</label>
                    <select name="subject" class="form-control" required style="appearance: auto;">
                        <option value="">-- Select Subject --</option>
                        <option value="General Query">General Query</option>
                        <option value="Whistleblower Tip">Whistleblower Tip (Corporate)</option>
                        <option value="Portal Feedback">Portal Feedback</option>
                        <option value="Licensing Fraud">Licensing Fraud Issue</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 30px;">
                    <label class="form-label">Confidential Message</label>
                    <textarea name="message" class="form-control" rows="6" required placeholder="Provide your tip or specific query details here..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 1.1rem;">Send Message to FSSAI</button>
            </form>
        </div>
        
    </div>
</div>

<footer class="text-center" style="background: var(--ink); color: var(--white); padding: 30px 0;">
    <div class="container">
        <p class="text-muted" style="font-size: 0.8rem;">© 2026 Food Safety and Standards Authority of India (FSSAI). All rights reserved.</p>
    </div>
</footer>

<script src="script.js"></script>
</body>
</html>
