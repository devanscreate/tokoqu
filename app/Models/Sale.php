<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'client_uuid',
        'invoice_no',
        'shift_id',
        'register_id',
        'cashier_id',
        'status',
        'subtotal',
        'discount_total',
        'order_discount_amount',
        'tax_mode',
        'tax_rate',
        'tax_total',
        'grand_total',
        'is_offline',
        'stock_conflict',
        'voided_at',
        'voided_by',
        'void_reason',
        'approved_by',
        'created_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'order_discount_amount' => 'integer',
            'tax_rate' => 'float',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'is_offline' => 'boolean',
            'stock_conflict' => 'boolean',
            'voided_at' => 'datetime',
            'created_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}

