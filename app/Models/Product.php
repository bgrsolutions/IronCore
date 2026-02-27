<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['sku', 'barcode', 'name', 'description', 'product_type', 'is_active'];

    public function companies(): HasMany
    {
        return $this->hasMany(ProductCompany::class);
    }
}
