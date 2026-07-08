<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderItemController extends Controller {
    public function index() {
        $items = OrderItem::with('product')->orderBy('id', 'desc')->get();
        $formatted = $items->map(function($item) {
            return [
                'id' => (int)$item->id,
                'order_id' => (int)$item->order_id,
                'product_id' => (int)$item->product_id,
                'product_name' => $item->product->name ?? 'Unknown',
                'quantity' => (int)$item->quantity,
                'price' => (float)$item->price
            ];
        });
        return response()->json($formatted);
    }

    public function show($id) {
        $item = OrderItem::with('product')->find($id);
        if (!$item) {
            return response()->json(['error' => "Order Item with ID {$id} not found"], 404);
        }
        return response()->json([
            'id' => (int)$item->id,
            'order_id' => (int)$item->order_id,
            'product_id' => (int)$item->product_id,
            'product_name' => $item->product->name ?? 'Unknown',
            'quantity' => (int)$item->quantity,
            'price' => (float)$item->price
        ]);
    }

    public function create(Request $request) {
        $orderId = $request->input('order_id');
        $productId = $request->input('product_id');
        $quantity = $request->input('quantity');

        if (!$orderId || !$productId || !$quantity || $quantity <= 0) {
            return response()->json(['error' => "order_id, product_id, and positive quantity are required"], 400);
        }

        try {
            $result = DB::transaction(function() use ($orderId, $productId, $quantity) {
                $order = Order::find($orderId);
                if (!$order) {
                    throw new Exception("Order with ID {$orderId} not found", 404);
                }

                $product = Product::lockForUpdate()->find($productId);
                if (!$product) {
                    throw new Exception("Product with ID {$productId} not found", 404);
                }

                $availableStock = (int)$product->inventory;
                if ($availableStock < $quantity) {
                    throw new Exception("Insufficient stock for product '{$product->name}'. Requested: {$quantity}, Available: {$availableStock}", 422);
                }

                $price = (float)$product->price;

                $orderItem = OrderItem::create([
                    'order_id' => (int)$orderId,
                    'product_id' => (int)$productId,
                    'quantity' => (int)$quantity,
                    'price' => $price
                ]);

                $product->inventory = $availableStock - $quantity;
                $product->save();

                return [
                    'id' => (int)$orderItem->id,
                    'order_id' => (int)$orderId,
                    'product_id' => (int)$productId,
                    'product_name' => $product->name,
                    'quantity' => (int)$quantity,
                    'price' => $price
                ];
            });

            return response()->json([
                'message' => 'Item added to order successfully',
                'order_item' => $result
            ], 201);

        } catch (Exception $e) {
            $code = $e->getCode();
            if ($code < 400 || $code > 500) {
                $code = 500;
            }
            return response()->json(['error' => $e->getMessage()], $code);
        }
    }

    public function update(Request $request, $id) {
        $newQuantity = $request->input('quantity');

        if ($newQuantity === null || !is_int($newQuantity) || $newQuantity <= 0) {
            return response()->json(['error' => "Positive integer quantity is required"], 400);
        }

        try {
            DB::transaction(function() use ($id, $newQuantity) {
                $item = OrderItem::find($id);
                if (!$item) {
                    throw new Exception("Order Item with ID {$id} not found", 404);
                }

                $productId = $item->product_id;
                $oldQuantity = (int)$item->quantity;
                $quantityDiff = $newQuantity - $oldQuantity;

                $product = Product::lockForUpdate()->find($productId);
                if (!$product) {
                    throw new Exception("Product with ID {$productId} not found", 404);
                }

                $availableStock = (int)$product->inventory;

                if ($quantityDiff > 0 && $availableStock < $quantityDiff) {
                    throw new Exception("Insufficient stock for product '{$product->name}'. Needed additional: {$quantityDiff}, Available: {$availableStock}", 422);
                }

                $item->quantity = $newQuantity;
                $item->save();

                $product->inventory = $availableStock - $quantityDiff;
                $product->save();
            });

            return $this->show($id);

        } catch (Exception $e) {
            $code = $e->getCode();
            if ($code < 400 || $code > 500) {
                $code = 500;
            }
            return response()->json(['error' => $e->getMessage()], $code);
        }
    }

    public function delete($id) {
        try {
            DB::transaction(function() use ($id) {
                $item = OrderItem::find($id);
                if (!$item) {
                    throw new Exception("Order Item with ID {$id} not found", 404);
                }

                $productId = $item->product_id;
                $quantity = (int)$item->quantity;

                $product = Product::lockForUpdate()->find($productId);
                if ($product) {
                    $product->inventory = $product->inventory + $quantity;
                    $product->save();
                }

                $item->delete();
            });

            return response()->json(['message' => "Order item with ID {$id} removed successfully, and stock restored"]);
        } catch (Exception $e) {
            $code = $e->getCode();
            if ($code < 400 || $code > 500) {
                $code = 500;
            }
            return response()->json(['error' => $e->getMessage()], $code);
        }
    }
}
