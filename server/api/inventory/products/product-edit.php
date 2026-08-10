<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $product_id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $product_type = $_POST['productType'] ?? 'physical';
    $uom_type = $_POST['uomType'] ?? 'unit';
    $company = !empty($_POST['company']) ? $_POST['company'] : null;
    $default_unit = !empty($_POST['defaultUnit']) ? $_POST['defaultUnit'] : null;
    $category = !empty($_POST['category']) ? $_POST['category'] : null;
    $subcategory = !empty($_POST['subcategory']) ? $_POST['subcategory'] : null;
    $inventory_account = !empty($_POST['inventoryAccount']) ? $_POST['inventoryAccount'] : null;
    $vendor_id = !empty($_POST['vendor']) ? $_POST['vendor'] : null;
    $parent_product_id = !empty($_POST['parentProduct']) ? $_POST['parentProduct'] : null;

    $description = $_POST['description'] ?? null;
    $qr_code = !empty($_POST['qrCode']) ? $_POST['qrCode'] : null;
    $barcode = !empty($_POST['barcode']) ? $_POST['barcode'] : null;
    $purchase_price = !empty($_POST['purchasePrice']) ? $_POST['purchasePrice'] : 0;
    $trade_price = !empty($_POST['tradePrice']) ? $_POST['tradePrice'] : 0;
    $wholesale_price = !empty($_POST['wholesalePrice']) ? $_POST['wholesalePrice'] : 0;
    $mrp = !empty($_POST['mrp']) ? $_POST['mrp'] : 0;
    $default_discount = !empty($_POST['defaultDiscount']) ? $_POST['defaultDiscount'] : 0;
    $trade_offer_discount = !empty($_POST['tradeOfferDiscount']) ? $_POST['tradeOfferDiscount'] : 0;
    $default_foc = !empty($_POST['defaultFoc']) ? $_POST['defaultFoc'] : 0;
    $carton_conversion = !empty($_POST['cartonConversion']) ? $_POST['cartonConversion'] : 0;
    $tax_regime_id = !empty($_POST['salesTaxType']) ? $_POST['salesTaxType'] : null;
    $min_stock = !empty($_POST['minStock']) ? $_POST['minStock'] : 0;
    $max_stock = !empty($_POST['maxStock']) ? $_POST['maxStock'] : 0;
    $manufacturing_date = !empty($_POST['manufacturingDate']) ? $_POST['manufacturingDate'] : null;
    $expiry_date = !empty($_POST['expiryDate']) ? $_POST['expiryDate'] : null;
    $is_active = isset($_POST['active']) ? 1 : 0;
    $stock_affects = isset($_POST['stockAffects']) ? 1 : 0;
    $invoice_affects = isset($_POST['invoiceAffects']) ? 1 : 0;
    $product_conversion_factor = !empty($_POST['productConversionFactor']) ? $_POST['productConversionFactor'] : null;
    $uom_group_id = !empty($_POST['uomGroup']) ? $_POST['uomGroup'] : null;
    $group_conversion_factors = isset($_POST['groupConversionFactor']) ? $_POST['groupConversionFactor'] : [];
    
    // Handle photo upload
    $photo_filename = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo_result = handlePhotoUpload($_FILES['photo']);
        if ($photo_result['success']) {
            $photo_filename = $photo_result['filename'];
        } else {
            echo json_encode(['success' => false, 'message' => $photo_result['message']]);
            exit;
        }
    }
    
    // Validate required fields
    if (empty($product_id) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Product ID and name are required']);
        exit;
    }
    
    // Verify product belongs to tenant
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$product_id, $tenant_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    // Update product
    if ($photo_filename) {
        $stmt = $pdo->prepare("
            UPDATE products SET 
                company_id = ?, name = ?, product_type = ?, uom_type = ?, default_unit_id = ?, uom_group_id = ?, product_conversion_factor = ?, category_id = ?, subcategory_id = ?,
                inventory_account_id = ?, vendor_id = ?, parent_product_id = ?, description = ?, qr_code = ?, barcode = ?, purchase_price = ?, 
                trade_price = ?, wholesale_price = ?, mrp = ?, default_discount = ?, trade_offer_discount = ?, default_foc = ?, 
                carton_conversion = ?, tax_regime_id = ?, min_stock_level = ?, max_stock_level = ?, 
                manufacturing_date = ?, expiry_date = ?, photo = ?, is_active = ?, stock_affects = ?, invoice_affects = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND tenant_id = ?
        ");
        
        $stmt->execute([
            $company, $name, $product_type, $uom_type, $default_unit, $uom_group_id, $product_conversion_factor, $category, $subcategory,
            $inventory_account, $vendor_id, $parent_product_id, $description, $qr_code, $barcode, $purchase_price,
            $trade_price, $wholesale_price, $mrp, $default_discount, $trade_offer_discount, $default_foc,
            $carton_conversion, $tax_regime_id, $min_stock, $max_stock,
            $manufacturing_date, $expiry_date, $photo_filename, $is_active, $stock_affects, $invoice_affects, $product_id, $tenant_id
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE products SET 
                company_id = ?, name = ?, product_type = ?, uom_type = ?, default_unit_id = ?, uom_group_id = ?, product_conversion_factor = ?, category_id = ?, subcategory_id = ?,
                inventory_account_id = ?, vendor_id = ?, parent_product_id = ?, description = ?, qr_code = ?, barcode = ?, purchase_price = ?, 
                trade_price = ?, wholesale_price = ?, mrp = ?, default_discount = ?, trade_offer_discount = ?, default_foc = ?, 
                carton_conversion = ?, tax_regime_id = ?, min_stock_level = ?, max_stock_level = ?, 
                manufacturing_date = ?, expiry_date = ?, is_active = ?, stock_affects = ?, invoice_affects = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND tenant_id = ?
        ");
        
        $stmt->execute([
            $company, $name, $product_type, $uom_type, $default_unit, $uom_group_id, $product_conversion_factor, $category, $subcategory,
            $inventory_account, $vendor_id, $parent_product_id, $description, $qr_code, $barcode, $purchase_price,
            $trade_price, $wholesale_price, $mrp, $default_discount, $trade_offer_discount, $default_foc,
            $carton_conversion, $tax_regime_id, $min_stock, $max_stock,
            $manufacturing_date, $expiry_date, $is_active, $stock_affects, $invoice_affects, $product_id, $tenant_id
        ]);
    }
    
    // Update group conversion factors BEFORE processing stock
    $pdo->prepare("DELETE FROM product_uom_conversions WHERE product_id = ?")->execute([$product_id]);
    
    if ($uom_type === 'group' && !empty($group_conversion_factors)) {
        $conversionStmt = $pdo->prepare("
            INSERT INTO product_uom_conversions (product_id, uom_id, conversion_factor)
            VALUES (?, ?, ?)
        ");
        
        foreach ($group_conversion_factors as $uom_id => $factor) {
            if (!empty($factor)) {
                $conversionStmt->execute([$product_id, $uom_id, $factor]);
                error_log("Saved conversion: UOM=$uom_id, Factor=$factor");
            }
        }
    }
    
    // Update stock opening entries if provided
    $debugInfo = [];
    $debugInfo['branch_isset'] = isset($_POST['branch']);
    $debugInfo['branch_is_array'] = isset($_POST['branch']) && is_array($_POST['branch']);
    $debugInfo['branch_count'] = isset($_POST['branch']) ? count($_POST['branch']) : 0;
    $debugInfo['uom_type'] = $uom_type;
    $debugInfo['uom_group_id'] = $uom_group_id;
    $debugInfo['default_unit'] = $default_unit;
    
    // Check for openingQty data
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'openingQty') === 0) {
            $debugInfo['qty_keys'][] = $key . ' => ' . (is_array($value) ? 'array[' . count($value) . ']' : $value);
        }
    }
    
    if (isset($_POST['branch']) && is_array($_POST['branch'])) {
        $debugInfo['stock_processing'] = 'YES';
        error_log("CONDITION MET: Proceeding with stock update");
        error_log("Branch array: " . print_r($_POST['branch'], true));
        
        // Log openingQty structure
        if (isset($_POST['openingQty'])) {
            error_log("openingQty structure: " . print_r($_POST['openingQty'], true));
        }
        
        // Log openingPrice structure  
        if (isset($_POST['openingPrice'])) {
            error_log("openingPrice structure: " . print_r($_POST['openingPrice'], true));
        }
        
        // Delete existing stock opening and ledger entries
        $pdo->prepare("DELETE FROM stock_opening WHERE product_id = ? AND tenant_id = ?")->execute([$product_id, $tenant_id]);
        $pdo->prepare("DELETE FROM stock_ledger WHERE product_id = ? AND tenant_id = ? AND transaction_type = 'Opening Stock'")->execute([$product_id, $tenant_id]);
        
        // Insert new stock opening entries
        $stockOpeningStmt = $pdo->prepare("
            INSERT INTO stock_opening (product_id, tenant_id, branch_id, opening_qty, opening_price, unit_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stockLedgerStmt = $pdo->prepare("
            INSERT INTO stock_ledger (tenant_id, account_id, branch_id, product_id, reference_table, reference_id, qty_in, unit_cost, unit_id, transaction_type, transaction_date)
            VALUES (?, ?, ?, ?, 'stock_opening', ?, ?, ?, ?, 'Opening Stock', CURDATE())
        ");
        
        for ($i = 0; $i < count($_POST['branch']); $i++) {
            if (!empty($_POST['branch'][$i])) {
                $branchId = $_POST['branch'][$i];
                $openingPrice = $_POST['openingPrice'][$i] ?? 0;
                
                if ($uom_type === 'group' && $uom_group_id) {
                    // UOM GROUP MODE: Insert SEPARATE stock_opening entries for EACH unit
                    error_log("Edit: Processing GROUP UOM for branch $branchId");
                    
                    $hasGroupData = false;
                    if (isset($_POST['openingQty']) && is_array($_POST['openingQty'])) {
                        foreach ($_POST['openingQty'] as $unit_id => $quantities) {
                            if (is_array($quantities) && isset($quantities[$i]) && !empty($quantities[$i])) {
                                $unitQty = floatval($quantities[$i]);
                                $hasGroupData = true;
                                
                                // Insert stock_opening for this specific unit
                                try {
                                    error_log("Edit: Saving stock_opening for unit {$unit_id}: qty={$unitQty}");
                                    $stockOpeningStmt->execute([
                                        $product_id,
                                        $tenant_id,
                                        $branchId,
                                        $unitQty,  // Original quantity for this unit
                                        $openingPrice,
                                        $unit_id   // Store the specific unit
                                    ]);
                                    $stockOpeningId = $pdo->lastInsertId();
                                    error_log("Edit: Stock opening saved with ID: {$stockOpeningId}");
                                    
                                    // Insert corresponding stock_ledger entry for this unit
                                    $stockLedgerStmt->execute([
                                        $tenant_id,
                                        $inventory_account,
                                        $branchId,
                                        $product_id,
                                        $stockOpeningId,
                                        $unitQty,  // Store original quantity
                                        $openingPrice,
                                        $unit_id   // Store the actual unit used
                                    ]);
                                    error_log("Edit: Stock ledger entry created for unit {$unit_id}, referencing stock_opening {$stockOpeningId}");
                                } catch (Exception $e) {
                                    error_log("Edit: Error saving stock for unit {$unit_id}: " . $e->getMessage());
                                }
                            }
                        }
                    }
                    
                    if (!$hasGroupData) {
                        error_log("Edit: No group data found for row {$i}");
                    }
                    continue; // Skip the single insert below for group mode
                } else {
                    // Default Unit mode - single quantity
                    $totalQtyInBaseUnits = 0;
                    $unitIdForStock = $default_unit;
                    if (!empty($_POST['openingQty'][$i])) {
                        $originalQty = floatval($_POST['openingQty'][$i]); // Store ORIGINAL qty
                        $totalQtyInBaseUnits = convertSingleUnitToBaseUnits($originalQty, $default_unit, $product_conversion_factor, $pdo);
                    } else {
                        $originalQty = 0;
                    }
                    
                    if ($totalQtyInBaseUnits > 0) {
                        error_log("Edit: INSERTING single unit - Branch=$branchId, Qty=$originalQty, Price=$openingPrice");
                        // Insert into stock_opening with ORIGINAL quantity
                        $stockOpeningStmt->execute([
                            $product_id,
                            $tenant_id,
                            $branchId,
                            $originalQty,  // Store ORIGINAL quantity
                            $openingPrice,
                            $unitIdForStock  // Store the unit used
                        ]);
                        
                        $stockOpeningId = $pdo->lastInsertId();
                        error_log("Edit: Stock Opening ID: $stockOpeningId");
                        
                        // Insert corresponding stock_ledger entry
                        $stockLedgerStmt->execute([
                            $tenant_id,
                            $inventory_account,
                            $branchId,
                            $product_id,
                            $stockOpeningId,
                            $originalQty,  // Store original quantity
                            $openingPrice,
                            $unitIdForStock  // Store the actual unit used
                        ]);
                        error_log("Edit: Stock Ledger inserted successfully");
                    } else {
                        error_log("Edit: SKIPPED single unit - Branch=$branchId, Qty=$originalQty (qty is 0 or negative)");
                    }
                }
            }
        }
    } else {
        error_log("CONDITION NOT MET: Stock update skipped - branch data not provided or not array");
    }
    
    // Save schemes if provided
    error_log('=== SCHEME SAVE DEBUG (EDIT) ===');
    error_log('Schemes isset: ' . (isset($_POST['schemes']) ? 'YES' : 'NO'));
    error_log('Schemes is array: ' . (is_array($_POST['schemes'] ?? null) ? 'YES' : 'NO'));
    error_log('Schemes count: ' . count($_POST['schemes'] ?? []));
    error_log('Schemes data: ' . print_r($_POST['schemes'] ?? [], true));
    
    if (isset($_POST['schemes']) && is_array($_POST['schemes']) && !empty($_POST['schemes'])) {
        // Get all unit IDs from schemes
        $unitIds = [];
        foreach ($_POST['schemes'] as $schemeJson) {
            $scheme = json_decode($schemeJson, true);
            if (!empty($scheme['unit_id'])) {
                $unitIds[] = $scheme['unit_id'];
            }
        }
        $unitIds = array_unique($unitIds);
        
        error_log('Unit IDs to delete: ' . print_r($unitIds, true));
        
        // Delete existing schemes for affected units
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $params = array_merge([$product_id, $tenant_id], $unitIds);
            $pdo->prepare("DELETE FROM product_schemes WHERE product_id = ? AND tenant_id = ? AND unit_id IN ($placeholders)")
                ->execute($params);
            error_log('Old schemes deleted');
        }
        
        // Insert new schemes
        $schemeStmt = $pdo->prepare("
            INSERT INTO product_schemes (tenant_id, product_id, unit_id, promo_qty, bonus_qty, to_qty, to_rs)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $inserted = 0;
        foreach ($_POST['schemes'] as $schemeJson) {
            error_log('Processing scheme JSON: ' . $schemeJson);
            $scheme = json_decode($schemeJson, true);
            error_log('Decoded scheme: ' . print_r($scheme, true));
            
            if (!empty($scheme['unit_id']) && (!empty($scheme['promo_qty']) || !empty($scheme['bonus_qty']) || !empty($scheme['to_qty']) || !empty($scheme['to_rs']))) {
                $schemeStmt->execute([
                    $tenant_id,
                    $product_id,
                    $scheme['unit_id'],
                    $scheme['promo_qty'] ?? 0,
                    $scheme['bonus_qty'] ?? 0,
                    $scheme['to_qty'] ?? 0,
                    $scheme['to_rs'] ?? 0
                ]);
                $inserted++;
                error_log('Scheme inserted successfully');
            } else {
                error_log('Scheme skipped - missing unit_id or all values empty');
            }
        }
        error_log('Total schemes inserted: ' . $inserted);
    }
    error_log('=== END SCHEME DEBUG (EDIT) ===');
    
    echo json_encode(['success' => true, 'message' => 'Product updated successfully', 'debug' => $debugInfo]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function handlePhotoUpload($file) {
    $allowed_types = ['png', 'jpg', 'jpeg', 'gif', 'pdf', 'webp', 'avif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File size exceeds 5MB limit'];
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    $upload_dir = '../../../../client/assets/uploads/products/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $filename = uniqid() . '_' . time();
    
    if ($file_ext !== 'webp' && $file_ext !== 'pdf') {
        $webp_filename = $filename . '.webp';
        $webp_path = $upload_dir . $webp_filename;
        
        if (convertToWebP($file['tmp_name'], $webp_path, $file_ext)) {
            return ['success' => true, 'filename' => $webp_filename];
        } else {
            return ['success' => false, 'message' => 'Failed to process image'];
        }
    } else {
        $final_filename = $filename . '.' . $file_ext;
        $final_path = $upload_dir . $final_filename;
        
        if (move_uploaded_file($file['tmp_name'], $final_path)) {
            return ['success' => true, 'filename' => $final_filename];
        } else {
            return ['success' => false, 'message' => 'Failed to upload file'];
        }
    }
}

function convertToWebP($source_path, $destination_path, $source_ext) {
    $image = null;
    
    switch ($source_ext) {
        case 'jpg':
        case 'jpeg':
            $image = imagecreatefromjpeg($source_path);
            break;
        case 'png':
            $image = imagecreatefrompng($source_path);
            break;
        case 'gif':
            $image = imagecreatefromgif($source_path);
            break;
        case 'avif':
            if (function_exists('imagecreatefromavif')) {
                $image = imagecreatefromavif($source_path);
            }
            break;
    }
    
    if ($image) {
        $result = imagewebp($image, $destination_path, 80);
        imagedestroy($image);
        return $result;
    }
    
    return false;
}

function convertSingleUnitToBaseUnits($qty, $unit_id, $product_conversion_factor, $pdo) {
    if (empty($unit_id)) return $qty;
    
    // Get unit info
    $stmt = $pdo->prepare("SELECT unit_scope, is_base_unit, base_unit_id, conversion_factor FROM uom WHERE id = ?");
    $stmt->execute([$unit_id]);
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$unit) return $qty;
    
    // If already base unit, return as is
    if ($unit['is_base_unit'] == 1) {
        return $qty;
    }
    
    // Convert based on unit_scope
    if ($unit['unit_scope'] === 'per_product' && !empty($product_conversion_factor)) {
        // Use product-specific conversion factor
        return $qty * $product_conversion_factor;
    } elseif ($unit['unit_scope'] === 'universal' && !empty($unit['conversion_factor'])) {
        // Use universal conversion factor
        return $qty * $unit['conversion_factor'];
    }
    
    return $qty;
}

function convertGroupUnitsToBaseUnits($postData, $rowIndex, $product_id, $pdo) {
    $totalQty = 0;
    error_log("Converting group units for row $rowIndex, product $product_id");
    
    if (!isset($postData['openingQty']) || !is_array($postData['openingQty'])) {
        error_log("openingQty not found or not an array");
        return 0;
    }
    
    error_log("openingQty structure: " . print_r($postData['openingQty'], true));
    
    foreach ($postData['openingQty'] as $unit_id => $quantities) {
        error_log("Processing unit_id: $unit_id, quantities: " . print_r($quantities, true));
        
        if (is_array($quantities) && isset($quantities[$rowIndex]) && !empty($quantities[$rowIndex])) {
            $qty = floatval($quantities[$rowIndex]);
            error_log("Found qty for unit $unit_id: $qty");
            
            $stmt = $pdo->prepare("SELECT unit_scope, is_base_unit, base_unit_id, conversion_factor FROM uom WHERE id = ?");
            $stmt->execute([$unit_id]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($unit) {
                if ($unit['is_base_unit'] == 1) {
                    error_log("Unit $unit_id is base unit, adding $qty directly");
                    $totalQty += $qty;
                } elseif ($unit['unit_scope'] === 'per_product') {
                    $convStmt = $pdo->prepare("SELECT conversion_factor FROM product_uom_conversions WHERE product_id = ? AND uom_id = ?");
                    $convStmt->execute([$product_id, $unit_id]);
                    $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($conv && !empty($conv['conversion_factor'])) {
                        $converted = $qty * $conv['conversion_factor'];
                        error_log("Unit $unit_id per_product: $qty * {$conv['conversion_factor']} = $converted");
                        $totalQty += $converted;
                    } else {
                        error_log("Unit $unit_id per_product but no conversion found");
                        $totalQty += $qty;
                    }
                } elseif ($unit['unit_scope'] === 'universal' && !empty($unit['conversion_factor'])) {
                    $converted = $qty * $unit['conversion_factor'];
                    error_log("Unit $unit_id universal: $qty * {$unit['conversion_factor']} = $converted");
                    $totalQty += $converted;
                } else {
                    $totalQty += $qty;
                }
            }
        }
    }
    
    error_log("Total qty in base units: $totalQty");
    return $totalQty;
}

function insertGroupStockLedgerEntries($postData, $rowIndex, $product_id, $stockOpeningId, $branchId, $inventory_account, $tenant_id, $pricePerBaseUnit, $pdo) {
    error_log("Inserting separate stock ledger entries for row $rowIndex");
    
    $stockLedgerStmt = $pdo->prepare("
        INSERT INTO stock_ledger (tenant_id, account_id, branch_id, product_id, reference_table, reference_id, qty_in, unit_cost, unit_id, transaction_type, transaction_date)
        VALUES (?, ?, ?, ?, 'products', ?, ?, ?, ?, 'Opening Stock', CURDATE())
    ");
    
    foreach ($postData['openingQty'] as $unit_id => $quantities) {
        if (is_array($quantities) && isset($quantities[$rowIndex]) && !empty($quantities[$rowIndex])) {
            $qty = floatval($quantities[$rowIndex]);
            
            // Get conversion factor
            $conversionFactor = getConversionFactor($unit_id, $product_id, $pdo);
            
            // Calculate value: qty * conversion_factor * price_per_base_unit
            $qtyInBaseUnits = $qty * $conversionFactor;
            $value = $qtyInBaseUnits * $pricePerBaseUnit;
            
            error_log("Inserting ledger: Unit=$unit_id, Qty=$qty, ConvFactor=$conversionFactor, BaseQty=$qtyInBaseUnits, Price=$pricePerBaseUnit, Value=$value");
            
            $stockLedgerStmt->execute([
                $tenant_id,
                $inventory_account,
                $branchId,
                $product_id,
                $stockOpeningId,
                $qty,  // Store original quantity
                $pricePerBaseUnit,  // Store price per base unit
                $unit_id  // Store the actual unit used
            ]);
        }
    }
    
    error_log("All stock ledger entries inserted successfully");
}

function getConversionFactor($unit_id, $product_id, $pdo) {
    // Get unit info
    $stmt = $pdo->prepare("SELECT unit_scope, is_base_unit, conversion_factor FROM uom WHERE id = ?");
    $stmt->execute([$unit_id]);
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$unit) return 1;
    
    // Base unit has conversion factor of 1
    if ($unit['is_base_unit'] == 1) {
        return 1;
    }
    
    // Per-product unit: get from product_uom_conversions
    if ($unit['unit_scope'] === 'per_product') {
        $convStmt = $pdo->prepare("SELECT conversion_factor FROM product_uom_conversions WHERE product_id = ? AND uom_id = ?");
        $convStmt->execute([$product_id, $unit_id]);
        $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conv && !empty($conv['conversion_factor'])) {
            return floatval($conv['conversion_factor']);
        }
    }
    
    // Universal unit: use conversion_factor from uom table
    if ($unit['unit_scope'] === 'universal' && !empty($unit['conversion_factor'])) {
        return floatval($unit['conversion_factor']);
    }
    
    return 1;
}