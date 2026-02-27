<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseLine extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $fillable = ['company_id', 'expense_id', 'description', 'quantity', 'unit_price', 'net_amount', 'tax_amount', 'gross_amount'];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
