<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateInvoiceJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $subscriptionId = null,
        public ?string $cycleStartDate = null,
        public ?string $cycleEndDate = null
    ) {}

    /**
     * Execute the job to generate invoices.
     */
    public function handle(\App\Services\BillingService $billingService): void
    {
        $cycleStart = $this->cycleStartDate
            ? \Carbon\Carbon::parse($this->cycleStartDate)
            : now()->startOfMonth();

        $cycleEnd = $this->cycleEndDate
            ? \Carbon\Carbon::parse($this->cycleEndDate)
            : now()->endOfMonth();

        if ($this->subscriptionId !== null) {
            $subscription = \App\Models\Subscription::find($this->subscriptionId);
            if ($subscription) {
                $billingService->generateInvoice($subscription, $cycleStart, $cycleEnd);
            }
            return;
        }

        // Process all active subscriptions in chunks of 500
        \App\Models\Subscription::where('status', 'active')
            ->chunkById(500, function ($subscriptions) use ($billingService, $cycleStart, $cycleEnd) {
                foreach ($subscriptions as $subscription) {
                    $billingService->generateInvoice($subscription, $cycleStart, $cycleEnd);
                }
            });
    }
}
