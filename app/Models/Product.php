<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'image_url',
        'category_id',
        'price',
        'cost',
        'track_stock',
        'allow_decimal_qty',
        'stock_qty',
        'min_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'cost' => 'integer',
            'track_stock' => 'boolean',
            'allow_decimal_qty' => 'boolean',
            'stock_qty' => 'float',
            'min_stock' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}

