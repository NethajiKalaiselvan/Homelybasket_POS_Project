<?php
require_once 'config/database.php';
require_once 'includes/session.php';

requireLogin();

// Initialize database connection
$db = new Database();

// Define required functions locally
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_product':
                $name = sanitize($_POST['name']);
                $barcode = sanitize($_POST['barcode']);
                $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
                $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
                $unit_price = (float)$_POST['unit_price'];
                $cost_price = (float)$_POST['cost_price'];
                $stock_quantity = (int)$_POST['stock_quantity'];
                $min_stock_level = (int)$_POST['min_stock_level'];
                $unit = sanitize($_POST['unit']);
                $tax_rate = (float)$_POST['tax_rate'];
                $description = sanitize($_POST['description']);

                try {
                    $query = "INSERT INTO products (name, barcode, category_id, brand_id, unit_price, cost_price, stock_quantity, min_stock_level, unit, tax_rate, description) 
                             VALUES (:name, :barcode, :category_id, :brand_id, :unit_price, :cost_price, :stock_quantity, :min_stock_level, :unit, :tax_rate, :description)";
                    
                    $params = [
                        ':name' => $name,
                        ':barcode' => $barcode ?: null,
                        ':category_id' => $category_id,
                        ':brand_id' => $brand_id,
                        ':unit_price' => $unit_price,
                        ':cost_price' => $cost_price,
                        ':stock_quantity' => $stock_quantity,
                        ':min_stock_level' => $min_stock_level,
                        ':unit' => $unit,
                        ':tax_rate' => $tax_rate,
                        ':description' => $description
                    ];
                    
                    $db->query($query, $params);
                    $message = 'Product added successfully!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error adding product: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'update_product':
                $id = (int)$_POST['id'];
                $name = sanitize($_POST['name']);
                $barcode = sanitize($_POST['barcode']);
                $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
                $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
                $unit_price = (float)$_POST['unit_price'];
                $cost_price = (float)$_POST['cost_price'];
                $stock_quantity = (int)$_POST['stock_quantity'];
                $min_stock_level = (int)$_POST['min_stock_level'];
                $unit = sanitize($_POST['unit']);
                $tax_rate = (float)$_POST['tax_rate'];
                $description = sanitize($_POST['description']);

                try {
                    $query = "UPDATE products SET name = :name, barcode = :barcode, category_id = :category_id, brand_id = :brand_id, 
                             unit_price = :unit_price, cost_price = :cost_price, stock_quantity = :stock_quantity, 
                             min_stock_level = :min_stock_level, unit = :unit, tax_rate = :tax_rate, description = :description 
                             WHERE id = :id";
                    
                    $params = [
                        ':id' => $id,
                        ':name' => $name,
                        ':barcode' => $barcode ?: null,
                        ':category_id' => $category_id,
                        ':brand_id' => $brand_id,
                        ':unit_price' => $unit_price,
                        ':cost_price' => $cost_price,
                        ':stock_quantity' => $stock_quantity,
                        ':min_stock_level' => $min_stock_level,
                        ':unit' => $unit,
                        ':tax_rate' => $tax_rate,
                        ':description' => $description
                    ];
                    
                    $db->query($query, $params);
                    $message = 'Product updated successfully!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error updating product: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'delete_product':
                $id = (int)$_POST['id'];
                try {
                    $query = "UPDATE products SET status = 'inactive' WHERE id = :id";
                    $db->query($query, [':id' => $id]);
                    $message = 'Product deleted successfully!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error deleting product: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get products with category and brand info
$query = "SELECT p.*, c.name as category_name, b.name as brand_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN brands b ON p.brand_id = b.id 
          WHERE p.status = 'active'
          ORDER BY p.name";
$products = $db->resultset($query);

// Get categories and brands for dropdowns
$categories = $db->resultset("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$brands = $db->resultset("SELECT * FROM brands WHERE status = 'active' ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Supermarket Billing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
         
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-boxes me-2"></i>
                        Product Management
                    </h1>
                    <div class="btn-toolbar">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#productModal">
                            <i class="fas fa-plus me-1"></i>Add Product
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Products Table -->
                <div class="card shadow">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">All Products</h5>
                            <div class="input-group" style="width: 300px;">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" class="form-control" id="searchProducts" placeholder="Search products...">
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="productsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Barcode</th>
                                        <th>Category</th>
                                        <th>Brand</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                    <?php if ($product['description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?>...</small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['barcode']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['brand_name']); ?></td>
                                            <td><?php echo formatCurrency($product['unit_price']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $product['stock_quantity'] <= $product['min_stock_level'] ? 'bg-danger' : 'bg-success'; ?>">
                                                    <?php echo $product['stock_quantity']; ?> <?php echo $product['unit']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">Active</span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="productForm">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-box me-2"></i>
                            <span id="modalTitle">Add New Product</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="add_product">
                        <input type="hidden" name="id" id="productId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Name *</label>
                                <input type="text" class="form-control" name="name" id="productName" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Barcode</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="barcode" id="productBarcode">
                                    <button type="button" class="btn btn-outline-secondary" onclick="generateBarcode()">
                                        <i class="fas fa-barcode"></i> Generate
                                    </button>
                                </div>
                                <div id="barcodeContainer" class="mt-2" style="display: none;">
                                    <canvas id="barcodeCanvas"></canvas>
                                    <div class="btn-group mt-2">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="printBarcode()">
                                            <i class="fas fa-print"></i> Print
                                        </button>
                                        <button type="button" class="btn btn-sm btn-success" onclick="downloadBarcode()">
                                            <i class="fas fa-download"></i> Download
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category_id" id="productCategory">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Brand</label>
                                <select class="form-select" name="brand_id" id="productBrand">
                                    <option value="">Select Brand</option>
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?php echo $brand['id']; ?>">
                                            <?php echo htmlspecialchars($brand['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unit Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="unit_price" id="productPrice" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cost Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="cost_price" id="productCost" step="0.01">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Stock Quantity *</label>
                                <input type="number" class="form-control" name="stock_quantity" id="productStock" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Min Stock Level</label>
                                <input type="number" class="form-control" name="min_stock_level" id="productMinStock" value="5">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Unit</label>
                                <select class="form-select" name="unit" id="productUnit">
                                    <option value="piece">Piece</option>
                                    <option value="kg">Kilogram</option>
                                    <option value="liter">Liter</option>
                                    <option value="dozen">Dozen</option>
                                    <option value="box">Box</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tax Rate (%)</label>
                                <input type="number" class="form-control" name="tax_rate" id="productTax" step="0.01" value="18">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="productDescription" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>Save Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script>
        // Search products
        document.getElementById('searchProducts').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#productsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Generate barcode
        function generateBarcode() {
            const barcode = Date.now().toString();
            document.getElementById('productBarcode').value = barcode;
            
            // Generate and display barcode
            const canvas = document.getElementById('barcodeCanvas');
            JsBarcode(canvas, barcode, {
                format: "CODE128",
                width: 2,
                height: 100,
                displayValue: true
            });
            
            document.getElementById('barcodeContainer').style.display = 'block';
        }

        // Print barcode
        function printBarcode() {
            const canvas = document.getElementById('barcodeCanvas');
            const printWindow = window.open('', '_blank');
            printWindow.document.write('<html><head><title>Print Barcode</title></head><body>');
            printWindow.document.write('<div style="text-align: center;">');
            printWindow.document.write('<img src="' + canvas.toDataURL() + '"/>');
            printWindow.document.write('</div></body></html>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }

        // Download barcode
        function downloadBarcode() {
            const canvas = document.getElementById('barcodeCanvas');
            const barcode = document.getElementById('productBarcode').value;
            const link = document.createElement('a');
            link.download = 'barcode-' + barcode + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        }

        // Edit product
        function editProduct(product) {
            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('formAction').value = 'update_product';
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productBarcode').value = product.barcode || '';
            document.getElementById('productCategory').value = product.category_id || '';
            document.getElementById('productBrand').value = product.brand_id || '';
            document.getElementById('productPrice').value = product.unit_price;
            document.getElementById('productCost').value = product.cost_price;
            document.getElementById('productStock').value = product.stock_quantity;
            document.getElementById('productMinStock').value = product.min_stock_level;
            document.getElementById('productUnit').value = product.unit;
            document.getElementById('productTax').value = product.tax_rate;
            document.getElementById('productDescription').value = product.description || '';
            
            const modal = new bootstrap.Modal(document.getElementById('productModal'));
            modal.show();
        }

        // Delete product
        function deleteProduct(id, name) {
            if (confirm(`Are you sure you want to delete "${name}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Reset form when modal is closed
        document.getElementById('productModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('productForm').reset();
            document.getElementById('modalTitle').textContent = 'Add New Product';
            document.getElementById('formAction').value = 'add_product';
            document.getElementById('productId').value = '';
        });
    </script>
</body>
</html>