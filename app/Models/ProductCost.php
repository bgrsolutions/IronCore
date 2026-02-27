<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCost extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $fillable = ['company_id', 'product_id', 'avg_cost', 'last_calculated_at'];

    protected $casts = ['last_calculated_at' => 'datetime'];
}
