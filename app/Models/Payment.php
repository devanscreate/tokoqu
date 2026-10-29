<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'method',
        'amount',
        'tendered',
        'change_amount',
        'reference',
        'needs_manual_reversal',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'tendered' => 'integer',
            'change_amount' => 'integer',
            'needs_manual_reversal' => 'boolean',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}

