<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;

Route::get('/', function() {
    return redirect('/docs');
});

Route::get('/docs', function() {
    return response()->file(public_path('docs.html'));
});

Route::get('/openapi.json', function() {
    return response()->file(public_path('openapi.json'));
});

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'create']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'delete']);

Route::get('/orders', [OrderController::class, 'index']);
Route::post('/orders', [OrderController::class, 'create']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
Route::put('/orders/{id}', [OrderController::class, 'update']);
Route::delete('/orders/{id}', [OrderController::class, 'delete']);

Route::get('/order-items', [OrderItemController::class, 'index']);
Route::post('/order-items', [OrderItemController::class, 'create']);
Route::get('/order-items/{id}', [OrderItemController::class, 'show']);
Route::put('/order-items/{id}', [OrderItemController::class, 'update']);
Route::delete('/order-items/{id}', [OrderItemController::class, 'delete']);
