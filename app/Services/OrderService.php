<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderService {
    public function placeOrder(string $customerName, array $items): array {
        if (empty($customerName)) {
            throw new Exception("Customer name is required", 400);
        }

        if (empty($items)) {
            throw new Exception("An order must consist of at least one order item", 400);
        }

        return DB::transaction(function() use ($customerName, $items) {
            $processedItems = [];
            $totalAmount = 0.0;

            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $quantity = $item['quantity'] ?? null;

                if (!$productId || !$quantity || $quantity <= 0) {
                    throw new Exception("Invalid product ID or quantity in order items", 400);
                }

                $product = Product::lockForUpdate()->find($productId);

                if (!$product) {
                    throw new Exception("Product with ID {$productId} not found", 404);
                }

                $availableStock = $product->inventory;
                if ($availableStock < $quantity) {
                    throw new Exception("Insufficient stock for product '{$product->name}'. Requested: {$quantity}, Available: {$availableStock}", 422);
                }

                $price = $product->price;
                $processedItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'price' => $price,
                    'new_inventory' => $availableStock - $quantity
                ];

                $totalAmount += $price * $quantity;
            }

            $order = Order::create([
                'customer_name' => $customerName
            ]);
            
            $order->refresh();

            $itemsResponse = [];
            foreach ($processedItems as $pItem) {
                $product = $pItem['product'];
                $quantity = $pItem['quantity'];
                $price = $pItem['price'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price
                ]);

                $product->inventory = $pItem['new_inventory'];
                $product->save();

                $itemsResponse[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $quantity,
                    'price' => $price
                ];
            }

            return [
                'id' => $order->id,
                'customer_name' => $order->customer_name,
                'created_at' => $order->created_at ?? date('c'),
                'items' => $itemsResponse,
                'total_amount' => $totalAmount
            ];
        });
    }
}
