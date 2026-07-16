<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailingList extends Model
{
    protected $table = 'lists';

    protected $fillable = [
        'name',
        'description',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class, 'list_id');
    }

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(Subscriber::class, 'subscriber_lists', 'list_id', 'subscriber_id')
            ->withPivot(['unsubscribed', 'subscribed_at'])
            ->withTimestamps();
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(EmailCampaign::class, 'campaign_list', 'list_id', 'email_campaign_id');
    }

    public function verifiedCount(): int
    {
        return $this->subscribers()
            ->where('subscribers.verified', true)
            ->where('subscribers.unsubscribed', false)
            ->where('subscriber_lists.unsubscribed', false)
            ->count();
    }

    public function pendingCount(): int
    {
        return $this->subscribers()
            ->where('subscribers.verified', false)
            ->where('subscribers.unsubscribed', false)
            ->where('subscriber_lists.unsubscribed', false)
            ->count();
    }

    public function unsubscribedCount(): int
    {
        return $this->subscribers()
            ->where(function ($query) {
                $query->where('subscribers.unsubscribed', true)
                    ->orWhere('subscriber_lists.unsubscribed', true);
            })
            ->count();
    }
}
