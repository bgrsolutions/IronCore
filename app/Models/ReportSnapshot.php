<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportSnapshot extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'snapshot_type', 'snapshot_date', 'week_start_date',
        'payload', 'generated_at', 'generated_by_user_id', 'created_at',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'week_start_date' => 'date',
        'generated_at' => 'datetime',
        'created_at' => 'datetime',
        'payload' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ReportSnapshotItem::class);
    }
}
