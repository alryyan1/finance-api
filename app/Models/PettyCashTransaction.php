<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashTransaction extends Model
{
    protected $fillable = [
        'fund_id', 'created_by_user_id', 'type', 'status', 'date', 'amount', 'beneficiary_name', 'contra_account_id',
        'description', 'document_path', 'document_original_name', 'document_firebase_url', 'journal_entry_id',
        'auditor_approved_at', 'auditor_approved_by_user_id',
        'manager_approved_at', 'manager_approved_by_user_id',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'auditor_approved_at' => 'datetime',
        'manager_approved_at' => 'datetime',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(PettyCashFund::class, 'fund_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function contraAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'contra_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function auditorApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_approved_by_user_id');
    }

    public function managerApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by_user_id');
    }

    /**
     * The manager's approval is what completes the operation (posts the journal
     * entry and deducts the fund balance). The auditor's approval is informational
     * only — it records that the auditor reviewed the expense, but doesn't gate
     * posting on its own or in combination with the manager's.
     */
    public function isReadyToPost(): bool
    {
        return $this->manager_approved_at !== null;
    }
}
