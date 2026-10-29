<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'shift_id',
        'return_no',
        'refund_method',
        'refund_total',
        'reason',
        'user_id',
        'approved_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'refund_total' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

