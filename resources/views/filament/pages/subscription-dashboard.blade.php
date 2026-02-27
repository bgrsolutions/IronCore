<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 bg-white shadow rounded">
            <div class="text-sm text-gray-500">Subscriptions due today</div>
            <div class="text-3xl font-bold">{{ $dueToday }}</div>
        </div>
        <div class="p-4 bg-white shadow rounded">
            <div class="text-sm text-gray-500">Failed runs (7 days)</div>
            <div class="text-3xl font-bold">{{ $failed7d }}</div>
        </div>
        <div class="p-4 bg-white shadow rounded">
            <div class="text-sm text-gray-500">Upcoming renewals (14 days)</div>
            <div class="text-3xl font-bold">{{ $upcoming }}</div>
        </div>
    </div>
</x-filament-panels::page>
