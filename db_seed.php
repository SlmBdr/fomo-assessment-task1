<?php

// 1. Setup Autoloading
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Custom autoloader fallback
    spl_autoload_register(function ($class) {
        $prefix = 'App\\';
        $base_dir = __DIR__ . '/src/';
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

echo "=== FOMO Online Store DB Seeder ===\n";

try {
    // 2. Load Env
    Database::loadEnv();
    
    // 3. Connect to Database
    echo "Connecting to database...\n";
    $db = Database::getConnection();
    echo "Connected successfully!\n\n";
    
    // 4. Create Tables
    echo "Creating tables (dropping existing ones first)...\n";
    
    $schema = "
    -- Drop tables if they exist
    DROP TABLE IF EXISTS order_items CASCADE;
    DROP TABLE IF EXISTS orders CASCADE;
    DROP TABLE IF EXISTS products CASCADE;

    -- Create products table
    CREATE TABLE products (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        inventory INT NOT NULL DEFAULT 0,
        CONSTRAINT check_inventory_non_negative CHECK (inventory >= 0)
    );

    -- Create orders table
    CREATE TABLE orders (
        id SERIAL PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
    );

    -- Create order_items table
    CREATE TABLE order_items (
        id SERIAL PRIMARY KEY,
        order_id INT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
        product_id INT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
        quantity INT NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        CONSTRAINT check_quantity_positive CHECK (quantity > 0)
    );
    ";
    
    $db->exec($schema);
    echo "Tables created successfully.\n\n";
    
    // 5. Seed Products
    echo "Seeding initial products...\n";
    $products = [
        ['name' => 'Ultra Phone 15', 'price' => 999.99, 'inventory' => 10],
        ['name' => 'M4 Pro Laptop', 'price' => 1999.50, 'inventory' => 5],
        ['name' => 'Acoustic Wireless Headphones', 'price' => 149.00, 'inventory' => 50],
        ['name' => 'Super Flash Deal Item', 'price' => 1.99, 'inventory' => 5], // 5 stock for flash sale testing
    ];
    
    $stmt = $db->prepare("INSERT INTO products (name, price, inventory) VALUES (:name, :price, :inventory)");
    foreach ($products as $prod) {
        $stmt->execute($prod);
        echo " - Seeded product: {$prod['name']} (Price: \${$prod['price']}, Stock: {$prod['inventory']})\n";
    }
    
    echo "\nSeeding complete! You can now delete this file ('db_seed.php') if you wish.\n";
    echo "===================================\n";

} catch (Exception $e) {
    echo "\n[ERROR] Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
