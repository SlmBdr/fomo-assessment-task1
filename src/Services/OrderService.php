<?php

namespace App\Services;

use App\Database;
use PDO;
use Exception;

class OrderService {
    /**
     * Places an order and updates inventory using database transactions with row-level locking.
     *
     * @param string $customerName
     * @param array $items Array of items: [['product_id' => int, 'quantity' => int], ...]
     * @return array The created order details
     * @throws Exception
     */
    public function placeOrder(string $customerName, array $items): array {
        if (empty($customerName)) {
            throw new Exception("Customer name is required", 400);
        }

        if (empty($items)) {
            throw new Exception("An order must consist of at least one order item", 400);
        }

        $db = Database::getConnection();
        
        // Start transaction
        $db->beginTransaction();

        try {
            $processedItems = [];
            $totalAmount = 0.0;

            // 1. Process items and lock inventory rows
            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $quantity = $item['quantity'] ?? null;

                if (!$productId || !$quantity || $quantity <= 0) {
                    throw new Exception("Invalid product ID or quantity in order items", 400);
                }

                // Pessimistic Locking: FOR UPDATE prevents other transactions from modifying
                // or selecting this row for update until this transaction commits/rolls back.
                $stmt = $db->prepare("SELECT id, name, price, inventory FROM products WHERE id = :id FOR UPDATE");
                $stmt->execute(['id' => $productId]);
                $product = $stmt->fetch();

                if (!$product) {
                    throw new Exception("Product with ID {$productId} not found", 404);
                }

                $availableStock = (int)$product['inventory'];
                if ($availableStock < $quantity) {
                    throw new Exception("Insufficient stock for product '{$product['name']}'. Requested: {$quantity}, Available: {$availableStock}", 422);
                }

                $price = (float)$product['price'];
                $processedItems[] = [
                    'product_id' => $productId,
                    'name' => $product['name'],
                    'quantity' => $quantity,
                    'price' => $price,
                    'new_inventory' => $availableStock - $quantity
                ];

                $totalAmount += $price * $quantity;
            }

            // 2. Insert Order
            $stmt = $db->prepare("INSERT INTO orders (customer_name) VALUES (:customer_name) RETURNING id, created_at");
            $stmt->execute(['customer_name' => $customerName]);
            $order = $stmt->fetch();
            $orderId = $order['id'];
            $createdAt = $order['created_at'];

            // 3. Insert Order Items and Update Product Inventory
            $insertItemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (:order_id, :product_id, :quantity, :price)");
            $updateStockStmt = $db->prepare("UPDATE products SET inventory = :inventory WHERE id = :id");

            $itemsResponse = [];
            foreach ($processedItems as $item) {
                $insertItemStmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price']
                ]);

                $updateStockStmt->execute([
                    'inventory' => $item['new_inventory'],
                    'id' => $item['product_id']
                ]);

                $itemsResponse[] = [
                    'product_id' => $item['product_id'],
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price']
                ];
            }

            // Commit transaction
            $db->commit();

            return [
                'id' => $orderId,
                'customer_name' => $customerName,
                'created_at' => $createdAt,
                'items' => $itemsResponse,
                'total_amount' => $totalAmount
            ];

        } catch (Exception $e) {
            // Rollback transaction on failure
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
