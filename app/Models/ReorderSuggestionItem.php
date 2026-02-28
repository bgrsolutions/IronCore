<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReorderSuggestionItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'reorder_suggestion_id',
        'product_id',
        'suggested_qty',
        'days_cover_target',
        'avg_daily_sold',
        'on_hand',
        'supplier_available',
        'negative_exposure',
        'last_supplier_unit_cost',
        'estimated_spend',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'suggested_qty' => 'decimal:3',
        'avg_daily_sold' => 'decimal:4',
        'on_hand' => 'decimal:3',
        'supplier_available' => 'decimal:3',
        'negative_exposure' => 'decimal:3',
        'last_supplier_unit_cost' => 'decimal:4',
        'estimated_spend' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(ReorderSuggestion::class, 'reorder_suggestion_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
