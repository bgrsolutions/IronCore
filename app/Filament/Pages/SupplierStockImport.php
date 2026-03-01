<?php

namespace App\Filament\Pages;

use App\Models\Supplier;
use App\Services\SupplierStockImportService;
use App\Support\Company\CompanyContext;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SupplierStockImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Reporting';

    protected static ?string $navigationLabel = 'Supplier Stock Import';

    protected static ?string $slug = 'reports/supplier-stock-import';

    protected static string $view = 'filament.pages.supplier-stock-import';

    public ?int $supplier_id = null;

    public ?string $warehouse_name = null;

    public array $column_map = [];

    public ?string $csv_file = null;

    protected function getFormSchema(): array
    {
        return [
            Select::make('supplier_id')
                ->label('Supplier')
                ->options(fn () => Supplier::query()->pluck('name', 'id'))
                ->required(),
            TextInput::make('warehouse_name')->required()->default('Main supplier warehouse'),
            FileUpload::make('csv_file')->disk('local')->directory('imports')->required(),
            TextInput::make('column_map.supplier_sku')->label('Supplier SKU column')->default('supplier_sku'),
            TextInput::make('column_map.barcode')->label('Barcode column')->default('barcode'),
            TextInput::make('column_map.product_name')->label('Name column')->default('product_name'),
            TextInput::make('column_map.qty_available')->label('Quantity column')->default('qty_available'),
            TextInput::make('column_map.unit_cost')->label('Unit cost column')->default('unit_cost'),
            TextInput::make('column_map.currency')->label('Currency column')->default('currency'),
            TextInput::make('column_map.sku')->label('Internal SKU column')->default('sku'),
        ];
    }

    public function import(): void
    {
        $companyId = (int) CompanyContext::get();
        abort_if($companyId <= 0, 400, 'Company context required');

        $path = storage_path('app/'.$this->csv_file);
        $content = file_get_contents($path);
        if ($content === false) {
            Notification::make()->danger()->title('Unable to read CSV file')->send();

            return;
        }

        $service = app(SupplierStockImportService::class);
        $items = $service->parseCsv($content, $this->column_map);
        $snapshot = $service->import($companyId, (int) $this->supplier_id, (string) $this->warehouse_name, $items, 'import');

        Notification::make()->success()->title('Imported snapshot #'.$snapshot->id.' with '.$snapshot->items->count().' items')->send();
    }
}
