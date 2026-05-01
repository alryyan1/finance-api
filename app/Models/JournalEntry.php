<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = ['date', 'reference', 'description', 'is_posted', 'reversal_of', 'reversed_by'];

    protected $casts = [
        'date'        => 'date:Y-m-d',
        'is_posted'   => 'boolean',
        'reversal_of' => 'integer',
        'reversed_by' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
