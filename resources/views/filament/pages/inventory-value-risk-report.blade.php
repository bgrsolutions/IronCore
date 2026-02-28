<x-filament-panels::page>
    <x-filament::section heading="Stock Value by Warehouse">
        <div class="text-sm">{{ json_encode($warehouseValue) }}</div>
    </x-filament::section>
    <x-filament::section heading="Negative Stock List">
        <div class="text-sm">{{ json_encode($negativeRows) }}</div>
    </x-filament::section>
    <x-filament::section heading="Dead Stock List">
        <div class="text-sm">{{ json_encode($deadRows) }}</div>
    </x-filament::section>
</x-filament-panels::page>
