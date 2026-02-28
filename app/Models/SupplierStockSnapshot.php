<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierStockSnapshot extends Model
{
    use HasFactory;
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = ['company_id', 'supplier_id', 'warehouse_name', 'snapshot_at', 'source', 'created_at'];

    protected $casts = ['snapshot_at' => 'datetime', 'created_at' => 'datetime'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierStockSnapshotItem::class);
    }
}
