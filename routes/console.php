<?php

use App\Support\CctvStorage;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sentra:meva {state : on atau off}', function () {
    $state = $this->argument('state');
    if (! in_array($state, ['on', 'off'], true)) {
        $this->error('Gunakan: php artisan sentra:meva on|off');

        return 1;
    }
    if (! DB::table('cameras')->where('key', 'CAM-05')->exists()) {
        $this->error('Jalankan php artisan migrate terlebih dahulu.');

        return 1;
    }
    if ($state === 'on' && ! is_file(storage_path('app/demo-media/meva-g331.mp4'))) {
        $this->error('File pilot meva-g331.mp4 belum tersedia.');

        return 1;
    }
    DB::table('workspace_settings')->updateOrInsert(['key' => 'meva_demo'], ['payload' => json_encode(['enabled' => $state === 'on']), 'updated_at' => now()]);
    $this->info('Pilot MEVA '.($state === 'on' ? 'aktif' : 'nonaktif').'. Akun dan catatan insiden tetap tersimpan.');

    return 0;
})->purpose('Aktifkan/nonaktifkan satu klip CCTV uji tanpa menghapus data');

Artisan::command('sentra:backup {--scheduled} {--demo-setup}', function () {
    $storage = app(CctvStorage::class);
    if ($this->option('demo-setup')) {
        if ($storage->settings()['configured'] ?? false) {
            $this->error('Pengaturan sudah ada. Gunakan halaman Pengaturan untuk mengubah sumber.');

            return 1;
        }
        $storage->configure(['source' => 'demo', 'source_path' => '', 'destination' => 'test', 'destination_path' => '', 'camera_name' => 'Kamera 1', 'retention' => 30, 'schedule' => '02:00', 'automatic' => false, 'interval' => 15]);
    }
    if ($this->option('scheduled')) {
        $storage->tick();
        $this->info('Jadwal diperiksa.');

        return 0;
    }
    $report = $storage->run();
    $this->info(json_encode($report, JSON_UNESCAPED_UNICODE));

    return $report['status'] === 'failed' ? 1 : 0;
})->purpose('Salin dan verifikasi rekaman CCTV sesuai pengaturan');

Schedule::command('sentra:backup --scheduled')->everyMinute()->withoutOverlapping(60);
