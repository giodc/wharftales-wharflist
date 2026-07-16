<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateLimit extends Model
{
    protected $fillable = [
        'ip_address',
        'site_id',
        'attempts',
        'last_attempt',
    ];

    protected function casts(): array
    {
        return [
            'last_attempt' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
