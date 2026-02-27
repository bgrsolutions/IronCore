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

<<<<<<< codex/implement-release-1-of-ironcore-erp-dift7s
    protected $fillable = ['company_id', 'vendor_bill_id', 'product_id', 'is_stock_item', 'description', 'quantity', 'unit_price', 'net_amount', 'tax_amount', 'gross_amount'];

    protected $casts = ['is_stock_item' => 'boolean'];
=======
    protected $fillable = ['company_id', 'vendor_bill_id', 'description', 'quantity', 'unit_price', 'net_amount', 'tax_amount', 'gross_amount'];
>>>>>>> main

    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class);
    }
<<<<<<< codex/implement-release-1-of-ironcore-erp-dift7s

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
=======
>>>>>>> main
}
