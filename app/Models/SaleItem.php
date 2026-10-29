<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'product_id',
        'name',
        'sku',
        'qty',
        'unit_price',
        'unit_cost',
        'line_discount',
        'order_discount_alloc',
        'net_total',
        'returned_qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'unit_price' => 'integer',
            'unit_cost' => 'integer',
            'line_discount' => 'integer',
            'order_discount_alloc' => 'integer',
            'net_total' => 'integer',
            'returned_qty' => 'float',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

