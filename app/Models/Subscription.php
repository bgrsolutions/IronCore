<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'customer_id', 'plan_id', 'status', 'starts_at', 'next_run_at', 'ends_at', 'cancel_reason', 'auto_post', 'doc_type', 'series', 'price_net', 'tax_rate', 'currency', 'notes', 'created_by_user_id'];

    protected $casts = ['starts_at' => 'datetime', 'next_run_at' => 'datetime', 'ends_at' => 'datetime', 'auto_post' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SubscriptionRun::class);
    }
}
