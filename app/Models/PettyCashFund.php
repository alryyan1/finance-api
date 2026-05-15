<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PettyCashFund extends Model
{
    protected $fillable = [
        'name', 'custodian_name', 'account_id', 'bank_account_id',
        'max_amount', 'current_balance', 'low_balance_threshold', 'status',
    ];

    protected $casts = [
        'max_amount'            => 'decimal:2',
        'current_balance'       => 'decimal:2',
        'low_balance_threshold' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PettyCashRequest::class, 'fund_id');
    }

    public function replenishments(): HasMany
    {
        return $this->hasMany(PettyCashReplenishment::class, 'fund_id');
    }

    public function isLowBalance(): bool
    {
        return (float) $this->current_balance <= (float) $this->low_balance_threshold;
    }
}
