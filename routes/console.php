<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sentra:meva {state : on atau off}', function () {
    $state = $this->argument('state');
    if (! in_array($state, ['on', 'off'], true)) {
        $this->error('Gunakan: php artisan sentra:meva on|off');
        return 1;
    }
    if (! \Illuminate\Support\Facades\DB::table('cameras')->where('key', 'CAM-05')->exists()) {
        $this->error('Jalankan php artisan migrate terlebih dahulu.');
        return 1;
    }
    if ($state === 'on' && ! is_file(storage_path('app/demo-media/meva-g331.mp4'))) {
        $this->error('File pilot meva-g331.mp4 belum tersedia.');
        return 1;
    }
    \Illuminate\Support\Facades\DB::table('workspace_settings')->updateOrInsert(['key' => 'meva_demo'], ['payload' => json_encode(['enabled' => $state === 'on']), 'updated_at' => now()]);
    $this->info('Pilot MEVA '.($state === 'on' ? 'aktif' : 'nonaktif').'. Akun dan catatan insiden tetap tersimpan.');
    return 0;
})->purpose('Aktifkan/nonaktifkan satu klip CCTV uji tanpa menghapus data');
