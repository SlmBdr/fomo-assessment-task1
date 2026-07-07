<?php

namespace App\Controllers;

use App\Database;
use PDO;
use Exception;

class ProductController {
    /**
     * GET /products - List all products
     */
    public function index() {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT id, name, price, inventory FROM products ORDER BY id ASC");
        $products = $stmt->fetchAll();

        header('Content-Type: application/json');
        echo json_encode($products);
    }

    /**
     * GET /products/{id} - Get a specific product
     */
    public function show(int $id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, name, price, inventory FROM products WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();

        header('Content-Type: application/json');
        if (!$product) {
            http_response_code(404);
            echo json_encode(['error' => "Product with ID {$id} not found"]);
            return;
        }

        echo json_encode($product);
    }

    /**
     * POST /products - Create a new product
     */
    public function create() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        $name = trim($input['name'] ?? '');
        $price = $input['price'] ?? null;
        $inventory = $input['inventory'] ?? null;

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['error' => "Name is required"]);
            return;
        }

        if ($price === null || !is_numeric($price) || $price < 0) {
            http_response_code(400);
            echo json_encode(['error' => "Price must be a non-negative number"]);
            return;
        }

        if ($inventory === null || !is_int($inventory) || $inventory < 0) {
            http_response_code(400);
            echo json_encode(['error' => "Inventory must be a non-negative integer"]);
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO products (name, price, inventory) VALUES (:name, :price, :inventory) RETURNING id");
        $stmt->execute([
            'name' => $name,
            'price' => $price,
            'inventory' => $inventory
        ]);
        $result = $stmt->fetch();

        http_response_code(201);
        echo json_encode([
            'message' => 'Product created successfully',
            'product' => [
                'id' => $result['id'],
                'name' => $name,
                'price' => (float)$price,
                'inventory' => (int)$inventory
            ]
        ]);
    }

    /**
     * PUT /products/{id} - Update a product
     */
    public function update(int $id) {
        header('Content-Type: application/json');
        $db = Database::getConnection();
        
        // Check if product exists
        $stmt = $db->prepare("SELECT id FROM products WHERE id = :id");
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => "Product with ID {$id} not found"]);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => "Invalid request body"]);
            return;
        }

        // Build update statement dynamically based on provided fields
        $fields = [];
        $params = ['id' => $id];

        if (isset($input['name'])) {
            $name = trim($input['name']);
            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['error' => "Name cannot be empty"]);
                return;
            }
            $fields[] = "name = :name";
            $params['name'] = $name;
        }

        if (isset($input['price'])) {
            $price = $input['price'];
            if (!is_numeric($price) || $price < 0) {
                http_response_code(400);
                echo json_encode(['error' => "Price must be a non-negative number"]);
                return;
            }
            $fields[] = "price = :price";
            $params['price'] = $price;
        }

        if (isset($input['inventory'])) {
            $inventory = $input['inventory'];
            if (!is_int($inventory) || $inventory < 0) {
                http_response_code(400);
                echo json_encode(['error' => "Inventory must be a non-negative integer"]);
                return;
            }
            $fields[] = "inventory = :inventory";
            $params['inventory'] = $inventory;
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => "No fields to update"]);
            return;
        }

        $sql = "UPDATE products SET " . implode(", ", $fields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        // Fetch updated product
        $stmt = $db->prepare("SELECT id, name, price, inventory FROM products WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $updatedProduct = $stmt->fetch();

        echo json_encode([
            'message' => 'Product updated successfully',
            'product' => $updatedProduct
        ]);
    }

    /**
     * DELETE /products/{id} - Delete a product
     */
    public function delete(int $id) {
        header('Content-Type: application/json');
        $db = Database::getConnection();

        // Check if product exists
        $stmt = $db->prepare("SELECT id FROM products WHERE id = :id");
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => "Product with ID {$id} not found"]);
            return;
        }

        $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute(['id' => $id]);

        echo json_encode(['message' => "Product with ID {$id} deleted successfully"]);
    }
}
