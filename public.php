<?php
require 'config.php';

// Fetch Announcements
$announcements = $conn->query("SELECT * FROM public_announcements ORDER BY created_at DESC LIMIT 6");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PACKED FOOD FRAUD DETECTOR</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .hero { background: var(--blue); color: var(--white); padding: 80px 0; text-align: center; }
        .hero h1 { font-size: 3rem; margin-bottom: 20px; font-family: 'Lora', serif; }
        .hero p { font-size: 1.25rem; opacity: 0.9; max-width: 600px; margin: 0 auto 30px auto; }
        .section-title { font-size: 2rem; text-align: center; margin-bottom: 40px; }
        .accordion { background: var(--white); border-radius: var(--radius); border: 1px solid var(--border); overflow: hidden; text-align: left; }
        .accordion-item { border-bottom: 1px solid var(--border); }
        .accordion-item:last-child { border-bottom: none; }
        .accordion-header { padding: 20px; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: #fafbfc; user-select: none; }
        .accordion-body { padding: 20px; display: none; background: var(--white); border-top: 1px solid var(--border); }
        .accordion-header.active + .accordion-body { display: block; animation: fadeIn 0.3s ease; }
        .cta-banner { background: var(--ink); color: var(--white); padding: 60px 0; text-align: center; }
    </style>
</head>
<body>

<div class="top-strip">
    <div class="container">
        Official Portal of Food Safety and Standards Authority of India | National Toll-Free: 1800-11-2100
    </div>
</div>

<nav class="navbar">
    <div class="container flex justify-between items-center">
        <a href="public.php" style="font-size: 1.5rem; font-weight: 800; color: var(--blue);">PACKED FOOD FRAUD DETECTOR</a>
        <div class="nav-links">
            <a href="#alerts">Alerts</a>
            <a href="#how-it-works">How it Works</a>
            <a href="contact.php">Contact</a>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo isAdmin() ? 'admin.php' : 'index.php'; ?>" class="btn btn-primary">Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">Sign In / Report</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<section class="hero">
    <div class="container">
        <h1>Ensuring Safe Food for a Billion Indians</h1>
        <p>A unified platform to verify product authenticity, report suspected food fraud, and track ongoing FSSAI investigations across the nation.</p>
        <div class="flex justify-center gap-4">
            <a href="login.php" class="btn btn-outline" style="color: white; border-color: rgba(255,255,255,0.5);">Report a Fake Product</a>
            <a href="#how-it-works" class="btn btn-primary" style="background: var(--white); color: var(--blue);">How it Works</a>
        </div>
    </div>
</section>

<!-- Stats section removed per user request -->
<div class="container" style="margin-bottom: 40px;"></div>

<section id="alerts" style="padding: 80px 0; background: var(--bg);">
    <div class="container">
        <h2 class="section-title text-red font-serif">Official FSSAI Alerts</h2>
        <div class="grid grid-3">
            <?php 
            if($announcements):
                while($alert = $announcements->fetch_assoc()): 
                    $badgeClass = 'badge-primary';
                    if ($alert['announcement_type'] === 'warning') $badgeClass = 'badge-warning';
                    if ($alert['announcement_type'] === 'fraud_alert') $badgeClass = 'badge-danger';
                    if ($alert['announcement_type'] === 'action_taken') $badgeClass = 'badge-success';
                ?>
                <div class="card">
                    <span class="badge <?php echo $badgeClass; ?>"><?php echo str_replace('_', ' ', strtoupper($alert['announcement_type'])); ?></span>
                    <h4 style="margin: 12px 0;"><?php echo htmlspecialchars($alert['title']); ?></h4>
                    <p class="text-muted" style="font-size: 0.9rem;"><?php echo htmlspecialchars($alert['content']); ?></p>
                    <p style="font-size: 0.8rem; margin-top: 15px; color: var(--muted);"><?php echo date('d M Y', strtotime($alert['created_at'])); ?></p>
                </div>
                <?php endwhile; 
            endif; ?>
        </div>
    </div>
</section>

<section id="how-it-works" style="padding: 80px 0; background: var(--white);">
    <div class="container">
        <h2 class="section-title font-serif">How It Works</h2>
        <div class="grid grid-4 text-center">
            <div class="card">
                <div style="font-size: 3rem; margin-bottom: 20px;">📱</div>
                <h4>1. Scan Product</h4>
                <p class="text-muted" style="margin-top:10px; font-size: 0.9rem;">Use your phone camera or enter the barcode manually on our portal.</p>
            </div>
            <div class="card">
                <div style="font-size: 3rem; margin-bottom: 20px;">🔍</div>
                <h4>2. Verify Dates</h4>
                <p class="text-muted" style="margin-top:10px; font-size: 0.9rem;">Our system decodes the batch number and calculates true expiry dates.</p>
            </div>
            <div class="card">
                <div style="font-size: 3rem; margin-bottom: 20px;">🚩</div>
                <h4>3. Report Flag</h4>
                <p class="text-muted" style="margin-top:10px; font-size: 0.9rem;">If printed dates don't match our database, submit a fraud report.</p>
            </div>
            <div class="card">
                <div style="font-size: 3rem; margin-bottom: 20px;">⚖️</div>
                <h4>4. FSSAI Action</h4>
                <p class="text-muted" style="margin-top:10px; font-size: 0.9rem;">Authorities investigate the batch and take decisive legal action.</p>
            </div>
        </div>
    </div>
</section>

<section id="science-of-detection" style="padding: 80px 0; background: #f8fafc; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
    <div class="container" style="max-width: 900px;">
        <h2 class="section-title font-serif text-blue">The Science of Food Fraud Detection</h2>
        <p style="text-align:center; font-size:1.1rem; color:var(--muted); margin-bottom:50px;">How our mathematical engine reverse-engineers factory codes to catch scammers.</p>
        
        <div class="card" style="margin-bottom: 30px; padding: 30px; border-left: 4px solid var(--blue);">
            <h3 style="margin-bottom: 15px; color: var(--ink);">1. Decoding the Barcode (GS1 Standards)</h3>
            <p style="margin-bottom: 15px; line-height: 1.6;">Every authentic product uses a 13-digit EAN barcode regulated by global GS1 standards. The barcode is mathematically broken down into 4 specific segments:</p>
            <ul style="line-height: 1.6; margin-left: 20px; margin-bottom: 15px;">
                <li><strong>Country Code (First 3 digits):</strong> <code>890</code> means the product was legally registered in India.</li>
                <li><strong>Manufacturer Code (Next 4-5 digits):</strong> Our engine natively identifies these. For example, <code>10580</code> means Nestlé, and <code>1719</code> means Parle.</li>
                <li><strong>Item Reference (Next 5 digits):</strong> This is the specific <em>Product Code</em>. It tells the manufacturer exactly what is inside the wrapper (e.g., <code>00001</code> = 50g KitKat vs <code>00002</code> = 100g KitKat).</li>
                <li><strong>Checksum (13th digit):</strong> Our engine runs a complex Modulo 10 algorithm on this final digit to mathematically guarantee the barcode is legally registered and wasn't randomly invented by a scammer.</li>
            </ul>
        </div>

        <div class="card" style="margin-bottom: 30px; padding: 30px; border-left: 4px solid var(--amber);">
            <h3 style="margin-bottom: 15px; color: var(--ink);">2. Cracking the Secret Batch Codes</h3>
            <p style="margin-bottom: 15px; line-height: 1.6;">Scammers wipe off the printed Expiry Date, but they cannot erase the factory Batch Code. Different companies use different secret formats to encode the exact Manufacturing (Mfg) Date inside the Batch Code:</p>
            <ul style="line-height: 1.6; margin-left: 20px; margin-bottom: 15px;">
                <li style="margin-bottom:10px;"><strong>Standard Dates (YYMMDD):</strong> Companies like Parle often write batch codes like <code>PE240815</code>. Our system strips the letters and decodes `24-08-15` as <strong>August 15, 2024</strong>.</li>
                <li><strong>Julian Formats (YDDD):</strong> Premium brands like Nestlé and Cadbury use highly secretive Julian codes. If a Batch Code is <code>4125B</code>, the <code>4</code> means the year (2024), and the <code>125</code> means the 125th day of the year (May 4th). Our system natively calculates this complex math!</li>
            </ul>
        </div>

        <div class="card" style="padding: 30px; border-left: 4px solid var(--green);">
            <h3 style="margin-bottom: 15px; color: var(--ink);">3. Calculating the True Expiry Date</h3>
            <p style="margin-bottom: 15px; line-height: 1.6;">Once our engine cracks the exact Manufacturing Date, it determines the product category (either via Global Open Food Facts API or our offline Dictionary) and applies our biological <strong>Shelf Life Heuristic</strong>:</p>
            <div style="background:#fff; padding: 15px; border-radius:var(--radius); border:1px solid #eee; margin-bottom:15px; font-family:monospace; font-size:0.95rem;">
                + 1 Month &nbsp;(Perishables: Milk, Paneer, Bread)<br>
                + 6 Months (Snacks, Biscuits, Soda)<br>
                + 15 Months (Masala, Spices)<br>
            </div>
            <p style="line-height: 1.6;">We mathematically add this Shelf Life to the Mfg Date to get the <strong>True Expiry Date</strong>. If the printed wrapper date is much later than our True Expiry Date, our system immediately flags it as a <strong>Counterfeit or Restamped Product</strong>!</p>
        </div>
    </div>
</section>

<section class="cta-banner">
    <div class="container">
        <h2 class="font-serif" style="margin-bottom: 20px;">Spotted a suspicious expiry date?</h2>
        <p style="opacity:0.8; margin-bottom: 30px; font-size:1.1rem; max-width: 600px; margin-left: auto; margin-right: auto;">Your vigilance can save lives. Join our community of watchmen across India to eradicate food fraud at the source.</p>
        <a href="login.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 15px 30px; background: var(--white); color: var(--ink);">Access the Scanner</a>
    </div>
</section>

<footer class="text-center" style="background: var(--ink); color: var(--white); padding: 60px 0 20px 0;">
    <div class="container">
        <h3 class="font-serif" style="margin-bottom:20px;">PACKED FOOD FRAUD DETECTOR</h3>
        <div class="flex justify-center gap-6" style="margin-bottom: 40px; font-size: 0.9rem;">
            <a href="public.php" style="color: rgba(255,255,255,0.7); text-decoration: none;">Portal Home</a>
            <a href="login.php" style="color: rgba(255,255,255,0.7); text-decoration: none;">User Account</a>
            <a href="contact.php" style="color: rgba(255,255,255,0.7); text-decoration: none;">Contact Authority</a>
            <a href="#" style="color: rgba(255,255,255,0.7); text-decoration: none;">Privacy Policy</a>
        </div>
        <p class="text-muted" style="font-size: 0.8rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">© 2026 Food Safety and Standards Authority of India (FSSAI). All rights reserved.</p>
    </div>
</footer>

<script src="script.js"></script>
</body>
</html>
