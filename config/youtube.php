<?php

return [

    /*
    |--------------------------------------------------------------------------
    | YouTube Downloader Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the YouTube downloader service
    |
    */

    /*
    |--------------------------------------------------------------------------
    | yt-dlp Binary Path
    |--------------------------------------------------------------------------
    |
    | Path to the yt-dlp binary. This can be a full path or just 'yt-dlp'
    | if it's available in the system PATH.
    |
    */
    'yt_dlp_path' => env('YT_DLP_PATH', '/opt/homebrew/bin/yt-dlp'),

    /*
    |--------------------------------------------------------------------------
    | Download Directory
    |--------------------------------------------------------------------------
    |
    | Directory where downloaded files will be stored. This should be
    | writable by the web server.
    |
    */
    'download_path' => env('YOUTUBE_DOWNLOAD_PATH', storage_path('app/downloads')),

    /*
    |--------------------------------------------------------------------------
    | Supported Formats
    |--------------------------------------------------------------------------
    |
    | List of supported download formats and their configurations
    |
    */
    'supported_formats' => [
        'mp4' => [
            'ext' => 'mp4',
            'format' => 'best[ext=mp4]',
            'description' => 'MP4 Video',
        ],
        'mp3' => [
            'ext' => 'mp3',
            'format' => 'bestaudio[ext=m4a]/bestaudio/best',
            'description' => 'MP3 Audio',
        ],
        'webm' => [
            'ext' => 'webm',
            'format' => 'best[ext=webm]',
            'description' => 'WebM Video',
        ],
        'avi' => [
            'ext' => 'avi',
            'format' => 'best[ext=avi]',
            'description' => 'AVI Video',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Download Limits
    |--------------------------------------------------------------------------
    |
    | Limits for download operations
    |
    */
    'limits' => [
        'max_file_size' => env('YOUTUBE_MAX_FILE_SIZE', 1024 * 1024 * 1024), // 1GB in bytes
        'max_duration' => env('YOUTUBE_MAX_DURATION', 3600), // 1 hour in seconds
        'timeout' => env('YOUTUBE_TIMEOUT', 3600), // 1 hour in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | File Cleanup
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic file cleanup
    |
    */
    'cleanup' => [
        'enabled' => env('YOUTUBE_CLEANUP_ENABLED', true),
        'days_old' => env('YOUTUBE_CLEANUP_DAYS', 7),
        'schedule' => env('YOUTUBE_CLEANUP_SCHEDULE', '0 2 * * *'), // Daily at 2 AM
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Default rate limits for API keys
    |
    */
    'rate_limits' => [
        'default' => [
            'per_minute' => env('YOUTUBE_RATE_LIMIT_MINUTE', 60),
            'per_hour' => env('YOUTUBE_RATE_LIMIT_HOUR', 1000),
            'per_day' => env('YOUTUBE_RATE_LIMIT_DAY', 10000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for queue processing
    |
    */
    'queue' => [
        'connection' => env('YOUTUBE_QUEUE_CONNECTION', 'redis'),
        'queue' => env('YOUTUBE_QUEUE_NAME', 'downloads'),
        'max_tries' => env('YOUTUBE_MAX_TRIES', 3),
        'timeout' => env('YOUTUBE_JOB_TIMEOUT', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configuration
    |
    */
    'security' => [
        'allowed_domains' => [
            'youtube.com',
            'youtu.be',
            'www.youtube.com',
            'm.youtube.com',
        ],
        'blocked_patterns' => [
            // Add patterns for blocked content if needed
        ],
        'max_concurrent_downloads' => env('YOUTUBE_MAX_CONCURRENT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Logging configuration for YouTube downloads
    |
    */
    'logging' => [
        'enabled' => env('YOUTUBE_LOGGING_ENABLED', true),
        'level' => env('YOUTUBE_LOG_LEVEL', 'info'),
        'channel' => env('YOUTUBE_LOG_CHANNEL', 'single'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Cache configuration for video information and rate limiting
    |
    */
    'cache' => [
        'video_info_ttl' => env('YOUTUBE_CACHE_VIDEO_INFO_TTL', 3600), // 1 hour
        'rate_limit_ttl' => [
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400,
        ],
    ],

];
