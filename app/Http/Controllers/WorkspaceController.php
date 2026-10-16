<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function cameras(): array
    {
        $pilot = json_decode(DB::table('workspace_settings')->where('key', 'meva_demo')->value('payload') ?? '{}', true);
        return DB::table('cameras')->orderBy('key')->get()->filter(fn ($r) => $r->key !== 'CAM-05' || ($pilot['enabled'] ?? false))->map(function ($r) {
            $c = json_decode($r->payload, true);
            if (isset($c['video'])) {
                $c['video'] = route('media', ['file' => basename($c['video'])]);
            }

return $c;
        })->values()->all();
    }

    public function recordings(): array
    {
        $legacy = ['CAM-01' => 'REC-ROAD-001', 'CAM-02' => 'REC-002', 'CAM-03' => 'REC-CROWD-003'];
        $records = [];
        foreach ($this->cameras() as $index => $c) {
            if (! $c['online']) continue;
            $records[] = ['id' => $c['recordId'] ?? $legacy[$c['id']], 'cam' => $index, 'start' => $c['recordedStart'] ?? '08.00', 'date' => $c['recordedDate'] ?? now('Asia/Jakarta')->toDateString(), 'sourceTime' => isset($c['recordedDate']), 'protected' => $c['id'] === 'CAM-02'];
        }
        return $records;
    }

    private function payload($r): array
    {
        $c = json_decode(DB::table('cameras')->where('key', $r->camera_key)->value('payload') ?? '{}', true);

        return ['id' => 'INC-'.str_pad($r->id, 6, '0', STR_PAD_LEFT), 'dbId' => $r->id, 'title' => $r->title, 'camera' => $c['name'], 'date' => $r->date, 'note' => $r->note ?? '', 'record' => $r->record_key, 'protected' => (bool) $r->protected, 'clip' => $r->clip_second !== null ? ['cameraId' => $r->camera_key, 'second' => (float) $r->clip_second] : null];
    }

    public function index()
    {
        $s = DB::table('workspace_settings')->pluck('payload', 'key')->map(fn ($v) => json_decode($v, true));
        $boot = ['cameras' => $this->cameras(), 'records' => $this->recordings(), 'incidents' => DB::table('incidents')->latest('id')->get()->map(fn ($r) => $this->payload($r))->all(), 'policy' => $s['policy'] ?? null, 'settings' => $s['settings'] ?? [],
            'urls' => ['incidents' => route('incidents.store'), 'protect' => route('incidents.protect', ['id' => '__ID__']), 'policy' => route('policy.save'), 'settings' => route('settings.save')]];

        return response()->view('dashboard', compact('boot'))->header('Cache-Control', 'no-store');
    }

    public function storeIncident(Request $r)
    {
        $cams = $this->cameras();
        $d = $r->validate(['title' => ['required', 'string', 'max:120'], 'camera' => ['required', Rule::in(array_column($cams, 'name'))], 'date' => ['required', 'date_format:Y-m-d'], 'note' => ['nullable', 'string', 'max:1000'], 'record' => ['nullable', 'string', 'max:80'], 'clip' => ['nullable', 'array:cameraId,second'], 'clip.cameraId' => ['required_with:clip', 'string'], 'clip.second' => ['required_with:clip', 'numeric', 'min:0', 'max:86400']]);
        $c = collect($cams)->firstWhere('name', $d['camera']);
        if (! empty($d['clip'])) {
            $record = collect($this->recordings())->firstWhere('id', $d['record'] ?? '');
            abort_unless($c['online'] && $record && $cams[$record['cam']]['id'] === $c['id'] && $d['clip']['cameraId'] === $c['id'] && $d['clip']['second'] <= ($c['seconds'] ?? 0), 422, 'Posisi rekaman tidak valid.');
        }
        $id = DB::table('incidents')->insertGetId(['user_id' => $r->user()->id, 'camera_key' => $c['id'], 'title' => $d['title'], 'date' => $d['date'], 'note' => $d['note'] ?? '', 'record_key' => $d['record'] ?? null, 'clip_second' => $d['clip']['second'] ?? null, 'protected' => true, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json($this->payload(DB::table('incidents')->find($id)), 201);
    }

    public function protect(int $id)
    {
        abort_unless(DB::table('incidents')->where('id', $id)->exists(), 404);
        DB::table('incidents')->where('id', $id)->update(['protected' => true, 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function policy(Request $r)
    {
        $d = $r->validate(['retention' => ['required', Rule::in([7, 14, 30, 60, 90])], 'backupRetention' => ['required', Rule::in([30, 60, 90])], 'destination' => ['required', 'string', 'max:100'], 'schedule' => ['required', 'date_format:H:i']]);

        return $this->saveSetting('policy', $d);
    }

    public function settings(Request $r)
    {
        $d = $r->validate(['server' => ['required', 'url:https', 'max:250'], 'location' => ['nullable', 'string', 'max:80']]);
        abort_if(parse_url($d['server'], PHP_URL_USER) !== null || parse_url($d['server'], PHP_URL_PASS) !== null, 422, 'Jangan simpan kredensial dalam URL.');

        return $this->saveSetting('settings', $d);
    }

    private function saveSetting(string $k, array $d)
    {
        DB::table('workspace_settings')->updateOrInsert(['key' => $k], ['payload' => json_encode($d), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function media(string $file)
    {
        $allowed = array_map(fn ($c) => basename($c['video'] ?? ''), $this->cameras());
        abort_unless(preg_match('/\A[a-z0-9-]+\.mp4\z/', $file) && in_array($file, $allowed, true), 404);

        return response()->file(storage_path('app/demo-media/'.$file),['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store']);
    }
}
