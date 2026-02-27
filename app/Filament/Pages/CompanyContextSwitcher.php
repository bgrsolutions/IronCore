<?php

namespace App\Filament\Pages;

use App\Support\Company\CompanyContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CompanyContextSwitcher extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationLabel = 'Company Context';

    protected static ?string $slug = 'company-context';

    protected static string $view = 'filament.pages.company-context-switcher';

    public ?int $company_id = null;

    public function mount(): void
    {
        $this->company_id = CompanyContext::get();
        $this->form->fill(['company_id' => $this->company_id]);
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('company_id')
                ->label('Active company')
                ->options(auth()->user()?->companies()->pluck('name', 'companies.id') ?? [])
                ->required(),
        ];
    }

    public function save(): void
    {
        CompanyContext::set($this->company_id);
        Notification::make()->success()->title('Company context updated')->send();
        $this->redirect('/admin');
    }
}
