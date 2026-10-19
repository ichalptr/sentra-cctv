<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PublicCameraController extends Controller
{
    public function status(): JsonResponse
    {
        abort_unless(config('public-camera.enabled'), 404);

        $result = Cache::remember('public-camera.status', now()->addSeconds(5), function (): array {
            $checkedAt = now()->toIso8601String();
            try {
                $response = Http::connectTimeout(3)->timeout(6)
                    ->withOptions(['allow_redirects' => false])
                    ->get(config('public-camera.playlist_url'));
            } catch (ConnectionException) {
                return ['status' => 'unavailable', 'checkedAt' => $checkedAt];
            }

            $body = $response->body();
            if (! $response->successful() || ! str_starts_with(trim($body), '#EXTM3U')
                || ! str_contains($body, '#EXTINF:') || str_contains($body, '#EXT-X-ENDLIST')
                || ! preg_match('/#EXT-X-MEDIA-SEQUENCE:(\d+)/', $body, $matches)) {
                return ['status' => 'unavailable', 'checkedAt' => $checkedAt];
            }

            $sequence = $matches[1];
            $previous = Cache::get('public-camera.sequence');
            $changedAt = $previous && $previous['sequence'] === $sequence
                ? $previous['changedAt'] : now()->timestamp;
            Cache::put('public-camera.sequence', ['sequence' => $sequence, 'changedAt' => $changedAt], now()->addMinutes(5));

            return [
                'status' => now()->timestamp - $changedAt > 30 ? 'stale' : 'available',
                'checkedAt' => $checkedAt,
            ];
        });

        return response()->json($result)->header('Cache-Control', 'no-store');
    }
}
