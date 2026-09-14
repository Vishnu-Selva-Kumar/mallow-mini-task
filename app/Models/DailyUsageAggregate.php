<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyUsageAggregate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'usage_date',
        'total_usage',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'total_usage' => 'integer',
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
