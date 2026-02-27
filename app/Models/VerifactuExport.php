<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VerifactuExport extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'export_type', 'period_start', 'period_end', 'generated_at', 'generated_by_user_id',
        'file_path', 'file_hash', 'record_count', 'status',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'generated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(VerifactuExportItem::class);
    }
}
