<x-filament-panels::page>
    <form wire:submit="postTicket" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit">One-click Post Ticket</x-filament::button>
    </form>
</x-filament-panels::page>
