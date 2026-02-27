<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(['name' => 'IronCore Demo SL'], ['tax_id' => 'B12345678']);

        CompanySetting::updateOrCreate(
            ['company_id' => $company->id],
            [
                'tax_regime_label' => 'IGIC',
                'default_currency' => 'EUR',
                'invoice_series_prefixes' => ['T' => 'T', 'F' => 'F', 'NC' => 'NC'],
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@ironcore.local'],
            ['name' => 'IronCore Admin', 'password' => Hash::make('password')]
        );

        $admin->assignRole('admin');
        $admin->companies()->syncWithoutDetaching([$company->id]);
    }
}
