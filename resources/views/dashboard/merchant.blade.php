<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $merchant->name }} — Real-Time Merchant Analytics Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            900: '#312e81',
                        },
                        dark: {
                            800: '#1e2230',
                            850: '#171a26',
                            900: '#0f121d',
                            950: '#0a0c14',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0a0c14;
            color: #f1f5f9;
        }
        .glass-card {
            background: rgba(23, 26, 38, 0.75);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.07);
        }
        .glass-card:hover {
            border-color: rgba(99, 102, 241, 0.3);
            box-shadow: 0 10px 30px -10px rgba(99, 102, 241, 0.15);
        }
        .gradient-brand {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        }
    </style>
</head>
<body class="min-h-screen bg-dark-950 text-slate-100 antialiased p-4 md:p-8 selection:bg-brand-500 selection:text-white">

    <div class="max-w-7xl mx-auto space-y-8">

        <!-- Top Navigation & Merchant Switcher -->
        <header class="glass-card rounded-2xl p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl gradient-brand flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-indigo-500/20">
                    {{ strtoupper(substr($merchant->name, 0, 2)) }}
                </div>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-white">{{ $merchant->name }}</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            Live Telemetry
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Cycle: <span class="text-slate-200 font-medium">{{ \Carbon\Carbon::parse($data['metrics']['cycle_start'])->format('M d, Y') }} — {{ \Carbon\Carbon::parse($data['metrics']['cycle_end'])->format('M d, Y') }}</span>
                    </p>
                </div>
            </div>

            <!-- Merchant Switcher & API Link -->
            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <div class="flex items-center gap-2 bg-dark-900 border border-slate-800 rounded-xl p-1 text-xs">
                    <span class="text-slate-400 px-2 font-medium">Merchant:</span>
                    @foreach($allMerchants as $m)
                        <a href="{{ route('merchants.dashboard', ['merchant' => $m->id]) }}"
                           class="px-3 py-1.5 rounded-lg font-medium transition-all {{ $m->id === $merchant->id ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                            {{ $m->name }}
                        </a>
                    @endforeach
                </div>

                <a href="{{ route('api.merchants.dashboard', ['merchant' => $merchant->id]) }}" target="_blank"
                   class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700/80 transition-all flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                    JSON API
                </a>
            </div>
        </header>

        <!-- 3 Key Metric Cards -->
        <section class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- Card 1: Current Cycle Usage -->
            <div class="glass-card rounded-2xl p-6 transition-all relative overflow-hidden">
                <div class="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider mb-3">
                    <span>Current Cycle Usage</span>
                    <span class="p-2 rounded-lg bg-brand-500/10 text-brand-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </span>
                </div>

                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-white tracking-tight">
                        {{ number_format($data['metrics']['current_cycle_usage']) }}
                    </span>
                    <span class="text-sm font-medium text-slate-400">
                        / {{ number_format($data['metrics']['total_allowance']) }} units
                    </span>
                </div>

                <!-- Allowance Progress Bar -->
                <div class="mt-4">
                    <div class="flex justify-between text-xs font-medium mb-1.5">
                        <span class="text-slate-400">Allowance Consumed</span>
                        <span class="{{ $data['metrics']['usage_percentage'] > 100 ? 'text-rose-400 font-bold' : ($data['metrics']['usage_percentage'] > 80 ? 'text-amber-400' : 'text-emerald-400') }}">
                            {{ $data['metrics']['usage_percentage'] }}%
                        </span>
                    </div>
                    <div class="w-full bg-slate-800 rounded-full h-2.5 overflow-hidden">
                        <div class="h-2.5 rounded-full transition-all duration-700 {{ $data['metrics']['usage_percentage'] > 100 ? 'bg-gradient-to-r from-amber-500 to-rose-500' : ($data['metrics']['usage_percentage'] > 80 ? 'bg-amber-400' : 'bg-gradient-to-r from-brand-500 to-emerald-400') }}"
                             style="width: {{ min(100, $data['metrics']['usage_percentage']) }}%"></div>
                    </div>
                </div>

                <p class="text-xs text-slate-400 mt-4 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                    {{ $data['metrics']['active_subscribers'] }} active customer subscriptions
                </p>
            </div>

            <!-- Card 2: Projected Overage Revenue -->
            <div class="glass-card rounded-2xl p-6 transition-all relative overflow-hidden">
                <div class="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider mb-3">
                    <span>Projected Overage Revenue</span>
                    <span class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                </div>

                <div class="flex items-baseline gap-1.5">
                    <span class="text-3xl font-extrabold text-emerald-400 tracking-tight">
                        ₹ {{ number_format($data['metrics']['projected_overage_revenue'], 2) }}
                    </span>
                </div>

                <div class="mt-4 p-3 rounded-xl bg-dark-900/60 border border-slate-800/80">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400">Overage Units Billable:</span>
                        <span class="font-bold text-slate-200">{{ number_format($data['metrics']['projected_overage_units']) }} units</span>
                    </div>
                    <div class="flex items-center justify-between text-xs mt-1">
                        <span class="text-slate-400">Settlement Date:</span>
                        <span class="font-medium text-slate-300">{{ \Carbon\Carbon::parse($data['metrics']['cycle_end'])->format('M d, Y') }}</span>
                    </div>
                </div>

                <p class="text-xs text-slate-400 mt-4 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    Calculated in real-time from active usage events
                </p>
            </div>

            <!-- Card 3: Active Plan -->
            <div class="glass-card rounded-2xl p-6 transition-all relative overflow-hidden">
                <div class="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider mb-3">
                    <span>Active Plan Configuration</span>
                    <span class="p-2 rounded-lg bg-purple-500/10 text-purple-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </span>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-2xl font-extrabold text-white tracking-tight">
                            {{ $data['active_plan']['name'] ?? 'Multi-Plan Tier' }}
                        </span>
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-md bg-purple-500/20 text-purple-300 font-semibold border border-purple-500/30">
                            {{ ucfirst($data['active_plan']['billing_cycle'] ?? 'monthly') }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 p-3 rounded-xl bg-dark-900/60 border border-slate-800/80 space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Base Price:</span>
                        <span class="font-bold text-slate-200">₹ {{ number_format($data['active_plan']['base_price'] ?? 0, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Included Units:</span>
                        <span class="font-medium text-slate-300">{{ number_format($data['active_plan']['included_units'] ?? 0) }} units</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Overage Rate:</span>
                        <span class="font-medium text-amber-300">₹ {{ number_format($data['active_plan']['overage_rate'] ?? 0, 2) }} / unit</span>
                    </div>
                </div>

                <p class="text-xs text-slate-400 mt-4 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-purple-400"></span>
                    Cached in Redis (TTL 10m)
                </p>
            </div>
        </section>

        <!-- 30-Day Daily Usage Trend Chart -->
        <section class="glass-card rounded-2xl p-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 mb-6">
                <div>
                    <h2 class="text-lg font-bold text-white tracking-tight">30-Day Daily Usage Trend</h2>
                    <p class="text-xs text-slate-400">Chronological daily units ingested across all merchant subscribers</p>
                </div>
                <div class="flex items-center gap-2 text-xs font-medium text-slate-400 bg-dark-900/80 px-3 py-1.5 rounded-xl border border-slate-800">
                    <span class="w-2.5 h-2.5 rounded-full bg-brand-500"></span>
                    Daily Metered Units
                </div>
            </div>

            <div class="h-64 md:h-72 w-full">
                <canvas id="dailyUsageChart"></canvas>
            </div>
        </section>

        <!-- Two Column Layout: Top Customers & Churn Risk -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Top 5 Customers by Usage Table (7 Cols) -->
            <section class="lg:col-span-7 glass-card rounded-2xl p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-lg font-bold text-white tracking-tight">Top 5 Customers by Usage</h2>
                            <p class="text-xs text-slate-400">Ranked by units consumed in the active cycle</p>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                            Current Cycle
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-800/80">
                                    <th class="py-3 px-2">Customer</th>
                                    <th class="py-3 px-2">Plan</th>
                                    <th class="py-3 px-2 text-right">Units Used</th>
                                    <th class="py-3 px-2 text-right">% Allowance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/40 text-sm">
                                @forelse($data['top_customers'] as $customer)
                                    <tr class="hover:bg-slate-800/30 transition-colors">
                                        <td class="py-3.5 px-2">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-lg bg-dark-900 border border-slate-800 flex items-center justify-center font-bold text-xs text-brand-400">
                                                    {{ strtoupper(substr($customer['customer_name'], 0, 1)) }}
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-white leading-tight">{{ $customer['customer_name'] }}</p>
                                                    <p class="text-[11px] text-slate-400">{{ $customer['customer_email'] }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-2">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-slate-800 text-slate-300 border border-slate-700/60">
                                                {{ $customer['plan_name'] }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-2 text-right font-mono font-semibold text-slate-100">
                                            {{ number_format($customer['usage_units']) }}
                                        </td>
                                        <td class="py-3.5 px-2 text-right">
                                            <div class="inline-flex flex-col items-end gap-1">
                                                <span class="text-xs font-bold font-mono {{ $customer['percentage_of_allowance'] > 100 ? 'text-rose-400' : ($customer['percentage_of_allowance'] > 80 ? 'text-amber-400' : 'text-emerald-400') }}">
                                                    {{ $customer['percentage_of_allowance'] }}%
                                                </span>
                                                <div class="w-16 bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                                    <div class="h-1.5 rounded-full {{ $customer['percentage_of_allowance'] > 100 ? 'bg-rose-500' : ($customer['percentage_of_allowance'] > 80 ? 'bg-amber-400' : 'bg-emerald-400') }}"
                                                         style="width: {{ min(100, $customer['percentage_of_allowance']) }}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-8 text-center text-xs text-slate-500">
                                            No usage events recorded for this merchant's customers yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-800/60 text-xs text-slate-400 flex items-center justify-between">
                    <span>Showing top {{ count($data['top_customers']) }} subscribers</span>
                    <span class="text-brand-400 font-medium">Ranked by metered units</span>
                </div>
            </section>

            <!-- Churn Risk Alert Panel (5 Cols) -->
            <section class="lg:col-span-5 glass-card rounded-2xl p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-lg font-bold text-white tracking-tight">Churn Risk Alerts</h2>
                                <p class="text-xs text-slate-400">> 50% Month-over-Month (MoM) drop</p>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ count($data['churn_risks']) > 0 ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' }}">
                            {{ count($data['churn_risks']) }} Detected
                        </span>
                    </div>

                    <div class="space-y-3 mt-4">
                        @forelse($data['churn_risks'] as $risk)
                            <div class="p-3.5 rounded-xl bg-rose-950/20 border border-rose-500/20 hover:border-rose-500/40 transition-all">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-white">{{ $risk['customer_name'] }}</p>
                                        <p class="text-xs text-slate-400 mt-0.5">
                                            Usage dropped from <span class="font-mono text-slate-300 font-semibold">{{ number_format($risk['previous_cycle_usage']) }}</span> to <span class="font-mono text-rose-400 font-semibold">{{ number_format($risk['current_cycle_usage']) }}</span>
                                        </p>
                                    </div>
                                    <span class="px-2 py-1 rounded-lg text-xs font-extrabold bg-rose-500/20 text-rose-400 border border-rose-500/30 font-mono">
                                        -{{ $risk['drop_percentage'] }}%
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="py-12 text-center">
                                <div class="w-10 h-10 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center mx-auto mb-2.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-slate-200">No Churn Anomalies</p>
                                <p class="text-xs text-slate-400 mt-1 max-w-xs mx-auto">All active customer usage is consistent with historical baselines.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-800/60 text-xs text-slate-400">
                    Calculated automatically from consecutive 30-day billing windows.
                </div>
            </section>
        </div>

        <!-- System Status Informational Widget -->
        <footer class="glass-card rounded-2xl p-6">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                System Architecture & Operational Health
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Status 1: Plan Cache -->
                <div class="p-3.5 rounded-xl bg-dark-900/80 border border-slate-800 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-200">Plan Pricing Cache</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Redis (TTL {{ $data['system_status']['redis_cache']['ttl_minutes'] }}m)</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $data['system_status']['redis_cache']['status'] === 'operational' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                        {{ ucfirst($data['system_status']['redis_cache']['status']) }}
                    </span>
                </div>

                <!-- Status 2: Nightly Aggregation -->
                <div class="p-3.5 rounded-xl bg-dark-900/80 border border-slate-800 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-200">Nightly Aggregation Job</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Queued ({{ number_format($data['system_status']['nightly_aggregation']['chunk_size']) }} rows/batch)</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        {{ ucfirst($data['system_status']['nightly_aggregation']['status']) }}
                    </span>
                </div>

                <!-- Status 3: Rate Limiting -->
                <div class="p-3.5 rounded-xl bg-dark-900/80 border border-slate-800 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-200">Usage Endpoint Rate Limit</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">{{ $data['system_status']['rate_limit']['limit'] }} req/min per API Key</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-brand-500/10 text-brand-400 border border-brand-500/20">
                        {{ ucfirst($data['system_status']['rate_limit']['status']) }}
                    </span>
                </div>
            </div>
        </footer>

    </div>

    <!-- Chart.js Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const trendData = @json($data['daily_usage_trend']);
            const labels = trendData.map(item => item.date);
            const dataPoints = trendData.map(item => item.units);

            const ctx = document.getElementById('dailyUsageChart').getContext('2d');
            
            const gradient = ctx.createLinearGradient(0, 0, 0, 240);
            gradient.addColorStop(0, 'rgba(99, 102, 241, 0.45)');
            gradient.addColorStop(1, 'rgba(99, 102, 241, 0.00)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Units Consumed',
                        data: dataPoints,
                        borderColor: '#6366f1',
                        borderWidth: 2.5,
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2.5,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#818cf8',
                        pointBorderColor: '#0a0c14',
                        pointBorderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#171a26',
                            titleColor: '#f1f5f9',
                            bodyColor: '#818cf8',
                            borderColor: '#312e81',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y.toLocaleString() + ' units';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false,
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 11,
                                    family: "'Plus Jakarta Sans', sans-serif"
                                },
                                maxTicksLimit: 12,
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.05)',
                                drawBorder: false,
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 11,
                                    family: "'Plus Jakarta Sans', sans-serif"
                                },
                                callback: function(value) {
                                    if (value >= 1000) {
                                        return (value / 1000) + 'k';
                                    }
                                    return value;
                                }
                            },
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
