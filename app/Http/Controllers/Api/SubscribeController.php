<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SubscribeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubscribeController extends Controller
{
    public function __construct(protected SubscribeService $subscribeService) {}

    public function options(): Response
    {
        return response('', 204)->withHeaders($this->corsHeaders());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->all();

        if ($request->isJson()) {
            $payload = array_merge($payload, $request->json()->all());
        }

        $result = $this->subscribeService->subscribe(
            $payload,
            $request->ip(),
            $request->headers->get('referer'),
        );

        // Prefer 200 for business outcomes so embed widgets can always read JSON
        // without treating "already subscribed" / rate limits as opaque transport errors.
        $status = $result['http'] ?? ($result['success'] ? 200 : 400);

        if (in_array($status, [409, 429], true)) {
            $status = 200;
        }

        return response()
            ->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'],
            ], $status)
            ->withHeaders($this->corsHeaders());
    }

    /**
     * @return array<string, string>
     */
    protected function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept',
        ];
    }
}
