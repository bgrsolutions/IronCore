<x-filament-panels::page>
    <div class="grid grid-cols-5 gap-4">
        <x-filament::section><div>Total stock value: <strong>{{ $stockValue }}</strong></div></x-filament::section>
        <x-filament::section><div>Low stock items: <strong>{{ $lowStockCount }}</strong></div></x-filament::section>
        <x-filament::section><div>Recently received (14d): <strong>{{ $recentReceipts }}</strong></div></x-filament::section>
        <x-filament::section><div>Negative stock products: <strong>{{ $negativeAlerts }}</strong></div></x-filament::section>
        <x-filament::section><div>Negative exposure: <strong>{{ $negativeExposure }}</strong></div></x-filament::section>
    </div>
    <x-filament::section>
        <table class="w-full text-sm">
            <thead><tr><th>Product</th><th>On hand</th><th>Reorder min</th><th>Avg Cost</th></tr></thead>
            <tbody>
            @foreach($onHandRows as $row)
                <tr><td>{{ $row['product'] }}</td><td>{{ $row['on_hand'] }}</td><td>{{ $row['reorder_min_qty'] }}</td><td>{{ $row['avg_cost'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
