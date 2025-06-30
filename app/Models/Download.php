<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Download extends Model
{
    use HasFactory;

    protected $fillable = [
        'youtube_url',
        'video_id',
        'video_title',
        'video_thumbnail',
        'video_duration',
        'format',
        'quality',
        'status',
        'download_path',
        'download_url',
        'file_size',
        'ip_address',
        'user_agent',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'video_duration' => 'integer',
        'file_size' => 'integer',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    // Supported formats
    const FORMATS = [
        'mp4' => 'MP4 Video',
        'mp3' => 'MP3 Audio',
        'webm' => 'WebM Video',
        'avi' => 'AVI Video',
    ];

    // Supported qualities
    const QUALITIES = [
        '144p' => '144p',
        '240p' => '240p',
        '360p' => '360p',
        '480p' => '480p',
        '720p' => '720p',
        '1080p' => '1080p',
        '1440p' => '1440p',
        '2160p' => '2160p (4K)',
    ];

    /**
     * Get the download's formatted file size.
     */
    protected function formattedFileSize(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->file_size ? $this->formatBytes($this->file_size) : null,
        );
    }

    /**
     * Get the download's formatted duration.
     */
    protected function formattedDuration(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->video_duration ? $this->formatDuration($this->video_duration) : null,
        );
    }

    /**
     * Check if download is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if download is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if download is in progress.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Mark download as started.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark download as completed.
     */
    public function markAsCompleted(string $downloadPath, int $fileSize = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'download_path' => $downloadPath,
            'file_size' => $fileSize,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark download as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Scope to get downloads by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get recent downloads.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Format duration in seconds to human readable format.
     */
    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
