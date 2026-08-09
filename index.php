<?php
require 'config.php';
requireLogin();

if (isAdmin()) {
    header("Location: admin.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Profile Update
if (isset($_POST['update_profile'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die("CSRF token validation failed.");
    }
    $name = trim(htmlspecialchars($_POST['name'] ?? ''));
    $phone = trim(htmlspecialchars($_POST['phone'] ?? ''));
    $city = trim(htmlspecialchars($_POST['city'] ?? ''));
    $bio = trim(htmlspecialchars($_POST['bio'] ?? ''));
    $stmt = $conn->prepare("UPDATE users SET name=?, phone=?, city=?, bio=? WHERE id=?");
    $stmt->bind_param("ssssi", $name, $phone, $city, $bio, $user_id);
    $stmt->execute();
    $_SESSION['name'] = $name;
}

// Password Change
$pwd_msg = '';
if (isset($_POST['change_password'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $pwd_msg = "<div class='alert alert-danger' style='display:block;'>CSRF token validation failed.</div>";
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $db_pass = $stmt->get_result()->fetch_assoc()['password'];
        
        $is_valid = password_verify($current, $db_pass);
        
        if (!$is_valid) {
            $pwd_msg = "<div class='alert alert-danger' style='display:block;'>Current password is incorrect.</div>";
        } elseif ($new !== $confirm) {
            $pwd_msg = "<div class='alert alert-danger' style='display:block;'>New passwords do not match.</div>";
        } elseif (strlen($new) < 6) {
            $pwd_msg = "<div class='alert alert-danger' style='display:block;'>New password must be at least 6 characters.</div>";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $upd->bind_param("si", $hash, $user_id);
            $upd->execute();
            $pwd_msg = "<div class='alert alert-success' style='display:block;'>Password updated successfully.</div>";
        }
    }
}

if (isset($_GET['mark_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    header("Location: index.php?tab=notifications");
    exit();
}

// User Data
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Stats
$stmt = $conn->prepare("SELECT COUNT(*) as c FROM scan_history WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_scans = $stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM scan_history WHERE user_id=? AND is_fraud=1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$fraud_found = $stmt->get_result()->fetch_assoc()['c'];

$safe_prods = max(0, $total_scans - $fraud_found);

// Reports History
$stmt = $conn->prepare("SELECT * FROM scan_history WHERE user_id=? ORDER BY scanned_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$history = $stmt->get_result();

// Notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
$unread_count = getUnreadNotifications($conn, $user_id);

function getInitials($name) {
    $name = trim($name ?? '');
    if (!$name) return 'U';
    $words = array_values(array_filter(explode(' ', $name)));
    if (count($words) > 1 && strlen($words[0]) > 0 && strlen($words[1]) > 0) {
        return strtoupper($words[0][0] . $words[1][0]);
    }
    return strlen($name) > 0 ? strtoupper($name[0]) : 'U';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - PACKED FOOD FRAUD DETECTOR</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
    <style>
        .bell-icon { position: relative; }
        .bell-badge { position: absolute; top: -5px; right: -5px; background: var(--red); color: white; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: bold; }
    </style>
</head>
<body>

<nav class="navbar" style="padding: 12px 24px; border-bottom: 1px solid var(--border);">
    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div style="font-weight: 800; font-size: 1.25rem; color: var(--blue);">PACKED FOOD FRAUD DETECTOR</div>
        <div class="nav-links">
            <a href="index.php?mark_read=1" class="bell-icon" title="Clear all notifications">
                🔔
                <?php if($unread_count > 0): ?><span class="bell-badge"><?php echo $unread_count; ?></span><?php endif; ?>
            </a>
            <div style="width: 32px; height: 32px; background: var(--bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: bold; background: var(--blue); color: white; margin-left:10px;">
                <?php echo getInitials($user['name']); ?>
            </div>
        </div>
    </div>
</nav>

<div class="dashboard-layout">
    <div class="sidebar">
        <div class="sidebar-profile">
            <div class="avatar"><?php echo getInitials($user['name']); ?></div>
            <h4 style="margin-bottom: 4px;"><?php echo htmlspecialchars($user['name']); ?></h4>
            <p style="font-size: 0.8rem; color: var(--muted); margin-bottom: 4px;"><?php echo htmlspecialchars($user['email']); ?></p>
            <span class="badge badge-primary"><?php echo htmlspecialchars($user['city'] ?? 'India'); ?></span>
        </div>
        
        <div class="sidebar-nav">
            <a href="#" data-tab-target="#tab-verify" class="active">Verify Product <span>🔍</span></a>
            <a href="#" data-tab-target="#tab-reports">My Reports <span>📋</span></a>
            <a href="#" data-tab-target="#tab-notifications">
                Notifications 
                <?php if($unread_count > 0): ?><span class="badge badge-danger"><?php echo $unread_count; ?></span><?php endif; ?>
            </a>
            <a href="#" data-tab-target="#tab-profile">My Profile <span>👤</span></a>
            <a href="#" data-tab-target="#tab-password">Change Password <span>🔒</span></a>
            <a href="public.php">Public Portal <span>🌐</span></a>
            <a href="contact.php">Contact FSSAI <span>✉️</span></a>
            <a href="logout.php" style="color: var(--red);">Sign Out <span>🚪</span></a>
        </div>
        
        <div style="margin-top: auto; padding: 20px; background: rgba(0,0,0,0.03); border-radius: 12px; margin: 20px;">
            <p style="font-size: 0.65rem; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; font-weight: bold; letter-spacing: 1px;">Network Status</p>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 8px; height: 8px; background: #22c55e; border-radius: 50%; box-shadow: 0 0 8px #22c55e;"></div>
                <span style="font-size: 0.75rem; color: #15803d; font-weight: 600;">Secure API Gateway Active</span>
            </div>
            <p style="font-size: 0.6rem; color: var(--muted); margin-top: 5px;">Linked to FoSCoS Node #4419</p>
        </div>
    </div>
    
    <div class="main-content">
        
        <div class="grid grid-3" style="margin-bottom: 30px;">
            <div class="card" style="padding: 20px;">
                <p class="text-muted" style="text-transform: uppercase; font-size: 0.8rem; font-weight: 600;">Total Reports</p>
                <h2 style="font-size: 2rem; color: var(--blue); margin-top: 5px;"><?php echo $total_scans; ?></h2>
            </div>
            <div class="card" style="padding: 20px;">
                <p class="text-muted" style="text-transform: uppercase; font-size: 0.8rem; font-weight: 600;">Fraud Flags</p>
                <h2 style="font-size: 2rem; color: var(--red); margin-top: 5px;"><?php echo $fraud_found; ?></h2>
            </div>
            <div class="card" style="padding: 20px;">
                <p class="text-muted" style="text-transform: uppercase; font-size: 0.8rem; font-weight: 600;">Account Status</p>
                <?php 
                    $status_text = "Citizen Watcher";
                    $status_color = "var(--green)";
                    if($fraud_found > 5) { $status_text = "Elite Scout"; $status_color = "var(--blue)"; }
                    if($total_scans > 20 && $fraud_found == 0) { $status_text = "Trusted Sentinel"; $status_color = "var(--ink)"; }
                ?>
                <h2 style="font-size: 1.5rem; color: <?php echo $status_color; ?>; margin-top: 10px;"><?php echo $status_text; ?></h2>
            </div>
        </div>

        <!-- Verify Product Tab -->
        <div id="tab-verify" class="tab-content active">
            <div class="card" style="max-width: 600px;">
                <h2 class="font-serif" style="margin-bottom: 24px;">Heuristic Anomaly Screening</h2>
                
                <button type="button" class="btn btn-outline" style="width: 100%; margin-bottom: 24px; display: flex; gap: 10px; align-items: center;" onclick="toggleScanner()">
                    📷 Use Camera Scanner (Barcode)
                </button>
                
                <div id="interactive" class="viewport"></div>

                <form id="verifyForm" onsubmit="verifyProduct(event)">
                    <div class="form-group">
                        <label class="form-label">Barcode Number (EAN-13)</label>
                        <input type="text" id="barcode_input" class="form-control" required placeholder="e.g. 8901058000290">
                    </div>
                    <div class="grid grid-1">
                        <div class="form-group">
                            <label class="form-label">Batch Code</label>
                            <input type="text" id="batch_input" class="form-control" required placeholder="e.g. 150 or 250115">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Printed Exp Date <br><small style="color:var(--muted); font-weight:normal;">(Required)</small></label>
                            <input type="date" id="expiry_input" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 15px; padding: 15px; border: 1px dashed var(--border); border-radius: var(--radius); background: #fdfdfd;">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px; color: var(--blue);">
                            📸 Visual Evidence Capture (Optional)
                        </label>
                        <p style="font-size: 0.75rem; color: var(--muted); margin-bottom: 10px;">Upload a clear photo of the product label showing the Barcode or Batch code for Forensic Image Analysis.</p>
                        <input type="file" id="evidence_image" class="form-control" accept="image/*" style="font-size: 0.8rem; padding: 6px;">
                    </div>
                    


                    <button type="submit" id="verify_btn" class="btn btn-primary" style="width: 100%;">Verify Product</button>
                    
                    <div id="gps_status" style="margin-top: 10px; font-size: 0.7rem; color: var(--muted); text-align: center; display: flex; align-items: center; justify-content: center; gap: 5px;">
                        <span id="gps_dot" style="width: 6px; height: 6px; background: #94a3b8; border-radius: 50%;"></span>
                        <span id="gps_text">Checking GPS System...</span>
                    </div>

                    <div id="verify_result" style="display: none; margin-top: 24px;"></div>
                </form>
            </div>
        </div>

        <!-- My Reports Tab -->
        <div id="tab-reports" class="tab-content">
            <div class="card">
                <h2 class="font-serif" style="margin-bottom: 24px;">Investigation History</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product Details</th>
                                <th>Status</th>
                                <th>Admin Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($history->num_rows > 0): while($row = $history->fetch_assoc()): 
                                $badgeClass = 'badge-primary';
                                if($row['status'] == 'confirmed') $badgeClass = 'badge-success';
                                if($row['status'] == 'false_report') $badgeClass = 'badge-danger';
                            ?>
                            <tr>
                                <td style="white-space:nowrap;"><?php echo date('d M Y, H:i', strtotime($row['scanned_at'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['product_name'] ?? 'Stateless Product'); ?></strong><br>
                                    <small class="text-muted">Barcode: <?php echo htmlspecialchars($row['scanned_bar_number']); ?> | Batch: <?php echo htmlspecialchars($row['batch_code']); ?></small>
                                </td>
                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo strtoupper(str_replace('_', ' ', $row['status'])); ?></span></td>
                                <td style="max-width:250px;"><small><?php echo htmlspecialchars($row['admin_notes'] ?? '-'); ?></small></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="4" class="text-center text-muted">You haven't submitted any reports yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Notifications Tab -->
        <div id="tab-notifications" class="tab-content">
            <div class="card">
                <div class="flex justify-between items-center" style="margin-bottom: 24px;">
                    <h2 class="font-serif">Notifications</h2>
                    <?php if($unread_count > 0): ?>
                        <a href="index.php?mark_read=1" class="btn btn-outline" style="padding: 5px 10px; font-size: 0.8rem;">Mark all as read</a>
                    <?php endif; ?>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php if($notifications->num_rows > 0): while($notif = $notifications->fetch_assoc()): ?>
                        <div style="padding: 16px; border: 1px solid var(--border); border-left: 4px solid <?php echo $notif['is_read'] ? 'var(--border)' : 'var(--blue)'; ?>; border-radius: var(--radius); background: <?php echo $notif['is_read'] ? 'var(--white)' : '#f0f5ff'; ?>;">
                            <div class="flex justify-between" style="margin-bottom: 8px;">
                                <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                                <small class="text-muted"><?php echo date('d M, H:i', strtotime($notif['created_at'])); ?></small>
                            </div>
                            <p style="font-size: 0.9rem; margin:0;"><?php echo htmlspecialchars($notif['message']); ?></p>
                        </div>
                    <?php endwhile; else: ?>
                        <p class="text-muted text-center">No notifications available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Profile Tab -->
        <div id="tab-profile" class="tab-content">
            <div class="card" style="max-width: 600px;">
                <h2 class="font-serif" style="margin-bottom: 24px;">Edit Profile</h2>
                <form method="POST" action="index.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="form-group">
                        <label class="form-label">Email Address (Read-only)</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly style="background:var(--bg); cursor:not-allowed;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($user['city']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bio (Optional)</label>
                        <textarea name="bio" class="form-control" rows="3"><?php echo htmlspecialchars($user['bio']); ?></textarea>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>

        <!-- Password Tab -->
        <div id="tab-password" class="tab-content">
            <div class="card" style="max-width: 600px;">
                <h2 class="font-serif" style="margin-bottom: 24px;">Change Security Key</h2>
                <?php echo $pwd_msg; ?>
                <form method="POST" action="index.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- Modal for Confirming Report -->
<div id="reportModal" class="modal">
    <div class="modal-content">
        <h3 class="font-serif text-red" style="margin-bottom: 20px;">Final Warning: Submit Official Report</h3>
        <p style="margin-bottom: 20px;">By proceeding, you are filing an official report with the Food Safety and Standards Authority of India.</p>
        <ul style="margin-bottom: 30px; padding-left: 20px;">
            <li style="margin-bottom: 10px;">I confirm the details entered match the physical product packaging exactly.</li>
            <li style="margin-bottom: 10px;">I understand filing repeated false reports will result in my account being flagged.</li>
            <li style="margin-bottom: 10px;">I am submitting this in good faith to protect public health.</li>
        </ul>
        <div class="flex gap-4">
            <button class="btn btn-primary" style="flex: 1;" id="submit_report_btn" onclick="submitReport()">Yes, Submit Report</button>
            <button class="btn btn-outline" style="flex: 1;" data-modal-close>Cancel</button>
        </div>
    </div>
</div>

<script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
