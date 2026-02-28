<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;
    use BelongsToCompany;

    public $timestamps = false;

    public const UPDATED_AT = null;

    protected $fillable = ['company_id', 'user_id', 'action', 'auditable_type', 'auditable_id', 'payload', 'created_at'];

    protected $casts = ['payload' => 'array', 'created_at' => 'datetime'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
