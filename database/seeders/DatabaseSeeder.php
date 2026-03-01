<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RoleSeeder::class, PermissionSeeder::class, CompanySeeder::class]);

        Company::query()->pluck('id')->each(function (int $companyId): void {
            CompanySetting::firstOrCreate(
                ['company_id' => $companyId],
                [
                    'tax_regime_label' => 'IGIC',
                    'default_currency' => 'EUR',
                    'invoice_series_prefixes' => ['T' => 'T', 'F' => 'F', 'NC' => 'NC'],
                ]
            );
        });
    }
}
