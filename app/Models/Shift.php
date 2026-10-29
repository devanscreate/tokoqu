<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'register_id',
        'cashier_id',
        'opened_at',
        'opening_cash',
        'closed_at',
        'counted_cash',
        'expected_cash',
        'difference',
        'status',
        'close_note',
        'forced_by',
        'force_reason',
        'late_adjusted',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'integer',
            'counted_cash' => 'integer',
            'expected_cash' => 'integer',
            'difference' => 'integer',
            'late_adjusted' => 'boolean',
        ];
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function forcedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forced_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }
}

