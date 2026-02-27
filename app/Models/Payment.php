<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $fillable = ['company_id','sales_document_id','method','amount','paid_at','reference'];

    protected $casts = ['paid_at' => 'datetime'];
}
