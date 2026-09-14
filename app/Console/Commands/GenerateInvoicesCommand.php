<?php

namespace App\Console\Commands;

use App\Jobs\GenerateInvoiceJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:generate-invoices {--subscription= : Optional specific subscription ID} {--start= : Cycle start date (YYYY-MM-DD)} {--end= : Cycle end date (YYYY-MM-DD)}')]
#[Description('Generate invoices with proration and overage calculations at cycle end')]
class GenerateInvoicesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $subscriptionId = $this->option('subscription') ? (int) $this->option('subscription') : null;
        $start = $this->option('start');
        $end = $this->option('end');

        $this->info("Starting invoice generation...");

        GenerateInvoiceJob::dispatchSync($subscriptionId, $start, $end);

        $this->info("Invoice generation completed successfully.");

        return self::SUCCESS;
    }
}
