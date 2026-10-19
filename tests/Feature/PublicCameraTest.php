<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicCameraTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_returns_401_for_guests_without_contacting_source(): void
    {
        Http::preventStrayRequests();

        $this->getJson('/api/public-camera/status')->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_status_uses_fixed_source_and_caches_repeated_checks(): void
    {
        $this->actingAs(User::factory()->create());
        config(['public-camera.playlist_url' => 'https://example.test/live.m3u8']);
        Http::preventStrayRequests();
        Http::fake(['https://example.test/live.m3u8' => Http::response("#EXTM3U\n#EXT-X-MEDIA-SEQUENCE:100\n#EXTINF:2,\nsegment.ts\n")]);

        $this->getJson('/api/public-camera/status?url=http://127.0.0.1/private')->assertOk()->assertJsonPath('status', 'available')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/public-camera/status')->assertOk()->assertJsonPath('status', 'available');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/live.m3u8');
    }

    public function test_status_reports_unavailable_for_failed_or_non_live_responses(): void
    {
        $this->actingAs(User::factory()->create());
        config(['public-camera.playlist_url' => 'https://example.test/live.m3u8']);
        Http::preventStrayRequests();
        Http::fake(['https://example.test/live.m3u8' => Http::sequence()
            ->push('Unavailable', 503)
            ->push('<html>Error</html>')
            ->push("#EXTM3U\n#EXTINF:2,\n#EXT-X-MEDIA-SEQUENCE:1\n#EXT-X-ENDLIST")
            ->push("#EXTM3U\n#EXTINF:2,\nsegment.ts")]);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            Cache::forget('public-camera.status');

            $this->getJson('/api/public-camera/status')->assertOk()->assertJsonPath('status', 'unavailable');
        }
        Http::assertSentCount(4);
    }

    public function test_status_handles_connection_failure_without_exposing_error_details(): void
    {
        $this->actingAs(User::factory()->create());
        config(['public-camera.playlist_url' => 'https://example.test/live.m3u8']);
        Http::preventStrayRequests();
        Http::fake(['https://example.test/live.m3u8' => Http::failedConnection()]);

        $this->getJson('/api/public-camera/status')->assertOk()->assertJsonPath('status', 'unavailable')->assertJsonMissingPath('exception');

        Http::assertSentCount(1);
    }

    public function test_status_detects_stale_playlist_and_recovers_when_sequence_advances(): void
    {
        $this->actingAs(User::factory()->create());
        $this->freezeTime();
        config(['public-camera.playlist_url' => 'https://example.test/live.m3u8']);
        Http::preventStrayRequests();
        Http::fake(['https://example.test/live.m3u8' => Http::sequence()
            ->push("#EXTM3U\n#EXT-X-MEDIA-SEQUENCE:100\n#EXTINF:2,\nsegment.ts\n")
            ->push("#EXTM3U\n#EXT-X-MEDIA-SEQUENCE:100\n#EXTINF:2,\nsegment.ts\n")
            ->push("#EXTM3U\n#EXT-X-MEDIA-SEQUENCE:101\n#EXTINF:2,\nsegment.ts\n")]);

        $this->getJson('/api/public-camera/status')->assertOk()->assertJsonPath('status', 'available');
        $this->travel(31)->seconds();
        $this->getJson('/api/public-camera/status')->assertOk()->assertJsonPath('status', 'stale');
        $this->travel(6)->seconds();
        $this->getJson('/api/public-camera/status')->assertOk()->assertJsonPath('status', 'available');

        Http::assertSentCount(3);
    }

    public function test_disabled_pilot_returns_404_and_does_not_replace_existing_recordings(): void
    {
        $this->actingAs(User::factory()->create());
        Http::preventStrayRequests();
        config(['public-camera.enabled' => false]);

        $this->getJson('/api/public-camera/status')->assertNotFound();
        $this->get('/')->assertOk()->assertViewHas('boot', fn ($boot) => $boot['publicCamera'] === null && count($boot['records']) === 3);

        Http::assertNothingSent();
    }

    public function test_live_source_is_separate_from_archives_and_rejects_unrecorded_clip_references(): void
    {
        $this->actingAs(User::factory()->create());
        Http::preventStrayRequests();

        $this->get('/')->assertOk()->assertViewHas('boot', fn ($boot) => $boot['publicCamera']['name'] === 'Simpang Gunung Agung'
            && count($boot['records']) === 3
            && count($boot['cameras']) === 4
        );
        $this->postJson('/api/incidents', [
            'title' => 'Unrecorded live clip', 'camera' => 'Simpang Gunung Agung',
            'date' => '2026-10-16', 'record' => 'PUBLIC-01',
            'clip' => ['cameraId' => 'PUBLIC-01', 'second' => 5],
        ])->assertUnprocessable()->assertJsonValidationErrors('camera');

        $this->assertDatabaseCount('incidents', 0);
        Http::assertNothingSent();
    }
}
