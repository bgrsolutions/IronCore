<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicToken extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'repair_id', 'purpose', 'token', 'expires_at', 'used_at', 'created_by_user_id', 'created_at',
    ];

    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime', 'created_at' => 'datetime'];

    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }
}
