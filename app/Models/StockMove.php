<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMove extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $fillable = ['company_id', 'product_id', 'warehouse_id', 'location_id', 'move_type', 'qty', 'unit_cost', 'total_cost', 'reference_type', 'reference_id', 'note', 'occurred_at', 'created_by_user_id'];

    protected $casts = ['occurred_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
