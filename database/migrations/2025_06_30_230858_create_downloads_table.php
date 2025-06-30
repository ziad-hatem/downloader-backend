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
        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->string('youtube_url');
            $table->string('video_id');
            $table->string('video_title')->nullable();
            $table->string('video_thumbnail')->nullable();
            $table->integer('video_duration')->nullable();
            $table->string('format');
            $table->string('quality')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('download_path')->nullable();
            $table->string('download_url')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->ipAddress('ip_address');
            $table->string('user_agent')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['video_id', 'format']);
            $table->index(['status']);
            $table->index(['ip_address']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('downloads');
    }
};
