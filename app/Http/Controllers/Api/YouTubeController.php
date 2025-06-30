<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\YouTubeDownloadRequest;
use App\Models\Download;
use App\Services\YouTubeDownloadService;
use App\Jobs\ProcessYouTubeDownload;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class YouTubeController extends Controller
{
    private YouTubeDownloadService $youtubeService;

    public function __construct(YouTubeDownloadService $youtubeService)
    {
        $this->youtubeService = $youtubeService;
    }

    /**
     * Get video information from YouTube URL.
     */
    public function getVideoInfo(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|string|url',
        ]);

        $url = $request->input('url');

        try {
            if (!$this->youtubeService->validateUrl($url)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_URL',
                        'message' => 'Invalid YouTube URL provided.',
                    ],
                ], 400);
            }

            $videoInfo = $this->youtubeService->getVideoInfo($url);

            return response()->json([
                'success' => true,
                'data' => [
                    'video' => $videoInfo,
                    'supported_formats' => Download::FORMATS,
                    'supported_qualities' => Download::QUALITIES,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get video info', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VIDEO_INFO_ERROR',
                    'message' => 'Failed to extract video information: ' . $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Start a download request.
     */
    public function download(YouTubeDownloadRequest $request): JsonResponse
    {
        try {
            $data = $request->getValidatedData();
            $isAsync = $data['async'] ?? true;

            // Get video information first
            $videoInfo = $this->youtubeService->getVideoInfo($data['url']);

            // Create download record
            $download = Download::create([
                'youtube_url' => $data['url'],
                'video_id' => $data['video_id'],
                'video_title' => $videoInfo['title'],
                'video_thumbnail' => $videoInfo['thumbnail'],
                'video_duration' => $videoInfo['duration'],
                'format' => $data['format'],
                'quality' => $data['quality'] ?? null,
                'ip_address' => $data['ip_address'],
                'user_agent' => $data['user_agent'],
            ]);

            Log::info('Download request created', [
                'download_id' => $download->id,
                'video_id' => $download->video_id,
                'format' => $download->format,
                'async' => $isAsync,
            ]);

            if ($isAsync) {
                // Queue the download
                ProcessYouTubeDownload::dispatch($download);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'download_id' => $download->id,
                        'status' => $download->status,
                        'message' => 'Download request queued. Use the download_id to check status.',
                        'video' => [
                            'id' => $download->video_id,
                            'title' => $download->video_title,
                            'thumbnail' => $download->video_thumbnail,
                            'duration' => $download->formatted_duration,
                        ],
                        'check_status_url' => route('api.youtube.status', $download->id),
                    ],
                ], 202);
            } else {
                // Process synchronously
                try {
                    $download->markAsProcessing();
                    $result = $this->youtubeService->downloadVideo($download);
                    $download->markAsCompleted($result['file_path'], $result['file_size']);

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'download_id' => $download->id,
                            'status' => $download->status,
                            'video' => [
                                'id' => $download->video_id,
                                'title' => $download->video_title,
                                'thumbnail' => $download->video_thumbnail,
                                'duration' => $download->formatted_duration,
                            ],
                            'file' => [
                                'size' => $download->formatted_file_size,
                                'format' => $download->format,
                                'quality' => $download->quality,
                            ],
                            'download_url' => route('api.youtube.file', $download->id),
                        ],
                    ]);

                } catch (\Exception $e) {
                    $download->markAsFailed($e->getMessage());
                    throw $e;
                }
            }

        } catch (\Exception $e) {
            Log::error('Download request failed', [
                'url' => $request->input('url'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DOWNLOAD_ERROR',
                    'message' => 'Download failed: ' . $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Check download status.
     */
    public function status(string $downloadId): JsonResponse
    {
        $download = Download::find($downloadId);

        if (!$download) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DOWNLOAD_NOT_FOUND',
                    'message' => 'Download not found.',
                ],
            ], 404);
        }

        $response = [
            'success' => true,
            'data' => [
                'download_id' => $download->id,
                'status' => $download->status,
                'video' => [
                    'id' => $download->video_id,
                    'title' => $download->video_title,
                    'thumbnail' => $download->video_thumbnail,
                    'duration' => $download->formatted_duration,
                ],
                'format' => $download->format,
                'quality' => $download->quality,
                'created_at' => $download->created_at->toISOString(),
                'started_at' => $download->started_at?->toISOString(),
                'completed_at' => $download->completed_at?->toISOString(),
            ],
        ];

        if ($download->isCompleted()) {
            $response['data']['file'] = [
                'size' => $download->formatted_file_size,
                'download_url' => route('api.youtube.file', $download->id),
            ];
        }

        if ($download->isFailed()) {
            $response['data']['error_message'] = $download->error_message;
        }

        return response()->json($response);
    }

    /**
     * Download the file.
     */
    public function downloadFile(string $downloadId): BinaryFileResponse|JsonResponse
    {
        $download = Download::find($downloadId);

        if (!$download) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DOWNLOAD_NOT_FOUND',
                    'message' => 'Download not found.',
                ],
            ], 404);
        }

        if (!$download->isCompleted()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DOWNLOAD_NOT_READY',
                    'message' => 'Download is not completed yet. Current status: ' . $download->status,
                ],
            ], 400);
        }

        if (!file_exists($download->download_path)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FILE_NOT_FOUND',
                    'message' => 'Download file not found on server.',
                ],
            ], 404);
        }

        $filename = pathinfo($download->download_path, PATHINFO_BASENAME);
        $safeTitle = $this->sanitizeFilename($download->video_title);
        $extension = pathinfo($download->download_path, PATHINFO_EXTENSION);
        $downloadName = "{$safeTitle}.{$extension}";

        return response()->download($download->download_path, $downloadName);
    }

    /**
     * Get download history.
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|string|in:pending,processing,completed,failed',
        ]);

        $query = Download::where('ip_address', $request->ip())
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->byStatus($request->input('status'));
        }

        $perPage = $request->input('per_page', 20);
        $downloads = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'downloads' => $downloads->items(),
                'pagination' => [
                    'current_page' => $downloads->currentPage(),
                    'per_page' => $downloads->perPage(),
                    'total' => $downloads->total(),
                    'last_page' => $downloads->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Get available qualities for a specific format.
     */
    public function getQualities(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|string|url',
            'format' => 'required|string|in:' . implode(',', array_keys(Download::FORMATS)),
        ]);

        $url = $request->input('url');
        $format = $request->input('format');

        try {
            if (!$this->youtubeService->validateUrl($url)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_URL',
                        'message' => 'Invalid YouTube URL provided.',
                    ],
                ], 400);
            }

            $qualities = $this->youtubeService->getAvailableQualities($url, $format);

            return response()->json([
                'success' => true,
                'data' => [
                    'format' => $format,
                    'available_qualities' => $qualities,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get available qualities', [
                'url' => $url,
                'format' => $format,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'QUALITIES_ERROR',
                    'message' => 'Failed to get available qualities: ' . $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get system status and statistics.
     */
    public function systemStatus(): JsonResponse
    {
        $stats = [
            'total_downloads' => Download::count(),
            'downloads_today' => Download::whereDate('created_at', today())->count(),
            'downloads_this_week' => Download::where('created_at', '>=', now()->startOfWeek())->count(),
            'downloads_by_status' => [
                'pending' => Download::byStatus(Download::STATUS_PENDING)->count(),
                'processing' => Download::byStatus(Download::STATUS_PROCESSING)->count(),
                'completed' => Download::byStatus(Download::STATUS_COMPLETED)->count(),
                'failed' => Download::byStatus(Download::STATUS_FAILED)->count(),
            ],
            'downloads_by_format' => Download::selectRaw('format, COUNT(*) as count')
                ->groupBy('format')
                ->pluck('count', 'format')
                ->toArray(),
        ];

        $systemInfo = [
            'yt_dlp_available' => $this->youtubeService->checkYtDlpAvailability(),
            'yt_dlp_version' => $this->youtubeService->getYtDlpVersion(),
            'supported_formats' => Download::FORMATS,
            'supported_qualities' => Download::QUALITIES,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'statistics' => $stats,
                'system' => $systemInfo,
            ],
        ]);
    }

    /**
     * Sanitize filename for download.
     */
    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '_');
        return substr($filename, 0, 100);
    }
}
