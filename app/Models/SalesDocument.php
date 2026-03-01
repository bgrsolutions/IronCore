<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesDocument extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'store_location_id',
        'customer_id',
        'doc_type',
        'series',
        'number',
        'full_number',
        'status',
        'issue_date',
        'posted_at',
        'locked_at',
        'cancelled_at',
        'cancel_reason',
        'currency',
        'tax_mode',
        'tax_rate',
        'net_total',
        'tax_total',
        'gross_total',
        'immutable_payload',
        'hash',
        'previous_hash',
        'qr_payload',
        'related_document_id',
        'source',
        'source_ref',
        'created_by_user_id',
    ];

    protected $casts = [
        'issue_date' => 'datetime',
        'posted_at' => 'datetime',
        'locked_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'immutable_payload' => 'array',
        'tax_rate' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(SalesDocumentLine::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function exportItems(): HasMany
    {
        return $this->hasMany(VerifactuExportItem::class);
    }

    public function storeLocation(): BelongsTo
    {
        return $this->belongsTo(StoreLocation::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
