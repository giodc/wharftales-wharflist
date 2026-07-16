<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignSend extends Model
{
    protected $fillable = [
        'campaign_id',
        'list_ids',
        'status',
        'progress',
        'total',
        'scheduled_at',
        'started_at',
        'completed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'list_ids' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'campaign_id');
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['pending', 'scheduled', 'processing'], true);
    }

    public function percentComplete(): int
    {
        if ($this->total <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->progress / $this->total) * 100));
    }
}
