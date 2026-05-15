<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashReplenishment extends Model
{
    protected $fillable = [
        'fund_id', 'amount', 'description', 'requested_by',
        'status', 'approved_by', 'approved_at', 'rejection_reason',
        'journal_entry_id',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(PettyCashFund::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
