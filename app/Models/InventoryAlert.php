<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryAlert extends Model
{
    use HasFactory;
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = ['company_id','product_id','warehouse_id','current_on_hand','alert_type','created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
