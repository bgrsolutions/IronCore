<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountantExportFile extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'accountant_export_batch_id',
        'file_name',
        'file_path',
        'rows_count',
        'sha256',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AccountantExportBatch::class, 'accountant_export_batch_id');
    }
}
