<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Exception;

class OrderController extends Controller {
    private OrderService $orderService;

    public function __construct() {
        $this->orderService = new OrderService();
    }

    public function index() {
        $orders = Order::with('items.product')->orderBy('id', 'desc')->get();
        
        $formattedOrders = $orders->map(function($order) {
            $totalAmount = 0.0;
            $items = $order->items->map(function($item) use (&$totalAmount) {
                $itemTotal = (float)$item->price * (int)$item->quantity;
                $totalAmount += $itemTotal;
                return [
                    'id' => (int)$item->id,
                    'product_id' => (int)$item->product_id,
                    'product_name' => $item->product->name ?? 'Unknown',
                    'quantity' => (int)$item->quantity,
                    'price' => (float)$item->price
                ];
            });

            return [
                'id' => (int)$order->id,
                'customer_name' => $order->customer_name,
                'created_at' => $order->created_at ?? null,
                'items' => $items,
                'total_amount' => $totalAmount
            ];
        });

        return response()->json($formattedOrders);
    }

    public function show($id) {
        $order = Order::with('items.product')->find($id);
        if (!$order) {
            return response()->json(['error' => "Order with ID {$id} not found"], 404);
        }

        $totalAmount = 0.0;
        $items = $order->items->map(function($item) use (&$totalAmount) {
            $itemTotal = (float)$item->price * (int)$item->quantity;
            $totalAmount += $itemTotal;
            return [
                'id' => (int)$item->id,
                'product_id' => (int)$item->product_id,
                'product_name' => $item->product->name ?? 'Unknown',
                'quantity' => (int)$item->quantity,
                'price' => (float)$item->price
            ];
        });

        return response()->json([
            'id' => (int)$order->id,
            'customer_name' => $order->customer_name,
            'created_at' => $order->created_at ?? null,
            'items' => $items,
            'total_amount' => $totalAmount
        ]);
    }

    public function create(Request $request) {
        try {
            $customerName = trim($request->input('customer_name', ''));
            $items = $request->input('items', []);

            $order = $this->orderService->placeOrder($customerName, $items);

            return response()->json([
                'message' => 'Order placed successfully',
                'order' => $order
            ], 201);
        } catch (Exception $e) {
            $code = $e->getCode();
            if ($code < 400 || $code > 500) {
                $code = 400;
            }
            return response()->json(['error' => $e->getMessage()], $code);
        }
    }

    public function update(Request $request, $id) {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['error' => "Order with ID {$id} not found"], 404);
        }

        $customerName = trim($request->input('customer_name', ''));
        if (empty($customerName)) {
            return response()->json(['error' => "Customer name is required"], 400);
        }

        $order->customer_name = $customerName;
        $order->save();

        return $this->show($id);
    }

    public function delete($id) {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['error' => "Order with ID {$id} not found"], 404);
        }

        $order->delete();

        return response()->json(['message' => "Order with ID {$id} and its items deleted successfully"]);
    }
}
