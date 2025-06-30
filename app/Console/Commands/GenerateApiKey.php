<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApiKey;

class GenerateApiKey extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api:generate-key
                            {name : Name for the API key}
                            {--rate-minute=60 : Rate limit per minute}
                            {--rate-hour=1000 : Rate limit per hour}
                            {--rate-day=10000 : Rate limit per day}
                            {--formats=* : Allowed formats (mp4, mp3, webm, avi)}
                            {--ips=* : Allowed IP addresses}
                            {--expires= : Expiration date (Y-m-d H:i:s)}
                            {--inactive : Create inactive key}';

    /**
     * The console command description.
     */
    protected $description = 'Generate a new API key for YouTube downloader';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');

        // Validate formats if provided
        $formats = $this->option('formats');
        if (!empty($formats)) {
            $validFormats = array_keys(\App\Models\Download::FORMATS);
            foreach ($formats as $format) {
                if (!in_array($format, $validFormats)) {
                    $this->error("Invalid format: {$format}. Valid formats: " . implode(', ', $validFormats));
                    return 1;
                }
            }
        }

        // Validate IPs if provided
        $ips = $this->option('ips');
        if (!empty($ips)) {
            foreach ($ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    $this->error("Invalid IP address: {$ip}");
                    return 1;
                }
            }
        }

        // Parse expiration date if provided
        $expiresAt = null;
        if ($this->option('expires')) {
            try {
                $expiresAt = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $this->option('expires'));
            } catch (\Exception $e) {
                $this->error("Invalid expiration date format. Use: Y-m-d H:i:s");
                return 1;
            }
        }

        try {
            $apiKey = ApiKey::createNew($name, [
                'is_active' => !$this->option('inactive'),
                'rate_limit_per_minute' => (int) $this->option('rate-minute'),
                'rate_limit_per_hour' => (int) $this->option('rate-hour'),
                'rate_limit_per_day' => (int) $this->option('rate-day'),
                'allowed_formats' => !empty($formats) ? $formats : null,
                'allowed_ips' => !empty($ips) ? $ips : null,
                'expires_at' => $expiresAt,
            ]);

            $this->info('API Key generated successfully!');
            $this->newLine();

            $this->table(['Field', 'Value'], [
                ['ID', $apiKey->id],
                ['Name', $apiKey->name],
                ['API Key', $apiKey->key],
                ['Status', $apiKey->is_active ? 'Active' : 'Inactive'],
                ['Rate Limits', sprintf('%d/min, %d/hour, %d/day',
                    $apiKey->rate_limit_per_minute,
                    $apiKey->rate_limit_per_hour,
                    $apiKey->rate_limit_per_day
                )],
                ['Allowed Formats', $apiKey->allowed_formats ? implode(', ', $apiKey->allowed_formats) : 'All'],
                ['Allowed IPs', $apiKey->allowed_ips ? implode(', ', $apiKey->allowed_ips) : 'All'],
                ['Expires At', $apiKey->expires_at ? $apiKey->expires_at->toDateTimeString() : 'Never'],
                ['Created At', $apiKey->created_at->toDateTimeString()],
            ]);

            $this->newLine();
            $this->warn('🔒 Store this API key securely! It cannot be retrieved again.');
            $this->warn('💡 Use this key in the Authorization header: Bearer ' . $apiKey->key);
            $this->warn('💡 Or as a parameter: ?api_key=' . $apiKey->key);

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to create API key: ' . $e->getMessage());
            return 1;
        }
    }
}
