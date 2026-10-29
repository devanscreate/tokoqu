<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'type',
        'qty_delta',
        'ref_type',
        'ref_id',
        'user_id',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_delta' => 'float',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Stock movements are append-only and cannot be updated (R3/R9).');
        });

        static::deleting(function () {
            throw new LogicException('Stock movements are append-only and cannot be deleted (R3/R9).');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

