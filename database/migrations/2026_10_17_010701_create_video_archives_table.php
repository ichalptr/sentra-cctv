<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('video_archives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('record_key');
            $table->string('camera_key');
            $table->text('source_path');
            $table->string('file_name');
            $table->unsignedBigInteger('bytes');
            $table->string('sha256', 64);
            $table->text('destination_root');
            $table->timestamp('file_modified_at');
            $table->timestamp('verified_at');
            $table->timestamp('expires_at');
            $table->timestamp('restore_tested_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_archives');
    }
};
