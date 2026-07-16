<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmailCampaign extends Model
{
    protected $fillable = [
        'list_id',
        'subject',
        'body',
        'status',
        'sent_count',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function primaryList(): BelongsTo
    {
        return $this->belongsTo(MailingList::class, 'list_id');
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(MailingList::class, 'campaign_list', 'email_campaign_id', 'list_id');
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CampaignSend::class, 'campaign_id');
    }

    public function latestSend(): HasOne
    {
        return $this->hasOne(CampaignSend::class, 'campaign_id')->latestOfMany();
    }

    public function listIds(): array
    {
        $ids = $this->lists()->pluck('lists.id')->all();

        if ($ids === [] && $this->list_id) {
            return [(int) $this->list_id];
        }

        return array_map('intval', $ids);
    }
}
