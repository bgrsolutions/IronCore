<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationRun extends Model
{
    use HasFactory;

    protected $fillable = ['company_id','integration','action','payload_hash','status','result_payload','error_message'];

    protected $casts = ['result_payload' => 'array'];
}
