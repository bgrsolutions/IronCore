<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairFeedback extends Model
{
    public $timestamps = false;

    protected $table = 'repair_feedback';

    protected $fillable = ['repair_id', 'rating', 'comment', 'submitted_at', 'created_at'];

    protected $casts = ['submitted_at' => 'datetime', 'created_at' => 'datetime'];

    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }
}
