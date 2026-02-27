<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionRun extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = ['company_id', 'subscription_id', 'run_at', 'period_start', 'period_end', 'status', 'message', 'generated_sales_document_id', 'error_class', 'error_message', 'created_at'];

    protected $casts = ['run_at' => 'datetime', 'period_start' => 'datetime', 'period_end' => 'datetime', 'created_at' => 'datetime'];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function salesDocument(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'generated_sales_document_id');
    }
}
