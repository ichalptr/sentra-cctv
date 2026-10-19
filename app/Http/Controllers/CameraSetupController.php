<?php

namespace App\Http\Controllers;

use App\Support\CctvStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CameraSetupController extends Controller
{
    public function save(Request $request, CctvStorage $storage): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'name' => ['required', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:80'],
            'player_url' => ['nullable', 'required_if:enabled,true', 'url:http,https', 'max:500'],
        ]);
        if ($data['enabled']) {
            $parts = parse_url($data['player_url']);
            abort_if(isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']), 422, 'Gunakan alamat player tanpa password, token, query, atau fragmen. Login gateway dilakukan pada player.');
            abort_if(preg_match('/\.(m3u8|mp4|sdp)\/?$/i', $parts['path'] ?? ''), 422, 'Masukkan halaman player gateway, bukan alamat file video atau playlist.');
        } else {
            $data['player_url'] = '';
        }
        $storage->saveSetting('camera_setup', $data);

        return response()->json(['ok' => true, 'message' => 'Pengaturan tersimpan. Pastikan tayangan benar-benar bergerak pada pratinjau.']);
    }
}
