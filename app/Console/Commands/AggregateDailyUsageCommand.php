<?php

namespace App\Console\Commands;

use App\Jobs\AggregateDailyUsageJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('usage:aggregate-daily {--date= : The target usage date (YYYY-MM-DD)}')]
#[Description('Aggregate raw usage events into daily aggregates in chunks')]
class AggregateDailyUsageCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date') ?: now()->toDateString();
        $this->info("Starting daily usage aggregation for {$date}...");

        AggregateDailyUsageJob::dispatchSync($date);

        $this->info("Daily usage aggregation completed for {$date}.");

        return self::SUCCESS;
    }
}
