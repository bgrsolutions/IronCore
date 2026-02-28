<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountantExportBatch extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'from_date',
        'to_date',
        'breakdown_by_store',
        'summary_payload',
        'zip_path',
        'zip_hash',
        'generated_by_user_id',
        'generated_at',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'breakdown_by_store' => 'boolean',
        'summary_payload' => 'array',
        'generated_at' => 'datetime',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(AccountantExportFile::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
