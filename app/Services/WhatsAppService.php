<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $baseUrl;

    protected bool $enabled;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('whatsapp.api_url', 'http://localhost:3050/api');
        $this->enabled = config('whatsapp.enabled', true);
        $this->timeout = config('whatsapp.timeout', 30);
    }

    /**
     * Send text message to WhatsApp number
     */
    public function sendMessage(string $to, string $message): array
    {
        if (! $this->enabled) {
            Log::info('WhatsApp disabled, skipping message', ['to' => $to]);

            return ['success' => false, 'message' => 'WhatsApp disabled'];
        }

        try {
            $phone = $this->formatPhoneNumber($to);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/send-message", [
                    'to' => $phone,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                Log::info('WhatsApp message sent', [
                    'to' => $phone,
                    'message' => substr($message, 0, 50).'...',
                ]);

                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error('WhatsApp API error', [
                'to' => $phone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp exception', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send message with media (image, video, document, etc)
     */
    public function sendMessageWithMedia(
        string $to,
        string $message,
        string $mediaUrl,
        string $mediaType = 'image'
    ): array {
        if (! $this->enabled) {
            Log::info('WhatsApp disabled, skipping media message', ['to' => $to]);

            return ['success' => false, 'message' => 'WhatsApp disabled'];
        }

        try {
            $phone = $this->formatPhoneNumber($to);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/send-message", [
                    'to' => $phone,
                    'message' => $message,
                    'media_url' => $mediaUrl,
                    'media_type' => $mediaType,
                ]);

            if ($response->successful()) {
                Log::info('WhatsApp media message sent', [
                    'to' => $phone,
                    'media_type' => $mediaType,
                ]);

                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error('WhatsApp media API error', [
                'to' => $phone,
                'status' => $response->status(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send media message',
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp media exception', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get WhatsApp connection status
     */
    public function getStatus(): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/status");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get status',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format phone number to WhatsApp format
     * Removes all non-numeric characters and ensures proper format
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If starts with 0, replace with 62 (Indonesia)
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        // If doesn't start with country code, add 62
        if (! str_starts_with($phone, '62')) {
            $phone = '62'.$phone;
        }

        return $phone;
    }

    /**
     * Check if WhatsApp is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
