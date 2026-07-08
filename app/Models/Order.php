<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model {
    public $timestamps = false;
    protected $fillable = ['customer_name'];

    public function items() {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
}
