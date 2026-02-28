<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex flex-wrap gap-2">
            <select wire:model.live="period_days" class="fi-input block rounded-lg border-gray-300">
                <option value="30">30 days</option>
                <option value="60">60 days</option>
                <option value="90">90 days</option>
            </select>
            <x-filament::button wire:click="generate">Generate</x-filament::button>
            <x-filament::button color="gray" wire:click="$toggle('filter_urgent')">Urgent only</x-filament::button>
            <x-filament::button color="gray" wire:click="$toggle('filter_no_supplier_stock')">No supplier stock</x-filament::button>
            <x-filament::button color="gray" wire:click="$toggle('filter_high_spend')">High spend</x-filament::button>
            <x-filament::button tag="a" href="{{ route('reports.export', ['type' => 'reorder-suggestions']) }}" color="success">CSV Export</x-filament::button>
        </div>

        <x-filament::section heading="Create Purchase Plan">
            <div class="flex flex-wrap gap-2">
                <select wire:model="purchase_plan_supplier_id" class="fi-input block rounded-lg border-gray-300">
                    <option value="">Any supplier</option>
                    @foreach ($suppliers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <select wire:model="purchase_plan_store_location_id" class="fi-input block rounded-lg border-gray-300">
                    <option value="">Company-wide</option>
                    @foreach ($stores as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <x-filament::button wire:click="createPurchasePlan" color="warning">Create Purchase Plan</x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section heading="Latest Suggestion Summary">
            <div class="text-sm">{{ json_encode($latest?->payload ?? []) }}</div>
        </x-filament::section>

        <x-filament::section heading="Suggestion Items">
            <div class="text-sm">{{ json_encode($rows) }}</div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
