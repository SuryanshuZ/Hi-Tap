<?php
require_once 'config.php';
requireRole('client');

$conn = getDBConnection();
$client_id = $_SESSION['client_id'];

$success = '';
$error = '';

// Handle menu item actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $item_name = sanitize($_POST['item_name']);
        $description = sanitize($_POST['description']);
        $price = floatval($_POST['price']);
        $category = sanitize($_POST['category']);
        $discount_active = isset($_POST['discount_active']) ? 1 : 0;
        $discount_percentage = intval($_POST['discount_percentage'] ?? 0);
        
        if (empty($item_name) || $price <= 0) {
            $error = "Item name and valid price are required";
        } else {
            $stmt = $conn->prepare("INSERT INTO nfc_menu (client_id, item_name, description, price, category, discount_active, discount_percentage) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$client_id, $item_name, $description, $price, $category, $discount_active, $discount_percentage]);
            $success = "Item added successfully!";
        }
    }
    
    if (isset($_POST['toggle_discount'])) {
        $item_id = $_POST['item_id'];
        $stmt = $conn->prepare("UPDATE nfc_menu SET discount_active = NOT discount_active WHERE id = ? AND client_id = ?");
        $stmt->execute([$item_id, $client_id]);
        $success = "Discount status updated!";
    }
    
    if (isset($_POST['delete_item'])) {
        $stmt = $conn->prepare("DELETE FROM nfc_menu WHERE id = ? AND client_id = ?");
        $stmt->execute([$_POST['item_id'], $client_id]);
        $success = "Item deleted successfully!";
    }
    
    if (isset($_POST['update_availability'])) {
        $stmt = $conn->prepare("UPDATE nfc_menu SET is_available = NOT is_available WHERE id = ? AND client_id = ?");
        $stmt->execute([$_POST['item_id'], $client_id]);
        $success = "Availability updated!";
    }
    
    if (isset($_POST['update_item'])) {
        $item_id = $_POST['item_id'];
        $item_name = sanitize($_POST['item_name']);
        $description = sanitize($_POST['description']);
        $price = floatval($_POST['price']);
        $category = sanitize($_POST['category']);
        $discount_percentage = intval($_POST['discount_percentage']);
        
        $stmt = $conn->prepare("UPDATE nfc_menu SET item_name = ?, description = ?, price = ?, category = ?, discount_percentage = ? WHERE id = ? AND client_id = ?");
        $stmt->execute([$item_name, $description, $price, $category, $discount_percentage, $item_id, $client_id]);
        $success = "Item updated successfully!";
    }
}

// Get all menu items
$menu = $conn->prepare("SELECT * FROM nfc_menu WHERE client_id = ? ORDER BY category, item_name");
$menu->execute([$client_id]);
$menu_items = $menu->fetchAll();

$menu_by_category = [];
foreach ($menu_items as $item) {
    $category = $item['category'] ?: 'Other';
    $menu_by_category[$category][] = $item;
}

// Get statistics
$total_items = count($menu_items);
$active_discounts = count(array_filter($menu_items, function($item) { return $item['discount_active']; }));
$available_items = count(array_filter($menu_items, function($item) { return $item['is_available']; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Manager</title>
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
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
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
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #f59e0b;
        }
        
        .stat-label {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        
        .add-item-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .card-title {
            font-size: 22px;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        input, select, textarea {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #f59e0b;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .menu-category-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .category-header {
            font-size: 22px;
            font-weight: bold;
            color: #f59e0b;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #f59e0b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .menu-grid {
            display: grid;
            gap: 15px;
        }
        
        .menu-item-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .menu-item-card:hover {
            border-color: #f59e0b;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .item-desc {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .item-meta {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .item-price {
            font-size: 20px;
            font-weight: bold;
            color: #f59e0b;
        }
        
        .discount-info {
            background: #ff4757;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .unavailable-badge {
            background: #6c757d;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .item-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }
        
        .btn-info { background: #3b82f6; color: white; }
        .btn-info:hover { background: #2563eb; }
        
        .unavailable {
            opacity: 0.6;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .item-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .menu-item-card {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="header-icon">📋</div>
            <h1>Menu Manager</h1>
        </div>
        <a href="client_dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
    </div>
    
    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-value"><?php echo $total_items; ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?php echo $available_items; ?></div>
                <div class="stat-label">Available</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?php echo $active_discounts; ?></div>
                <div class="stat-label">Active Discounts</div>
            </div>
        </div>
        
        <!-- Add New Item Form -->
        <div class="add-item-card">
            <h2 class="card-title">➕ Add New Menu Item</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Item Name *</label>
                        <input type="text" name="item_name" required placeholder="e.g., Cappuccino">
                    </div>
                    
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" placeholder="e.g., Beverages, Main Course">
                    </div>
                    
                    <div class="form-group">
                        <label>Price ($) *</label>
                        <input type="number" step="0.01" name="price" required placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label>Discount (%)</label>
                        <input type="number" name="discount_percentage" min="0" max="100" value="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Describe the item..."></textarea>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="discount_active" id="discount_active">
                    <label for="discount_active" style="margin: 0;">Activate Discount Immediately</label>
                </div>
                
                <button type="submit" name="add_item" class="btn btn-primary" style="margin-top: 15px;">
                    ➕ Add Item
                </button>
            </form>
        </div>
        
        <!-- Menu Items by Category -->
        <?php if (empty($menu_by_category)): ?>
            <div class="add-item-card" style="text-align: center; padding: 60px;">
                <div style="font-size: 64px; margin-bottom: 20px;">🍽️</div>
                <h2>No Menu Items Yet</h2>
                <p style="color: #666; margin-top: 10px;">Add your first menu item using the form above</p>
            </div>
        <?php else: ?>
            <?php foreach ($menu_by_category as $category => $items): ?>
                <div class="menu-category-section">
                    <div class="category-header">
                        🍴 <?php echo htmlspecialchars($category); ?>
                        <span style="font-size: 14px; font-weight: normal; color: #666;">(<?php echo count($items); ?> items)</span>
                    </div>
                    <div class="menu-grid">
                        <?php foreach ($items as $item): ?>
                            <div class="menu-item-card <?php echo !$item['is_available'] ? 'unavailable' : ''; ?>">
                                <div class="item-details">
                                    <div class="item-name">
                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                    </div>
                                    <?php if ($item['description']): ?>
                                        <div class="item-desc"><?php echo htmlspecialchars($item['description']); ?></div>
                                    <?php endif; ?>
                                    <div class="item-meta">
                                        <div class="item-price">
                                            <?php if ($item['discount_active']): ?>
                                                <span style="text-decoration: line-through; color: #999; font-size: 16px;">$<?php echo number_format($item['price'], 2); ?></span>
                                                $<?php echo number_format(calculateDiscountedPrice($item['price'], $item['discount_percentage']), 2); ?>
                                            <?php else: ?>
                                                $<?php echo number_format($item['price'], 2); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($item['discount_active']): ?>
                                            <span class="discount-info">-<?php echo $item['discount_percentage']; ?>% OFF</span>
                                        <?php endif; ?>
                                        <?php if (!$item['is_available']): ?>
                                            <span class="unavailable-badge">Unavailable</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="item-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="toggle_discount" class="btn btn-warning btn-small">
                                            <?php echo $item['discount_active'] ? '🔴 Disable' : '✅ Enable'; ?> Discount
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="update_availability" class="btn btn-info btn-small">
                                            <?php echo $item['is_available'] ? '❌ Unavailable' : '✅ Available'; ?>
                                        </button>
                                    </form>
                                    
                                    <button onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)" class="btn btn-success btn-small">
                                        ✏️ Edit
                                    </button>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this item?');">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="delete_item" class="btn btn-danger btn-small">🗑️ Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Edit Menu Item</h2>
                <button class="close-btn" onclick="closeModal()">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="item_id" id="edit_item_id">
                <div class="form-group">
                    <label>Item Name *</label>
                    <input type="text" name="item_name" id="edit_item_name" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" id="edit_category">
                </div>
                <div class="form-group">
                    <label>Price ($) *</label>
                    <input type="number" step="0.01" name="price" id="edit_price" required>
                </div>
                <div class="form-group">
                    <label>Discount (%)</label>
                    <input type="number" name="discount_percentage" id="edit_discount" min="0" max="100">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" rows="3"></textarea>
                </div>
                <button type="submit" name="update_item" class="btn btn-primary" style="width: 100%; margin-top: 15px;">
                    💾 Save Changes
                </button>
            </form>
        </div>
    </div>
    
    <script>
    function editItem(item) {
        document.getElementById('edit_item_id').value = item.id;
        document.getElementById('edit_item_name').value = item.item_name;
        document.getElementById('edit_category').value = item.category || '';
        document.getElementById('edit_price').value = item.price;
        document.getElementById('edit_discount').value = item.discount_percentage;
        document.getElementById('edit_description').value = item.description || '';
        
        document.getElementById('editModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('editModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('editModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    </script>
</body>
</html>
