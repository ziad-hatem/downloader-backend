# YouTube Downloader API Guide

A production-ready, clean and modular YouTube downloader API built with Laravel 12, featuring queue system, API key authentication, rate limiting, and comprehensive error handling.

## 📋 Table of Contents

-   [Features](#features)
-   [Requirements](#requirements)
-   [Installation](#installation)
-   [Configuration](#configuration)
-   [API Key Management](#api-key-management)
-   [API Documentation](#api-documentation)
-   [Usage Examples](#usage-examples)
-   [Queue Management](#queue-management)
-   [File Cleanup](#file-cleanup)
-   [Security](#security)
-   [Deployment](#deployment)
-   [Troubleshooting](#troubleshooting)

## ✨ Features

-   **Multiple Formats**: Download videos in MP4, WebM, AVI, or extract audio as MP3
-   **Quality Selection**: Choose from 144p to 4K (2160p) video quality
-   **Async Processing**: Queue system for handling long downloads without blocking requests
-   **API Key Authentication**: Secure access with rate limiting and IP restrictions
-   **Rate Limiting**: Configurable limits per minute, hour, and day
-   **Comprehensive Logging**: Track all download requests with video info, IP, and timestamps
-   **Auto Cleanup**: Automatic cleanup of old files and database records
-   **Error Handling**: Robust error handling for all edge cases
-   **Statistics**: Download statistics and system status endpoints

## 🛠 Requirements

### System Requirements

-   PHP 8.2 or higher
-   Composer
-   MySQL 8.0+ or PostgreSQL 13+
-   Redis (for caching and queues)
-   Node.js & NPM (for Vite)

### External Dependencies

-   **yt-dlp**: The core video downloading tool
-   **FFmpeg** (optional but recommended for audio conversion)

## 📦 Installation

### 1. Clone and Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### 2. Install yt-dlp

#### macOS (via Homebrew)

```bash
brew install yt-dlp
```

#### Ubuntu/Debian

```bash
sudo curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp
sudo chmod a+rx /usr/local/bin/yt-dlp
```

#### Windows

```bash
# Download the latest release
curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp.exe -o yt-dlp.exe
```

#### Python (Universal)

```bash
pip install yt-dlp
```

### 3. Install FFmpeg (Optional but Recommended)

#### macOS

```bash
brew install ffmpeg
```

#### Ubuntu/Debian

```bash
sudo apt update
sudo apt install ffmpeg
```

#### Windows

Download from [https://ffmpeg.org/download.html](https://ffmpeg.org/download.html)

### 4. Verify Installation

```bash
yt-dlp --version
ffmpeg -version  # if installed
```

## ⚙️ Configuration

### 1. Environment Setup

Copy the configuration from `config-example.env` to your `.env` file:

```bash
# Copy YouTube-specific configurations
cat config-example.env >> .env
```

### 2. Database Setup

```bash
# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Create database tables for downloads and API keys
```

### 3. Storage Setup

```bash
# Create storage directories
mkdir -p storage/app/downloads
chmod 755 storage/app/downloads

# Link public storage (if needed)
php artisan storage:link
```

### 4. Queue Setup

Make sure Redis is running:

```bash
# Start Redis (macOS with Homebrew)
brew services start redis

# Start Redis (Ubuntu/Debian)
sudo systemctl start redis-server

# Test Redis connection
redis-cli ping
```

## 🔑 API Key Management

### Generate Your First API Key

```bash
php artisan api:generate-key "My First Key"
```

### Advanced API Key Options

```bash
# Create key with custom rate limits
php artisan api:generate-key "High Volume Key" \
  --rate-minute=120 \
  --rate-hour=5000 \
  --rate-day=50000

# Create key restricted to specific formats
php artisan api:generate-key "MP3 Only Key" \
  --formats=mp3

# Create key with IP restrictions
php artisan api:generate-key "Office Key" \
  --ips=192.168.1.100,192.168.1.101

# Create key with expiration
php artisan api:generate-key "Temporary Key" \
  --expires="2024-12-31 23:59:59"

# Create inactive key (for testing)
php artisan api:generate-key "Test Key" --inactive
```

## 📚 API Documentation

### Base URL

```
http://your-domain.com/api/v1
```

### Authentication

Include your API key in one of these ways:

**Authorization Header (Recommended):**

```
Authorization: Bearer YOUR_API_KEY
```

**Custom Header:**

```
X-API-Key: YOUR_API_KEY
```

**Query Parameter:**

```
?api_key=YOUR_API_KEY
```

### Endpoints

#### 1. Health Check

```http
GET /api/v1/health
```

**Response:**

```json
{
    "success": true,
    "message": "YouTube Downloader API is running",
    "version": "1.0.0",
    "timestamp": "2024-01-15T10:30:00.000Z"
}
```

#### 2. Get Video Information

```http
POST /api/v1/video/info
Content-Type: application/json

{
    "url": "https://www.youtube.com/watch?v=VIDEO_ID"
}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "video": {
            "id": "VIDEO_ID",
            "title": "Video Title",
            "description": "Video description...",
            "thumbnail": "https://img.youtube.com/vi/VIDEO_ID/maxresdefault.jpg",
            "duration": 180,
            "uploader": "Channel Name",
            "view_count": 1000000,
            "formats": {
                "mp4": ["144p", "240p", "360p", "480p", "720p", "1080p"],
                "webm": ["144p", "240p", "360p", "480p", "720p"],
                "mp3": ["audio"]
            }
        },
        "supported_formats": {
            "mp4": "MP4 Video",
            "mp3": "MP3 Audio",
            "webm": "WebM Video",
            "avi": "AVI Video"
        }
    }
}
```

#### 3. Download Video

```http
POST /api/v1/download
Content-Type: application/json

{
    "url": "https://www.youtube.com/watch?v=VIDEO_ID",
    "format": "mp4",
    "quality": "720p",
    "async": true
}
```

**Async Response (202 Accepted):**

```json
{
    "success": true,
    "data": {
        "download_id": 123,
        "status": "pending",
        "message": "Download request queued. Use the download_id to check status.",
        "video": {
            "id": "VIDEO_ID",
            "title": "Video Title",
            "thumbnail": "https://img.youtube.com/vi/VIDEO_ID/maxresdefault.jpg",
            "duration": "03:00"
        },
        "check_status_url": "http://your-domain.com/api/v1/download/123/status"
    }
}
```

**Sync Response (async: false):**

```json
{
    "success": true,
    "data": {
        "download_id": 123,
        "status": "completed",
        "video": {
            "id": "VIDEO_ID",
            "title": "Video Title",
            "thumbnail": "https://img.youtube.com/vi/VIDEO_ID/maxresdefault.jpg",
            "duration": "03:00"
        },
        "file": {
            "size": "15.6 MB",
            "format": "mp4",
            "quality": "720p"
        },
        "download_url": "http://your-domain.com/api/v1/download/123/file"
    }
}
```

#### 4. Check Download Status

```http
GET /api/v1/download/{id}/status
```

**Response:**

```json
{
    "success": true,
    "data": {
        "download_id": 123,
        "status": "completed",
        "video": {
            "id": "VIDEO_ID",
            "title": "Video Title",
            "thumbnail": "https://img.youtube.com/vi/VIDEO_ID/maxresdefault.jpg",
            "duration": "03:00"
        },
        "format": "mp4",
        "quality": "720p",
        "file": {
            "size": "15.6 MB",
            "download_url": "http://your-domain.com/api/v1/download/123/file"
        },
        "created_at": "2024-01-15T10:30:00.000Z",
        "started_at": "2024-01-15T10:30:05.000Z",
        "completed_at": "2024-01-15T10:32:15.000Z"
    }
}
```

#### 5. Download File

```http
GET /api/v1/download/{id}/file
```

Returns the actual file with appropriate headers for download.

#### 6. Download History

```http
GET /api/v1/downloads?page=1&per_page=20&status=completed
```

#### 7. System Status

```http
GET /api/v1/system/status
```

## 💻 Usage Examples

### cURL Examples

```bash
# Get video info
curl -X POST "http://localhost:8000/api/v1/video/info" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ"}'

# Download video (async)
curl -X POST "http://localhost:8000/api/v1/download" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    "format": "mp4",
    "quality": "720p",
    "async": true
  }'

# Check status
curl "http://localhost:8000/api/v1/download/123/status" \
  -H "Authorization: Bearer YOUR_API_KEY"

# Download file
curl "http://localhost:8000/api/v1/download/123/file" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -o "video.mp4"
```

### JavaScript/Node.js Example

```javascript
const axios = require("axios");

const apiKey = "YOUR_API_KEY";
const baseURL = "http://localhost:8000/api/v1";

const api = axios.create({
    baseURL,
    headers: {
        Authorization: `Bearer ${apiKey}`,
        "Content-Type": "application/json",
    },
});

async function downloadVideo(url, format = "mp4", quality = "720p") {
    try {
        // Start download
        const response = await api.post("/download", {
            url,
            format,
            quality,
            async: true,
        });

        const downloadId = response.data.data.download_id;
        console.log(`Download started with ID: ${downloadId}`);

        // Poll for completion
        let status = "pending";
        while (status === "pending" || status === "processing") {
            await new Promise((resolve) => setTimeout(resolve, 2000)); // Wait 2 seconds

            const statusResponse = await api.get(
                `/download/${downloadId}/status`
            );
            status = statusResponse.data.data.status;
            console.log(`Status: ${status}`);
        }

        if (status === "completed") {
            console.log("Download completed!");
            return `${baseURL}/download/${downloadId}/file`;
        } else {
            console.error("Download failed");
            return null;
        }
    } catch (error) {
        console.error("Error:", error.response?.data || error.message);
        return null;
    }
}

// Usage
downloadVideo("https://www.youtube.com/watch?v=dQw4w9WgXcQ").then(
    (downloadUrl) => {
        if (downloadUrl) {
            console.log(`File available at: ${downloadUrl}`);
        }
    }
);
```

### Python Example

```python
import requests
import time

class YouTubeDownloader:
    def __init__(self, api_key, base_url='http://localhost:8000/api/v1'):
        self.api_key = api_key
        self.base_url = base_url
        self.headers = {
            'Authorization': f'Bearer {api_key}',
            'Content-Type': 'application/json'
        }

    def download_video(self, url, format='mp4', quality='720p', async_download=True):
        # Start download
        response = requests.post(
            f'{self.base_url}/download',
            headers=self.headers,
            json={
                'url': url,
                'format': format,
                'quality': quality,
                'async': async_download
            }
        )

        if not response.ok:
            raise Exception(f'Download failed: {response.text}')

        data = response.json()
        download_id = data['data']['download_id']

        if not async_download:
            return data['data']['download_url']

        # Poll for completion
        while True:
            status_response = requests.get(
                f'{self.base_url}/download/{download_id}/status',
                headers=self.headers
            )

            status_data = status_response.json()
            status = status_data['data']['status']

            print(f'Status: {status}')

            if status == 'completed':
                return status_data['data']['file']['download_url']
            elif status == 'failed':
                raise Exception(f'Download failed: {status_data["data"].get("error_message")}')

            time.sleep(2)

    def get_video_info(self, url):
        response = requests.post(
            f'{self.base_url}/video/info',
            headers=self.headers,
            json={'url': url}
        )

        return response.json()

# Usage
downloader = YouTubeDownloader('YOUR_API_KEY')

# Get video info
info = downloader.get_video_info('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
print(f"Title: {info['data']['video']['title']}")

# Download video
download_url = downloader.download_video(
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    format='mp4',
    quality='720p'
)
print(f"Download URL: {download_url}")
```

## 🚀 Queue Management

### Start Queue Workers

```bash
# Start queue worker
php artisan queue:work redis --queue=downloads --tries=3 --timeout=3600

# Start multiple workers (recommended for production)
php artisan queue:work redis --queue=downloads --tries=3 --timeout=3600 &
php artisan queue:work redis --queue=downloads --tries=3 --timeout=3600 &
php artisan queue:work redis --queue=downloads --tries=3 --timeout=3600 &
```

### Monitor Queue

```bash
# Check queue status
php artisan queue:monitor redis:downloads

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### Supervisor Configuration (Production)

Create `/etc/supervisor/conf.d/youtube-downloader.conf`:

```ini
[program:youtube-downloader-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/artisan queue:work redis --queue=downloads --sleep=3 --tries=3 --timeout=3600 --max-time=3600
directory=/path/to/your/app
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/path/to/your/app/storage/logs/worker.log
stopwaitsecs=3600
```

## 🧹 File Cleanup

### Manual Cleanup

```bash
# Clean up files older than 7 days (dry run)
php artisan youtube:cleanup --dry-run

# Clean up files older than 3 days
php artisan youtube:cleanup --days=3

# Clean up without confirmation prompt
php artisan youtube:cleanup --force

# Clean up only files, keep database records
php artisan youtube:cleanup --files-only

# Clean up only database records, keep files
php artisan youtube:cleanup --records-only
```

### Automated Cleanup (Cron)

Add to your crontab:

```bash
# Run cleanup daily at 2 AM
0 2 * * * /usr/bin/php /path/to/your/app/artisan youtube:cleanup --force --days=7
```

Or add to Laravel's scheduler in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('youtube:cleanup --force --days=7')->daily();
```

## 🔒 Security

### Rate Limiting

Rate limits are enforced per API key:

-   **Per minute**: Default 60 requests
-   **Per hour**: Default 1,000 requests
-   **Per day**: Default 10,000 requests

### IP Restrictions

Restrict API keys to specific IP addresses:

```bash
php artisan api:generate-key "Office Key" --ips=192.168.1.100,192.168.1.101
```

### Format Restrictions

Restrict API keys to specific formats:

```bash
php artisan api:generate-key "Audio Only Key" --formats=mp3
```

### Security Best Practices

1. **Use HTTPS in production**
2. **Store API keys securely**
3. **Regularly rotate API keys**
4. **Monitor usage and logs**
5. **Set appropriate file size and duration limits**
6. **Use firewall rules to restrict access**
7. **Keep yt-dlp updated**

### Content Security

The API includes basic validations:

-   Only YouTube URLs are accepted
-   File size limits prevent abuse
-   Duration limits prevent extremely long downloads
-   Failed download attempts are logged

## 🌐 Deployment

### Local Development

```bash
# Start the development server
php artisan serve

# Start queue worker
php artisan queue:work

# Start Vite (if using frontend)
npm run dev
```

### Production Deployment

#### 1. VPS/Dedicated Server

**Server Requirements:**

-   Ubuntu 20.04+ or CentOS 8+
-   Nginx or Apache
-   PHP 8.2+ with extensions: `pdo_mysql`, `redis`, `gmp`, `bcmath`, `mbstring`, `xml`, `curl`
-   MySQL 8.0+ or PostgreSQL 13+
-   Redis 6.0+
-   Supervisor for queue management

**Deployment Steps:**

```bash
# 1. Clone repository
git clone your-repo.git /var/www/youtube-downloader
cd /var/www/youtube-downloader

# 2. Install dependencies
composer install --optimize-autoloader --no-dev
npm install && npm run build

# 3. Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# 4. Configure environment
cp .env.example .env
nano .env  # Configure your environment

# 5. Generate key and migrate
php artisan key:generate
php artisan migrate --force

# 6. Install yt-dlp
sudo curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp
sudo chmod a+rx /usr/local/bin/yt-dlp

# 7. Configure web server (Nginx example)
sudo nano /etc/nginx/sites-available/youtube-downloader
```

**Nginx Configuration:**

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/youtube-downloader/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;

    # File upload size
    client_max_body_size 100M;
}
```

#### 2. Docker Deployment

Create `docker-compose.yml`:

```yaml
version: "3.8"

services:
    app:
        build: .
        ports:
            - "8000:8000"
        environment:
            - APP_ENV=production
            - DB_HOST=mysql
            - REDIS_HOST=redis
        volumes:
            - ./storage:/var/www/html/storage
        depends_on:
            - mysql
            - redis

    mysql:
        image: mysql:8.0
        environment:
            MYSQL_ROOT_PASSWORD: secret
            MYSQL_DATABASE: youtube_downloader
        volumes:
            - mysql_data:/var/lib/mysql

    redis:
        image: redis:7-alpine
        volumes:
            - redis_data:/data

    worker:
        build: .
        command: php artisan queue:work redis --queue=downloads --tries=3
        depends_on:
            - mysql
            - redis
        volumes:
            - ./storage:/var/www/html/storage

volumes:
    mysql_data:
    redis_data:
```

Create `Dockerfile`:

```dockerfile
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    yt-dlp \
    ffmpeg

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql

# Copy application
COPY . /var/www/html
WORKDIR /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --optimize-autoloader --no-dev

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

#### 3. Cloud Deployment (AWS/DigitalOcean/etc.)

**Using Laravel Forge:**

1. Connect your server to Forge
2. Deploy your repository
3. Configure environment variables
4. Set up queue workers
5. Configure SSL certificate

**Manual Cloud Deployment:**
Follow the VPS deployment steps with cloud-specific configurations for load balancers, auto-scaling, and managed databases.

### Performance Optimization

1. **Enable OPcache** in production
2. **Use Redis** for caching and sessions
3. **Optimize Composer** autoloader
4. **Configure queue workers** appropriately
5. **Use CDN** for static assets
6. **Monitor resource usage**

## 🔧 Troubleshooting

### Common Issues

#### 1. yt-dlp Not Found

**Error:** `yt-dlp: command not found`

**Solutions:**

```bash
# Check if yt-dlp is installed
which yt-dlp

# Install via pip if not found
pip install yt-dlp

# Or specify full path in .env
YT_DLP_PATH=/usr/local/bin/yt-dlp
```

#### 2. Permission Errors

**Error:** `Permission denied` when downloading

**Solutions:**

```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage/
sudo chmod -R 775 storage/

# Create downloads directory
mkdir -p storage/app/downloads
chmod 755 storage/app/downloads
```

#### 3. Queue Jobs Not Processing

**Error:** Downloads stuck in "pending" status

**Solutions:**

```bash
# Check if queue worker is running
ps aux | grep "queue:work"

# Start queue worker
php artisan queue:work redis --queue=downloads

# Check Redis connection
redis-cli ping

# Check failed jobs
php artisan queue:failed
```

#### 4. Download Fails with "Video unavailable"

**Error:** Video extraction fails

**Solutions:**

```bash
# Update yt-dlp to latest version
pip install --upgrade yt-dlp

# Or via package manager
brew upgrade yt-dlp  # macOS
sudo apt update && sudo apt upgrade yt-dlp  # Ubuntu

# Test manually
yt-dlp --version
yt-dlp "https://www.youtube.com/watch?v=VIDEO_ID"
```

#### 5. High Memory Usage

**Issue:** Server running out of memory

**Solutions:**

1. **Limit concurrent downloads:**

    ```bash
    YOUTUBE_MAX_CONCURRENT=3
    ```

2. **Increase memory limit:**

    ```bash
    memory_limit = 512M  # in php.ini
    ```

3. **Monitor queue workers:**
    ```bash
    php artisan queue:work --memory=256
    ```

#### 6. SSL/HTTPS Issues

**Error:** Certificate verification failed

**Solutions:**

```bash
# Add to yt-dlp command (in service)
--no-check-certificate

# Or update certificates
sudo apt update && sudo apt install ca-certificates
```

### Debug Mode

Enable debug mode for troubleshooting:

```bash
# In .env
APP_DEBUG=true
YOUTUBE_DEBUG=true
LOG_LEVEL=debug
```

### Logs

Check logs for detailed error information:

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Queue worker logs (if using Supervisor)
tail -f storage/logs/worker.log

# System logs
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/php8.2-fpm.log
```

### Health Checks

Monitor system health:

```bash
# API health check
curl http://localhost:8000/api/v1/health

# System status (requires API key)
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://localhost:8000/api/v1/system/status
```

---

## 📞 Support

If you encounter issues not covered in this guide:

1. **Check the logs** first (`storage/logs/laravel.log`)
2. **Test yt-dlp directly** with the problematic URL
3. **Verify your environment** configuration
4. **Check queue workers** are running
5. **Monitor system resources** (disk space, memory)

For additional help, ensure you have:

-   Laravel version: `php artisan --version`
-   PHP version: `php --version`
-   yt-dlp version: `yt-dlp --version`
-   Error logs and stack traces

---

**🎉 Congratulations!** You now have a fully functional, production-ready YouTube downloader API. The system is designed to be scalable, secure, and maintainable for production use.
