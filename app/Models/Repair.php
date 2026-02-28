<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Repair extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'store_location_id', 'customer_id', 'linked_sales_document_id', 'technician_user_id', 'status', 'device_brand', 'device_model', 'serial_number',
        'reported_issue', 'internal_notes', 'diagnostic_fee_added', 'diagnostic_fee_net', 'diagnostic_fee_tax_rate',
    ];

    protected $casts = [
        'diagnostic_fee_added' => 'boolean',
        'diagnostic_fee_net' => 'decimal:2',
        'diagnostic_fee_tax_rate' => 'decimal:4',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function linkedSalesDocument(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'linked_sales_document_id');
    }

    public function storeLocation(): BelongsTo
    {
        return $this->belongsTo(StoreLocation::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(RepairSignature::class);
    }

    public function pickups(): HasMany
    {
        return $this->hasMany(RepairPickup::class);
    }

    public function feedbackEntries(): HasMany
    {
        return $this->hasMany(RepairFeedback::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(RepairStatusHistory::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(RepairLineItem::class);
    }

    public function documentAttachments(): MorphMany
    {
        return $this->morphMany(DocumentAttachment::class, 'attachable');
    }
}
