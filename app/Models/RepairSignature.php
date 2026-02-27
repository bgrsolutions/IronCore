<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairSignature extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'repair_id', 'signature_type', 'signer_name', 'signed_at', 'signature_image_path', 'signature_hash', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = ['signed_at' => 'datetime', 'created_at' => 'datetime'];

    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }
}
