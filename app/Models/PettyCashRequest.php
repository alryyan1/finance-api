<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashRequest extends Model
{
    protected $fillable = [
        'fund_id', 'requester_name', 'date', 'amount', 'category',
        'description', 'reference', 'status',
        'approved_by', 'approved_at', 'rejection_reason',
        'paid_by', 'paid_at', 'expense_account_id',
        'document_path', 'document_original_name',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at'     => 'datetime',
    ];

    public const CATEGORIES = [
        'transportation' => 'مواصلات',
        'stationery'     => 'قرطاسية',
        'hospitality'    => 'ضيافة',
        'maintenance'    => 'صيانة',
        'utilities'      => 'مرافق',
        'other'          => 'متنوعات',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(PettyCashFund::class);
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }
}
