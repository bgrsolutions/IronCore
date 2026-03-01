<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Expense extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'merchant',
        'date',
        'category',
        'currency',
        'pdf_path',
        'net_total',
        'tax_total',
        'gross_total',
        'status',
        'posted_at',
        'locked_at',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = ['date' => 'date', 'posted_at' => 'datetime', 'locked_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class);
    }

    public function documentAttachments(): MorphMany
    {
        return $this->morphMany(DocumentAttachment::class, 'attachable');
    }
}
