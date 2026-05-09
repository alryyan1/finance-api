<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashVoucher extends Model
{
    protected $fillable = [
        'type', 'date', 'reference', 'amount', 'payment_method',
        'cash_account_id', 'contra_account_id', 'party_id', 'description', 'journal_entry_id',
    ];

    protected $casts = [
        'date'   => 'date:Y-m-d',
        'amount' => 'decimal:2',
    ];

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    public function contraAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'contra_account_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
