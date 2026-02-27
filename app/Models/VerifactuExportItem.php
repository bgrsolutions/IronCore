<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerifactuExportItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['verifactu_export_id', 'sales_document_id', 'included_at', 'created_at'];

    protected $casts = ['included_at' => 'datetime', 'created_at' => 'datetime'];

    public function export(): BelongsTo
    {
        return $this->belongsTo(VerifactuExport::class, 'verifactu_export_id');
    }

    public function salesDocument(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class);
    }
}
