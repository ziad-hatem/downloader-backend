<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'secret',
        'is_active',
        'rate_limit_per_minute',
        'rate_limit_per_hour',
        'rate_limit_per_day',
        'usage_count',
        'allowed_ips',
        'allowed_formats',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allowed_ips' => 'array',
        'allowed_formats' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'rate_limit_per_minute' => 'integer',
        'rate_limit_per_hour' => 'integer',
        'rate_limit_per_day' => 'integer',
        'usage_count' => 'integer',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * Generate a new API key.
     */
    public static function generateKey(): string
    {
        return 'yt_' . Str::random(32);
    }

    /**
     * Generate a new API secret.
     */
    public static function generateSecret(): string
    {
        return Str::random(64);
    }

    /**
     * Create a new API key with generated credentials.
     */
    public static function createNew(string $name, array $options = []): self
    {
        return self::create([
            'name' => $name,
            'key' => self::generateKey(),
            'secret' => hash('sha256', self::generateSecret()),
            'is_active' => $options['is_active'] ?? true,
            'rate_limit_per_minute' => $options['rate_limit_per_minute'] ?? 60,
            'rate_limit_per_hour' => $options['rate_limit_per_hour'] ?? 1000,
            'rate_limit_per_day' => $options['rate_limit_per_day'] ?? 10000,
            'allowed_ips' => $options['allowed_ips'] ?? null,
            'allowed_formats' => $options['allowed_formats'] ?? null,
            'expires_at' => $options['expires_at'] ?? null,
        ]);
    }

    /**
     * Check if the API key is valid.
     */
    public function isValid(): bool
    {
        return $this->is_active &&
               ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Check if the IP address is allowed.
     */
    public function isIpAllowed(string $ip): bool
    {
        if ($this->allowed_ips === null) {
            return true;
        }

        return in_array($ip, $this->allowed_ips);
    }

    /**
     * Check if the format is allowed.
     */
    public function isFormatAllowed(string $format): bool
    {
        if ($this->allowed_formats === null) {
            return true;
        }

        return in_array($format, $this->allowed_formats);
    }

    /**
     * Update the last used timestamp and increment usage count.
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Check rate limit for the given period.
     */
    public function checkRateLimit(string $period): bool
    {
        $cacheKey = "api_rate_limit:{$this->key}:{$period}";
        $current = cache()->get($cacheKey, 0);

        $limit = match($period) {
            'minute' => $this->rate_limit_per_minute,
            'hour' => $this->rate_limit_per_hour,
            'day' => $this->rate_limit_per_day,
            default => 0,
        };

        return $current < $limit;
    }

        /**
     * Increment rate limit counter.
     */
    public function incrementRateLimit(): void
    {
        $now = now();

        // Minute counter
        $minuteKey = "api_rate_limit:{$this->key}:minute";
        $current = cache()->get($minuteKey, 0);
        cache()->put($minuteKey, $current + 1, 60); // 60 seconds

        // Hour counter
        $hourKey = "api_rate_limit:{$this->key}:hour";
        $current = cache()->get($hourKey, 0);
        cache()->put($hourKey, $current + 1, 3600); // 3600 seconds (1 hour)

        // Day counter
        $dayKey = "api_rate_limit:{$this->key}:day";
        $current = cache()->get($dayKey, 0);
        cache()->put($dayKey, $current + 1, 86400); // 86400 seconds (1 day)
    }

    /**
     * Get current rate limit usage.
     */
    public function getRateLimitUsage(): array
    {
        return [
            'minute' => [
                'used' => cache()->get("api_rate_limit:{$this->key}:minute", 0),
                'limit' => $this->rate_limit_per_minute,
            ],
            'hour' => [
                'used' => cache()->get("api_rate_limit:{$this->key}:hour", 0),
                'limit' => $this->rate_limit_per_hour,
            ],
            'day' => [
                'used' => cache()->get("api_rate_limit:{$this->key}:day", 0),
                'limit' => $this->rate_limit_per_day,
            ],
        ];
    }

    /**
     * Scope to get active API keys.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope to get expired API keys.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }
}
