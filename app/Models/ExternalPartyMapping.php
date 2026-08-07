<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalPartyMapping extends Model
{
    protected $fillable = ['source_system', 'source_type', 'source_id', 'party_id'];

    protected $casts = [
        'party_id' => 'integer',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
