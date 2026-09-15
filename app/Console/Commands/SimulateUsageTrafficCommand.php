<?php

namespace App\Console\Commands;

use App\DTOs\RecordUsageDTO;
use App\Models\UsageEvent;
use App\Models\User;
use App\Services\UsageService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SimulateUsageTrafficCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:simulate-traffic
        {--users=12 : Number of active users to generate usage for}
        {--records-per-user=10000 : Number of usage records per user}
        {--start-date= : Start date for usage dates (YYYY-MM-DD, defaults to start of current month)}
        {--end-date= : End date for usage dates (YYYY-MM-DD, defaults to end of current month)}
        {--batch-size=1000 : Batch size for memory safety and bulk ingestion}
        {--http : Dispatch requests via HTTP client to /api/usage}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate high-throughput usage events for active users without database seeders';

    /**
     * Execute the console command.
     */
    public function handle(UsageService $usageService): int
    {
        $userLimit = (int) $this->option('users');
        $recordsPerUser = (int) $this->option('records-per-user');
        $batchSize = max(50, (int) $this->option('batch-size'));
        $isHttp = (bool) $this->option('http');

        $startDate = $this->option('start-date')
            ? Carbon::parse($this->option('start-date'))
            : now()->startOfMonth();

        $endDate = $this->option('end-date')
            ? Carbon::parse($this->option('end-date'))
            : now()->endOfMonth();

        // 1. Retrieve users with active subscriptions
        $users = User::whereHas('subscriptions', function ($query) {
            $query->where('status', 'active');
        })->take($userLimit)->get();

        if ($users->isEmpty()) {
            $this->error('No users with active subscriptions found. Please run seeders first.');
            return Command::FAILURE;
        }

        // 2. Precompute available date strings in range
        $period = CarbonPeriod::create($startDate, $endDate);
        $availableDates = [];
        foreach ($period as $date) {
            $availableDates[] = $date->toDateString();
        }
        $dateCount = count($availableDates);

        $totalRecords = count($users) * $recordsPerUser;
        $this->info("Starting usage simulation for " . count($users) . " users ({$recordsPerUser} records each = {$totalRecords} total)...");
        $this->info("Date range: {$startDate->toDateString()} to {$endDate->toDateString()}");
        $this->info("Mode: " . ($isHttp ? "HTTP POST /api/usage" : "High-Speed Ingestion Pipeline (UsageService & RecordUsageDTO)"));

        $startTime = microtime(true);
        $bar = $this->output->createProgressBar($totalRecords);
        $bar->start();

        $totalInserted = 0;

        if ($isHttp) {
            $endpoint = rtrim(config('app.url', 'http://localhost:8800'), '/') . '/api/usage';
            
            foreach ($users as $user) {
                for ($i = 1; $i <= $recordsPerUser; $i++) {
                    $randomDate = $availableDates[($i % $dateCount)];
                    $units = rand(5, 95);
                    $key = "sim-http-u{$user->id}-{$randomDate}-{$i}-" . Str::random(8);

                    Http::post($endpoint, [
                        'user_id' => $user->id,
                        'usage_date' => $randomDate,
                        'units' => $units,
                        'idempotency_key' => $key,
                    ]);

                    $totalInserted++;
                    $bar->advance();
                }
            }
        } else {
            // Pipeline mode: batch DTOs through UsageService
            $dtoBatch = [];

            foreach ($users as $user) {
                for ($i = 1; $i <= $recordsPerUser; $i++) {
                    $randomDate = $availableDates[($i % $dateCount)];
                    $units = rand(5, 95);
                    $key = "sim-pipe-u{$user->id}-{$randomDate}-{$i}-" . Str::random(8);

                    $dtoBatch[] = new RecordUsageDTO(
                        userId: $user->id,
                        usageDate: $randomDate,
                        units: $units,
                        idempotencyKey: $key,
                    );

                    if (count($dtoBatch) >= $batchSize) {
                        $totalInserted += $usageService->recordBatchUsage($dtoBatch);
                        $bar->advance(count($dtoBatch));
                        $dtoBatch = [];
                    }
                }
            }

            // Flush remaining DTOs in final batch
            if (!empty($dtoBatch)) {
                $totalInserted += $usageService->recordBatchUsage($dtoBatch);
                $bar->advance(count($dtoBatch));
                $dtoBatch = [];
            }
        }

        $bar->finish();
        $this->newLine(2);

        $duration = round(microtime(true) - $startTime, 2);
        $totalDbCount = UsageEvent::count();

        $this->info("Simulation completed successfully in {$duration}s!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Users Processed', count($users)],
                ['Records Per User', number_format($recordsPerUser)],
                ['Total Records Ingested', number_format($totalInserted)],
                ['Total DB UsageEvents', number_format($totalDbCount)],
                ['Duration', "{$duration} seconds"],
                ['Throughput', round($totalInserted / max(0.01, $duration), 2) . ' records/sec'],
            ]
        );

        return Command::SUCCESS;
    }
}
