<?php

namespace App\Controllers;

use App\Database;
use PDO;
use Exception;

class OrderItemController {
    public function index() {
        header('Content-Type: application/json');
        try {
            $db = Database::getConnection();
            $stmt = $db->query("
                SELECT oi.id, oi.order_id, oi.product_id, p.name as product_name, oi.quantity, oi.price 
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                ORDER BY oi.id DESC
            ");
            $items = $stmt->fetchAll();
            
            foreach ($items as &$item) {
                $item['id'] = (int)$item['id'];
                $item['order_id'] = (int)$item['order_id'];
                $item['product_id'] = (int)$item['product_id'];
                $item['quantity'] = (int)$item['quantity'];
                $item['price'] = (float)$item['price'];
            }
            
            echo json_encode($items);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function show(int $id) {
        header('Content-Type: application/json');
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT oi.id, oi.order_id, oi.product_id, p.name as product_name, oi.quantity, oi.price 
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $item = $stmt->fetch();

            if (!$item) {
                http_response_code(404);
                echo json_encode(['error' => "Order Item with ID {$id} not found"]);
                return;
            }

            $item['id'] = (int)$item['id'];
            $item['order_id'] = (int)$item['order_id'];
            $item['product_id'] = (int)$item['product_id'];
            $item['quantity'] = (int)$item['quantity'];
            $item['price'] = (float)$item['price'];

            echo json_encode($item);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function create() {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $orderId = $input['order_id'] ?? null;
            $productId = $input['product_id'] ?? null;
            $quantity = $input['quantity'] ?? null;

            if (!$orderId || !$productId || !$quantity || $quantity <= 0) {
                http_response_code(400);
                echo json_encode(['error' => "order_id, product_id, and positive quantity are required"]);
                return;
            }

            $db = Database::getConnection();
            $db->beginTransaction();

            $orderStmt = $db->prepare("SELECT id FROM orders WHERE id = :id");
            $orderStmt->execute(['id' => $orderId]);
            if (!$orderStmt->fetch()) {
                $db->rollBack();
                http_response_code(404);
                echo json_encode(['error' => "Order with ID {$orderId} not found"]);
                return;
            }

            $productStmt = $db->prepare("SELECT id, name, price, inventory FROM products WHERE id = :id FOR UPDATE");
            $productStmt->execute(['id' => $productId]);
            $product = $productStmt->fetch();

            if (!$product) {
                $db->rollBack();
                http_response_code(404);
                echo json_encode(['error' => "Product with ID {$productId} not found"]);
                return;
            }

            $availableStock = (int)$product['inventory'];
            if ($availableStock < $quantity) {
                $db->rollBack();
                http_response_code(422);
                echo json_encode(['error' => "Insufficient stock for product '{$product['name']}'. Requested: {$quantity}, Available: {$availableStock}"]);
                return;
            }

            $price = (float)$product['price'];

            $insertStmt = $db->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price) 
                VALUES (:order_id, :product_id, :quantity, :price) 
                RETURNING id
            ");
            $insertStmt->execute([
                'order_id' => $orderId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price
            ]);
            $newItem = $insertStmt->fetch();

            $updateProductStmt = $db->prepare("UPDATE products SET inventory = :inventory WHERE id = :id");
            $updateProductStmt->execute([
                'inventory' => $availableStock - $quantity,
                'id' => $productId
            ]);

            $db->commit();

            http_response_code(201);
            echo json_encode([
                'message' => 'Item added to order successfully',
                'order_item' => [
                    'id' => (int)$newItem['id'],
                    'order_id' => (int)$orderId,
                    'product_id' => (int)$productId,
                    'product_name' => $product['name'],
                    'quantity' => (int)$quantity,
                    'price' => $price
                ]
            ]);

        } catch (Exception $e) {
            $db = Database::getConnection();
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function update(int $id) {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $newQuantity = $input['quantity'] ?? null;

            if ($newQuantity === null || !is_int($newQuantity) || $newQuantity <= 0) {
                http_response_code(400);
                echo json_encode(['error' => "Positive integer quantity is required"]);
                return;
            }

            $db = Database::getConnection();
            $db->beginTransaction();

            $itemStmt = $db->prepare("SELECT order_id, product_id, quantity FROM order_items WHERE id = :id");
            $itemStmt->execute(['id' => $id]);
            $item = $itemStmt->fetch();

            if (!$item) {
                $db->rollBack();
                http_response_code(404);
                echo json_encode(['error' => "Order Item with ID {$id} not found"]);
                return;
            }

            $productId = $item['product_id'];
            $oldQuantity = (int)$item['quantity'];
            $quantityDiff = $newQuantity - $oldQuantity;

            $productStmt = $db->prepare("SELECT id, name, inventory FROM products WHERE id = :id FOR UPDATE");
            $productStmt->execute(['id' => $productId]);
            $product = $productStmt->fetch();

            if (!$product) {
                $db->rollBack();
                http_response_code(404);
                echo json_encode(['error' => "Product with ID {$productId} not found"]);
                return;
            }

            $availableStock = (int)$product['inventory'];

            if ($quantityDiff > 0 && $availableStock < $quantityDiff) {
                $db->rollBack();
                http_response_code(422);
                echo json_encode(['error' => "Insufficient stock for product '{$product['name']}'. Needed additional: {$quantityDiff}, Available: {$availableStock}"]);
                return;
            }

            $updateItemStmt = $db->prepare("UPDATE order_items SET quantity = :quantity WHERE id = :id");
            $updateItemStmt->execute([
                'quantity' => $newQuantity,
                'id' => $id
            ]);

            $updateProductStmt = $db->prepare("UPDATE products SET inventory = :inventory WHERE id = :id");
            $updateProductStmt->execute([
                'inventory' => $availableStock - $quantityDiff,
                'id' => $productId
            ]);

            $db->commit();

            $this->show($id);

        } catch (Exception $e) {
            $db = Database::getConnection();
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function delete(int $id) {
        header('Content-Type: application/json');
        try {
            $db = Database::getConnection();
            $db->beginTransaction();

            $itemStmt = $db->prepare("SELECT order_id, product_id, quantity FROM order_items WHERE id = :id");
            $itemStmt->execute(['id' => $id]);
            $item = $itemStmt->fetch();

            if (!$item) {
                $db->rollBack();
                http_response_code(404);
                echo json_encode(['error' => "Order Item with ID {$id} not found"]);
                return;
            }

            $productId = $item['product_id'];
            $quantity = (int)$item['quantity'];

            $productStmt = $db->prepare("SELECT id, inventory FROM products WHERE id = :id FOR UPDATE");
            $productStmt->execute(['id' => $productId]);
            $product = $productStmt->fetch();

            if ($product) {
                $availableStock = (int)$product['inventory'];
                $updateProductStmt = $db->prepare("UPDATE products SET inventory = :inventory WHERE id = :id");
                $updateProductStmt->execute([
                    'inventory' => $availableStock + $quantity,
                    'id' => $productId
                ]);
            }

            $deleteStmt = $db->prepare("DELETE FROM order_items WHERE id = :id");
            $deleteStmt->execute(['id' => $id]);

            $db->commit();

            echo json_encode(['message' => "Order item with ID {$id} removed successfully, and stock restored"]);
        } catch (Exception $e) {
            $db = Database::getConnection();
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
