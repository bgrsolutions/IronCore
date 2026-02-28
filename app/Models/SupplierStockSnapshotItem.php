<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierStockSnapshotItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'supplier_stock_snapshot_id',
        'product_id',
        'supplier_sku',
        'barcode',
        'product_name',
        'qty_available',
        'unit_cost',
        'currency',
        'created_at',
    ];

    protected $casts = [
        'qty_available' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SupplierStockSnapshot::class, 'supplier_stock_snapshot_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
