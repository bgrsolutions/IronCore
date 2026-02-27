<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCompany extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $table = 'customer_company';

    protected $fillable = ['company_id','customer_id','fiscal_name','tax_id','address_line_1','address_line_2','city','postal_code','province','wants_full_invoice','default_payment_terms'];

    protected $casts = ['wants_full_invoice' => 'boolean'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
