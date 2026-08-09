<?php
require 'config.php';
requireAdmin();

$msg = '';
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// Handle Purge All Reports
if (isset($_GET['action']) && $_GET['action'] == 'purge') {
    $conn->query("DELETE FROM fssai_cases");
    $conn->query("DELETE FROM scan_history");
    header("Location: admin.php?msg=" . urlencode("All historical reports and cases have been thoroughly purged from the database."));
    exit;
}

// Handle Report Actions
if (isset($_POST['update_report_status'])) {
    $report_id = $_POST['report_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $notes = $_POST['admin_notes'] ?? '';
    
    $stmt = $conn->prepare("UPDATE scan_history SET status=?, admin_notes=? WHERE id=?");
    $stmt->bind_param("ssi", $status, $notes, $report_id);
    if ($stmt->execute()) {
        $msg = "Report marked as $status.";
        $stmt = $conn->prepare("SELECT user_id, scanned_bar_number FROM scan_history WHERE id=?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        addNotification($conn, $r['user_id'], "Report Updated", "Your fraud report for {$r['scanned_bar_number']} is now " . strtoupper($status), "alert");
    }
}

// Handle Add Announcement
if (isset($_POST['add_announcement'])) {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $type = $_POST['type'] ?? '';
    $stmt = $conn->prepare("INSERT INTO public_announcements (title, content, announcement_type) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $title, $content, $type);
    $stmt->execute();
    $msg = "Announcement posted.";
}

// Handle Add Case
if (isset($_POST['file_case'])) {
    $case_num = 'FSSAI-' . date('Ymd-His');
    $case_type = $_POST['case_type'] ?? '';
    $report_id = !empty($_POST['report_id']) ? $_POST['report_id'] : NULL;
    $user_id = !empty($_POST['user_id']) ? $_POST['user_id'] : NULL;
    $title = $_POST['title'] ?? '';
    $desc = $_POST['description'] ?? '';
    $evidence = $_POST['evidence'] ?? '';
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    $stmt = $conn->prepare("INSERT INTO fssai_cases (case_number, case_type, report_id, user_id, title, description, evidence, is_public) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiisssi", $case_num, $case_type, $report_id, $user_id, $title, $desc, $evidence, $is_public);
    $stmt->execute();
    $msg = "New case filed: $case_num";
}

// Handle Case Update
if (isset($_POST['update_case'])) {
    $case_id = $_POST['case_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $action_taken = $_POST['action_taken'] ?? '';
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE fssai_cases SET status=?, action_taken=?, is_public=? WHERE id=?");
    $stmt->bind_param("ssii", $status, $action_taken, $is_public, $case_id);
    $stmt->execute();
    $msg = "Case updated successfully.";
}

// Mark Message Read
if (isset($_GET['msg_read'])) {
    $id = (int)$_GET['msg_read'];
    $stmt = $conn->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: admin.php"); exit();
}

$stats = [
    'under_review' => (int)($conn->query("SELECT COUNT(*) as c FROM scan_history WHERE status='under_review'")->fetch_assoc()['c'] ?? 0),
    'confirmed' => (int)($conn->query("SELECT COUNT(*) as c FROM scan_history WHERE status='confirmed'")->fetch_assoc()['c'] ?? 0),
    'cases_filed' => (int)($conn->query("SELECT COUNT(*) as c FROM fssai_cases")->fetch_assoc()['c'] ?? 0),
    'users' => (int)($conn->query("SELECT COUNT(*) as c FROM users WHERE role='user'")->fetch_assoc()['c'] ?? 0),
    'new_messages' => (int)($conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE is_read=0")->fetch_assoc()['c'] ?? 0)
];

$reports = $conn->query("SELECT s.*, u.name as user_name, u.email as user_email, u.phone as user_phone FROM scan_history s LEFT JOIN users u ON s.user_id=u.id ORDER BY FIELD(s.status, 'under_review', 'confirmed', 'false_report'), s.scanned_at DESC");

$cases = $conn->query("SELECT c.*, s.scanned_bar_number as barcode, u.name as registered_by FROM fssai_cases c LEFT JOIN scan_history s ON c.report_id = s.id LEFT JOIN users u ON c.user_id = u.id ORDER BY c.updated_at DESC");

$users_list = $conn->query("SELECT u.*, (SELECT COUNT(*) FROM scan_history s WHERE s.user_id = u.id) as rep_count, (SELECT COUNT(*) FROM scan_history s WHERE s.user_id = u.id AND s.status='confirmed') as fraud_count FROM users u WHERE u.role='user' ORDER BY fraud_count DESC, rep_count DESC");

$announcements = $conn->query("SELECT * FROM public_announcements ORDER BY created_at DESC");
$messages = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC");

function getInitials($name) {
    $name = trim($name ?? '');
    if (!$name) return 'A';
    $words = array_values(array_filter(explode(' ', $name)));
    if (count($words) > 1 && strlen($words[0]) > 0 && strlen($words[1]) > 0) {
        return strtoupper($words[0][0] . $words[1][0]);
    }
    return strlen($name) > 0 ? strtoupper($name[0]) : 'A';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - PACKED FOOD FRAUD DETECTOR</title>
    <link rel="stylesheet" href="style.css">
    <script>
    function openReportModal(id, product, barcode, batch, reason, user, email, lat, lng, image, conf, status) {
        document.getElementById('m_report_id').value = id;
        document.getElementById('m_report_product').innerText = product;
        document.getElementById('m_report_barcode').innerText = barcode;
        document.getElementById('m_report_batch').innerText = batch;
        document.getElementById('m_report_reason').innerText = reason;
        document.getElementById('m_report_user').innerText = user + " (" + email + ")";
        document.getElementById('m_report_conf').innerText = conf + "%";
        
        let imgHtml = "<div style='color:var(--muted); font-size:0.8rem; padding:10px; border:1px dashed #ccc; text-align:center;'>No image evidence uploaded.</div>";
        if (image && image !== 'null' && image !== '') {
            imgHtml = `<img src="uploads/${image}" style="width:100%; border-radius:8px; border:1px solid var(--border); box-shadow:0 4px 10px rgba(0,0,0,0.1);">`;
        }
        document.getElementById('m_report_image').innerHTML = imgHtml;
        
        let geoHtml = "<span style='color:var(--muted);'>No GPS data attached</span>";
        if (lat && lng) {
            geoHtml = `<a href="https://maps.google.com/?q=${lat},${lng}" target="_blank" style="color:var(--blue); font-weight:bold; text-decoration:none;">📍 View on Google Maps (${lat}, ${lng})</a>`;
        }
        document.getElementById('m_report_geo').innerHTML = geoHtml;
        
        // Setup file case button logic
        document.getElementById('file_case_report_id').value = id;
        document.getElementById('file_case_title').value = "Anomaly Case: " + product + " - Batch " + batch;
        
        openModal('reportDetailModal');
    }
    
    function setReportAction(status) {
        document.getElementById('m_report_status').value = status;
        document.getElementById('form_update_report').submit();
    }
    
    function fillCaseModal(id, num, status, action, ispub) {
        document.getElementById('m_case_id').value = id;
        document.getElementById('m_case_num').innerText = num;
        document.getElementById('m_case_status').value = status;
        document.getElementById('m_case_action').value = action;
        document.getElementById('m_case_pub').checked = ispub == 1 ? true : false;
        openModal('updateCaseModal');
    }
    </script>
</head>
<body>

<nav class="navbar" style="padding: 12px 24px; border-bottom: 1px solid var(--border);">
    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div style="font-weight: 800; font-size: 1.25rem; color: var(--blue);">PACKED FOOD FRAUD DETECTOR</div>
        <div class="nav-links">
            <a href="analytics.php" class="btn btn-outline" style="padding:5px 15px; font-size:0.85rem;">View Analytics Panel</a>
            <div style="width: 32px; height: 32px; background: var(--blue); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: bold; color: white;">HQ</div>
        </div>
    </div>
</nav>

<div class="dashboard-layout">
    <div class="sidebar sidebar-admin">
        <div class="sidebar-profile">
            <div class="avatar" style="background: rgba(255,255,255,0.1); color: var(--white);">HQ</div>
            <h4 style="margin-bottom: 4px;">National Admin</h4>
            <p style="font-size: 0.8rem; opacity: 0.7; margin-bottom: 4px;">admin@fssai.gov.in</p>
        </div>
        
        <div class="sidebar-nav">
            <a href="#" data-tab-target="#tab-reports" class="active">Fraud Reports 
                <?php if($stats['under_review'] > 0): ?><span class="badge badge-warning"><?php echo $stats['under_review']; ?></span><?php endif; ?>
            </a>
            <a href="#" data-tab-target="#tab-cases">Cases <span class="badge badge-primary"><?php echo $stats['cases_filed']; ?></span></a>
            <a href="#" data-tab-target="#tab-users">Users <span class="badge" style="background:rgba(255,255,255,0.2)"><?php echo $stats['users']; ?></span></a>
            <a href="#" data-tab-target="#tab-announcements">Announcements <span>📢</span></a>
            <a href="#" data-tab-target="#tab-messages">Messages
                <?php if($stats['new_messages'] > 0): ?><span class="badge badge-danger"><?php echo $stats['new_messages']; ?></span><?php endif; ?>
            </a>
            <a href="analytics.php">Analytics Dashboard <span>📊</span></a>
            <a href="logout.php" style="color: #ff7b72; margin-top: auto;">
             <span>Sign Out</span>
             <span>🚪</span>
</a>
           
        </div>
    </div>
    
    <div class="main-content">
        
        <?php if($msg): ?>
            <div class="alert alert-success" style="display:block;"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <div class="grid grid-4" style="margin-bottom: 30px;">
            <div class="card" style="padding: 20px;">
                <p class="text-muted" style="text-transform: uppercase; font-size: 0.8rem; font-weight: 600;">Under Review</p>
                <h2 style="font-size: 2rem; color: var(--amber); margin-top: 5px;"><?php echo $stats['under_review']; ?></h2>
            </div>
            <div class="card" style="padding: 20px;">
                <p class="text-muted" style="text-transform: uppercase; font-size: 0.8rem; font-weight: 600;">Confirmed Fraud</p>
                <h2 style="font-size: 2rem; color: var(--red); margin-top: 5px;"><?php echo $stats['confirmed']; ?></h2>
            </div>
            <div class="card" style="padding: 20px;">
                <p class="text-muted" style="text-transform: uppercase; font-size: 0.8rem; font-weight: 600;">Cases Filed</p>
                <h2 style="font-size: 2rem; color: var(--blue); margin-top: 5px;"><?php echo $stats['cases_filed']; ?></h2>
            </div>
            <div class="card" style="padding: 20px;">
                <p class="text-muted" style="text-transform: uppercase; font-size: 0.8rem; font-weight: 600;">New Messages</p>
                <h2 style="font-size: 2rem; color: var(--green); margin-top: 5px;"><?php echo $stats['new_messages']; ?></h2>
            </div>
        </div>

        <!-- Fraud Reports Tab -->
        <div id="tab-reports" class="tab-content active">
            <div class="card">
                <div class="flex justify-between items-center" style="margin-bottom: 24px;">
                    <h2 class="font-serif">Fraud Reports Queue</h2>
                    <a href="admin.php?action=purge" class="btn btn-outline" style="color:#cf2020; border-color:#cf2020;" onclick="return confirm('Are you sure you want to permanently delete ALL reports and cases from the national database?');">🗑️ Purge All Reports</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reporter</th>
                                <th>Product / Barcode</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($reports->num_rows > 0): while($row = $reports->fetch_assoc()): 
                                $bClass = 'badge-primary';
                                if($row['status'] == 'confirmed') $bClass = 'badge-danger';
                                if($row['status'] == 'under_review') $bClass = 'badge-warning';
                            ?>
                            <tr style="<?php if($row['status']=='under_review') echo 'background: #fffdf5;'; ?>">
                                <td style="white-space:nowrap;"><?php echo date('d M Y', strtotime($row['scanned_at'])); ?></td>
                                <td><?php echo htmlspecialchars($row['user_name']); ?><br><small><?php echo htmlspecialchars($row['user_email']); ?><br><span style="color:var(--blue);">📞 <?php echo htmlspecialchars($row['user_phone'] ?? 'N/A'); ?></span></small></td>
                                <td><strong><?php echo htmlspecialchars($row['product_name'] ?? 'Unknown'); ?></strong><br>Code: <?php echo htmlspecialchars($row['scanned_bar_number']); ?></td>
                                <td><span class="badge <?php echo $bClass; ?>"><?php echo strtoupper(str_replace('_', ' ', $row['status'])); ?></span></td>
                                <td>
                                    <button class="btn btn-outline" style="padding: 4px 10px; font-size: 0.8rem;" 
                                     onclick="openReportModal(
                                        <?php echo (int)$row['id']; ?>, 
                                        <?php echo htmlspecialchars(json_encode($row['product_name'] ?? 'Unknown')); ?>, 
                                        <?php echo htmlspecialchars(json_encode($row['scanned_bar_number'])); ?>, 
                                        <?php echo htmlspecialchars(json_encode($row['batch_code'])); ?>, 
                                        <?php echo htmlspecialchars(json_encode(str_replace(["\r", "\n"], [' ', '\n'], $row['fraud_reason'] ?? ''))); ?>, 
                                        <?php echo htmlspecialchars(json_encode($row['user_name'])); ?>, 
                                        <?php echo htmlspecialchars(json_encode($row['user_email'])); ?>, 
                                        <?php echo htmlspecialchars(json_encode($row['latitude'] ?? '')); ?>, 
                                        <?php echo htmlspecialchars(json_encode($row['longitude'] ?? '')); ?>, 
                                        <?php echo htmlspecialchars(json_encode($row['evidence_image'] ?? '')); ?>, 
                                        <?php echo (int)($row['confidence_score'] ?? 0); ?>, 
                                        <?php echo htmlspecialchars(json_encode($row['status'] ?? 'unknown')); ?>)">
                                        Review
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Cases Tab -->
        <div id="tab-cases" class="tab-content">
            <div class="card">
                <div class="flex justify-between items-center" style="margin-bottom: 24px;">
                    <h2 class="font-serif">Official Cases</h2>
                    <button class="btn btn-primary" onclick="openModal('newCaseModal')">File New Case</button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Case Number</th>
                                <th>Details</th>
                                <th>Status</th>
                                <th>Public</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($cases->num_rows > 0): while($case = $cases->fetch_assoc()): 
                                $sClass = 'badge-primary';
                                if($case['status'] == 'closed' || $case['status'] == 'convicted') $sClass = 'badge-success';
                                if($case['status'] == 'open') $sClass = 'badge-warning';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($case['case_number']); ?></strong><br><small><?php echo date('d M Y', strtotime($case['filed_at'])); ?></small></td>
                                <td><?php echo htmlspecialchars($case['title']); ?><br><small>Type: <?php echo htmlspecialchars($case['case_type']); ?></small></td>
                                <td><span class="badge <?php echo $sClass; ?>"><?php echo strtoupper($case['status']); ?></span></td>
                                <td><?php echo $case['is_public'] ? 'Yes' : 'No'; ?></td>
                                <td>
                                    <button class="btn btn-outline" style="padding: 4px 10px; font-size: 0.8rem;" 
                                    onclick="fillCaseModal(
                                        <?php echo (int)$case['id']; ?>, 
                                        <?php echo htmlspecialchars(json_encode($case['case_number'])); ?>, 
                                        <?php echo htmlspecialchars(json_encode($case['status'])); ?>, 
                                        <?php echo htmlspecialchars(json_encode($case['action_taken'] ?? '')); ?>, 
                                        <?php echo (int)$case['is_public']; ?>)">
                                        Update
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Users Tab -->
        <div id="tab-users" class="tab-content">
            <div class="card">
                <h2 class="font-serif" style="margin-bottom: 24px;">Registered Users</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email / City</th>
                                <th>Reports Made</th>
                                <th>Fraud Count</th>
                                <th>Risk Indicator</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($users_list->num_rows > 0): while($u = $users_list->fetch_assoc()): 
                                $isRisk = $u['fraud_count'] > 3;
                            ?>
                            <tr style="<?php if($isRisk) echo 'background: #fff5f5; border-left: 3px solid var(--red);'; ?>">
                                <td><strong><?php echo htmlspecialchars($u['name']); ?></strong><br><small>Joined: <?php echo date('M Y', strtotime($u['created_at'])); ?></small></td>
                                <td><?php echo htmlspecialchars($u['email']); ?><br><small><?php echo htmlspecialchars($u['city']); ?></small></td>
                                <td><?php echo $u['rep_count']; ?></td>
                                <td><?php echo $u['fraud_count']; ?></td>
                                <td>
                                    <?php if($isRisk): ?>
                                        <span class="badge badge-danger">HIGH RISK (<?php echo $u['fraud_count']; ?> frauds)</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">LOW RISK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Announcements Tab -->
        <div id="tab-announcements" class="tab-content">
            <div class="grid grid-2" style="gap: 30px;">
                <div class="card">
                    <h3 class="font-serif" style="margin-bottom: 24px;">Post New Announcement</h3>
                    <form method="POST" action="admin.php">
                        <input type="hidden" name="add_announcement" value="1">
                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-control" required style="appearance:auto">
                                <option value="warning">Warning</option>
                                <option value="fraud_alert">Fraud Alert (Severe)</option>
                                <option value="action_taken">Action Taken</option>
                                <option value="case_update">Case Update</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Content</label>
                            <textarea name="content" class="form-control" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Post to Portal</button>
                    </form>
                </div>
                
                <div class="card">
                    <h3 class="font-serif" style="margin-bottom: 24px;">Recent Announcements</h3>
                    <div style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                    <?php if($announcements->num_rows > 0): while($a = $announcements->fetch_assoc()): ?>
                        <div style="padding-bottom: 15px; border-bottom: 1px solid var(--border); margin-bottom: 15px;">
                            <strong><?php echo htmlspecialchars($a['title']); ?></strong>
                            <span class="badge badge-primary" style="font-size: 0.65rem; margin-left: 5px;"><?php echo strtoupper(str_replace('_', ' ', $a['announcement_type'])); ?></span>
                            <p style="font-size: 0.85rem; color: var(--muted); margin-top: 5px;"><?php echo htmlspecialchars($a['content']); ?></p>
                            <small class="text-muted"><?php echo date('d M Y H:i', strtotime($a['created_at'])); ?></small>
                        </div>
                    <?php endwhile; endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages Tab -->
        <div id="tab-messages" class="tab-content">
            <div class="card">
                <h2 class="font-serif" style="margin-bottom: 24px;">Public Inquiries & Tips</h2>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php if($messages->num_rows > 0): while($msg = $messages->fetch_assoc()): ?>
                        <div style="padding: 20px; border: 1px solid var(--border); border-left: 4px solid <?php echo $msg['is_read'] ? 'var(--border)' : 'var(--blue)'; ?>; border-radius: var(--radius); background: <?php echo $msg['is_read'] ? 'var(--white)' : '#f0f5ff'; ?>;">
                            <div class="flex justify-between items-center" style="margin-bottom: 12px;">
                                <div>
                                    <strong style="font-size: 1.1rem;"><?php echo htmlspecialchars($msg['subject']); ?></strong>
                                    <span style="margin-left: 10px; font-size: 0.85rem; color: var(--muted);">From: <?php echo htmlspecialchars($msg['name']); ?> (<?php echo htmlspecialchars($msg['email']); ?>)</span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <small class="text-muted"><?php echo date('d M Y, H:i', strtotime($msg['created_at'])); ?></small>
                                    <?php if(!$msg['is_read']): ?>
                                        <a href="admin.php?msg_read=<?php echo $msg['id']; ?>" class="btn btn-outline" style="padding: 2px 8px; font-size: 0.75rem;">Mark Read</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p style="font-size: 0.95rem; margin:0; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                        </div>
                    <?php endwhile; else: ?>
                        <p class="text-muted text-center">No messages available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Report Review Modal -->
<div id="reportDetailModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <h3 class="font-serif" style="margin-bottom: 20px;">Review Fraud Report</h3>
        <div style="background: var(--bg); padding: 15px; border-radius: var(--radius); margin-bottom: 20px;">
            <p style="float:right;"><span class="badge badge-primary" id="m_report_conf" style="font-size:1rem; padding:5px 10px;">0%</span></p>
            <p><strong>Reporter:</strong> <span id="m_report_user"></span></p>
            <p><strong>Product:</strong> <span id="m_report_product"></span></p>
            <p><strong>Barcode:</strong> <span id="m_report_barcode"></span> | <strong>Batch:</strong> <span id="m_report_batch"></span></p>
            <p><strong>Location:</strong> <span id="m_report_geo"></span></p>
            <p style="margin-top: 10px; color: var(--red);"><strong>Reason:</strong> <span id="m_report_reason"></span></p>
        </div>

        <div id="m_report_image" style="margin-bottom: 20px;"></div>
        
        <form id="form_update_report" method="POST" action="admin.php">
            <input type="hidden" name="update_report_status" value="1">
            <input type="hidden" name="report_id" id="m_report_id">
            <input type="hidden" name="status" id="m_report_status">
            
            <div class="form-group">
                <label class="form-label">Admin Notes (Visible to Reporter)</label>
                <textarea name="admin_notes" class="form-control" rows="2" placeholder="e.g. Forwarded to designated lab for testing."></textarea>
            </div>
            
            <div class="flex gap-2" style="margin-bottom: 20px;">
                <button type="button" class="btn btn-primary" style="flex:1; background: var(--red);" onclick="setReportAction('confirmed')">Verify Anomaly</button>
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="setReportAction('false_report')">Dismiss (False Positive)</button>
            </div>
            
            <hr style="border: none; border-top: 1px solid var(--border); margin: 20px 0;">
            
            <button type="button" class="btn btn-primary" style="width: 100%;" onclick="closeModal('reportDetailModal'); openModal('newCaseModal');">Create Legal Case From Report</button>
        </form>
        <button class="btn btn-outline" style="width: 100%; margin-top: 10px;" data-modal-close>Close</button>
    </div>
</div>

<!-- New Case Modal -->
<div id="newCaseModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <h3 class="font-serif" style="margin-bottom: 20px;">File Official Case</h3>
        <form method="POST" action="admin.php">
            <input type="hidden" name="file_case" value="1">
            <input type="hidden" name="report_id" id="file_case_report_id">
            
            <div class="form-group">
                <label class="form-label">Case Title</label>
                <input type="text" name="title" id="file_case_title" class="form-control" required>
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Case Type</label>
                    <select name="case_type" class="form-control" style="appearance:auto;">
                        <option value="adulteration">Adulteration</option>
                        <option value="counterfeit">Counterfeit Brand</option>
                        <option value="fake_expiry">Expiry Date Tampering</option>
                        <option value="user_penalty">False Reporter Penalty</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Public Visibility</label>
                    <div style="padding: 10px;">
                        <input type="checkbox" name="is_public" id="ispub" checked> <label for="ispub">Visible on Portal</label>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Details & Description</label>
                <textarea name="description" class="form-control" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Evidentiary Notes</label>
                <textarea name="evidence" class="form-control" rows="2"></textarea>
            </div>
            
            <div class="flex gap-4 mt-4">
                <button type="submit" class="btn btn-primary" style="flex: 1;">File Case</button>
                <button type="button" class="btn btn-outline" style="flex: 1;" data-modal-close>Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Update Case Modal -->
<div id="updateCaseModal" class="modal">
    <div class="modal-content">
        <h3 class="font-serif" style="margin-bottom: 20px;">Update Case: <span id="m_case_num" style="color:var(--blue)"></span></h3>
        <form method="POST" action="admin.php">
            <input type="hidden" name="update_case" value="1">
            <input type="hidden" name="case_id" id="m_case_id">
            
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="m_case_status" class="form-control" style="appearance:auto;">
                    <option value="open">Open</option>
                    <option value="investigating">Investigating</option>
                    <option value="closed">Closed</option>
                    <option value="convicted">Convicted</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Action Taken</label>
                <textarea name="action_taken" id="m_case_action" class="form-control" rows="3" placeholder="e.g. Factory sealed. License revoked."></textarea>
            </div>
            <div class="form-group">
                <input type="checkbox" name="is_public" id="m_case_pub"> <label for="m_case_pub">Public Visibility</label>
            </div>
            
            <div class="flex gap-4 mt-4">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Update Case</button>
                <button type="button" class="btn btn-outline" style="flex: 1;" data-modal-close>Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="script.js"></script>
</body>
</html>
