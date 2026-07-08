<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller {
    public function index() {
        return response()->json(Product::orderBy('id', 'asc')->get());
    }

    public function show($id) {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['error' => "Product with ID {$id} not found"], 404);
        }
        return response()->json($product);
    }

    public function create(Request $request) {
        $name = trim($request->input('name', ''));
        $price = $request->input('price');
        $inventory = $request->input('inventory');

        if (empty($name)) {
            return response()->json(['error' => "Name is required"], 400);
        }

        if ($price === null || !is_numeric($price) || $price < 0) {
            return response()->json(['error' => "Price must be a non-negative number"], 400);
        }

        if ($inventory === null || !is_int($inventory) || $inventory < 0) {
            return response()->json(['error' => "Inventory must be a non-negative integer"], 400);
        }

        $product = Product::create([
            'name' => $name,
            'price' => (float)$price,
            'inventory' => (int)$inventory
        ]);

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product
        ], 201);
    }

    public function update(Request $request, $id) {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['error' => "Product with ID {$id} not found"], 404);
        }

        if ($request->has('name')) {
            $name = trim($request->input('name'));
            if (empty($name)) {
                return response()->json(['error' => "Name cannot be empty"], 400);
            }
            $product->name = $name;
        }

        if ($request->has('price')) {
            $price = $request->input('price');
            if (!is_numeric($price) || $price < 0) {
                return response()->json(['error' => "Price must be a non-negative number"], 400);
            }
            $product->price = (float)$price;
        }

        if ($request->has('inventory')) {
            $inventory = $request->input('inventory');
            if (!is_int($inventory) || $inventory < 0) {
                return response()->json(['error' => "Inventory must be a non-negative integer"], 400);
            }
            $product->inventory = (int)$inventory;
        }

        if (!$request->hasAny(['name', 'price', 'inventory'])) {
            return response()->json(['error' => "No fields to update"], 400);
        }

        $product->save();

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ]);
    }

    public function delete($id) {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['error' => "Product with ID {$id} not found"], 404);
        }
        $product->delete();
        return response()->json(['message' => "Product with ID {$id} deleted successfully"]);
    }
}
