<x-filament-panels::page>
    <x-filament::section heading="Open Exposure">
        <div class="text-xl font-bold">€{{ number_format($openExposure, 2) }}</div>
    </x-filament::section>
    <x-filament::section heading="Open Plans by Supplier">
        <div class="text-sm">{{ $openPlans->groupBy(fn($p) => $p->supplier?->name ?? 'Unassigned')->map->count()->toJson() }}</div>
    </x-filament::section>
    <x-filament::section heading="Late Arrivals">
        <div class="text-sm">{{ $latePlans->map(fn($p) => ['plan_id' => $p->id, 'expected_at' => optional($p->expected_at)->toDateString()])->values()->toJson() }}</div>
    </x-filament::section>
    <div class="mt-3 flex gap-2">
        <x-filament::button tag="a" href="{{ route('reports.export', ['type' => 'purchase-plans']) }}" color="success">Purchase Plan CSV</x-filament::button>
        <x-filament::button tag="a" href="{{ route('reports.export', ['type' => 'open-purchase-plans']) }}" color="success">Open Plans CSV</x-filament::button>
    </div>
</x-filament-panels::page>
