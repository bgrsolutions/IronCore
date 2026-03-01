<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'type',
        'address_street',
        'address_city',
        'address_region',
        'address_postcode',
        'address_country',
        'contact_name',
        'contact_email',
        'contact_phone',
        'is_default',
        'counts_for_stock',
        'is_external_supplier_stock',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductWarehouseStock::class);
    }
}
