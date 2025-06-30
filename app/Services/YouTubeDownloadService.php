<?php

namespace App\Services;

use App\Models\Download;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class YouTubeDownloadService
{
    private string $ytDlpPath;
    private string $downloadPath;
    private array $supportedFormats;

    public function __construct()
    {
        $this->ytDlpPath = config('youtube.yt_dlp_path', 'yt-dlp');
        $this->downloadPath = storage_path('app/downloads');
        $this->supportedFormats = config('youtube.supported_formats', [
            'mp4' => ['ext' => 'mp4', 'format' => 'best[ext=mp4]'],
            'mp3' => ['ext' => 'mp3', 'format' => 'bestaudio[ext=m4a]/bestaudio/best'],
            'webm' => ['ext' => 'webm', 'format' => 'best[ext=webm]'],
        ]);

        // Ensure download directory exists
        if (!is_dir($this->downloadPath)) {
            mkdir($this->downloadPath, 0755, true);
        }
    }

    /**
     * Extract video information from YouTube URL.
     */
    public function getVideoInfo(string $url): array
    {
        $process = new Process([
            $this->ytDlpPath,
            '--dump-json',
            '--no-check-certificate',
            $url
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('Failed to extract video info', [
                'url' => $url,
                'error' => $process->getErrorOutput()
            ]);
            throw new \Exception('Failed to extract video information: ' . $process->getErrorOutput());
        }

        $output = $process->getOutput();
        $videoInfo = json_decode($output, true);

        if (!$videoInfo) {
            throw new \Exception('Invalid video information received');
        }

        return [
            'id' => $videoInfo['id'] ?? null,
            'title' => $videoInfo['title'] ?? 'Unknown',
            'description' => $videoInfo['description'] ?? '',
            'thumbnail' => $videoInfo['thumbnail'] ?? null,
            'duration' => $videoInfo['duration'] ?? 0,
            'uploader' => $videoInfo['uploader'] ?? 'Unknown',
            'upload_date' => $videoInfo['upload_date'] ?? null,
            'view_count' => $videoInfo['view_count'] ?? 0,
            'like_count' => $videoInfo['like_count'] ?? 0,
            'formats' => $this->extractAvailableFormats($videoInfo['formats'] ?? []),
        ];
    }

    /**
     * Download video in specified format and quality.
     */
    public function downloadVideo(Download $download): array
    {
        $url = $download->youtube_url;
        $format = $download->format;
        $quality = $download->quality;

        // Create filename
        $safeTitle = $this->sanitizeFilename($download->video_title ?? 'video');
        $filename = "{$download->video_id}_{$safeTitle}.{$this->supportedFormats[$format]['ext']}";
        $outputPath = $this->downloadPath . '/' . $filename;

        // Build yt-dlp command
        $command = [
            $this->ytDlpPath,
            '--no-check-certificate',
            '--output', $outputPath,
        ];

        // Add format selection
        if ($format === 'mp3') {
            $command = array_merge($command, [
                '--extract-audio',
                '--audio-format', 'mp3',
                '--audio-quality', '192K',
            ]);
        } else {
            $formatSelector = $this->buildFormatSelector($format, $quality);
            $command = array_merge($command, ['--format', $formatSelector]);
        }

        $command[] = $url;

        Log::info('Starting download', [
            'download_id' => $download->id,
            'command' => implode(' ', $command),
        ]);

        $process = new Process($command);
        $process->setTimeout(3600); // 1 hour timeout

        $process->run();

        if (!$process->isSuccessful()) {
            $error = $process->getErrorOutput();
            Log::error('Download failed', [
                'download_id' => $download->id,
                'error' => $error,
            ]);
            throw new ProcessFailedException($process);
        }

        // Check if file was created
        if (!file_exists($outputPath)) {
            throw new \Exception('Download completed but file not found');
        }

        $fileSize = filesize($outputPath);

        Log::info('Download completed', [
            'download_id' => $download->id,
            'file_path' => $outputPath,
            'file_size' => $fileSize,
        ]);

        return [
            'file_path' => $outputPath,
            'file_size' => $fileSize,
            'filename' => $filename,
        ];
    }

    /**
     * Get available qualities for a format.
     */
    public function getAvailableQualities(string $url, string $format): array
    {
        try {
            $videoInfo = $this->getVideoInfo($url);
            $formats = $videoInfo['formats'];

            $qualities = [];
            foreach ($formats as $formatInfo) {
                if ($this->matchesFormat($formatInfo, $format)) {
                    if (!empty($formatInfo['height'])) {
                        $quality = $formatInfo['height'] . 'p';
                        if (!in_array($quality, $qualities)) {
                            $qualities[] = $quality;
                        }
                    }
                }
            }

            // Sort qualities
            usort($qualities, function($a, $b) {
                return (int)$a <=> (int)$b;
            });

            return $qualities;
        } catch (\Exception $e) {
            Log::error('Failed to get available qualities', [
                'url' => $url,
                'format' => $format,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Validate YouTube URL.
     */
    public function validateUrl(string $url): bool
    {
        $pattern = '/^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/v\/)[a-zA-Z0-9_-]{11}(&.*)?$/';
        return preg_match($pattern, $url) === 1;
    }

    /**
     * Extract video ID from URL.
     */
    public function extractVideoId(string $url): ?string
    {
        preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/v\/)([a-zA-Z0-9_-]{11})/', $url, $matches);
        return $matches[1] ?? null;
    }

    /**
     * Check if yt-dlp is available.
     */
    public function checkYtDlpAvailability(): bool
    {
        $process = new Process([$this->ytDlpPath, '--version']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Get yt-dlp version.
     */
    public function getYtDlpVersion(): ?string
    {
        $process = new Process([$this->ytDlpPath, '--version']);
        $process->run();

        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        return null;
    }

    /**
     * Extract available formats from video info.
     */
    private function extractAvailableFormats(array $formats): array
    {
        $available = [];

        foreach ($formats as $format) {
            $ext = $format['ext'] ?? '';
            $height = $format['height'] ?? null;

            if (in_array($ext, ['mp4', 'webm']) && $height) {
                $quality = $height . 'p';
                if (!isset($available[$ext])) {
                    $available[$ext] = [];
                }
                if (!in_array($quality, $available[$ext])) {
                    $available[$ext][] = $quality;
                }
            }
        }

        // Add audio formats
        $available['mp3'] = ['audio'];

        return $available;
    }

    /**
     * Build format selector for yt-dlp.
     */
    private function buildFormatSelector(string $format, ?string $quality): string
    {
        if ($format === 'mp3') {
            return 'bestaudio[ext=m4a]/bestaudio/best';
        }

        if ($quality) {
            $height = (int) str_replace('p', '', $quality);
            return "best[height<={$height}][ext={$format}]/best[ext={$format}]/best";
        }

        return "best[ext={$format}]/best";
    }

    /**
     * Check if format info matches requested format.
     */
    private function matchesFormat(array $formatInfo, string $requestedFormat): bool
    {
        $ext = $formatInfo['ext'] ?? '';

        if ($requestedFormat === 'mp3') {
            return in_array($ext, ['m4a', 'mp3', 'aac']);
        }

        return $ext === $requestedFormat;
    }

    /**
     * Sanitize filename for safe storage.
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove or replace invalid characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        // Remove multiple consecutive underscores
        $filename = preg_replace('/_+/', '_', $filename);
        // Trim underscores from start and end
        $filename = trim($filename, '_');
        // Limit length
        return substr($filename, 0, 100);
    }

    /**
     * Clean up old download files.
     */
    public function cleanupOldFiles(int $daysOld = 7): int
    {
        $cleaned = 0;
        $cutoffTime = time() - ($daysOld * 24 * 60 * 60);

        $files = glob($this->downloadPath . '/*');

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $cleaned++;
                    Log::info('Cleaned up old download file', ['file' => $file]);
                }
            }
        }

        return $cleaned;
    }
}
