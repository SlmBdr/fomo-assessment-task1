<?php

namespace App\Controllers;

use App\Database;
use App\Services\OrderService;
use PDO;
use Exception;

class OrderController {
    private OrderService $orderService;

    public function __construct() {
        $this->orderService = new OrderService();
    }

    public function index() {
        header('Content-Type: application/json');
        try {
            $db = Database::getConnection();
            
            $stmt = $db->query("SELECT id, customer_name, created_at FROM orders ORDER BY id DESC");
            $orders = $stmt->fetchAll();

            $stmtItems = $db->query("
                SELECT oi.id, oi.order_id, oi.product_id, p.name as product_name, oi.quantity, oi.price 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id
                ORDER BY oi.id ASC
            ");
            $allItems = $stmtItems->fetchAll();

            $itemsByOrder = [];
            foreach ($allItems as $item) {
                $orderId = $item['order_id'];
                unset($item['order_id']);
                $itemsByOrder[$orderId][] = [
                    'id' => $item['id'],
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => (int)$item['quantity'],
                    'price' => (float)$item['price']
                ];
            }

            foreach ($orders as &$order) {
                $orderId = $order['id'];
                $order['id'] = (int)$order['id'];
                $order['items'] = $itemsByOrder[$orderId] ?? [];
                
                $total = 0.0;
                foreach ($order['items'] as $item) {
                    $total += $item['price'] * $item['quantity'];
                }
                $order['total_amount'] = $total;
            }

            echo json_encode($orders);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function show(int $id) {
        header('Content-Type: application/json');
        try {
            $db = Database::getConnection();
            
            $stmt = $db->prepare("SELECT id, customer_name, created_at FROM orders WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $order = $stmt->fetch();

            if (!$order) {
                http_response_code(404);
                echo json_encode(['error' => "Order with ID {$id} not found"]);
                return;
            }

            $stmtItems = $db->prepare("
                SELECT oi.id, oi.product_id, p.name as product_name, oi.quantity, oi.price 
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = :order_id
                ORDER BY oi.id ASC
            ");
            $stmtItems->execute(['order_id' => $id]);
            $items = $stmtItems->fetchAll();

            $formattedItems = [];
            $totalAmount = 0.0;
            foreach ($items as $item) {
                $formattedItems[] = [
                    'id' => $item['id'],
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => (int)$item['quantity'],
                    'price' => (float)$item['price']
                ];
                $totalAmount += (float)$item['price'] * (int)$item['quantity'];
            }

            echo json_encode([
                'id' => (int)$order['id'],
                'customer_name' => $order['customer_name'],
                'created_at' => $order['created_at'],
                'items' => $formattedItems,
                'total_amount' => $totalAmount
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function create() {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $customerName = trim($input['customer_name'] ?? '');
            $items = $input['items'] ?? [];

            $order = $this->orderService->placeOrder($customerName, $items);

            http_response_code(201);
            echo json_encode([
                'message' => 'Order placed successfully',
                'order' => $order
            ]);

        } catch (Exception $e) {
            $code = $e->getCode();
            if ($code < 400 || $code > 500) {
                $code = 400;
            }
            http_response_code($code);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function update(int $id) {
        header('Content-Type: application/json');
        try {
            $db = Database::getConnection();

            $stmt = $db->prepare("SELECT id FROM orders WHERE id = :id");
            $stmt->execute(['id' => $id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => "Order with ID {$id} not found"]);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $customerName = trim($input['customer_name'] ?? '');

            if (empty($customerName)) {
                http_response_code(400);
                echo json_encode(['error' => "Customer name is required"]);
                return;
            }

            $stmtUpdate = $db->prepare("UPDATE orders SET customer_name = :customer_name WHERE id = :id");
            $stmtUpdate->execute([
                'customer_name' => $customerName,
                'id' => $id
            ]);

            $this->show($id);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function delete(int $id) {
        header('Content-Type: application/json');
        try {
            $db = Database::getConnection();

            $stmt = $db->prepare("SELECT id FROM orders WHERE id = :id");
            $stmt->execute(['id' => $id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => "Order with ID {$id} not found"]);
                return;
            }

            $stmtDelete = $db->prepare("DELETE FROM orders WHERE id = :id");
            $stmtDelete->execute(['id' => $id]);

            echo json_encode(['message' => "Order with ID {$id} and its items deleted successfully"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
