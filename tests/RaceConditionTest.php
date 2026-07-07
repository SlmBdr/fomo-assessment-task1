<?php

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'App\\';
        $base_dir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
}

use App\Database;

echo "=== Concurrency & Race Condition Test ===\n";

try {
    Database::loadEnv();
    $db = Database::getConnection();
} catch (Exception $e) {
    echo "[ERROR] Database connection failed: " . $e->getMessage() . "\n";
    echo "Make sure you have configured DATABASE_URL in .env and your database is running.\n";
    exit(1);
}

echo "Initializing test product with limited stock...\n";
try {
    $db->exec("TRUNCATE order_items, orders, products RESTART IDENTITY CASCADE");

    $stmt = $db->prepare("INSERT INTO products (name, price, inventory) VALUES (:name, :price, :inventory) RETURNING id");
    $stmt->execute([
        'name' => 'Flash Sale Phone',
        'price' => 99.99,
        'inventory' => 5
    ]);
    $product = $stmt->fetch();
    $productId = $product['id'];
    echo "Created Flash Sale Product with ID: {$productId}, Stock: 5\n\n";
} catch (Exception $e) {
    echo "[ERROR] Failed to seed test database: " . $e->getMessage() . "\n";
    exit(1);
}

$apiUrl = "http://localhost:8000/orders";
$numRequests = 20;
$mh = curl_multi_init();
$handles = [];

echo "Preparing {$numRequests} concurrent order requests...\n";
for ($i = 1; $i <= $numRequests; $i++) {
    $ch = curl_init();
    
    $payload = json_encode([
        'customer_name' => "Concurrent Buyer #{$i}",
        'items' => [
            ['product_id' => $productId, 'quantity' => 1]
        ]
    ]);
    
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}

echo "Sending requests concurrently (Flash sale starts!)...\n";
$running = null;
do {
    curl_multi_exec($mh, $running);
} while ($running > 0);

$successCount = 0;
$failureCount = 0;
$statusCodes = [];

foreach ($handles as $ch) {
    $response = curl_multi_getcontent($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $statusCodes[$httpCode] = ($statusCodes[$httpCode] ?? 0) + 1;
    
    if ($httpCode === 201) {
        $successCount++;
    } else {
        $failureCount++;
    }
    
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);

echo "\n--- HTTP Response Summary ---\n";
foreach ($statusCodes as $code => $count) {
    echo "HTTP {$code}: {$count} times\n";
}
echo "Total Successes (HTTP 201): {$successCount}\n";
echo "Total Failures (Others): {$failureCount}\n";

echo "\n--- Database Verification ---\n";
$stmt = $db->prepare("SELECT inventory FROM products WHERE id = :id");
$stmt->execute(['id' => $productId]);
$finalInventory = (int)$stmt->fetchColumn();

$totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalOrderItems = (int)$db->query("SELECT COUNT(*) FROM order_items WHERE product_id = {$productId}")->fetchColumn();

echo "Final Product Inventory: {$finalInventory}\n";
echo "Total Orders Created: {$totalOrders}\n";
echo "Total Order Items Created: {$totalOrderItems}\n";

$testPassed = true;

if ($finalInventory !== 0) {
    echo "[FAIL] Inventory was not fully depleted. Expected 0, got {$finalInventory}\n";
    $testPassed = false;
}

if ($successCount !== 5) {
    echo "[FAIL] Successful API responses ({$successCount}) does not match initial stock (5)\n";
    $testPassed = false;
}

if ($totalOrders !== 5) {
    echo "[FAIL] Total orders created in database ({$totalOrders}) does not match expected count (5)\n";
    $testPassed = false;
}

if ($totalOrderItems !== 5) {
    echo "[FAIL] Total order items created in database ({$totalOrderItems}) does not match expected count (5)\n";
    $testPassed = false;
}

if ($finalInventory < 0) {
    echo "[FAIL] CRITICAL: Negative inventory occurred! Final stock: {$finalInventory}\n";
    $testPassed = false;
}

if ($testPassed) {
    echo "\n[PASS] Race condition handled successfully! Row locking prevented double-buying and negative inventory.\n";
} else {
    echo "\n[FAIL] Race condition test failed. See error details above.\n";
    exit(1);
}
