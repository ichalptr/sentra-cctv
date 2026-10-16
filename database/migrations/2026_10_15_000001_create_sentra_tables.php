<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cameras', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('payload');
        });
        Schema::create('incidents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('camera_key');
            $t->foreign('camera_key')->references('key')->on('cameras');
            $t->string('title', 120);
            $t->date('date');
            $t->text('note')->nullable();
            $t->string('record_key')->nullable();
            $t->decimal('clip_second', 10, 3)->nullable();
            $t->boolean('protected')->default(true);
            $t->timestamps();
        });
        Schema::create('workspace_settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('payload');
            $t->timestamps();
        });
        foreach (json_decode(file_get_contents(database_path('seeders/cameras.json')), true) as $c) {
            DB::table('cameras')->insert(['key' => $c['id'], 'payload' => json_encode($c)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('workspace_settings');
        Schema::dropIfExists('cameras');
    }
};
