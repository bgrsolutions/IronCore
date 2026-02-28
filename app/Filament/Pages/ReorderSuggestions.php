<?php

namespace App\Filament\Pages;

use App\Models\ReorderSuggestion;
use App\Services\ReorderSuggestionService;
use App\Support\Company\CompanyContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ReorderSuggestions extends Page
{
    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Reorder Suggestions';

    protected static ?string $slug = 'reports/reorder-suggestions';

    protected static string $view = 'filament.pages.reorder-suggestions';

    public int $period_days = 30;

    public bool $filter_urgent = false;

    public bool $filter_no_supplier_stock = false;

    public bool $filter_high_spend = false;

    public function generate(): void
    {
        $companyId = (int) CompanyContext::get();
        if ($companyId <= 0) {
            Notification::make()->danger()->title('Company context is required')->send();

            return;
        }

        app(ReorderSuggestionService::class)->generate($companyId, $this->period_days, auth()->id());
        Notification::make()->success()->title('Reorder suggestion generated')->send();
    }

    protected function getViewData(): array
    {
        $companyId = (int) CompanyContext::get();
        $latest = ReorderSuggestion::query()
            ->with('items.product')
            ->where('company_id', $companyId)
            ->latest('generated_at')
            ->first();

        $rows = collect($latest?->items ?? [])->map(function ($item): array {
            return [
                'product' => $item->product?->name,
                'avg_daily_sold' => (float) $item->avg_daily_sold,
                'on_hand' => (float) $item->on_hand,
                'supplier_available' => $item->supplier_available !== null ? (float) $item->supplier_available : null,
                'suggested_qty' => (float) $item->suggested_qty,
                'estimated_spend' => $item->estimated_spend !== null ? (float) $item->estimated_spend : null,
                'reason' => (string) $item->reason,
                'negative_exposure' => $item->negative_exposure !== null ? (float) $item->negative_exposure : 0.0,
            ];
        });

        if ($this->filter_urgent) {
            $rows = $rows->filter(fn (array $r): bool => $r['negative_exposure'] > 0);
        }
        if ($this->filter_no_supplier_stock) {
            $rows = $rows->filter(fn (array $r): bool => ($r['supplier_available'] ?? 0) <= 0);
        }
        if ($this->filter_high_spend) {
            $rows = $rows->filter(fn (array $r): bool => ($r['estimated_spend'] ?? 0) >= 250);
        }

        return [
            'latest' => $latest,
            'rows' => $rows->values()->all(),
        ];
    }
}
