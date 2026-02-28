<x-filament-panels::page>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-filament::section heading="Active Subscriptions">{{ $activeCount }}</x-filament::section>
        <x-filament::section heading="MRR Estimate">€ {{ number_format($mrrEstimate, 2) }}</x-filament::section>
        <x-filament::section heading="Due Soon (30d)">{{ $dueSoon }}</x-filament::section>
        <x-filament::section heading="Failed Runs (7d)">{{ $failedRuns }}</x-filament::section>
    </div>
</x-filament-panels::page>
