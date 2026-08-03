<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashTransactionSourceLine extends Model
{
    protected $fillable = [
        'petty_cash_transaction_id', 'source_account_id', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function pettyCashTransaction(): BelongsTo
    {
        return $this->belongsTo(PettyCashTransaction::class);
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }
}
