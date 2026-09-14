<?php

namespace App\Jobs;

use App\Models\DailyUsageAggregate;
use App\Models\UsageEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class AggregateDailyUsageJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?string $targetDate = null
    ) {
        $this->targetDate = $targetDate ?? now()->toDateString();
    }

    /**
     * Execute the job in memory-safe 5,000 row chunks using Eloquent UsageEvent model.
     */
    public function handle(): void
    {
        $date = $this->targetDate;

        UsageEvent::query()
            ->select('subscription_id', 'user_id', 'usage_date', DB::raw('SUM(units) as total_units'))
            ->whereDate('usage_date', $date)
            ->groupBy('subscription_id', 'user_id', 'usage_date')
            ->orderBy('subscription_id')
            ->chunk(5000, function ($records) use ($date) {
                $now = now();
                $upsertData = [];

                foreach ($records as $record) {
                    $upsertData[] = [
                        'user_id' => $record->user_id,
                        'subscription_id' => $record->subscription_id,
                        'usage_date' => $date,
                        'total_usage' => (int) $record->total_units,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($upsertData)) {
                    DailyUsageAggregate::upsert(
                        $upsertData,
                        ['subscription_id', 'usage_date'],
                        ['total_usage', 'updated_at']
                    );
                }
            });
    }
}
