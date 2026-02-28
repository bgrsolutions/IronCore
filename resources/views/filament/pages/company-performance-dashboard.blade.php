<x-filament-panels::page>
    @php($m = $metrics)
    <div class="space-y-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <x-filament::section heading="Revenue Net">€ {{ number_format($m['sales']['revenue_net'] ?? 0, 2) }}</x-filament::section>
            <x-filament::section heading="COGS">€ {{ number_format($m['sales']['cogs_total'] ?? 0, 2) }}</x-filament::section>
            <x-filament::section heading="Gross Profit">€ {{ number_format($m['sales']['gross_profit'] ?? 0, 2) }} ({{ number_format($m['sales']['gross_margin_percent'] ?? 0, 2) }}%)</x-filament::section>
            <x-filament::section heading="Stock Value">€ {{ number_format($m['inventory']['stock_value'] ?? 0, 2) }}</x-filament::section>
            <x-filament::section heading="Negative Stock Alerts">{{ $m['inventory']['negative_stock_count'] ?? 0 }}</x-filament::section>
            <x-filament::section heading="Repairs Invoiced / Total">{{ $m['repairs']['repairs_invoiced_count'] ?? 0 }} / {{ $m['repairs']['repairs_count'] ?? 0 }}</x-filament::section>
            <x-filament::section heading="Subscription MRR">€ {{ number_format($m['subscriptions']['mrr_estimate'] ?? 0, 2) }}</x-filament::section>
            <x-filament::section heading="Renewals 30d">{{ $m['subscriptions']['upcoming_renewals_30d'] ?? 0 }}</x-filament::section>
            <x-filament::section heading="Below-cost sales (last 7d)">
                {{ $m['sales']['below_cost_sales_last_7_days']['count'] ?? 0 }}
            </x-filament::section>
        </div>
        <x-filament::section heading="Top Products by Gross Profit">
            <div class="text-sm">{{ json_encode($m['sales']['top_products_by_gross_profit'] ?? []) }}</div>
        </x-filament::section>
        <x-filament::section heading="Top Products by Revenue">
            <div class="text-sm">{{ json_encode($m['sales']['top_products_by_revenue'] ?? []) }}</div>
        </x-filament::section>
        <x-filament::section heading="Dead Stock by Value">
            <div class="text-sm">{{ json_encode($m['inventory']['top_dead_stock_by_value'] ?? []) }}</div>
        </x-filament::section>
        <x-filament::section heading="Negative Margin Documents">
            <div class="text-sm">{{ json_encode($m['sales']['negative_margin_documents'] ?? []) }}</div>
        </x-filament::section>
        <x-filament::section heading="Below-cost Sales Last 7 Days">
            <div class="text-sm">{{ json_encode($m['sales']['below_cost_sales_last_7_days']['documents'] ?? []) }}</div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
