<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasePlan extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'store_location_id',
        'supplier_id',
        'status',
        'planned_at',
        'ordered_at',
        'expected_at',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'planned_at' => 'datetime',
        'ordered_at' => 'datetime',
        'expected_at' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchasePlanItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function storeLocation(): BelongsTo
    {
        return $this->belongsTo(StoreLocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
