<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairPickup extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'repair_id', 'picked_up_at', 'pickup_method', 'pickup_confirmed', 'pickup_note', 'pickup_signature_id', 'created_at',
    ];

    protected $casts = ['picked_up_at' => 'datetime', 'pickup_confirmed' => 'boolean', 'created_at' => 'datetime'];

    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(RepairSignature::class, 'pickup_signature_id');
    }
}
