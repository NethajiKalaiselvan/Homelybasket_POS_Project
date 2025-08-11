<?php
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

requireLogin();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => ''];
    
    switch ($_POST['action']) {
        case 'search_product':
            $search = sanitize($_POST['search']);
            if (!empty($search)) {
                $query = "SELECT p.*, c.name as category_name, b.name as brand_name 
                         FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         LEFT JOIN brands b ON p.brand_id = b.id 
                         WHERE p.status = 'active' AND p.stock_quantity > 0 
                         AND (p.name LIKE :search OR p.barcode = :exact_search)
                         ORDER BY p.name LIMIT 20";
                
                $products = $db->resultset($query, [
                    ':search' => "%$search%",
                    ':exact_search' => $search
                ]);
                
                $response['success'] = true;
                $response['data'] = $products;
            }
            break;
            
        case 'process_sale':
            try {
                $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
                $items = json_decode($_POST['items'], true);
                $subtotal = (float)$_POST['subtotal'];
                $tax_amount = (float)$_POST['tax_amount'];
                $discount_amount = (float)$_POST['discount_amount'];
                $total_amount = (float)$_POST['total_amount'];
                $payment_method = sanitize($_POST['payment_method']);
                $notes = sanitize($_POST['notes']);

                if (empty($items)) {
                    throw new Exception('No items in cart');
                }

                $db->getConnection()->beginTransaction();

                // Generate bill number
                $bill_number = generateBillNumber();

                // Insert bill
                $query = "INSERT INTO bills (bill_number, customer_id, cashier_id, subtotal, tax_amount, discount_amount, total_amount, payment_method, notes) 
                         VALUES (:bill_number, :customer_id, :cashier_id, :subtotal, :tax_amount, :discount_amount, :total_amount, :payment_method, :notes)";

                $db->query($query, [
                    ':bill_number' => $bill_number,
                    ':customer_id' => $customer_id,
                    ':cashier_id' => getUserId(),
                    ':subtotal' => $subtotal,
                    ':tax_amount' => $tax_amount,
                    ':discount_amount' => $discount_amount,
                    ':total_amount' => $total_amount,
                    ':payment_method' => $payment_method,
                    ':notes' => $notes
                ]);

                $bill_id = $db->lastInsertId();

                // Insert bill items and update stock
                foreach ($items as $item) {
                    $query = "INSERT INTO bill_items (bill_id, product_id, quantity, unit_price, total_price, tax_rate) 
                             VALUES (:bill_id, :product_id, :quantity, :unit_price, :total_price, :tax_rate)";

                    $db->query($query, [
                        ':bill_id' => $bill_id,
                        ':product_id' => $item['id'],
                        ':quantity' => $item['quantity'],
                        ':unit_price' => $item['unit_price'],
                        ':total_price' => $item['total_price'],
                        ':tax_rate' => $item['tax_rate']
                    ]);

                    // Update stock
                    updateStock($item['id'], $item['quantity'], 'out', 'sale', $bill_id);
                }

                // Update customer total purchases
                if ($customer_id) {
                    $query = "UPDATE customers SET total_purchases = total_purchases + :amount, last_purchase = CURDATE() WHERE id = :id";
                    $db->query($query, [':amount' => $total_amount, ':id' => $customer_id]);
                }

                $db->getConnection()->commit();

                // Retrieve customer name if available
                $customer_name = null;
                if ($customer_id) {
                    $query = "SELECT name FROM customers WHERE id = :id";
                    $customer = $db->single($query, [':id' => $customer_id]);
                    $customer_name = $customer['name'] ?? null;
                }

                $response['success'] = true;
                $response['message'] = 'Sale processed successfully';
                $response['bill_id'] = $bill_id;
                $response['bill_number'] = $bill_number;
                $response['customer_name'] = $customer_name; // Include customer name in response

                // Redirect to invoice page if not an AJAX request
                if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['REQUEST_METHOD']) === 'POST') {
                    header("Location: invoice.php?invoice=$bill_number");
                    exit();
                }

            } catch (Exception $e) {
                $db->getConnection()->rollBack();
                $response['message'] = $e->getMessage();
            }
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Get customers for dropdown
$customers = $db->resultset("SELECT * FROM customers WHERE status = 'active' ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing / POS - Supermarket Billing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
       
        .pos-container {
            height: calc(100vh - 120px);
        }
        .product-search {
            position: sticky;
            top: 0;
            z-index: 10;
            background: white;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .product-list {
            height: 400px;
            overflow-y: auto;
        }
        .cart-section {
            border-left: 2px solid #e5e7eb;
            background: #f8fafc;
        }
        .cart-items {
            height: 300px;
            overflow-y: auto;
        }
        .product-item {
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #e5e7eb;
        }
        .product-item:hover {
            background: #f0f9ff;
            border-color: #3b82f6;
            transform: translateY(-1px);
        }
        .cart-item {
            border-bottom: 1px solid #e5e7eb;
            background: white;
            margin-bottom: 0.5rem;
            border-radius: 0.5rem;
            padding: 0.75rem;
        }
        .total-section {
            background: white;
            padding: 1rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .container-fluid {
            padding-left: 0;
            padding-right: 0;
        }
        .main-content {
            margin-left: 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-cash-register me-2"></i>
                        Point of Sale
                    </h1>
                    <div class="btn-toolbar">
                        <button type="button" class="btn btn-outline-secondary me-2" onclick="clearCart()">
                            <i class="fas fa-trash me-1"></i>Clear Cart
                        </button>
                        <button type="button" class="btn btn-success" onclick="processSale()" id="processBtn">
                            <i class="fas fa-credit-card me-1"></i>Process Sale
                        </button>
                    </div>  
                </div>

                <div class="row pos-container">
                    <!-- Product Search and List -->
                    <div class="col-lg-8">
                        <div class="card h-100">
                            <div class="product-search">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control form-control-lg" 
                                           id="productSearch" placeholder="Search products by name or scan barcode...">
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="product-list p-3" id="productList">
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-search fa-3x mb-3"></i>
                                        <p>Search for products to add to cart</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cart Section -->
                    <div class="col-lg-4">
                        <div class="card h-100 cart-section">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-shopping-cart me-2"></i>
                                    Shopping Cart
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <!-- Customer Selection -->
                                <div class="p-3 border-bottom">
                                    <label class="form-label">Customer (Optional)</label>
                                    <select class="form-select" id="customerId">
                                        <option value="">Walk-in Customer</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo $customer['id']; ?>">
                                                <?php echo htmlspecialchars($customer['name']); ?> - <?php echo $customer['phone']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Cart Items -->
                                <div class="cart-items p-3" id="cartItems">
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                        <p>Cart is empty</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Cart Total -->
                            <div class="card-footer">
                                <div class="total-section">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal:</span>
                                        <span id="subtotal">$0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Tax:</span>
                                        <span id="taxAmount">$0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Discount:</span>
                                        <span id="discountAmount">$0.00</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between fw-bold h5">
                                        <span>Total:</span>
                                        <span id="totalAmount">$0.00</span>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <label class="form-label">Payment Method</label>
                                        <select class="form-select" id="paymentMethod">
                                            <option value="cash">Cash</option>
                                            <option value="card">Credit/Debit Card</option>
                                            <option value="upi">UPI</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Sale Processing Modal -->
    <div class="modal fade" id="saleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>
                        Sale Completed
                    </h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                    <h4>Sale Processed Successfully!</h4>
                    <p class="mb-3">Bill Number: <strong id="billNumber"></strong></p>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" onclick="printInvoice()">
                            <i class="fas fa-print me-2"></i>Print Invoice
                        </button>
                        <button type="button" class="btn btn-success" onclick="newSale()">
                            <i class="fas fa-plus me-2"></i>New Sale
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let cart = [];
        let searchTimeout;

        document.getElementById('productSearch').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchProducts(this.value);
            }, 300);
        });

        function searchProducts(search) {
            if (search.length < 2) {
                document.getElementById('productList').innerHTML = `
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-search fa-3x mb-3"></i>
                        <p>Search for products to add to cart</p>
                    </div>
                `;
                return;
            }

            const formData = new FormData();
            formData.append('action', 'search_product');
            formData.append('search', search);

            fetch('billing.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayProducts(data.data);
                } else {
                    document.getElementById('productList').innerHTML = `
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                            <p>No products found</p>
                        </div>
                    `;
                }
            });
        }

        function displayProducts(products) {
            const html = products.map(product => `
                <div class="product-item p-3 mb-2 rounded" onclick="addToCart(${product.id})">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${product.name}</h6>
                            <small class="text-muted">${product.category_name || 'No Category'} - ${product.brand_name || 'No Brand'}</small>
                            <br>
                            <small class="text-info">Stock: ${product.stock_quantity} ${product.unit}</small>
                        </div>
                        <div class="text-end">
                            <h6 class="text-primary mb-0">$${parseFloat(product.unit_price).toFixed(2)}</h6>
                            ${product.barcode ? `<small class="text-muted">${product.barcode}</small>` : ''}
                        </div>
                    </div>
                </div>
            `).join('');

            document.getElementById('productList').innerHTML = html;
        }

        function addToCart(productId) {
            // Find product from last search results
            const productElement = document.querySelector(`[onclick="addToCart(${productId})"]`);
            if (!productElement) return;

            // Extract product info
            const name = productElement.querySelector('h6').textContent;
            const priceText = productElement.querySelector('.text-primary').textContent;
            const price = parseFloat(priceText.replace('$', ''));
            const stockText = productElement.querySelector('.text-info').textContent;
            const stock = parseInt(stockText.match(/\d+/)[0]);

            // Check if product already in cart
            const existingItem = cart.find(item => item.id === productId);
            
            if (existingItem) {
                if (existingItem.quantity < stock) {
                    existingItem.quantity++;
                    existingItem.total_price = existingItem.quantity * existingItem.unit_price;
                } else {
                    alert('Insufficient stock!');
                    return;
                }
            } else {
                cart.push({
                    id: productId,
                    name: name,
                    unit_price: price,
                    quantity: 1,
                    total_price: price,
                    tax_rate: 0 // Default tax rate
                });
            }

            updateCartDisplay();
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
        }

        function updateQuantity(index, quantity) {
            if (quantity <= 0) {
                removeFromCart(index);
                return;
            }
            
            cart[index].quantity = quantity;
            cart[index].total_price = quantity * cart[index].unit_price;
            updateCartDisplay();
        }

        function updateCartDisplay() {
            const cartItemsDiv = document.getElementById('cartItems');
            
            if (cart.length === 0) {
                cartItemsDiv.innerHTML = `
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                        <p>Cart is empty</p>
                    </div>
                `;
            } else {
                const html = cart.map((item, index) => `
                    <div class="cart-item">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${item.name}</h6>
                                <small class="text-muted">$${item.unit_price.toFixed(2)} each</small>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <button class="btn btn-outline-secondary" onclick="updateQuantity(${index}, ${item.quantity - 1})">-</button>
                                <input type="number" class="form-control text-center" value="${item.quantity}" 
                                       onchange="updateQuantity(${index}, this.value)">
                                <button class="btn btn-outline-secondary" onclick="updateQuantity(${index}, ${item.quantity + 1})">+</button>
                            </div>
                            <strong>$${item.total_price.toFixed(2)}</strong>
                        </div>
                    </div>
                `).join('');
                
                cartItemsDiv.innerHTML = html;
            }

            // Update totals
            updateTotals();
        }

        function updateTotals() {
            const subtotal = cart.reduce((sum, item) => sum + item.total_price, 0);
            const taxRate = 0; // Default tax rate
            const taxAmount = subtotal * (taxRate / 100);
            const discountAmount = 0; // TODO: Implement discount logic
            const total = subtotal + taxAmount - discountAmount;

            document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('taxAmount').textContent = '$' + taxAmount.toFixed(2);
            document.getElementById('discountAmount').textContent = '$' + discountAmount.toFixed(2);
            document.getElementById('totalAmount').textContent = '$' + total.toFixed(2);
        }

        function clearCart() {
            cart = [];
            updateCartDisplay();
        }

        function processSale() {
            if (cart.length === 0) {
                alert('Cart is empty!');
                return;
            }

            const customerId = document.getElementById('customerId').value;
            const paymentMethod = document.getElementById('paymentMethod').value;
            const subtotal = cart.reduce((sum, item) => sum + item.total_price, 0);
            const taxAmount = subtotal * 0; // 0% tax
            const discountAmount = 0;
            const totalAmount = subtotal + taxAmount - discountAmount;

            const formData = new FormData();
            formData.append('action', 'process_sale');
            formData.append('customer_id', customerId);
            formData.append('items', JSON.stringify(cart));
            formData.append('subtotal', subtotal);
            formData.append('tax_amount', taxAmount);
            formData.append('discount_amount', discountAmount);
            formData.append('total_amount', totalAmount);
            formData.append('payment_method', paymentMethod);
            formData.append('notes', '');

            document.getElementById('processBtn').disabled = true;
            document.getElementById('processBtn').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

            fetch('billing.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('billNumber').textContent = data.bill_number;
                    const modal = new bootstrap.Modal(document.getElementById('saleModal'));
                    modal.show();
                    clearCart();
                } else {
                    alert('Error processing sale: ' + data.message);
                }
            })
            .finally(() => {
                document.getElementById('processBtn').disabled = false;
                document.getElementById('processBtn').innerHTML = '<i class="fas fa-credit-card me-1"></i>Process Sale';
            });
        }

        function printInvoice() {
            // Open the invoice page for the last bill number
            const billNumber = document.getElementById('billNumber').textContent;
            if (billNumber) {
                window.open('invoice.php?invoice=' + encodeURIComponent(billNumber), '_blank');
            } else {
                alert('No bill number found.');
            }
        }

        function newSale() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('saleModal'));
            modal.hide();
            document.getElementById('productSearch').value = '';
            document.getElementById('customerId').value = '';
            searchProducts('');
        }

        // Auto-focus on search when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('productSearch').focus();
        });
    </script>
</body>
</html>