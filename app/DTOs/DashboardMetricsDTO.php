<?php

namespace App\DTOs;

readonly class DashboardMetricsDTO
{
    /**
     * @param array{id: int, name: string} $merchant
     * @param array{id: int, name: string, billing_cycle: string, base_price: float, included_units: int, overage_rate: float}|null $activePlan
     * @param array{
     *     current_cycle_usage: int,
     *     total_allowance: int,
     *     usage_percentage: float,
     *     projected_overage_revenue: float,
     *     projected_overage_units: int,
     *     active_subscribers: int,
     *     cycle_start: string,
     *     cycle_end: string
     * } $metrics
     * @param array<int, array{
     *     user_id: int,
     *     customer_name: string,
     *     customer_email: string,
     *     plan_name: string,
     *     usage_units: int,
     *     allowance_units: int,
     *     percentage_of_allowance: float
     * }> $topCustomers
     * @param array<int, array{
     *     user_id: int,
     *     customer_name: string,
     *     previous_cycle_usage: int,
     *     current_cycle_usage: int,
     *     drop_percentage: float
     * }> $churnRisks
     * @param array<int, array{
     *     date: string,
     *     full_date: string,
     *     units: int
     * }> $dailyUsageTrend
     * @param array{
     *     redis_cache: array{status: string, ttl_minutes: int, driver: string},
     *     nightly_aggregation: array{status: string, chunk_size: int, schedule: string},
     *     rate_limit: array{status: string, limit: int, interval: string}
     * } $systemStatus
     */
    public function __construct(
        public array $merchant,
        public ?array $activePlan,
        public array $metrics,
        public array $topCustomers,
        public array $churnRisks,
        public array $dailyUsageTrend,
        public array $systemStatus,
    ) {}

    public function toArray(): array
    {
        return [
            'merchant' => $this->merchant,
            'active_plan' => $this->activePlan,
            'metrics' => $this->metrics,
            'top_customers' => $this->topCustomers,
            'churn_risks' => $this->churnRisks,
            'daily_usage_trend' => $this->dailyUsageTrend,
            'system_status' => $this->systemStatus,
        ];
    }
}
