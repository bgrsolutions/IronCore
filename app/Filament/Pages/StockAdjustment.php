<?php

namespace App\Filament\Pages;

use App\Domain\Inventory\StockService;
use App\Models\Location;
use App\Models\Product;
use App\Models\Warehouse;
use App\Support\Company\CompanyContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class StockAdjustment extends Page implements HasForms
{
    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager']);
    }

    use InteractsWithForms;

    protected static ?string $navigationLabel = 'Stock Adjustment';

    protected static ?string $slug = 'stock-adjustment';

    protected static string $view = 'filament.pages.stock-adjustment';

    public ?int $product_id = null;
    public ?int $warehouse_id = null;
    public ?int $location_id = null;
    public ?float $qty = null;
    public ?float $unit_cost = null;
    public ?string $note = null;

    protected function getFormSchema(): array
    {
        return [
            Select::make('product_id')->options(fn () => Product::query()->where('product_type', 'stock')->pluck('name', 'id'))->required(),
            Select::make('warehouse_id')->options(fn () => Warehouse::query()->pluck('name', 'id'))->required(),
            Select::make('location_id')->options(fn () => Location::query()->pluck('name', 'id')),
            TextInput::make('qty')->numeric()->required()->helperText('Positive = adjustment in, Negative = adjustment out'),
            TextInput::make('unit_cost')->numeric(),
            Textarea::make('note')->required(),
        ];
    }

    public function submit(): void
    {
        if (! auth()->user()?->hasAnyRole(['admin', 'manager'])) {
            abort(403, 'Unauthorized');
        }
        $moveType = ($this->qty ?? 0) >= 0 ? 'adjustment_in' : 'adjustment_out';
        app(StockService::class)->postMove([
            'company_id' => CompanyContext::get(),
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'location_id' => $this->location_id,
            'move_type' => $moveType,
            'qty' => (float) $this->qty,
            'unit_cost' => $this->unit_cost,
            'note' => $this->note,
            'occurred_at' => now(),
        ]);

        Notification::make()->success()->title('Stock adjusted')->send();
    }
}
