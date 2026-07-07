<?php

// 1. Setup Autoloading
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Custom autoloader fallback if composer hasn't been run
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

// 2. Load Environment Variables
try {
    Database::loadEnv();
} catch (Exception $e) {
    // Fail silently if .env is missing or invalid, database connection will handle it
}

// 3. Simple Router Configuration
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Handle potential subdirectory path prefixes (like if running in a subdirectory of a web root)
// If it's running via `php -S`, $uri starts with /
$routes = [
    'GET' => [
        '#^/products$#' => ['App\Controllers\ProductController', 'index'],
        '#^/products/(\d+)$#' => ['App\Controllers\ProductController', 'show'],
        '#^/orders$#' => ['App\Controllers\OrderController', 'index'],
        '#^/orders/(\d+)$#' => ['App\Controllers\OrderController', 'show'],
        '#^/order-items$#' => ['App\Controllers\OrderItemController', 'index'],
        '#^/order-items/(\d+)$#' => ['App\Controllers\OrderItemController', 'show'],
    ],
    'POST' => [
        '#^/products$#' => ['App\Controllers\ProductController', 'create'],
        '#^/orders$#' => ['App\Controllers\OrderController', 'create'],
        '#^/order-items$#' => ['App\Controllers\OrderItemController', 'create'],
    ],
    'PUT' => [
        '#^/products/(\d+)$#' => ['App\Controllers\ProductController', 'update'],
        '#^/orders/(\d+)$#' => ['App\Controllers\OrderController', 'update'],
        '#^/order-items/(\d+)$#' => ['App\Controllers\OrderItemController', 'update'],
    ],
    'DELETE' => [
        '#^/products/(\d+)$#' => ['App\Controllers\ProductController', 'delete'],
        '#^/orders/(\d+)$#' => ['App\Controllers\OrderController', 'delete'],
        '#^/order-items/(\d+)$#' => ['App\Controllers\OrderItemController', 'delete'],
    ]
];

// Check if method exists in route definition
if (isset($routes[$method])) {
    foreach ($routes[$method] as $pattern => $handler) {
        if (preg_match($pattern, $uri, $matches)) {
            // Remove full match from array
            array_shift($matches);
            
            // Re-type integer captures
            $params = array_map(function($val) {
                return is_numeric($val) ? (int)$val : $val;
            }, $matches);

            $controllerName = $handler[0];
            $actionName = $handler[1];

            try {
                $controller = new $controllerName();
                call_user_func_array([$controller, $actionName], $params);
            } catch (Exception $e) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['error' => 'Server Error: ' . $e->getMessage()]);
            }
            exit;
        }
    }
}

// 4. Route Not Found
header('Content-Type: application/json');
http_response_code(404);
echo json_encode([
    'error' => 'Endpoint Not Found',
    'requested_method' => $method,
    'requested_uri' => $uri
]);
