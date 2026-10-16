<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('cameras')->updateOrInsert(['key' => 'CAM-05'], ['payload' => json_encode([
            'id' => 'CAM-05', 'name' => 'Terminal · MEVA G331', 'zone' => 'Dataset uji · Kamera tetap / area tunggu',
            'online' => true, 'video' => 'media/meva-g331.mp4', 'seconds' => 300.166667,
            'provider' => 'MEVA', 'source' => 'https://mevadata.org/', 'sourceTitle' => 'Kitware Inc. / IARPA · MEVA G331',
            'sourceFile' => 'https://mevadata-public-01.s3.amazonaws.com/drops-123-r13/2018-03-07/16/2018-03-07.16-50-00.16-55-00.bus.G331.r13.avi',
            'license' => 'https://mevadata.org/resources/MEVA-data-license.txt', 'licenseName' => 'CC BY 4.0',
            'changes' => 'Video AVI dikonversi ke MP4 H.264 30 fps dengan normalisasi timestamp untuk playback browser. Adegan dataset diperagakan untuk riset.',
            'recordId' => 'REC-MEVA-G331-20180307', 'recordedDate' => '2018-03-07', 'recordedStart' => '16:50:00',
        ], JSON_UNESCAPED_SLASHES)]);
        DB::table('workspace_settings')->insertOrIgnore(['key' => 'meva_demo', 'payload' => '{"enabled":false}', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Keep incident references valid; disabling the pilot is the safe rollback.
        DB::table('workspace_settings')->where('key', 'meva_demo')->update(['payload' => '{"enabled":false}', 'updated_at' => now()]);
    }
};
