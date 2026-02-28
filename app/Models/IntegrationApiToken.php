<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationApiToken extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $fillable = ['company_id','name','token_hash','is_active','last_used_at'];

    protected $casts = ['is_active' => 'boolean','last_used_at' => 'datetime'];
}
