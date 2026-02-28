<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReorderSuggestion extends Model
{
    use HasFactory;
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'generated_at',
        'period_days',
        'from_date',
        'to_date',
        'payload',
        'created_by_user_id',
        'created_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'from_date' => 'date',
        'to_date' => 'date',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ReorderSuggestionItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
