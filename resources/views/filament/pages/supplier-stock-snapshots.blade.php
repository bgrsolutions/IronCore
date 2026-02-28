<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::button wire:click="createPlaceholdersFromLatest" color="warning">Create placeholders for unmatched in latest snapshot</x-filament::button>
        <x-filament::section heading="Supplier stock snapshots">
            <div class="text-sm">{{ json_encode($rows) }}</div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
