<?php
require_once 'config.php';

// Get table ID from URL
$table_id = $_GET['table'] ?? null;

if (!$table_id) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invalid NFC Tag</title>
        <style>
            body { font-family: Arial; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; }
            .error-box { background: white; padding: 40px; border-radius: 20px; text-align: center; max-width: 400px; }
            .error-icon { font-size: 64px; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <div class="error-icon">❌</div>
            <h2>Invalid NFC Tag</h2>
            <p>This link is not valid. Please scan a valid NFC tag.</p>
        </div>
    </body>
    </html>
    ');
}

$conn = getDBConnection();

// Get table info
$table = $conn->prepare("SELECT t.*, c.* FROM nfc_tables t JOIN nfc_clients c ON t.client_id = c.id WHERE t.id = ?");
$table->execute([$table_id]);
$table_info = $table->fetch();

if (!$table_info) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Table Not Found</title>
        <style>
            body { font-family: Arial; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; }
            .error-box { background: white; padding: 40px; border-radius: 20px; text-align: center; max-width: 400px; }
            .error-icon { font-size: 64px; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <div class="error-icon">🔍</div>
            <h2>Table Not Found</h2>
            <p>This table is not registered in the system.</p>
        </div>
    </body>
    </html>
    ');
}

$client_id = $table_info['client_id'];

// Log NFC tap
$tap_log = $conn->prepare("INSERT INTO nfc_tap_logs (table_id, client_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
$tap_log->execute([$table_id, $client_id, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown']);

// Update analytics
$update_analytics = $conn->prepare("
    UPDATE nfc_analytics 
    SET tap_count = tap_count + 1,
        daily_taps = daily_taps + 1,
        weekly_taps = weekly_taps + 1,
        monthly_taps = monthly_taps + 1,
        last_tap_time = NOW()
    WHERE table_id = ?
");
$update_analytics->execute([$table_id]);

// Get menu items
$menu = $conn->prepare("SELECT * FROM nfc_menu WHERE client_id = ? AND is_available = 1 ORDER BY category, item_name");
$menu->execute([$client_id]);
$menu_items = $menu->fetchAll();

$menu_by_category = [];
foreach ($menu_items as $item) {
    $category = $item['category'] ?: 'Other';
    $menu_by_category[$category][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($table_info['business_name']); ?> - Table <?php echo htmlspecialchars($table_info['table_number']); ?></title>
    <meta name="theme-color" content="#667eea">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .header-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .business-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 15px;
        }
        
        .business-name {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .table-number {
            font-size: 18px;
            color: #667eea;
            font-weight: 600;
            padding: 8px 20px;
            background: #f0f0ff;
            border-radius: 20px;
            display: inline-block;
        }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .action-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            text-decoration: none;
            color: #333;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            transition: all 0.3s;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        }
        
        .action-card:nth-child(1) { animation-delay: 0.1s; }
        .action-card:nth-child(2) { animation-delay: 0.2s; }
        .action-card:nth-child(3) { animation-delay: 0.3s; }
        
        .action-icon {
            font-size: 40px;
            margin-bottom: 10px;
            display: block;
        }
        
        .action-title {
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }
        
        .action-subtitle {
            font-size: 11px;
            color: #999;
            margin-top: 3px;
        }
        
        .menu-card {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .menu-header {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .category-section {
            margin-bottom: 25px;
        }
        
        .category-title {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
            padding: 10px 15px;
            background: linear-gradient(135deg, #f0f0ff 0%, #faf5ff 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .menu-item {
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .menu-item:last-child {
            border-bottom: none;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 5px;
        }
        
        .item-name {
            font-weight: 600;
            color: #333;
            font-size: 16px;
            flex: 1;
        }
        
        .item-price {
            font-weight: bold;
            color: #667eea;
            font-size: 16px;
            white-space: nowrap;
            margin-left: 10px;
        }
        
        .discount-badge {
            background: #ff4757;
            color: white;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 11px;
            margin-left: 8px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .item-desc {
            font-size: 14px;
            color: #666;
            line-height: 1.4;
        }
        
        .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 13px;
            margin-right: 8px;
        }
        
        .empty-menu {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-icon {
            font-size: 64px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .footer {
            text-align: center;
            color: white;
            margin-top: 30px;
            padding: 20px;
            font-size: 14px;
            opacity: 0.9;
        }
        
        @media (max-width: 480px) {
            .action-grid {
                grid-template-columns: 1fr;
            }
            
            .business-name {
                font-size: 24px;
            }
            
            .container {
                padding: 0;
            }
            
            body {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header-card">
            <div class="business-logo">🏪</div>
            <div class="business-name"><?php echo htmlspecialchars($table_info['business_name']); ?></div>
            <div class="table-number">📍 Table <?php echo htmlspecialchars($table_info['table_number']); ?></div>
        </div>
        
        <!-- Action Cards -->
        <div class="action-grid">
            <a href="<?php echo htmlspecialchars($table_info['google_review_link']); ?>" target="_blank" class="action-card">
                <div class="action-icon">⭐</div>
                <div class="action-title">Review Us</div>
                <div class="action-subtitle">on Google</div>
            </a>
            
            <a href="<?php echo htmlspecialchars($table_info['instagram_link']); ?>" target="_blank" class="action-card">
                <div class="action-icon">📸</div>
                <div class="action-title">Follow Us</div>
                <div class="action-subtitle">on Instagram</div>
            </a>
            
            <a href="#" onclick="showWifi(); return false;" class="action-card">
                <div class="action-icon">📶</div>
                <div class="action-title">WiFi</div>
                <div class="action-subtitle">Free Access</div>
            </a>
        </div>
        
        <!-- Menu -->
        <div class="menu-card">
            <div class="menu-header">🍽️ Our Menu</div>
            
            <?php if (empty($menu_by_category)): ?>
                <div class="empty-menu">
                    <div class="empty-icon">🍴</div>
                    <p>Menu coming soon...</p>
                </div>
            <?php else: ?>
                <?php foreach ($menu_by_category as $category => $items): ?>
                    <div class="category-section">
                        <div class="category-title">
                            <span>🍴</span>
                            <span><?php echo htmlspecialchars($category); ?></span>
                        </div>
                        
                        <?php foreach ($items as $item): ?>
                            <div class="menu-item">
                                <div class="item-header">
                                    <div class="item-name">
                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                        <?php if ($item['discount_active']): ?>
                                            <span class="discount-badge">-<?php echo $item['discount_percentage']; ?>% OFF</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-price">
                                        <?php if ($item['discount_active']): ?>
                                            <span class="original-price">$<?php echo number_format($item['price'], 2); ?></span>
                                            $<?php echo number_format(calculateDiscountedPrice($item['price'], $item['discount_percentage']), 2); ?>
                                        <?php else: ?>
                                            $<?php echo number_format($item['price'], 2); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($item['description']): ?>
                                    <div class="item-desc"><?php echo htmlspecialchars($item['description']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>Thank you for visiting <?php echo htmlspecialchars($table_info['business_name']); ?>! 😊</p>
            <p style="font-size: 12px; margin-top: 10px; opacity: 0.7;">Powered by NFC Technology</p>
        </div>
    </div>
    
    <script>
    function showWifi() {
        const ssid = <?php echo json_encode($table_info['wifi_ssid']); ?>;
        const password = <?php echo json_encode($table_info['wifi_password']); ?>;
        
        if (ssid && password) {
            alert('🔐 WiFi Credentials:\n\nNetwork: ' + ssid + '\nPassword: ' + password + '\n\nConnect and enjoy free internet!');
        } else {
            alert('WiFi information is not available. Please ask the staff for assistance.');
        }
    }
    
    // Add animation on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    });
    
    document.querySelectorAll('.category-section').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'all 0.5s ease';
        observer.observe(el);
    });
    </script>
</body>
</html>
