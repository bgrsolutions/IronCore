<?php

namespace App\Filament\Concerns;

use App\Support\Company\CompanyContext;
use Illuminate\Database\Eloquent\Builder;

trait HasCompanyScopedResource
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->check()) {
            $allowedIds = auth()->user()->companies()->pluck('companies.id')->all();
            $query->whereIn('company_id', $allowedIds ?: [0]);
        }

        $companyId = CompanyContext::get();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }
}
