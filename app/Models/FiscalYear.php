<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalYear extends Model
{
    protected $fillable = [
        'name', 'start_date', 'end_date', 'status', 'closing_entry_id', 'closed_at',
    ];

    protected $casts = [
        'start_date'       => 'date:Y-m-d',
        'end_date'         => 'date:Y-m-d',
        'closed_at'        => 'datetime',
        'closing_entry_id' => 'integer',
    ];

    public static function isDateLocked(string $date): bool
    {
        return static::where('status', 'closed')
            ->where('start_date', '<=', $date)
            ->where('end_date',   '>=', $date)
            ->exists();
    }
}
