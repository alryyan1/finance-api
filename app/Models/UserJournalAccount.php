<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserJournalAccount extends Model
{
    protected $fillable = ['user_id', 'cash_box_account_id', 'bank_account_id'];

    protected $casts = [
        'user_id'             => 'integer',
        'cash_box_account_id' => 'integer',
        'bank_account_id'     => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
