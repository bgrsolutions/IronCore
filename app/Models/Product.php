<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'barcode',
        'ean',
        'name',
        'description',
        'image_url',
        'image_path',
        'product_type',
        'cost',
        'default_margin_percent',
        'lead_time_days',
        'default_warehouse_id',
        'supplier_id',
        'category_id',
        'is_active',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(ProductCompany::class);
    }

    public function reorderSetting(): HasOne
    {
        return $this->hasOne(ProductReorderSetting::class);
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function companyPricings(): HasMany
    {
        return $this->hasMany(ProductCompanyPricing::class);
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(ProductWarehouseStock::class);
    }
}
