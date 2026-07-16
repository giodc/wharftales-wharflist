<?php

namespace App\Services;

use App\Models\Subscriber;
use Illuminate\Support\Collection;

class CampaignAudience
{
    /**
     * @param  list<int>  $listIds
     * @return Collection<int, Subscriber>
     */
    public function forLists(array $listIds): Collection
    {
        $listIds = array_values(array_unique(array_filter(array_map('intval', $listIds))));

        if ($listIds === []) {
            return collect();
        }

        return Subscriber::query()
            ->campaignAudience($listIds)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<int>  $listIds
     */
    public function count(array $listIds): int
    {
        $listIds = array_values(array_unique(array_filter(array_map('intval', $listIds))));

        if ($listIds === []) {
            return 0;
        }

        return Subscriber::query()->campaignAudience($listIds)->count();
    }
}
