<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesDocumentLine extends Model
{
    use HasFactory;

    protected $fillable = ['sales_document_id','line_no','product_id','description','qty','unit_price','tax_rate','line_net','line_tax','line_gross','cost_unit','cost_total'];

    public function document(): BelongsTo { return $this->belongsTo(SalesDocument::class, 'sales_document_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
