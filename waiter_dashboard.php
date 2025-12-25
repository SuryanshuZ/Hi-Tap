<?php
require_once 'config.php';
requireRole('waiter');

$conn = getDBConnection();
$client_id = $_SESSION['client_id'];
$waiter_id = $_SESSION['user_id'];

// Get menu items
$menu = $conn->prepare("SELECT * FROM nfc_menu WHERE client_id = ? AND is_available = 1 ORDER BY category, item_name");
$menu->execute([$client_id]);
$menu_items = $menu->fetchAll();

// Get tables
$tables = $conn->prepare("SELECT * FROM nfc_tables WHERE client_id = ? ORDER BY table_number");
$tables->execute([$client_id]);
$table_list = $tables->fetchAll();

// Handle order submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $table_id = $_POST['table_id'] ?? null;
    $selected_items = $_POST['items'] ?? [];
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (!$table_id) {
        $error = "Please select a table";
    } elseif (empty(array_filter($selected_items))) {
        $error = "Please select at least one item";
    } else {
        $items_details = [];
        $total = 0;
        
        foreach ($selected_items as $item_id => $quantity) {
            if ($quantity > 0) {
                $item_query = $conn->prepare("SELECT * FROM nfc_menu WHERE id = ? AND client_id = ?");
                $item_query->execute([$item_id, $client_id]);
                $item = $item_query->fetch();
                
                if ($item) {
                    $price = $item['price'];
                    if ($item['discount_active']) {
                        $price = calculateDiscountedPrice($price, $item['discount_percentage']);
                    }
                    
                    $items_details[] = $item['item_name'] . ' x' . $quantity;
                    $total += $price * $quantity;
                }
            }
        }
        
        if (!empty($items_details)) {
            $items_str = implode(', ', $items_details);
            
            $insert = $conn->prepare("INSERT INTO nfc_orders (client_id, table_id, waiter_id, items, total_amount, notes, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $insert->execute([$client_id, $table_id, $waiter_id, $items_str, $total, $notes]);
            
            $success = "Order submitted successfully! Total: $" . number_format($total, 2);
            
            // Reset form
            $_POST = [];
        }
    }
}

// Group menu by category
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
    <title>Waiter Dashboard</title>
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .header h1 {
            font-size: 24px;
            color: #333;
        }
        
        .header p {
            font-size: 14px;
            color: #666;
            margin-top: 3px;
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
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .order-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .form-section {
            margin-bottom: 35px;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .section-icon {
            font-size: 24px;
        }
        
        .form-section h2 {
            font-size: 20px;
            color: #333;
        }
        
        .table-select {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 12px;
        }
        
        .table-option {
            position: relative;
        }
        
        .table-option input[type="radio"] {
            display: none;
        }
        
        .table-option label {
            display: block;
            padding: 25px 15px;
            background: #f8f9fa;
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: bold;
            font-size: 18px;
        }
        
        .table-option label:hover {
            background: #e9ecef;
            border-color: #10b981;
        }
        
        .table-option input[type="radio"]:checked + label {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-color: #10b981;
            transform: scale(1.05);
        }
        
        .menu-category {
            margin-bottom: 30px;
        }
        
        .category-title {
            font-size: 18px;
            font-weight: bold;
            color: #10b981;
            margin-bottom: 15px;
            padding: 10px 15px;
            background: #f0fdf4;
            border-radius: 8px;
            border-left: 4px solid #10b981;
        }
        
        .menu-items {
            display: grid;
            gap: 12px;
        }
        
        .menu-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px solid transparent;
            transition: all 0.2s;
        }
        
        .menu-item:hover {
            border-color: #10b981;
            background: #f0fdf4;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .item-description {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .item-price {
            color: #10b981;
            font-weight: bold;
            font-size: 16px;
        }
        
        .discount-badge {
            background: #ff4757;
            color: white;
            padding: 4px 10px;
            border-radius: 5px;
            font-size: 11px;
            margin-left: 8px;
            font-weight: bold;
        }
        
        .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 13px;
            margin-right: 8px;
        }
        
        .item-quantity {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .qty-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: #10b981;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 20px;
            font-weight: bold;
            transition: all 0.2s;
        }
        
        .qty-btn:hover {
            background: #059669;
            transform: scale(1.1);
        }
        
        .qty-btn:active {
            transform: scale(0.95);
        }
        
        .qty-input {
            width: 60px;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
            font-weight: bold;
            font-size: 16px;
        }
        
        .qty-input:focus {
            outline: none;
            border-color: #10b981;
        }
        
        .notes-field {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            font-size: 14px;
        }
        
        .notes-field:focus {
            outline: none;
            border-color: #10b981;
        }
        
        .submit-section {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 16px 40px;
            font-size: 16px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);
        }
        
        .btn-reset {
            background: #f0f0f0;
            color: #333;
            padding: 16px 40px;
            font-size: 16px;
        }
        
        .btn-reset:hover {
            background: #e0e0e0;
        }
        
        .order-summary {
            background: #f0fdf4;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 2px solid #10b981;
        }
        
        .summary-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .summary-total {
            font-size: 24px;
            font-weight: bold;
            color: #10b981;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .table-select {
                grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
            }
            
            .submit-section {
                flex-direction: column;
            }
            
            .btn-submit, .btn-reset {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="header-icon">🍽️</div>
            <div>
                <h1>Waiter Dashboard</h1>
                <p>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></p>
            </div>
        </div>
        <a href="logout.php" class="btn btn-secondary">🚪 Logout</a>
    </div>
    
    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                ❌ <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="order-form" id="orderForm">
            <!-- Table Selection -->
            <div class="form-section">
                <div class="section-header">
                    <span class="section-icon">🪑</span>
                    <h2>Select Table</h2>
                </div>
                <div class="table-select">
                    <?php foreach ($table_list as $table): ?>
                        <div class="table-option">
                            <input type="radio" name="table_id" id="table_<?php echo $table['id']; ?>" value="<?php echo $table['id']; ?>">
                            <label for="table_<?php echo $table['id']; ?>">
                                <?php echo htmlspecialchars($table['table_number']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Menu Items -->
            <div class="form-section">
                <div class="section-header">
                    <span class="section-icon">📋</span>
                    <h2>Select Items</h2>
                </div>
                
                <?php if (empty($menu_by_category)): ?>
                    <p style="text-align: center; color: #999; padding: 40px;">No menu items available</p>
                <?php else: ?>
                    <?php foreach ($menu_by_category as $category => $items): ?>
                        <div class="menu-category">
                            <div class="category-title">🍴 <?php echo htmlspecialchars($category); ?></div>
                            <div class="menu-items">
                                <?php foreach ($items as $item): ?>
                                    <div class="menu-item">
                                        <div class="item-info">
                                            <div class="item-name">
                                                <?php echo htmlspecialchars($item['item_name']); ?>
                                                <?php if ($item['discount_active']): ?>
                                                    <span class="discount-badge">-<?php echo $item['discount_percentage']; ?>% OFF</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($item['description']): ?>
                                                <div class="item-description"><?php echo htmlspecialchars($item['description']); ?></div>
                                            <?php endif; ?>
                                            <div class="item-price">
                                                <?php 
                                                    $price = $item['price'];
                                                    if ($item['discount_active']) {
                                                        $discounted = calculateDiscountedPrice($price, $item['discount_percentage']);
                                                        echo '<span class="original-price">$' . number_format($price, 2) . '</span>';
                                                        echo '$' . number_format($discounted, 2);
                                                    } else {
                                                        echo '$' . number_format($price, 2);
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="item-quantity">
                                            <button type="button" class="qty-btn" onclick="changeQty(<?php echo $item['id']; ?>, -1)">−</button>
                                            <input type="number" class="qty-input" name="items[<?php echo $item['id']; ?>]" id="qty_<?php echo $item['id']; ?>" value="0" min="0" max="99" readonly>
                                            <button type="button" class="qty-btn" onclick="changeQty(<?php echo $item['id']; ?>, 1)">+</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Notes -->
            <div class="form-section">
                <div class="section-header">
                    <span class="section-icon">📝</span>
                    <h2>Order Notes (Optional)</h2>
                </div>
                <textarea name="notes" class="notes-field" placeholder="Special instructions, allergies, modifications..."></textarea>
            </div>
            
            <!-- Submit -->
            <div class="submit-section">
                <button type="button" class="btn btn-reset" onclick="resetForm()">🔄 Reset</button>
                <button type="submit" name="submit_order" class="btn btn-submit">✅ Submit Order</button>
            </div>
        </form>
    </div>
    
    <script>
    function changeQty(itemId, change) {
        const input = document.getElementById('qty_' + itemId);
        const currentValue = parseInt(input.value) || 0;
        const newValue = Math.max(0, Math.min(99, currentValue + change));
        input.value = newValue;
        
        // Visual feedback
        if (newValue > 0) {
            input.style.borderColor = '#10b981';
            input.style.fontWeight = 'bold';
        } else {
            input.style.borderColor = '#e0e0e0';
        }
    }
    
    function resetForm() {
        if (confirm('Reset all selections?')) {
            document.getElementById('orderForm').reset();
            document.querySelectorAll('.qty-input').forEach(input => {
                input.style.borderColor = '#e0e0e0';
            });
        }
    }
    
    // Prevent form submission if no table selected
    document.getElementById('orderForm').addEventListener('submit', function(e) {
        const tableSelected = document.querySelector('input[name="table_id"]:checked');
        if (!tableSelected) {
            e.preventDefault();
            alert('Please select a table first!');
            return false;
        }
        
        // Check if at least one item is selected
        const quantities = document.querySelectorAll('.qty-input');
        let hasItems = false;
        quantities.forEach(input => {
            if (parseInt(input.value) > 0) {
                hasItems = true;
            }
        });
        
        if (!hasItems) {
            e.preventDefault();
            alert('Please select at least one item!');
            return false;
        }
    });
    </script>
</body>
</html>
