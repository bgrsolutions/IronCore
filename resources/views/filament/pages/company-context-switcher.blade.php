<x-filament-panels::page>
    <x-filament::section heading="Getting Started" icon="heroicon-o-information-circle" class="mb-4">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Select your active company before using CRM, Repairs, Inventory, and Sales modules.
            You can change this anytime from the <strong>Company Context</strong> menu entry.
        </p>
    </x-filament::section>

    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit">Set Company Context</x-filament::button>
    </form>
</x-filament-panels::page>
