<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Subscriber extends Model
{
    protected $fillable = [
        'email',
        'site_id',
        'verification_token',
        'verified',
        'unsubscribed',
        'custom_data',
        'subscribed_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'unsubscribed' => 'boolean',
            'custom_data' => 'array',
            'subscribed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Subscriber $subscriber): void {
            if (blank($subscriber->verification_token)) {
                $subscriber->verification_token = Str::random(64);
            }

            if (blank($subscriber->subscribed_at)) {
                $subscriber->subscribed_at = now();
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(MailingList::class, 'subscriber_lists', 'subscriber_id', 'list_id')
            ->withPivot(['unsubscribed', 'subscribed_at'])
            ->withTimestamps();
    }

    public function statusLabel(): string
    {
        if ($this->unsubscribed) {
            return 'unsubscribed';
        }

        return $this->verified ? 'verified' : 'pending';
    }

    public function scopeCampaignAudience(Builder $query, array $listIds): Builder
    {
        return $query
            ->where('verified', true)
            ->where('unsubscribed', false)
            ->whereHas('lists', function (Builder $lists) use ($listIds): void {
                $lists->whereIn('lists.id', $listIds)
                    ->where('subscriber_lists.unsubscribed', false);
            })
            ->distinct();
    }

    public function regenerateVerificationToken(): void
    {
        $this->forceFill([
            'verification_token' => Str::random(64),
        ])->save();
    }
}
