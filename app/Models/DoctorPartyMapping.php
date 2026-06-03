<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorPartyMapping extends Model
{
    protected $fillable = ['jawda_doctor_id', 'finance_party_id'];

    protected $casts = [
        'jawda_doctor_id'  => 'integer',
        'finance_party_id' => 'integer',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'finance_party_id');
    }
}
