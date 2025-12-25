<?php
require_once 'config.php';
requireRole('client');

$conn = getDBConnection();
$client_id = $_SESSION['client_id'];

// Get client info
$client = $conn->prepare("SELECT * FROM nfc_clients WHERE id = ?");
$client->execute([$client_id]);
$client_info = $client->fetch();

// Get analytics summary
$analytics = $conn->prepare("
    SELECT 
        SUM(tap_count) as total_taps,
        SUM(daily_taps) as today_taps,
        SUM(weekly_taps) as week_taps,
        SUM(monthly_taps) as month_taps
    FROM nfc_analytics 
    WHERE client_id = ?
");
$analytics->execute([$client_id]);
$stats = $analytics->fetch();

// Get table analytics
$tables = $conn->prepare("
    SELECT t.table_number, t.status, t.id, a.tap_count, a.last_tap_time, a.daily_taps
    FROM nfc_tables t
    LEFT JOIN nfc_analytics a ON t.id = a.table_id
    WHERE t.client_id = ?
    ORDER BY t.table_number
");
$tables->execute([$client_id]);
$table_list = $tables->fetchAll();

// Get recent orders
$orders = $conn->prepare("
    SELECT o.*, t.table_number, u.name as waiter_name
    FROM nfc_orders o
    LEFT JOIN nfc_tables t ON o.table_id = t.id
    LEFT JOIN nfc_users u ON o.waiter_id = u.id
    WHERE o.client_id = ? AND o.status != 'paid'
    ORDER BY o.created_at DESC
    LIMIT 10
");
$orders->execute([$client_id]);
$order_list = $orders->fetchAll();

// Get menu items count
$menu_count = $conn->prepare("SELECT COUNT(*) as count FROM nfc_menu WHERE client_id = ?");
$menu_count->execute([$client_id]);
$menu_stats = $menu_count->fetch();

// Get discount items count
$discount_count = $conn->prepare("SELECT COUNT(*) as count FROM nfc_menu WHERE client_id = ? AND discount_active = 1");
$discount_count->execute([$client_id]);
$discount_stats = $discount_count->fetch();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_orders':
            $orders->execute([$client_id]);
            echo json_encode($orders->fetchAll());
            exit;
            
        case 'update_order_status':
            $stmt = $conn->prepare("UPDATE nfc_orders SET status = ? WHERE id = ? AND client_id = ?");
            $stmt->execute([$_POST['status'], $_POST['order_id'], $client_id]);
            echo json_encode(['success' => true]);
            exit;
            
        case 'get_analytics':
            $analytics->execute([$client_id]);
            $tables->execute([$client_id]);
            echo json_encode([
                'stats' => $analytics->fetch(),
                'tables' => $tables->fetchAll()
            ]);
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - <?php echo htmlspecialchars($client_info['business_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        .header {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .header-left h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .header-left p {
            color: #666;
            font-size: 14px;
        }
        
        .header-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 36px;
            margin-bottom: 10px;
            display: block;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-trend {
            font-size: 13px;
            color: #10b981;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card h2 {
            font-size: 20px;
            color: #333;
        }
        
        .refresh-btn {
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            font-size: 20px;
            padding: 5px;
            transition: all 0.3s;
        }
        
        .refresh-btn:hover {
            transform: rotate(180deg);
        }
        
        .table-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s;
        }
        
        .table-item:hover {
            background: #f8f9fa;
        }
        
        .table-item:last-child {
            border-bottom: none;
        }
        
        .table-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .table-number {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
        }
        
        .table-details {
            flex: 1;
        }
        
        .table-stats span {
            display: block;
            font-size: 13px;
            color: #666;
            margin-top: 3px;
        }
        
        .table-stats strong {
            color: #333;
            font-size: 14px;
        }
        
        .tap-count {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-top: 5px;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-occupied {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-reserved {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .order-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s;
        }
        
        .order-item:hover {
            background: #f8f9fa;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .order-table {
            font-weight: bold;
            color: #667eea;
            font-size: 16px;
        }
        
        .order-time {
            font-size: 12px;
            color: #999;
        }
        
        .order-items {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            line-height: 1.5;
        }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-total {
            font-weight: bold;
            color: #333;
            font-size: 18px;
        }
        
        .order-waiter {
            font-size: 12px;
            color: #999;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-preparing {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-served {
            background: #d4edda;
            color: #155724;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .quick-action-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }
        
        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .quick-action-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .quick-action-title {
            font-weight: 600;
            font-size: 14px;
        }
        
        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
            }
            
            .header-right {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 15px;
            }
            
            .btn {
                padding: 10px 16px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="header-icon">🏪</div>
            <div>
                <h1><?php echo htmlspecialchars($client_info['business_name']); ?></h1>
                <p>Client Dashboard • Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?></p>
            </div>
        </div>
        <div class="header-right">
            <a href="menu_manager.php" class="btn btn-primary">📋 Manage Menu</a>
            <a href="logout.php" class="btn btn-secondary">🚪 Logout</a>
        </div>
    </div>
    
    <div class="container">
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-label">Total NFC Taps</div>
                <div class="stat-value"><?php echo number_format($stats['total_taps'] ?? 0); ?></div>
                <div class="stat-trend">All time analytics</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-label">Today's Taps</div>
                <div class="stat-value"><?php echo number_format($stats['today_taps'] ?? 0); ?></div>
                <div class="stat-trend">Last 24 hours</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📈</div>
                <div class="stat-label">This Week</div>
                <div class="stat-value"><?php echo number_format($stats['week_taps'] ?? 0); ?></div>
                <div class="stat-trend">Last 7 days</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🍽️</div>
                <div class="stat-label">Menu Items</div>
                <div class="stat-value"><?php echo $menu_stats['count']; ?></div>
                <div class="stat-trend"><?php echo $discount_stats['count']; ?> with discounts</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="menu_manager.php" class="quick-action-card">
                <div class="quick-action-icon">📝</div>
                <div class="quick-action-title">Edit Menu</div>
            </a>
            
            <a href="#" onclick="window.print(); return false;" class="quick-action-card">
                <div class="quick-action-icon">🖨️</div>
                <div class="quick-action-title">Print QR Codes</div>
            </a>
            
            <a href="menu_manager.php" class="quick-action-card">
                <div class="quick-action-icon">🎁</div>
                <div class="quick-action-title">Manage Discounts</div>
            </a>
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Table Analytics -->
            <div class="card">
                <div class="card-header">
                    <h2>📍 Table Analytics</h2>
                    <button class="refresh-btn" onclick="refreshAnalytics()" title="Refresh">
                        🔄
                    </button>
                </div>
                <div class="table-list" id="tableList">
                    <?php if (empty($table_list)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🪑</div>
                            <p>No tables configured</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($table_list as $table): ?>
                            <div class="table-item">
                                <div class="table-info">
                                    <div class="table-number"><?php echo htmlspecialchars($table['table_number']); ?></div>
                                    <div class="table-details">
                                        <div class="table-stats">
                                            <strong>Table <?php echo htmlspecialchars($table['table_number']); ?></strong>
                                            <span class="status-badge status-<?php echo $table['status']; ?>">
                                                <?php echo ucfirst($table['status']); ?>
                                            </span>
                                        </div>
                                        <div class="table-stats">
                                            <span>Today: <?php echo $table['daily_taps'] ?? 0; ?> taps</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="tap-count"><?php echo $table['tap_count'] ?? 0; ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Live Orders -->
            <div class="card">
                <div class="card-header">
                    <h2>🔴 Live Orders</h2>
                    <button class="refresh-btn" onclick="refreshOrders()" title="Refresh">
                        🔄
                    </button>
                </div>
                <div class="order-list" id="orderList">
                    <?php if (empty($order_list)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">✅</div>
                            <p>No active orders</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($order_list as $order): ?>
                            <div class="order-item">
                                <div class="order-header">
                                    <span class="order-table">🍽️ Table <?php echo htmlspecialchars($order['table_number']); ?></span>
                                    <span class="order-time"><?php echo date('h:i A', strtotime($order['created_at'])); ?></span>
                                </div>
                                <div class="order-items"><?php echo htmlspecialchars($order['items']); ?></div>
                                <?php if ($order['notes']): ?>
                                    <div class="order-items" style="font-style: italic; color: #999;">
                                        Note: <?php echo htmlspecialchars($order['notes']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="order-footer">
                                    <div>
                                        <div class="order-total">$<?php echo number_format($order['total_amount'], 2); ?></div>
                                        <?php if ($order['waiter_name']): ?>
                                            <div class="order-waiter">by <?php echo htmlspecialchars($order['waiter_name']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Auto-refresh orders every 5 seconds
    setInterval(refreshOrders, 5000);
    
    function refreshOrders() {
        fetch('client_dashboard.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_orders'
        })
        .then(response => response.json())
        .then(data => {
            const orderList = document.getElementById('orderList');
            if (data.length === 0) {
                orderList.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <p>No active orders</p>
                    </div>
                `;
            } else {
                let html = '';
                data.forEach(order => {
                    const time = new Date(order.created_at).toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    html += `
                        <div class="order-item">
                            <div class="order-header">
                                <span class="order-table">🍽️ Table ${order.table_number}</span>
                                <span class="order-time">${time}</span>
                            </div>
                            <div class="order-items">${order.items}</div>
                            ${order.notes ? `<div class="order-items" style="font-style: italic; color: #999;">Note: ${order.notes}</div>` : ''}
                            <div class="order-footer">
                                <div>
                                    <div class="order-total">$${parseFloat(order.total_amount).toFixed(2)}</div>
                                    ${order.waiter_name ? `<div class="order-waiter">by ${order.waiter_name}</div>` : ''}
                                </div>
                                <div class="status-badge status-${order.status}">
                                    ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                                </div>
                            </div>
                        </div>
                    `;
                });
                orderList.innerHTML = html;
            }
        })
        .catch(error => console.error('Error refreshing orders:', error));
    }
    
    function refreshAnalytics() {
        fetch('client_dashboard.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_analytics'
        })
        .then(response => response.json())
        .then(data => {
            // Update table analytics
            const tableList = document.getElementById('tableList');
            if (data.tables.length === 0) {
                tableList.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">🪑</div>
                        <p>No tables configured</p>
                    </div>
                `;
            } else {
                let html = '';
                data.tables.forEach(table => {
                    html += `
                        <div class="table-item">
                            <div class="table-info">
                                <div class="table-number">${table.table_number}</div>
                                <div class="table-details">
                                    <div class="table-stats">
                                        <strong>Table ${table.table_number}</strong>
                                        <span class="status-badge status-${table.status}">
                                            ${table.status.charAt(0).toUpperCase() + table.status.slice(1)}
                                        </span>
                                    </div>
                                    <div class="table-stats">
                                        <span>Today: ${table.daily_taps || 0} taps</span>
                                    </div>
                                </div>
                            </div>
                            <div class="tap-count">${table.tap_count || 0}</div>
                        </div>
                    `;
                });
                tableList.innerHTML = html;
            }
        })
        .catch(error => console.error('Error refreshing analytics:', error));
    }
    
    // Add visual feedback for refresh buttons
    document.querySelectorAll('.refresh-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.style.transform = 'rotate(360deg)';
            setTimeout(() => {
                this.style.transform = 'rotate(0deg)';
            }, 500);
        });
    });
    </script>
</body>
</html>
