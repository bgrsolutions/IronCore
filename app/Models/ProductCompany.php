<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductCompany extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $table = 'product_company';

    protected $fillable = ['company_id', 'product_id', 'is_active', 'default_tax_profile_id', 'default_igic_rate', 'sale_price', 'reorder_min_qty', 'preferred_supplier_id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reorderSetting(): HasOne
    {
        return $this->hasOne(ProductReorderSetting::class, "product_id", "product_id")
            ->where("company_id", $this->company_id);
    }
}
