<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SentraTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_read_workspace_media_or_write_incidents(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/media/street.mp4')->assertRedirect('/login');
        $this->postJson('/api/incidents', [])->assertUnauthorized();
        $this->putJson('/api/policy', [])->assertUnauthorized();
    }

    public function test_initial_setup_creates_one_hashed_account_and_logout_protects_workspace(): void
    {
        $this->get('/setup')->assertOk();
        $password = 'Testing-Only-12345';
        $this->post('/setup', ['name' => 'Test Operator', 'email' => 'operator@example.test', 'password' => $password, 'password_confirmation' => $password])->assertRedirect('/login')->assertSessionHas('status');
        $user = User::first();
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertGuest();
        $this->get('/login')->assertOk()->assertSee('Akun administrator berhasil dibuat.');
        $this->post('/login', ['email' => 'operator@example.test', 'password' => $password])->assertRedirect('/');
        $this->assertAuthenticated();
        $this->get('/')->assertOk()->assertSee('window.SENTRA_BOOT', false)->assertSee('Administrator');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/setup', ['name' => 'Other'])->assertRedirect('/login');
        $this->get('/setup')->assertRedirect('/login');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_setup_is_local_and_rejects_short_password(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.5'])->get('/setup')->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->post('/setup', ['name' => 'Test', 'email' => 'test@example.test', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_rejects_wrong_password_and_accepts_valid_password(): void
    {
        User::factory()->create(['email' => 'login@example.test', 'password' => Hash::make('Correct-test-12345')]);
        $this->post('/login', ['email' => 'login@example.test', 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'login@example.test', 'password' => 'Correct-test-12345'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_incident_and_clip_are_persisted_and_invalid_clip_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $data = ['title' => 'Persistent incident', 'camera' => 'Jalan raya', 'date' => '2026-10-15', 'note' => 'Review test', 'record' => 'REC-ROAD-001', 'clip' => ['cameraId' => 'CAM-01', 'second' => 4.5]];
        $this->postJson('/api/incidents', $data)->assertCreated()->assertJsonPath('clip.second', 4.5)->assertJsonPath('protected', true);
        $this->assertDatabaseHas('incidents', ['user_id' => $user->id, 'title' => 'Persistent incident', 'clip_second' => 4.5]);
        $this->get('/')->assertOk()->assertSee('Persistent incident');
        $data['clip']['cameraId'] = 'CAM-03';
        $this->postJson('/api/incidents', $data)->assertUnprocessable();
        $this->postJson('/api/incidents', ['title' => 'Bad', 'camera' => 'Unknown', 'date' => 'not-date'])->assertUnprocessable();
        $this->assertDatabaseCount('incidents', 1);
    }

    public function test_policy_and_settings_are_validated_and_persisted(): void
    {
        $this->actingAs(User::factory()->create());
        $this->putJson('/api/policy', ['retention' => '60', 'backupRetention' => '90', 'destination' => 'NAS testing', 'schedule' => '02:30'])->assertOk();
        $policy = json_decode(DB::table('workspace_settings')->where('key', 'policy')->value('payload'), true);
        $this->assertSame('60', $policy['retention']);
        $this->putJson('/api/policy', ['retention' => '999'])->assertUnprocessable();
        $this->putJson('/api/settings', ['server' => 'https://cctv.example.test', 'location' => 'Test office'])->assertOk();
        $this->putJson('/api/settings', ['server' => 'http://cctv.example.test'])->assertUnprocessable();
        $this->putJson('/api/settings', ['server' => 'https://user:secret@cctv.example.test'])->assertUnprocessable();
    }

    public function test_authenticated_media_supports_seeking_and_rejects_unknown_files(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/media/unknown.mp4')->assertNotFound();
        $this->withHeaders(['Range' => 'bytes=0-1023'])->get('/media/street.mp4')->assertStatus(206)->assertHeader('Content-Type','video/mp4');
    }

    public function test_meva_pilot_can_be_disabled_without_losing_incidents_or_legacy_recordings(): void
    {
        $this->actingAs(User::factory()->create());
        $controller = new \App\Http\Controllers\WorkspaceController;
        $this->assertCount(4, $controller->cameras());
        $this->assertSame(['REC-ROAD-001', 'REC-002', 'REC-CROWD-003'], array_column($controller->recordings(), 'id'));
        $this->get('/media/meva-g331.mp4')->assertNotFound();
        $this->artisan('sentra:meva on')->assertSuccessful();
        $record = collect($controller->recordings())->firstWhere('id', 'REC-MEVA-G331-20180307');
        $this->assertSame('2018-03-07', $record['date']);
        $this->assertSame('16:50:00', $record['start']);
        $this->assertTrue($record['sourceTime']);
        $data = ['title' => 'MEVA playback test', 'camera' => 'Terminal · MEVA G331', 'date' => '2018-03-07', 'record' => $record['id'], 'clip' => ['cameraId' => 'CAM-05', 'second' => 180.5]];
        $this->postJson('/api/incidents', $data)->assertCreated()->assertJsonPath('clip.second', 180.5);
        $data['clip']['second'] = 301;
        $this->postJson('/api/incidents', $data)->assertUnprocessable();
        $data['clip']['second'] = 5;
        $data['record'] = 'REC-002';
        $this->postJson('/api/incidents', $data)->assertUnprocessable();
        $this->withHeaders(['Range' => 'bytes=500000-501023'])->get('/media/meva-g331.mp4')->assertStatus(206)->assertHeader('Content-Type', 'video/mp4');
        $this->artisan('sentra:meva off')->assertSuccessful();
        $this->get('/')->assertOk()->assertSee('MEVA playback test');
        $this->assertCount(4, $controller->cameras());
        $this->assertDatabaseCount('incidents', 1);
        $this->get('/media/meva-g331.mp4')->assertNotFound();
        $this->artisan('sentra:meva invalid')->assertExitCode(1);
    }
}
