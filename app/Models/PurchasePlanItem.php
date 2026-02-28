<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_plan_id',
        'product_id',
        'suggested_qty',
        'ordered_qty',
        'received_qty',
        'unit_cost_estimate',
        'currency',
        'source_reorder_suggestion_item_id',
        'status',
    ];

    protected $casts = [
        'suggested_qty' => 'decimal:3',
        'ordered_qty' => 'decimal:3',
        'received_qty' => 'decimal:3',
        'unit_cost_estimate' => 'decimal:4',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PurchasePlan::class, 'purchase_plan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceSuggestionItem(): BelongsTo
    {
        return $this->belongsTo(ReorderSuggestionItem::class, 'source_reorder_suggestion_item_id');
    }
}
