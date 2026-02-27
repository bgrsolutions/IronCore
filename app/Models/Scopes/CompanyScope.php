<?php

namespace App\Models\Scopes;

use App\Support\Company\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (!auth()->check()) {
            return;
        }

        $user = auth()->user();
        $allowedIds = method_exists($user, 'companies') ? $user->companies()->pluck('companies.id')->all() : [];

        if ($allowedIds !== []) {
            $builder->whereIn($model->qualifyColumn('company_id'), $allowedIds);
        }

        $companyId = CompanyContext::get();
        if ($companyId !== null) {
            $builder->where($model->qualifyColumn('company_id'), $companyId);
        }
    }
}
