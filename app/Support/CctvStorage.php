<?php

namespace App\Support;

use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CctvStorage
{
    public function settings(): array
    {
        return json_decode(DB::table('workspace_settings')->where('key', 'storage_setup')->value('payload') ?? 'null', true) ?? [
            'configured' => false, 'source' => 'demo', 'source_path' => '', 'destination' => 'test',
            'destination_path' => '', 'camera_name' => 'Kamera 1', 'retention' => 30, 'schedule' => '02:00', 'automatic' => false, 'interval' => 15,
        ];
    }

    public function saveSetting(string $key, array $value): void
    {
        DB::table('workspace_settings')->updateOrInsert(['key' => $key], ['payload' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
    }

    public function inside(string $path, string $root): bool
    {
        $normalize = fn (string $p): string => strtolower(str_replace('\\', '/', rtrim($p, '/\\')));

        return str_starts_with($normalize($path).'/', $normalize($root).'/');
    }

    public function roots(array $settings): array
    {
        $source = realpath($settings['source'] === 'demo' ? config('cctv.demo_path') : $settings['source_path']);
        if ($settings['destination'] === 'test') {
            $test = config('cctv.test_backup_path');
            if (! is_dir($test) && ! mkdir($test, 0770, true) && ! is_dir($test)) {
                throw new RuntimeException('Folder backup uji tidak dapat dibuat.');
            }
            $destination = realpath($test);
        } else {
            $destination = realpath($settings['destination_path']);
        }
        if (! $source || ! is_dir($source) || ! is_readable($source)) {
            throw new RuntimeException('Folder rekaman tidak ditemukan atau tidak dapat dibaca dari komputer server.');
        }
        if (! $destination || ! is_dir($destination) || ! is_writable($destination)) {
            throw new RuntimeException('Folder tujuan tidak tersedia atau tidak memiliki izin tulis.');
        }
        if ($this->inside($destination, $source) || $this->inside($source, $destination) || $this->inside($destination, public_path())) {
            throw new RuntimeException('Folder sumber dan tujuan harus terpisah, dan backup tidak boleh berada di folder public.');
        }

        return [$source, $destination];
    }

    public function configure(array $settings): array
    {
        if ($settings['source'] === 'folder' && DB::table('cameras')->where('key', '!=', 'CCTV-01')->get()->contains(fn ($camera) => (json_decode($camera->payload, true)['name'] ?? '') === $settings['camera_name'])) {
            throw ValidationException::withMessages(['camera_name' => 'Nama kamera sudah digunakan. Pilih nama yang berbeda.']);
        }

        try {
            [$source, $destination] = $this->roots($settings);
            $probe = $destination.DIRECTORY_SEPARATOR.'.sentra-probe-'.Str::uuid();
            $handle = @fopen($probe, 'x');
            if (! $handle) {
                throw new RuntimeException('Uji tulis folder tujuan gagal. Periksa izin akses.');
            }
            fwrite($handle, 'sentra');
            fclose($handle);
            unlink($probe);
        } catch (RuntimeException $error) {
            throw ValidationException::withMessages(['destination_path' => $error->getMessage()]);
        }
        $settings['configured'] = true;
        $settings['source_path'] = $settings['source'] === 'folder' ? $source : '';
        $settings['destination_path'] = $settings['destination'] === 'folder' ? $destination : '';
        $this->saveSetting('storage_setup', $settings);
        if ($settings['source'] === 'folder') {
            DB::table('cameras')->updateOrInsert(['key' => 'CCTV-01'], ['payload' => json_encode([
                'id' => 'CCTV-01', 'name' => $settings['camera_name'], 'zone' => 'Arsip dari folder rekaman',
                'online' => true, 'mode' => 'archive', 'seconds' => 86400, 'video' => '',
            ])]);
        }

        return $this->status();
    }

    public function path(object $archive): string
    {
        if (! Str::isUuid($archive->id)) {
            throw new RuntimeException('Identitas salinan tidak valid.');
        }
        $root = realpath($archive->destination_root);
        $folder = $root ? realpath($root.DIRECTORY_SEPARATOR.'sentra-videos') : false;
        if (! $root || ! $folder || ! $this->inside($folder, $root)) {
            throw new RuntimeException('Penyimpanan backup tidak tersedia.');
        }
        $path = $folder.DIRECTORY_SEPARATOR.$archive->id.'.mp4';
        if (is_link($path) || (file_exists($path) && ! $this->inside(realpath($path), $folder))) {
            throw new RuntimeException('Lokasi salinan tidak valid.');
        }

        return $path;
    }

    public function protected(string $record): bool
    {
        return DB::table('incidents')->where('record_key', $record)->exists();
    }

    public function status(): array
    {
        $archives = DB::table('video_archives')->whereNull('expired_at')->latest('created_at')->get()->map(fn ($r): array => [
            'id' => $r->id, 'cameraId' => $r->camera_key, 'record' => $r->record_key, 'name' => $r->file_name, 'bytes' => $r->bytes,
            'verifiedAt' => $r->verified_at, 'restoreTestedAt' => $r->restore_tested_at,
            'protected' => $this->protected($r->record_key), 'playUrl' => route('archives.play', $r->id),
        ])->all();
        $lastRun = json_decode(DB::table('workspace_settings')->where('key', 'backup_last_run')->value('payload') ?? 'null', true);
        $scheduler = json_decode(DB::table('workspace_settings')->where('key', 'scheduler_heartbeat')->value('payload') ?? 'null', true);

        return ['settings' => $this->settings(), 'archives' => $archives, 'lastRun' => $lastRun,
            'schedulerActive' => isset($scheduler['at']) && now()->diffInSeconds(Carbon::parse($scheduler['at']), true) < 180,
            'schedulerSeenAt' => $scheduler['at'] ?? null];
    }

    private function sources(array $settings, string $source): array
    {
        if ($settings['source'] === 'demo') {
            $controller = app(WorkspaceController::class);
            $cameras = $controller->cameras();

            return collect($controller->recordings())->filter(fn ($r) => ! str_starts_with($r['id'], 'ARC-'))->map(fn ($r): array => [
                'path' => $source.DIRECTORY_SEPARATOR.basename($cameras[$r['cam']]['video']),
                'camera' => $cameras[$r['cam']]['id'], 'record' => $r['id'],
            ])->all();
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && ! $file->isLink() && strtolower($file->getExtension()) === 'mp4' && $this->inside($file->getRealPath(), $source)) {
                $files[] = ['path' => $file->getRealPath(), 'camera' => 'CCTV-01', 'record' => null];
                if (count($files) > 500) {
                    throw new RuntimeException('Lebih dari 500 file. Pilih folder per kamera/periode yang lebih kecil.');
                }
            }
        }

        return $files;
    }

    public function run(): array
    {
        $lock = Cache::lock('sentra-video-backup', 3600);
        if (! $lock->get()) {
            throw new RuntimeException('Backup lain sedang berlangsung. Tunggu hingga selesai.');
        }
        $report = ['at' => now()->toIso8601String(), 'status' => 'success', 'copied' => 0, 'verified' => 0, 'pending' => 0, 'expired' => 0, 'errors' => []];
        try {
            $settings = $this->settings();
            if (! ($settings['configured'] ?? false)) {
                throw new RuntimeException('Selesaikan pengaturan penyimpanan terlebih dahulu.');
            }
            [$source, $destination] = $this->roots($settings);
            $folder = $destination.DIRECTORY_SEPARATOR.'sentra-videos';
            if (! is_dir($folder) && ! mkdir($folder, 0770) && ! is_dir($folder)) {
                throw new RuntimeException('Folder salinan tidak dapat dibuat.');
            }
            if (! $this->inside(realpath($folder), $destination)) {
                throw new RuntimeException('Folder salinan berada di luar tujuan yang dipilih.');
            }
            foreach ($this->sources($settings, $source) as $item) {
                try {
                    $path = $item['path'];
                    clearstatcache(true, $path);
                    if (! is_file($path) || is_link($path) || ! $this->inside(realpath($path), $source)) {
                        throw new RuntimeException('File sumber tidak tersedia.');
                    }
                    if (filemtime($path) > time() - 60) {
                        $report['pending']++;

                        continue;
                    }
                    $size = filesize($path);
                    $modified = filemtime($path);
                    if (! $size) {
                        throw new RuntimeException('File kosong.');
                    }
                    $hash = hash_file('sha256', $path);
                    if (! $hash) {
                        throw new RuntimeException('File tidak dapat dibaca.');
                    }
                    $existing = DB::table('video_archives')->where('source_path', $path)->where('sha256', $hash)->where('destination_root', $destination)->first();
                    if ($existing?->expired_at) {
                        continue;
                    }
                    if ($existing && is_file($this->path($existing)) && hash_file('sha256', $this->path($existing)) === $hash) {
                        DB::table('video_archives')->where('id', $existing->id)->update(['verified_at' => now()]);
                        $report['verified']++;

                        continue;
                    }
                    if ($report['copied'] >= 10) {
                        $report['pending']++;

                        continue;
                    }
                    $id = $existing->id ?? (string) Str::uuid();
                    $target = $folder.DIRECTORY_SEPARATOR.$id.'.mp4';
                    if (is_link($target)) {
                        throw new RuntimeException('Tujuan salinan tidak valid.');
                    }
                    $temporary = $folder.DIRECTORY_SEPARATOR.$id.'.'.Str::random(8).'.part';
                    try {
                        if (! copy($path, $temporary)) {
                            throw new RuntimeException('Penyalinan gagal. Periksa ruang disk dan izin.');
                        }
                        clearstatcache(true, $path);
                        if (filesize($path) !== $size || filemtime($path) !== $modified || hash_file('sha256', $temporary) !== $hash) {
                            throw new RuntimeException('File berubah saat disalin atau salinan tidak cocok. Akan dicoba lagi.');
                        }
                        if (file_exists($target) && ! unlink($target)) {
                            throw new RuntimeException('Salinan lama tidak dapat diperbaiki.');
                        }
                        if (! rename($temporary, $target)) {
                            throw new RuntimeException('Salinan tidak dapat difinalisasi.');
                        }
                    } finally {
                        if (is_file($temporary)) {
                            unlink($temporary);
                        }
                    }
                    DB::table('video_archives')->updateOrInsert(['id' => $id], [
                        'record_key' => $existing->record_key ?? $item['record'] ?? 'ARC-'.$id,
                        'camera_key' => $item['camera'], 'source_path' => $path, 'file_name' => basename($path),
                        'bytes' => $size, 'sha256' => $hash, 'destination_root' => $destination,
                        'file_modified_at' => date('Y-m-d H:i:s', $modified), 'verified_at' => now(),
                        'expires_at' => now()->addDays((int) $settings['retention']), 'created_at' => $existing->created_at ?? now(), 'updated_at' => now(),
                    ]);
                    $report['copied']++;
                } catch (\Throwable $error) {
                    $report['errors'][] = basename($item['path']).': '.($error instanceof RuntimeException ? $error->getMessage() : 'Gagal memproses file. Periksa izin dan ruang penyimpanan.');
                }
            }
            // Only app-owned backup copies expire. Original/NVR files are never removed.
            foreach (DB::table('video_archives')->whereNull('expired_at')->where('created_at', '<', now()->subDays((int) $settings['retention']))->get() as $archive) {
                if ($this->protected($archive->record_key)) {
                    continue;
                }
                try {
                    $path = $this->path($archive);
                    if (is_file($path) && ! unlink($path)) {
                        throw new RuntimeException('Gagal menghapus salinan kedaluwarsa.');
                    }
                    DB::table('video_archives')->where('id', $archive->id)->update(['expired_at' => now()]);
                    $report['expired']++;
                } catch (\Throwable) {
                    $report['errors'][] = 'Retensi satu salinan gagal; periksa penyimpanan backup.';
                }
            }
            if ($report['errors']) {
                $report['status'] = 'failed';
            } elseif ($report['pending']) {
                $report['status'] = 'pending';
            }
        } catch (\Throwable $error) {
            $report['status'] = 'failed';
            $report['errors'][] = $error instanceof RuntimeException ? $error->getMessage() : 'Backup gagal. Periksa izin, ruang disk, dan koneksi penyimpanan.';
        } finally {
            $report['finishedAt'] = now()->toIso8601String();
            $this->saveSetting('backup_last_run', $report);
            $lock->release();
        }

        return $report;
    }

    public function restoreTest(string $id): array
    {
        $archive = DB::table('video_archives')->where('id', $id)->whereNull('expired_at')->first();
        abort_unless($archive, 404);
        $source = $this->path($archive);
        if (! is_file($source)) {
            throw new RuntimeException('Salinan tidak ditemukan. Sambungkan penyimpanan tujuan.');
        }
        $folder = storage_path('app/restore-tests');
        if (! is_dir($folder)) {
            mkdir($folder, 0770, true);
        }
        $temporary = $folder.DIRECTORY_SEPARATOR.Str::uuid().'.mp4';
        try {
            if (! copy($source, $temporary) || hash_file('sha256', $temporary) !== $archive->sha256) {
                throw new RuntimeException('Uji pemulihan gagal: isi salinan tidak cocok. Jalankan backup ulang untuk memperbaiki.');
            }
            DB::table('video_archives')->where('id', $id)->update(['restore_tested_at' => now()]);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return ['ok' => true, 'message' => 'File berhasil dipulihkan ke folder uji dan cocok dengan sumber saat backup. File uji sementara dibersihkan.'];
    }

    public function tick(): void
    {
        $this->saveSetting('scheduler_heartbeat', ['at' => now()->toIso8601String()]);
        $settings = $this->settings();
        if (! ($settings['configured'] ?? false) || ! ($settings['automatic'] ?? false)) {
            return;
        }
        $interval = (int) ($settings['interval'] ?? 1440);
        if ($interval === 1440 && now('Asia/Jakarta')->format('H:i') < $settings['schedule']) {
            return;
        }
        $last = json_decode(DB::table('workspace_settings')->where('key', 'backup_last_run')->value('payload') ?? 'null', true);
        if (($last['status'] ?? '') === 'success') {
            $at = Carbon::parse($last['at'])->timezone('Asia/Jakarta');
            if (($interval === 1440 && $at->isToday()) || ($interval !== 1440 && $at->diffInMinutes(now(), true) < $interval)) {
                return;
            }
        }
        $this->run();
    }
}
