<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'description', 'plan_type', 'interval_months', 'price_net', 'tax_rate', 'currency', 'default_doc_type', 'default_series', 'auto_post', 'is_active'];

    protected $casts = ['auto_post' => 'boolean', 'is_active' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
