<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex gap-3">
            <x-filament::button wire:click="$toggle('only_flagged')" color="warning">
                Toggle flagged only ({{ $only_flagged ? 'ON' : 'OFF' }})
            </x-filament::button>
            <x-filament::button wire:click="$toggle('only_not_invoiced')" color="gray">
                Toggle not invoiced only ({{ $only_not_invoiced ? 'ON' : 'OFF' }})
            </x-filament::button>
        </div>

        <x-filament::section heading="Repair Profitability Rows">
            <div class="text-sm">{{ json_encode($rows) }}</div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
