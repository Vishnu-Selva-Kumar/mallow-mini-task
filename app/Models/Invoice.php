<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'invoice_date',
        'base_amount',
        'overage_amount',
        'total_amount',
        'units_used',
        'status',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'base_amount' => 'decimal:2',
        'overage_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'units_used' => 'integer',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
