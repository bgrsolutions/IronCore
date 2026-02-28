<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairStatusHistory extends Model
{
    use BelongsToCompany;

    protected $table = 'repair_status_history';

    protected $fillable = ['company_id', 'repair_id', 'from_status', 'to_status', 'changed_by', 'reason', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime'];

    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }
}
