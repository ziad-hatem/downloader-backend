<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\YouTubeController;

Route::prefix('v1')->group(function () {

    // Public routes (no authentication required)
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'YouTube Downloader API is running',
            'version' => '1.0.0',
            'timestamp' => now()->toISOString(),
        ]);
    });

    // Protected routes (require API key authentication)
    Route::middleware('api.auth')->group(function () {

        // Video information
        Route::post('/video/info', [YouTubeController::class, 'getVideoInfo'])
            ->name('api.youtube.info');

        // Get available qualities for a format
        Route::post('/video/qualities', [YouTubeController::class, 'getQualities'])
            ->name('api.youtube.qualities');

        // Download endpoints
        Route::post('/download', [YouTubeController::class, 'download'])
            ->name('api.youtube.download');

        // Download status
        Route::get('/download/{id}/status', [YouTubeController::class, 'status'])
            ->name('api.youtube.status')
            ->where('id', '[0-9]+');

        // Download file
        Route::get('/download/{id}/file', [YouTubeController::class, 'downloadFile'])
            ->name('api.youtube.file')
            ->where('id', '[0-9]+');

        // Download history
        Route::get('/downloads', [YouTubeController::class, 'history'])
            ->name('api.youtube.history');

        // System status and statistics
        Route::get('/system/status', [YouTubeController::class, 'systemStatus'])
            ->name('api.youtube.system.status');
    });
});

// Legacy v1 routes without prefix for backward compatibility
Route::middleware('api.auth')->group(function () {
    Route::post('/video-info', [YouTubeController::class, 'getVideoInfo']);
    Route::post('/download-video', [YouTubeController::class, 'download']);
    Route::get('/download-status/{id}', [YouTubeController::class, 'status']);
    Route::get('/download-file/{id}', [YouTubeController::class, 'downloadFile']);
});