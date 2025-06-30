<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Download;
use App\Services\YouTubeDownloadService;

class YouTubeDownloadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'url',
                function ($attribute, $value, $fail) {
                    $service = app(YouTubeDownloadService::class);
                    if (!$service->validateUrl($value)) {
                        $fail('The URL must be a valid YouTube video URL.');
                    }
                },
            ],
            'format' => [
                'required',
                'string',
                'in:' . implode(',', array_keys(Download::FORMATS)),
            ],
            'quality' => [
                'nullable',
                'string',
                'in:' . implode(',', array_keys(Download::QUALITIES)),
                function ($attribute, $value, $fail) {
                    // Quality is only applicable for video formats
                    if (!empty($value) && $this->input('format') === 'mp3') {
                        $fail('Quality parameter is not applicable for audio format.');
                    }
                },
            ],
            'async' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'url.required' => 'YouTube URL is required.',
            'url.url' => 'Please provide a valid URL.',
            'format.required' => 'Download format is required.',
            'format.in' => 'Invalid format. Supported formats: ' . implode(', ', array_keys(Download::FORMATS)),
            'quality.in' => 'Invalid quality. Supported qualities: ' . implode(', ', array_keys(Download::QUALITIES)),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'url' => 'YouTube URL',
            'format' => 'download format',
            'quality' => 'video quality',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize URL
        if ($this->has('url')) {
            $url = $this->input('url');

            // Remove any extra parameters that might interfere
            if (strpos($url, 'youtube.com/watch?v=') !== false) {
                preg_match('/[?&]v=([^&]+)/', $url, $matches);
                if (!empty($matches[1])) {
                    $videoId = $matches[1];
                    $url = "https://www.youtube.com/watch?v={$videoId}";
                }
            } elseif (strpos($url, 'youtu.be/') !== false) {
                preg_match('/youtu\.be\/([^?&]+)/', $url, $matches);
                if (!empty($matches[1])) {
                    $videoId = $matches[1];
                    $url = "https://www.youtube.com/watch?v={$videoId}";
                }
            }

            $this->merge(['url' => $url]);
        }

        // Set default quality for video formats
        if ($this->has('format') && !$this->has('quality')) {
            $format = $this->input('format');
            if ($format !== 'mp3') {
                $this->merge(['quality' => '720p']);
            }
        }

        // Ensure async is boolean
        if ($this->has('async')) {
            $this->merge(['async' => filter_var($this->input('async'), FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional validation after basic rules pass
            if (!$validator->errors()->has('url') && !$validator->errors()->has('format')) {
                $url = $this->input('url');
                $format = $this->input('format');

                // Check if we can extract video ID
                $service = app(YouTubeDownloadService::class);
                $videoId = $service->extractVideoId($url);

                if (!$videoId) {
                    $validator->errors()->add('url', 'Could not extract video ID from the provided URL.');
                    return;
                }

                // Check for recent duplicate requests (within last 5 minutes)
                $recentDownload = Download::where('video_id', $videoId)
                    ->where('format', $format)
                    ->where('ip_address', request()->ip())
                    ->where('created_at', '>=', now()->subMinutes(5))
                    ->first();

                if ($recentDownload) {
                    $validator->errors()->add('url', 'A download request for this video in the same format was made recently. Please wait before making another request.');
                }
            }
        });
    }

    /**
     * Get the validated data with additional computed fields.
     */
    public function getValidatedData(): array
    {
        $validated = $this->validated();

        $service = app(YouTubeDownloadService::class);
        $videoId = $service->extractVideoId($validated['url']);

        return array_merge($validated, [
            'video_id' => $videoId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
