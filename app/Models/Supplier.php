<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'tax_id', 'email', 'phone', 'contact_name', 'address'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function stockSnapshots(): HasMany
    {
        return $this->hasMany(SupplierStockSnapshot::class);
    }
}
