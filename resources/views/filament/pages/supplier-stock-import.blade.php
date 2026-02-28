<x-filament-panels::page>
    <form wire:submit="import" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit">Import CSV</x-filament::button>
    </form>
</x-filament-panels::page>
