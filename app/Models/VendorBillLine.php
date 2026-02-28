<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorBillLine extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $fillable = ['company_id', 'vendor_bill_id', 'product_id', 'is_stock_item', 'description', 'quantity', 'unit_price', 'net_amount', 'tax_rate', 'tax_amount', 'gross_amount', 'cost_increase_flag', 'cost_increase_percent'];

    protected $casts = ['is_stock_item' => 'boolean', 'cost_increase_flag' => 'boolean', 'tax_rate' => 'decimal:2', 'cost_increase_percent' => 'decimal:2'];

    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
