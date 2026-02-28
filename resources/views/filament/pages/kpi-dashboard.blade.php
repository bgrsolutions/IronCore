<x-filament-panels::page>
    <x-filament::section heading="KPI Payload">
        <div class="text-sm">{{ json_encode($metrics['kpi'] ?? []) }}</div>
    </x-filament::section>
    <x-filament::section heading="Breakdown by Store">
        <div class="text-sm">{{ json_encode($metrics['breakdown_by_store'] ?? []) }}</div>
    </x-filament::section>
    <x-filament::section heading="Breakdown by User">
        <div class="text-sm">{{ json_encode($metrics['breakdown_by_user'] ?? []) }}</div>
    </x-filament::section>
    <div class="mt-3 flex gap-2">
        <x-filament::button tag="a" href="{{ route('reports.export', ['type' => 'kpi-store']) }}" color="success">KPI CSV by Store</x-filament::button>
        <x-filament::button tag="a" href="{{ route('reports.export', ['type' => 'kpi-user']) }}" color="success">KPI CSV by User</x-filament::button>
    </div>
</x-filament-panels::page>
