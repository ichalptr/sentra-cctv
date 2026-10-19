<?php

namespace App\Http\Controllers;

use App\Support\CctvStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StorageController extends Controller
{
    public function status(CctvStorage $storage): JsonResponse
    {
        return response()->json($storage->status())->header('Cache-Control', 'no-store');
    }

    public function configure(Request $request, CctvStorage $storage): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(['demo', 'folder'])],
            'source_path' => ['nullable', 'required_if:source,folder', 'string', 'max:500'],
            'destination' => ['required', Rule::in(['test', 'folder'])],
            'destination_path' => ['nullable', 'required_if:destination,folder', 'string', 'max:500'],
            'camera_name' => ['required', 'string', 'max:80'],
            'retention' => ['required', Rule::in([7, 14, 30, 60, 90])],
            'schedule' => ['required', 'date_format:H:i'],
            'automatic' => ['required', 'boolean'],
            'interval' => ['nullable', Rule::in([5, 15, 60, 1440])],
        ]);

        $data['interval'] = $data['interval'] ?? 15;

        return response()->json($storage->configure($data));
    }

    public function run(CctvStorage $storage): JsonResponse
    {
        try {
            $report = $storage->run();
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 409);
        }

        return response()->json(['report' => $report, 'storage' => $storage->status()]);
    }

    public function restore(string $id, CctvStorage $storage): JsonResponse
    {
        try {
            return response()->json($storage->restoreTest($id));
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }
    }

    public function play(string $id, CctvStorage $storage): BinaryFileResponse
    {
        $archive = DB::table('video_archives')->where('id', $id)->whereNull('expired_at')->first();
        abort_unless($archive, 404);
        try {
            $path = $storage->path($archive);
        } catch (RuntimeException) {
            abort(404, 'Penyimpanan backup tidak tersedia.');
        }
        abort_unless(is_file($path), 404, 'Salinan tidak ditemukan.');
        abort_unless(hash_file('sha256', $path) === $archive->sha256, 409, 'Salinan tidak cocok. Jalankan backup ulang untuk memperbaiki.');

        return response()->file($path, ['Content-Type' => 'video/mp4', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
