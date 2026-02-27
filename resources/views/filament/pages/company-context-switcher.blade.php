<x-filament-panels::page>
    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit">Set Company Context</x-filament::button>
    </form>
</x-filament-panels::page>
