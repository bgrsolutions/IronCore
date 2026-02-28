<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class VendorBill extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $fillable = ['company_id', 'store_location_id', 'supplier_id', 'purchase_plan_id', 'invoice_number', 'invoice_date', 'due_date', 'currency', 'net_total', 'tax_total', 'gross_total', 'status', 'posted_at', 'locked_at', 'cancelled_at', 'cancel_reason'];

    protected $casts = ['invoice_date' => 'date', 'due_date' => 'date', 'posted_at' => 'datetime', 'locked_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function storeLocation(): BelongsTo
    {
        return $this->belongsTo(StoreLocation::class);
    }

    public function purchasePlan(): BelongsTo
    {
        return $this->belongsTo(PurchasePlan::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VendorBillLine::class);
    }

    public function documentAttachments(): MorphMany
    {
        return $this->morphMany(DocumentAttachment::class, 'attachable');
    }
}
