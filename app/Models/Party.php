<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Party extends Model
{
    protected $fillable = ['name', 'type', 'phone', 'email', 'address', 'account_id', 'is_active'];

    protected $casts = [
        'is_active'  => 'boolean',
        'account_id' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
