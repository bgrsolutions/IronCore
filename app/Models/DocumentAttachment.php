<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentAttachment extends Model
{
    use HasFactory;
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = ['company_id', 'document_id', 'attachable_type', 'attachable_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
