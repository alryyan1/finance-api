<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PettyCashFund extends Model
{
    protected $fillable = [
        'name', 'custodian_name', 'account_id', 'max_amount',
        'low_balance_threshold', 'current_balance', 'status',
    ];

    protected $casts = [
        'max_amount' => 'decimal:2',
        'low_balance_threshold' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PettyCashTransaction::class, 'fund_id');
    }
}
