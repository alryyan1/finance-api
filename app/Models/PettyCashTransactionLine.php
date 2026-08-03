<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashTransactionLine extends Model
{
    protected $fillable = [
        'petty_cash_transaction_id', 'contra_account_id', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function pettyCashTransaction(): BelongsTo
    {
        return $this->belongsTo(PettyCashTransaction::class);
    }

    public function contraAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'contra_account_id');
    }
}
