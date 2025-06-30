<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Download;
use App\Services\YouTubeDownloadService;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessYouTubeDownload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Download $download;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 3600; // 1 hour

    /**
     * Create a new job instance.
     */
    public function __construct(Download $download)
    {
        $this->download = $download;
        $this->onQueue('downloads');
    }

    /**
     * Execute the job.
     */
    public function handle(YouTubeDownloadService $youtubeService): void
    {
        Log::info('Starting YouTube download job', [
            'download_id' => $this->download->id,
            'video_id' => $this->download->video_id,
            'format' => $this->download->format,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Mark download as processing
            $this->download->markAsProcessing();

            // Perform the download
            $result = $youtubeService->downloadVideo($this->download);

            // Mark as completed
            $this->download->markAsCompleted($result['file_path'], $result['file_size']);

            Log::info('YouTube download job completed successfully', [
                'download_id' => $this->download->id,
                'file_size' => $result['file_size'],
                'file_path' => $result['file_path'],
            ]);

        } catch (Exception $e) {
            Log::error('YouTube download job failed', [
                'download_id' => $this->download->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries,
            ]);

            // If this is the final attempt, mark as failed
            if ($this->attempts() >= $this->tries) {
                $this->download->markAsFailed($e->getMessage());

                Log::error('YouTube download job permanently failed', [
                    'download_id' => $this->download->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Re-throw the exception to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('YouTube download job failed permanently', [
            'download_id' => $this->download->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Ensure the download is marked as failed
        if (!$this->download->isFailed()) {
            $this->download->markAsFailed($exception->getMessage());
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 300]; // 30 seconds, 1 minute, 5 minutes
    }

    /**
     * Determine if the job should be retried.
     */
    public function shouldRetry(Exception $exception): bool
    {
        // Don't retry for certain types of errors
        $nonRetryableErrors = [
            'Invalid video URL',
            'Video not available',
            'Private video',
            'Age-restricted video',
            'Copyright restricted',
        ];

        $errorMessage = $exception->getMessage();

        foreach ($nonRetryableErrors as $nonRetryableError) {
            if (stripos($errorMessage, $nonRetryableError) !== false) {
                Log::info('Job will not be retried due to non-retryable error', [
                    'download_id' => $this->download->id,
                    'error' => $errorMessage,
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'youtube-download',
            'video-id:' . $this->download->video_id,
            'format:' . $this->download->format,
            'download-id:' . $this->download->id,
        ];
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'youtube-download-' . $this->download->id;
    }
}
