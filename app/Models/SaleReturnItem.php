<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sale_return_id',
        'sale_item_id',
        'product_id',
        'qty',
        'refund_amount',
        'restock',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'refund_amount' => 'integer',
            'restock' => 'boolean',
        ];
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}

