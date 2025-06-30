<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Download;
use App\Services\YouTubeDownloadService;
use Illuminate\Support\Facades\Storage;

class CleanupDownloads extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'youtube:cleanup
                            {--days=7 : Delete files older than this many days}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Skip confirmation prompt}
                            {--files-only : Only clean up files, keep database records}
                            {--records-only : Only clean up database records, keep files}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old YouTube download files and database records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $filesOnly = $this->option('files-only');
        $recordsOnly = $this->option('records-only');

        if ($days < 1) {
            $this->error('Days must be at least 1');
            return 1;
        }

        $cutoffDate = now()->subDays($days);

        $this->info("🧹 YouTube Download Cleanup");
        $this->info("Cleaning up downloads older than {$days} days (before {$cutoffDate->toDateTimeString()})");

        if ($dryRun) {
            $this->warn("🔍 DRY RUN MODE - No files or records will be deleted");
        }

        $this->newLine();

        // Get old download records
        $oldDownloads = Download::where('created_at', '<', $cutoffDate)->get();

        if ($oldDownloads->isEmpty()) {
            $this->info('✅ No old downloads found to clean up');
            return 0;
        }

        $this->info("Found {$oldDownloads->count()} old download records");

        // Show summary
        $this->table(['Status', 'Count'], [
            ['Completed', $oldDownloads->where('status', Download::STATUS_COMPLETED)->count()],
            ['Failed', $oldDownloads->where('status', Download::STATUS_FAILED)->count()],
            ['Pending', $oldDownloads->where('status', Download::STATUS_PENDING)->count()],
            ['Processing', $oldDownloads->where('status', Download::STATUS_PROCESSING)->count()],
        ]);

        // Calculate file sizes
        $totalSize = 0;
        $fileCount = 0;

        foreach ($oldDownloads as $download) {
            if ($download->download_path && file_exists($download->download_path)) {
                $totalSize += filesize($download->download_path);
                $fileCount++;
            }
        }

        $this->info("Files to clean: {$fileCount} files, " . $this->formatBytes($totalSize));

        if (!$dryRun && !$force) {
            if (!$this->confirm('Do you want to proceed with the cleanup?')) {
                $this->info('Cleanup cancelled');
                return 0;
            }
        }

        $deletedFiles = 0;
        $deletedRecords = 0;
        $errors = [];

        foreach ($oldDownloads as $download) {
            try {
                // Delete file if it exists and not records-only mode
                if (!$recordsOnly && $download->download_path && file_exists($download->download_path)) {
                    if (!$dryRun) {
                        if (unlink($download->download_path)) {
                            $deletedFiles++;
                        } else {
                            $errors[] = "Failed to delete file: {$download->download_path}";
                        }
                    } else {
                        $deletedFiles++;
                        $this->line("Would delete file: {$download->download_path}");
                    }
                }

                // Delete database record if not files-only mode
                if (!$filesOnly) {
                    if (!$dryRun) {
                        $download->delete();
                        $deletedRecords++;
                    } else {
                        $deletedRecords++;
                        $this->line("Would delete record: Download #{$download->id} - {$download->video_title}");
                    }
                }

            } catch (\Exception $e) {
                $errors[] = "Error processing download #{$download->id}: " . $e->getMessage();
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("🔍 DRY RUN RESULTS:");
            $this->info("Would delete {$deletedFiles} files");
            $this->info("Would delete {$deletedRecords} database records");
        } else {
            $this->info("✅ CLEANUP COMPLETED:");
            $this->info("Deleted {$deletedFiles} files");
            $this->info("Deleted {$deletedRecords} database records");
        }

        if (!empty($errors)) {
            $this->newLine();
            $this->error("❌ ERRORS:");
            foreach ($errors as $error) {
                $this->error($error);
            }
        }

        // Also clean up using the service method
        if (!$recordsOnly && !$dryRun) {
            $service = app(YouTubeDownloadService::class);
            $additionalCleaned = $service->cleanupOldFiles($days);

            if ($additionalCleaned > 0) {
                $this->info("Cleaned up {$additionalCleaned} additional orphaned files");
            }
        }

        return 0;
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
}
