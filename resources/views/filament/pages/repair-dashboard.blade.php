<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-4 rounded bg-white shadow">
            <div class="text-sm text-gray-500">Feedback submissions</div>
            <div class="text-3xl font-bold">{{ $feedbackCount }}</div>
        </div>
        <div class="p-4 rounded bg-white shadow">
            <div class="text-sm text-gray-500">Average rating</div>
            <div class="text-3xl font-bold">{{ number_format($feedbackAvg, 2) }}/5</div>
        </div>
    </div>
</x-filament-panels::page>
