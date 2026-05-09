<?php
session_start();
include '../config/db.php';
include '../config/auth.php';
include_once '../config/helpers.php';

requireLogin();
// Permission check
if (!hasPermission('view_pos')) {
    header("HTTP/1.0 403 Forbidden");
    die('Access Denied: You do not have permission to access the POS.');
}

// Get products
$productsResult = $conn->query("SELECT id, name, price, stock, image_url, category, barcode, sku FROM products WHERE status = 'active' AND stock > 0 ORDER BY name");
$products = [];
while ($row = $productsResult->fetch_assoc()) {
    $products[] = $row;
}

// Get categories for filter
$categoriesResult = $conn->query("SELECT DISTINCT category FROM products WHERE status = 'active' AND category IS NOT NULL AND category != '' ORDER BY category");
$categories = [];
while ($row = $categoriesResult->fetch_assoc()) {
    $categories[] = $row['category'];
}

// Get customers
$customersResult = $conn->query("SELECT id, name FROM customers ORDER BY name");
$customers = [];
while ($row = $customersResult->fetch_assoc()) {
    $customers[] = $row;
}

include '../includes/header.php';
include '../includes/navbar.php';
?>
<script src="https://unpkg.com/html5-qrcode"></script>

<div class="main-container">
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-area pos-content-area">
        <div class="pos-layout">
            
            <!-- Left Side: Products Grid -->
            <div class="pos-products-section">
                <!-- POS Header & Search -->
                <div class="pos-header">
                    <h2><i class="fas fa-th-large"></i> Point of Sale</h2>
                    <div class="pos-search-wrapper">
                        <div class="pos-search-input-group">
                            <i class="fas fa-search"></i>
                            <input type="text" id="posSearch" placeholder="Search products or scan barcode..." oninput="filterProducts()">
                            <button type="button" class="pos-scan-btn" onclick="togglePOSScanner()" title="Scan with camera">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                        <div class="pos-search-hints">
                            <span class="pos-scanner-badge" id="posScannerBadge"><i class="fas fa-barcode"></i> Scanner Ready</span>
                            <span class="pos-shortcut-hint"><kbd>/</kbd> Search</span>
                            <span class="pos-shortcut-hint"><kbd>F9</kbd> Checkout</span>
                            <span class="pos-shortcut-hint"><kbd>F2</kbd> Clear</span>
                        </div>
                    </div>
                </div>
                <div id="pos-qr-reader" class="pos-scanner-container"></div>

                <!-- Category Filters -->
                <div class="pos-categories">
                    <button class="pos-cat-btn active" onclick="filterByCategory('all')">All</button>
                    <?php foreach ($categories as $cat): ?>
                    <button class="pos-cat-btn" onclick="filterByCategory('<?php echo htmlspecialchars(addslashes($cat)); ?>')"><?php echo htmlspecialchars($cat); ?></button>
                    <?php endforeach; ?>
                </div>

                <!-- Products Grid -->
                <div class="pos-products-grid" id="posProductsGrid">
                    <?php foreach ($products as $p): ?>
                    <div class="pos-product-card" 
                         data-id="<?php echo $p['id']; ?>" 
                         data-name="<?php echo htmlspecialchars(addslashes($p['name'])); ?>" 
                         data-price="<?php echo $p['price']; ?>" 
                         data-stock="<?php echo $p['stock']; ?>"
                         data-category="<?php echo htmlspecialchars(addslashes($p['category'])); ?>"
                         data-barcode="<?php echo htmlspecialchars($p['barcode'] ?? ''); ?>"
                         data-sku="<?php echo htmlspecialchars($p['sku'] ?? ''); ?>"
                         onclick="addToCart(this)">
                        <div class="pos-product-image">
                            <?php if (!empty($p['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($p['image_url']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
                            <?php else: ?>
                                <div class="pos-no-image"><i class="fas fa-box"></i></div>
                            <?php endif; ?>
                            <div class="pos-product-stock"><?php echo $p['stock']; ?> in stock</div>
                        </div>
                        <div class="pos-product-info">
                            <div class="pos-product-name"><?php echo htmlspecialchars($p['name']); ?></div>
                            <div class="pos-product-price">$<?php echo number_format($p['price'], 2); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Side: Cart Sidebar -->
            <div class="pos-cart-section">
                <div class="pos-cart-header">
                    <div>
                        <h3>Current Order</h3>
                        <div class="pos-cart-subtitle" id="posCartStatus">Ready for the next item</div>
                    </div>
                    <button class="btn btn-sm btn-danger" onclick="clearCart()"><i class="fas fa-trash"></i> Clear</button>
                </div>

                <!-- Customer Selection -->
                <div class="pos-customer-select">
                    <select id="posCustomer" class="pos-input">
                        <option value="">Walk-in Customer</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Cart Items -->
                <div class="pos-cart-items" id="posCartItems">
                    <!-- Cart items will be rendered here by JS -->
                    <div class="pos-empty-cart">
                        <i class="fas fa-shopping-basket"></i>
                        <p>Cart is empty</p>
                    </div>
                </div>

                <!-- Cart Totals & Checkout -->
                <div class="pos-cart-footer">
                    <div class="pos-cart-meta">
                        <div class="pos-cart-meta-card">
                            <span class="pos-cart-meta-label">Items</span>
                            <strong id="posItemCount">0</strong>
                        </div>
                        <div class="pos-cart-meta-card">
                            <span class="pos-cart-meta-label">Units</span>
                            <strong id="posUnitCount">0</strong>
                        </div>
                    </div>
                    <div class="pos-summary-row">
                        <span>Subtotal</span>
                        <span id="posSubtotal">$0.00</span>
                    </div>
                    <div class="pos-summary-row">
                        <span>Discount</span>
                        <span>
                            $<input type="number" id="posDiscount" class="pos-input" style="width: 70px; padding: 4px 8px; text-align: right; display: inline-block; background: transparent; border: 1px solid #ddd;" value="0.00" min="0" step="0.01" onchange="renderCart()">
                        </span>
                    </div>
                    <div class="pos-summary-row pos-total-row">
                        <span>Total</span>
                        <span id="posTotal">$0.00</span>
                    </div>

                    <div class="pos-payment-method">
                        <label>Payment Method</label>
                        <select id="posPaymentMethod" class="pos-input">
                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>

                    <button class="btn btn-primary pos-checkout-btn" onclick="checkout()" id="checkoutBtn" disabled>
                        <i class="fas fa-check-circle"></i> Complete Sale
                    </button>
                    <div class="pos-footer-note">Tip: you can scan from anywhere on this page.</div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- POS Success Modal -->
<div id="posSuccessModal" class="pos-modal">
    <div class="pos-modal-content">
        <div class="pos-success-icon"><i class="fas fa-check-circle"></i></div>
        <h2>Sale Completed!</h2>
        <p>The sale has been recorded successfully.</p>
        <div class="pos-modal-actions">
            <button class="btn btn-primary" onclick="printPosReceipt()"><i class="fas fa-print"></i> Print Receipt</button>
            <button class="btn btn-default" onclick="startNewSale()"><i class="fas fa-plus"></i> New Sale</button>
        </div>
    </div>
</div>

<style>
/* POS Specific Layout overrides */
.pos-content-area {
    padding: 10px 15px !important;
    height: calc(100vh - 20px);
    overflow: hidden;
}

.pos-layout {
    display: flex;
    gap: 15px;
    height: 100%;
}

/* Left Side */
.pos-products-section {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}

.pos-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pos-header h2 {
    margin: 0;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.pos-search-bar {
    position: relative;
    width: 300px;
}

.pos-search-bar i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #888;
}

.pos-search-bar input {
    width: 100%;
    padding: 10px 10px 10px 35px;
    border: 2px solid #E8D9C8;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.pos-search-bar input:focus {
    outline: none;
    border-color: #8B6F47;
}

.pos-search-wrapper {
    width: 450px;
}

.pos-search-hints {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
    align-items: center;
}

.pos-search-input-group {
    display: flex;
    align-items: center;
    background: #f3f4f6;
    border: 2px solid #E8D9C8;
    border-radius: 10px;
    padding: 2px 5px;
    transition: all 0.3s ease;
    position: relative;
}

.pos-search-input-group:focus-within {
    border-color: #8B6F47;
    background: white;
    box-shadow: 0 0 0 3px rgba(139, 111, 71, 0.1);
}

.pos-search-input-group i.fa-search {
    padding: 0 10px;
    color: #6b7280;
}

.pos-search-input-group input {
    flex: 1;
    border: none !important;
    background: transparent !important;
    padding: 10px 5px;
    font-size: 1rem;
    color: #1f2937;
}

.pos-search-input-group input:focus {
    box-shadow: none !important;
}

.pos-scan-btn {
    background: #8B6F47;
    color: white;
    border: none;
    border-radius: 8px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.pos-scan-btn:hover {
    background: #6F4E37;
    transform: scale(1.05);
}

.pos-scanner-container {
    display: none;
    margin: 15px auto;
    width: 90%;
    max-width: 500px;
    border-radius: 12px;
    overflow: hidden;
    border: 3px solid #8B6F47;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.pos-scanner-badge,
.pos-shortcut-hint {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
}

.pos-scanner-badge {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.pos-scanner-badge.scanning {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #bfdbfe;
}

.pos-scanner-badge.success {
    background: #fef3c7;
    color: #92400e;
    border-color: #fcd34d;
}

.pos-shortcut-hint {
    background: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.pos-shortcut-hint kbd {
    background: white;
    border: 1px solid #cbd5e1;
    border-bottom-width: 2px;
    border-radius: 6px;
    padding: 1px 6px;
    font-size: 0.72rem;
    font-family: inherit;
}

html.theme-dark .pos-search-input-group {
    background: #2d2d2d;
    border-color: #444;
}

html.theme-dark .pos-search-input-group input {
    color: #e5e7eb;
}

html.theme-dark .pos-scanner-badge {
    background: rgba(16, 185, 129, 0.15);
    color: #6ee7b7;
    border-color: rgba(110, 231, 183, 0.3);
}

html.theme-dark .pos-scanner-badge.scanning {
    background: rgba(59, 130, 246, 0.16);
    color: #93c5fd;
    border-color: rgba(147, 197, 253, 0.3);
}

html.theme-dark .pos-scanner-badge.success {
    background: rgba(245, 158, 11, 0.16);
    color: #fcd34d;
    border-color: rgba(252, 211, 77, 0.3);
}

html.theme-dark .pos-shortcut-hint {
    background: #2d2d2d;
    border-color: #444;
    color: #e5e7eb;
}

html.theme-dark .pos-shortcut-hint kbd {
    background: #1f2937;
    border-color: #475569;
    color: #e5e7eb;
}

.pos-categories {
    padding: 10px 20px;
    display: flex;
    gap: 10px;
    overflow-x: auto;
    border-bottom: 1px solid #eee;
    white-space: nowrap;
}

.pos-cat-btn {
    padding: 6px 15px;
    border: 1px solid #E8D9C8;
    background: #FAF7F3;
    border-radius: 20px;
    cursor: pointer;
    font-weight: 600;
    color: #6F4E37;
    transition: all 0.2s ease;
}

.pos-cat-btn:hover {
    background: #E8D9C8;
}

.pos-cat-btn.active {
    background: #8B6F47;
    color: white;
    border-color: #8B6F47;
}

.pos-products-grid {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 15px;
    align-content: start;
}

.pos-product-card {
    border: 1px solid #eee;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #fff;
    display: flex;
    flex-direction: column;
}

.pos-product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    border-color: #8B6F47;
}

.pos-product-image {
    height: 100px;
    background: #f8f9fa;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pos-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pos-no-image {
    font-size: 2rem;
    color: #ccc;
}

.pos-product-stock {
    position: absolute;
    top: 5px;
    right: 5px;
    background: rgba(0,0,0,0.6);
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: bold;
}

.pos-product-info {
    padding: 10px;
    text-align: center;
}

.pos-product-name {
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 5px;
    color: #333;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    height: 34px;
}

.pos-product-price {
    color: #8B6F47;
    font-weight: bold;
    font-size: 1.1rem;
}

/* Right Side */
.pos-cart-section {
    width: 350px;
    display: flex;
    flex-direction: column;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    border-top: 4px solid #8B6F47;
}

.pos-cart-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pos-cart-header h3 {
    margin: 0;
    color: #333;
}

.pos-cart-subtitle {
    margin-top: 4px;
    font-size: 0.82rem;
    color: #6b7280;
}

.pos-customer-select {
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.pos-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.95rem;
    background: #f9f9f9;
}

.pos-cart-items {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.pos-empty-cart {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #ccc;
}

.pos-empty-cart i {
    font-size: 3rem;
    margin-bottom: 10px;
}

.pos-cart-item {
    background: #f9f9f9;
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.pos-cart-item.highlighted {
    border-color: #8B6F47;
    background: #fffaf3;
    box-shadow: 0 0 0 3px rgba(139, 111, 71, 0.12);
}

.pos-item-header {
    display: flex;
    justify-content: space-between;
    font-weight: 600;
    color: #333;
}

.pos-item-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pos-qty-controls {
    display: flex;
    align-items: center;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.pos-qty-btn {
    background: #eee;
    border: none;
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-weight: bold;
    font-size: 1rem;
}

.pos-qty-btn:hover {
    background: #ddd;
}

.pos-qty-input {
    width: 48px;
    height: 34px;
    border: none;
    text-align: center;
    font-weight: 600;
}

.pos-qty-input:focus { outline: none; }

.pos-item-total {
    font-weight: bold;
    color: #8B6F47;
}

.pos-item-remove {
    color: #dc2626;
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
}

.pos-item-remove:hover { color: #991b1b; }

.pos-cart-footer {
    padding: 20px;
    background: #fafafa;
    border-top: 1px solid #ddd;
    border-radius: 0 0 12px 12px;
    position: sticky;
    bottom: 0;
}

.pos-cart-meta {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}

.pos-cart-meta-card {
    background: white;
    border: 1px solid #ece7df;
    border-radius: 10px;
    padding: 10px 12px;
}

.pos-cart-meta-label {
    display: block;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #6b7280;
    margin-bottom: 4px;
}

.pos-cart-meta-card strong {
    font-size: 1.1rem;
    color: #111827;
}

.pos-summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    color: #666;
    font-size: 0.95rem;
}

.pos-total-row {
    font-size: 1.4rem;
    font-weight: bold;
    color: #333;
    border-top: 2px dashed #ddd;
    padding-top: 10px;
    margin-bottom: 15px;
}

.pos-payment-method {
    margin-bottom: 15px;
}

.pos-checkout-btn {
    width: 100%;
    padding: 15px;
    font-size: 1.1rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    box-shadow: 0 10px 24px rgba(139, 111, 71, 0.22);
}

.pos-checkout-btn:disabled {
    background: #ccc !important;
    box-shadow: none !important;
    cursor: not-allowed;
    color: #888 !important;
}

.pos-footer-note {
    margin-top: 10px;
    text-align: center;
    font-size: 0.8rem;
    color: #6b7280;
}

/* Dark Mode Overrides */
html.theme-dark .pos-products-section, 
html.theme-dark .pos-cart-section {
    background: #1e1e1e;
    border-color: #333;
}

html.theme-dark .pos-header,
html.theme-dark .pos-cart-header,
html.theme-dark .pos-categories,
html.theme-dark .pos-customer-select {
    border-color: #333;
}

html.theme-dark .pos-header h2,
html.theme-dark .pos-cart-header h3 {
    color: #e0e0e0;
}

html.theme-dark .pos-search-bar input,
html.theme-dark .pos-input {
    background: #333;
    border-color: #444;
    color: #e0e0e0;
}

html.theme-dark .pos-product-card {
    background: #2d2d2d;
    border-color: #444;
}

html.theme-dark .pos-product-name {
    color: #e0e0e0;
}

html.theme-dark .pos-product-price {
    color: #bb86fc;
}

html.theme-dark .pos-product-image {
    background: #1a1a1a;
}

html.theme-dark .pos-cart-item {
    background: #2d2d2d;
    border-color: #444;
}

html.theme-dark .pos-item-header {
    color: #e0e0e0;
}

html.theme-dark .pos-qty-controls {
    background: #333;
    border-color: #555;
}

html.theme-dark .pos-qty-btn {
    background: #444;
    color: #e0e0e0;
}

html.theme-dark .pos-qty-input {
    background: #333;
    color: #e0e0e0;
}

html.theme-dark .pos-item-total {
    color: #bb86fc;
}

html.theme-dark .pos-cart-footer {
    background: #1e1e1e;
    border-color: #333;
}

html.theme-dark .pos-cart-subtitle,
html.theme-dark .pos-footer-note,
html.theme-dark .pos-cart-meta-label {
    color: #9ca3af;
}

html.theme-dark .pos-cart-meta-card {
    background: #2d2d2d;
    border-color: #444;
}

html.theme-dark .pos-cart-meta-card strong {
    color: #f3f4f6;
}

html.theme-dark .pos-total-row {
    color: #fff;
    border-color: #444;
}

html.theme-dark .pos-cat-btn {
    background: #333;
    border-color: #444;
    color: #e0e0e0;
}

html.theme-dark .pos-cat-btn.active {
    background: #bb86fc;
    color: #121212;
    border-color: #bb86fc;
}

@media (max-width: 992px) {
    .pos-layout {
        flex-direction: column;
    }
    .pos-cart-section {
        width: 100%;
        height: 400px;
    }
    .pos-content-area {
        height: auto;
        overflow: visible;
    }
    .pos-products-grid {
        max-height: 500px;
    }
}

/* POS Modal */
.pos-modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.6);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
}

.pos-modal-content {
    background-color: #fff;
    padding: 40px;
    border-radius: 16px;
    text-align: center;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

html.theme-dark .pos-modal-content {
    background-color: #1e1e1e;
    color: #e0e0e0;
}

.pos-success-icon i {
    font-size: 4rem;
    color: #10b981;
    margin-bottom: 20px;
}

.pos-modal-content h2 {
    margin: 0 0 10px 0;
    color: #333;
}
html.theme-dark .pos-modal-content h2 { color: #fff; }

.pos-modal-content p {
    color: #666;
    margin-bottom: 25px;
}
html.theme-dark .pos-modal-content p { color: #aaa; }

.pos-modal-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.pos-modal-actions .btn {
    width: 100%;
    padding: 12px;
    font-size: 1.05rem;
}

@keyframes popIn {
    0% { transform: scale(0.8); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
</style>

<script>
let cart = [];
let lastCompletedSale = null;
let lastAddedCartItemId = null;
let keyboardScanBuffer = '';
let lastKeyboardScanAt = 0;
let clearKeyboardScanTimer = null;
const KEYBOARD_SCAN_MAX_INTERVAL = 70;
const KEYBOARD_SCAN_MIN_LENGTH = 3;
const KEYBOARD_SCAN_CLEAR_DELAY = 120;
let scannerBadgeResetTimer = null;

function updatePOSStatus(message) {
    const statusEl = document.getElementById('posCartStatus');
    if (statusEl) {
        statusEl.textContent = message;
    }
}

function setScannerBadgeState(mode, text) {
    const badge = document.getElementById('posScannerBadge');
    if (!badge) return;

    badge.classList.remove('scanning', 'success');
    if (mode) {
        badge.classList.add(mode);
    }
    badge.innerHTML = '<i class="fas fa-barcode"></i> ' + text;

    if (scannerBadgeResetTimer) {
        clearTimeout(scannerBadgeResetTimer);
        scannerBadgeResetTimer = null;
    }

    if (mode) {
        scannerBadgeResetTimer = setTimeout(() => {
            setScannerBadgeState('', 'Scanner Ready');
        }, 1200);
    }
}

function playPOSFeedback(type) {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) return;

    try {
        if (!window.__vendixPosAudioCtx) {
            window.__vendixPosAudioCtx = new AudioContextClass();
        }

        const ctx = window.__vendixPosAudioCtx;
        if (ctx.state === 'suspended') {
            ctx.resume().catch(() => {});
        }

        const oscillator = ctx.createOscillator();
        const gain = ctx.createGain();
        const now = ctx.currentTime;

        oscillator.type = type === 'error' ? 'sawtooth' : 'sine';
        oscillator.frequency.setValueAtTime(type === 'error' ? 220 : 880, now);
        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(type === 'error' ? 0.035 : 0.05, now + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + (type === 'error' ? 0.16 : 0.12));

        oscillator.connect(gain);
        gain.connect(ctx.destination);
        oscillator.start(now);
        oscillator.stop(now + (type === 'error' ? 0.18 : 0.14));
    } catch (error) {
        console.warn('POS audio feedback unavailable', error);
    }
}

function clearKeyboardScannerBuffer() {
    keyboardScanBuffer = '';
    lastKeyboardScanAt = 0;
    if (clearKeyboardScanTimer) {
        clearTimeout(clearKeyboardScanTimer);
        clearKeyboardScanTimer = null;
    }
}

function isEditableElement(element) {
    if (!element) return false;
    if (element === document.getElementById('posSearch')) return false;
    const tagName = (element.tagName || '').toLowerCase();
    return element.isContentEditable || tagName === 'input' || tagName === 'textarea' || tagName === 'select';
}

function findProductCardByCode(code) {
    const normalizedCode = String(code || '').trim().toLowerCase();
    if (!normalizedCode) return null;

    const cards = document.querySelectorAll('.pos-product-card');
    for (const card of cards) {
        const barcode = (card.dataset.barcode || '').toLowerCase();
        const sku = (card.dataset.sku || '').toLowerCase();
        const id = String(card.dataset.id || '').toLowerCase();

        if (barcode === normalizedCode || sku === normalizedCode || id === normalizedCode) {
            return card;
        }
    }

    return null;
}

function handlePOSScanCode(code, options = {}) {
    const normalizedCode = String(code || '').trim().toLowerCase();
    if (!normalizedCode) return false;

    const { updateSearch = true, allowSearchFallback = false, notifyOnMiss = false } = options;
    const matchedCard = findProductCardByCode(normalizedCode);

    if (matchedCard) {
        addToCart(matchedCard);
        if (updateSearch) {
            document.getElementById('posSearch').value = '';
            filterProducts();
        }
        setScannerBadgeState('success', 'Added ' + matchedCard.dataset.name);
        return true;
    }

    if (allowSearchFallback && updateSearch) {
        document.getElementById('posSearch').value = normalizedCode;
        filterProducts();
    }

    if (notifyOnMiss) {
        vendixNotify('Product not found: ' + code, 'warning');
        updatePOSStatus('Product not found');
        playPOSFeedback('error');
    }

    return false;
}

function filterProducts() {
    const term = document.getElementById('posSearch').value.toLowerCase();
    const activeCat = document.querySelector('.pos-cat-btn.active').textContent.toLowerCase();
    const cards = document.querySelectorAll('.pos-product-card');
    
    cards.forEach(card => {
        const name = card.dataset.name.toLowerCase();
        const cat = card.dataset.category.toLowerCase();
        const matchSearch = name.includes(term);
        const matchCat = activeCat === 'all' || cat === activeCat;
        
        if (matchSearch && matchCat) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// Handle Barcode Scan (Enter key)
document.getElementById('posSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const term = this.value.trim();
        if (!term) return;

        const found = handlePOSScanCode(term, {
            updateSearch: false,
            allowSearchFallback: false,
            notifyOnMiss: false
        });

        if (found) {
            this.value = '';
            return;
        }

        this.value = term;
        filterProducts();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.defaultPrevented || e.ctrlKey || e.altKey || e.metaKey) return;
    if (e.key === 'Shift' || e.key === 'CapsLock' || e.key === 'Tab' || e.key.startsWith('Arrow')) return;
    if (document.activeElement === document.getElementById('posSearch')) return;

    if (e.key === '/') {
        e.preventDefault();
        document.getElementById('posSearch').focus();
        document.getElementById('posSearch').select();
        updatePOSStatus('Search focused');
        return;
    }

    if (e.key === 'F2') {
        e.preventDefault();
        if (cart.length > 0) {
            clearCart();
            vendixNotify('Cart cleared', 'info');
        }
        return;
    }

    if (e.key === 'F9') {
        e.preventDefault();
        if (!document.getElementById('checkoutBtn').disabled) {
            checkout();
        }
        return;
    }

    const now = Date.now();
    const isRapidContinuation = lastKeyboardScanAt && (now - lastKeyboardScanAt) <= KEYBOARD_SCAN_MAX_INTERVAL;

    if (e.key === 'Enter') {
        const scanValue = keyboardScanBuffer.trim();
        const shouldHandleScan = scanValue.length >= KEYBOARD_SCAN_MIN_LENGTH && isRapidContinuation;

        if (shouldHandleScan) {
            e.preventDefault();
            handlePOSScanCode(scanValue, {
                updateSearch: false,
                allowSearchFallback: false,
                notifyOnMiss: true
            });
        }

        clearKeyboardScannerBuffer();
        return;
    }

    if (e.key === 'Backspace' || e.key === 'Escape') {
        clearKeyboardScannerBuffer();
        return;
    }

    if (e.key.length !== 1) return;
    if (isEditableElement(document.activeElement) && !isRapidContinuation) return;

    if (!isRapidContinuation) {
        keyboardScanBuffer = '';
    }

    keyboardScanBuffer += e.key;
    lastKeyboardScanAt = now;
    setScannerBadgeState('scanning', 'Receiving scan...');

    if (clearKeyboardScanTimer) {
        clearTimeout(clearKeyboardScanTimer);
    }

    clearKeyboardScanTimer = setTimeout(() => {
        clearKeyboardScannerBuffer();
    }, KEYBOARD_SCAN_CLEAR_DELAY);
});

let posQrScanner = null;
let lastScanTime = 0;
const SCAN_COOLDOWN = 1500; // 1.5 seconds between scans of same item

async function togglePOSScanner() {
    const scannerDiv = document.getElementById('pos-qr-reader');
    
    if (scannerDiv.style.display === 'block') {
        stopPOSScanner();
        return;
    }
    
    scannerDiv.style.display = 'block';
    setScannerBadgeState('scanning', 'Camera scanner active');
    
    if (!posQrScanner) {
        posQrScanner = new Html5Qrcode("pos-qr-reader");
    }
    
    const config = { 
        fps: 15, 
        qrbox: { width: 300, height: 200 },
        aspectRatio: 1.0,
        // Support all common formats
        formatsToSupport: [ 
            Html5QrcodeSupportedFormats.QR_CODE,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.UPC_E,
            Html5QrcodeSupportedFormats.CODE_39
        ]
    };
    
    try {
        await posQrScanner.start(
            { facingMode: "environment" }, 
            config,
            (decodedText, decodedResult) => {
                const now = Date.now();
                if (now - lastScanTime < SCAN_COOLDOWN) return;
                
                // Success - find product and add to cart
                const term = decodedText.trim().toLowerCase();
                const found = handlePOSScanCode(term, {
                    updateSearch: false,
                    allowSearchFallback: false,
                    notifyOnMiss: false
                });
                
                if (found) {
                    lastScanTime = now;

                    // Small visual feedback on the scanner
                    const scannerEl = document.getElementById('pos-qr-reader');
                    scannerEl.style.borderColor = '#10b981';
                    setTimeout(() => { scannerEl.style.borderColor = '#8B6F47'; }, 500);
                } else {
                    vendixNotify('Product not found: ' + decodedText, 'warning');
                    lastScanTime = now; // Still prevent spamming notifications
                }
            }
        );
    } catch (err) {
        console.error(err);
        alert('Error starting camera: Make sure you have given camera permissions.');
        scannerDiv.style.display = 'none';
        setScannerBadgeState('', 'Scanner Ready');
    }
}

function stopPOSScanner() {
    const scannerDiv = document.getElementById('pos-qr-reader');
    if (posQrScanner && posQrScanner.isScanning) {
        posQrScanner.stop().then(() => {
            scannerDiv.style.display = 'none';
            setScannerBadgeState('', 'Scanner Ready');
        });
    } else {
        scannerDiv.style.display = 'none';
        setScannerBadgeState('', 'Scanner Ready');
    }
}

function filterByCategory(cat) {
    // Update active button
    document.querySelectorAll('.pos-cat-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.toLowerCase() === cat.toLowerCase()) {
            btn.classList.add('active');
        }
    });
    // Re-run filter
    filterProducts();
}

function addToCart(element) {
    const id = element.dataset.id;
    const name = element.dataset.name;
    const price = parseFloat(element.dataset.price);
    const maxStock = parseInt(element.dataset.stock);
    
    // Check if exists
    const existing = cart.find(i => i.id === id);
    if (existing) {
        if (existing.qty < maxStock) {
            existing.qty++;
        } else {
            alert('Cannot add more. Not enough stock.');
            updatePOSStatus('Stock limit reached for ' + name);
            playPOSFeedback('error');
            setScannerBadgeState('', 'Scanner Ready');
            return;
        }
    } else {
        cart.push({ id, name, price, qty: 1, maxStock });
    }

    lastAddedCartItemId = id;
    const activeItem = cart.find(i => i.id === id);
    updatePOSStatus(name + ' added to order');
    playPOSFeedback('success');
    vendixNotify(activeItem.qty > 1 ? `Added ${name} x${activeItem.qty}` : `Added ${name}`, 'success');
    renderCart();
}

function updateQty(id, change) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    
    const newQty = item.qty + change;
    if (newQty > 0 && newQty <= item.maxStock) {
        item.qty = newQty;
    } else if (newQty === 0) {
        removeFromCart(id);
        return;
    }
    renderCart();
}

function setQty(id, input) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    
    let newQty = parseInt(input.value);
    if (isNaN(newQty) || newQty <= 0) newQty = 1;
    if (newQty > item.maxStock) newQty = item.maxStock;
    
    item.qty = newQty;
    renderCart();
}

function removeFromCart(id) {
    cart = cart.filter(i => i.id !== id);
    updatePOSStatus('Item removed from order');
    renderCart();
}

function clearCart() {
    cart = [];
    lastAddedCartItemId = null;
    updatePOSStatus('Ready for the next item');
    renderCart();
}

function renderCart() {
    const container = document.getElementById('posCartItems');
    const checkoutBtn = document.getElementById('checkoutBtn');
    
    if (cart.length === 0) {
        container.innerHTML = `
            <div class="pos-empty-cart">
                <i class="fas fa-shopping-basket"></i>
                <p>Cart is empty</p>
            </div>
        `;
        document.getElementById('posSubtotal').textContent = '$0.00';
        document.getElementById('posTotal').textContent = '$0.00';
        document.getElementById('posItemCount').textContent = '0';
        document.getElementById('posUnitCount').textContent = '0';
        checkoutBtn.disabled = true;
        return;
    }
    
    let html = '';
    let total = 0;
    let unitCount = 0;
    
    cart.forEach(item => {
        const itemTotal = item.price * item.qty;
        total += itemTotal;
        unitCount += item.qty;
        const isHighlighted = String(item.id) === String(lastAddedCartItemId);
        
        html += `
            <div class="pos-cart-item${isHighlighted ? ' highlighted' : ''}">
                <div class="pos-item-header">
                    <span>${item.name}</span>
                    <button class="pos-item-remove" onclick="removeFromCart('${item.id}')"><i class="fas fa-times"></i></button>
                </div>
                <div class="pos-item-controls">
                    <div class="pos-qty-controls">
                        <button class="pos-qty-btn" onclick="updateQty('${item.id}', -1)">-</button>
                        <input type="text" class="pos-qty-input" value="${item.qty}" onchange="setQty('${item.id}', this)">
                        <button class="pos-qty-btn" onclick="updateQty('${item.id}', 1)">+</button>
                    </div>
                    <div class="pos-item-total">$${itemTotal.toFixed(2)}</div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    let discount = parseFloat(document.getElementById('posDiscount').value);
    if (isNaN(discount) || discount < 0) discount = 0;
    
    let finalTotal = total - discount;
    if (finalTotal < 0) finalTotal = 0;
    
    document.getElementById('posSubtotal').textContent = '$' + total.toFixed(2);
    document.getElementById('posTotal').textContent = '$' + finalTotal.toFixed(2);
    document.getElementById('posItemCount').textContent = String(cart.length);
    document.getElementById('posUnitCount').textContent = String(unitCount);
    checkoutBtn.disabled = false;
    
    // Auto-scroll to bottom of cart
    container.scrollTop = container.scrollHeight;

    if (lastAddedCartItemId !== null) {
        const highlightedItemId = lastAddedCartItemId;
        setTimeout(() => {
            if (String(lastAddedCartItemId) === String(highlightedItemId)) {
                lastAddedCartItemId = null;
                renderCart();
            }
        }, 1800);
    }
}

async function checkout() {
    if (cart.length === 0) return;
    
    const checkoutBtn = document.getElementById('checkoutBtn');
    checkoutBtn.disabled = true;
    checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    const customer_id = document.getElementById('posCustomer').value || null;
    const payment_method = document.getElementById('posPaymentMethod').value;
    
    let total_amount = 0;
    const items = cart.map(item => {
        const subtotal = item.price * item.qty;
        total_amount += subtotal;
        return {
            product_id: item.id,
            quantity: item.qty,
            unit_price: item.price,
            subtotal: subtotal
        };
    });
    
    let discount = parseFloat(document.getElementById('posDiscount').value) || 0;
    let final_amount = total_amount - discount;
    if (final_amount < 0) final_amount = 0;
    
    const data = {
        customer_id: customer_id,
        total_amount: final_amount,
        discount_amount: discount,
        payment_status: 'Paid', // Assuming POS is instant payment
        payment_method: payment_method,
        items: items
    };

    const csrfTokenMeta = document.querySelector('meta[name="vendix-csrf-token"]');
    const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') || '' : '';
    
    try {
        const response = await fetch('../api/sales.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(data)
        });
        
        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            alert('Server error. Failed to parse response.');
            checkoutBtn.disabled = false;
            checkoutBtn.innerHTML = '<i class="fas fa-check-circle"></i> Complete Sale';
            return;
        }
        
        
        if (result.status === 'success') {
            // Save sale data for printing
            lastCompletedSale = {
                id: result.id,
                date: new Date().toISOString(),
                customer_name: customer_id ? document.getElementById('posCustomer').options[document.getElementById('posCustomer').selectedIndex].text : 'Walk-in Customer',
                payment_method: payment_method,
                total_amount: final_amount,
                discount_amount: discount,
                items: [...cart]
            };
            
            // Show success modal
            document.getElementById('posSuccessModal').style.display = 'flex';
            updatePOSStatus('Sale completed successfully');
            setScannerBadgeState('', 'Scanner Ready');
        } else {
            alert('Error: ' + result.message);
            checkoutBtn.disabled = false;
            checkoutBtn.innerHTML = '<i class="fas fa-check-circle"></i> Complete Sale';
            updatePOSStatus('Checkout failed');
        }
    } catch (e) {
        alert('Connection Error: ' + e.message);
        checkoutBtn.disabled = false;
        checkoutBtn.innerHTML = '<i class="fas fa-check-circle"></i> Complete Sale';
        updatePOSStatus('Connection error during checkout');
    }
}

function startNewSale() {
    document.getElementById('posSuccessModal').style.display = 'none';
    clearCart();
    document.getElementById('posCustomer').value = '';
    document.getElementById('posPaymentMethod').value = 'Cash';
    document.getElementById('checkoutBtn').innerHTML = '<i class="fas fa-check-circle"></i> Complete Sale';
    // Reload to refresh stock levels
    window.location.reload();
}

function printPosReceipt() {
    if (!lastCompletedSale) return;
    
    const sale = lastCompletedSale;
    const printWindow = window.open('', '', 'height=600,width=800');
    
    let itemsHtml = '';
    let subtotal = 0;
    sale.items.forEach(item => {
        const itemTotal = item.price * item.qty;
        subtotal += itemTotal;
        itemsHtml += '<tr><td style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">' + htmlEscape(item.name) + '</td><td style="padding: 10px; text-align: center; border-bottom: 1px solid #ddd;">' + item.qty + '</td><td style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">$' + item.price.toFixed(2) + '</td><td style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">$' + itemTotal.toFixed(2) + '</td></tr>';
    });
    
    const discount = sale.discount_amount || 0;
    
    // Helper function definition for escaping HTML safely
    function htmlEscape(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    const html = '<!DOCTYPE html><html><head><title>Sale Receipt - SALE-' + String(sale.id).padStart(4, '0') + '</title><style>body{font-family:Arial,sans-serif;margin:0;padding:20px;background:#fff}.container{max-width:800px;margin:0 auto}.header{text-align:center;margin-bottom:30px;border-bottom:3px solid #8B6F47;padding-bottom:20px}.app-name{font-size:28px;font-weight:bold;color:#8B6F47;margin-bottom:5px}.title{font-size:18px;color:#666}.info-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}.info-label{font-size:12px;color:#999;text-transform:uppercase;margin-bottom:5px;font-weight:600}.info-value{font-size:14px;font-weight:600;color:#333}table{width:100%;border-collapse:collapse;margin-bottom:20px}th{background-color:#8B6F47;color:white;padding:12px;text-align:left;font-weight:600;font-size:14px;border:none}td{padding:10px 12px;border-bottom:1px solid #e5e7eb}.summary{text-align:right;margin-top:20px;background:#f9fafb;padding:15px;border-radius:6px}.summary-row{display:flex;justify-content:space-between;margin:8px 0;font-size:14px}.summary-label{font-weight:600;color:#666}.summary-value{font-weight:600;color:#333}.total-amount{font-size:16px !important;color:#8B6F47;font-weight:700;padding-top:10px;border-top:2px solid #e5e7eb}.total-amount .summary-label{color:#333}.footer{text-align:center;margin-top:30px;font-size:12px;color:#999;border-top:1px solid #e5e7eb;padding-top:15px}</style></head><body><div class="container"><div class="header"><div class="app-name">Vendix</div><div class="title">Sales Receipt</div></div><div class="info-row"><div class="info-item"><div class="info-label">Sale ID</div><div class="info-value">SALE-' + String(sale.id).padStart(4, '0') + '</div></div><div class="info-item"><div class="info-label">Date & Time</div><div class="info-value">' + new Date().toLocaleString() + '</div></div></div><div class="info-row"><div class="info-item"><div class="info-label">Customer</div><div class="info-value">' + htmlEscape(sale.customer_name) + '</div></div><div class="info-item"><div class="info-label">Cashier</div><div class="info-value">POS User</div></div></div><table><thead><tr><th>Product Name</th><th style="text-align:center;width:80px">Qty</th><th style="text-align:right;width:100px">Price</th><th style="text-align:right;width:100px">Total</th></tr></thead><tbody>' + itemsHtml + '</tbody></table><div class="summary"><div class="summary-row"><div class="summary-label">Subtotal:</div><div class="summary-value">$' + subtotal.toFixed(2) + '</div></div>' + (discount > 0 ? '<div class="summary-row"><div class="summary-label">Discount:</div><div class="summary-value" style="color:#e63946;">-$' + discount.toFixed(2) + '</div></div>' : '') + '<div class="summary-row total-amount"><div class="summary-label">Total Amount:</div><div class="summary-value">$' + sale.total_amount.toFixed(2) + '</div></div><div class="summary-row"><div class="summary-label">Payment Method:</div><div class="summary-value">' + htmlEscape(sale.payment_method) + '</div></div><div class="summary-row"><div class="summary-label">Status:</div><div class="summary-value">Paid</div></div></div><div class="footer"><p>Thank you for your purchase!</p><p>Generated on ' + new Date().toLocaleString() + '</p></div></div></body></html>';
    
    printWindow.document.write(html);
    printWindow.document.close();
    
    // Wait for styles to load then print
    setTimeout(() => {
        printWindow.print();
    }, 250);
}
</script>

<?php // include '../includes/footer.php'; ?>
