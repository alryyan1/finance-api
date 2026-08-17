<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PettyCashTransaction extends Model
{
    protected $fillable = [
        'source_account_id', 'created_by_user_id', 'type', 'status', 'date', 'amount', 'beneficiary_name', 'party_id', 'contra_account_id',
        'description', 'document_path', 'document_original_name', 'document_firebase_url', 'journal_entry_id',
        'manager_approved_at', 'manager_approved_by_user_id',
        'auditor_approved_at', 'auditor_approved_by_user_id', 'auditor_comment',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'manager_approved_at' => 'datetime',
        'auditor_approved_at' => 'datetime',
    ];

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function contraAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'contra_account_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PettyCashTransactionLine::class);
    }

    public function creditLines(): HasMany
    {
        return $this->hasMany(PettyCashTransactionSourceLine::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function managerApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by_user_id');
    }

    public function auditorApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_approved_by_user_id');
    }

    /**
     * The manager's approval is what completes the operation — it posts the journal entry.
     */
    public function isReadyToPost(): bool
    {
        return $this->manager_approved_at !== null;
    }
}
