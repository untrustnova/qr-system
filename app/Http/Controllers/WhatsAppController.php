<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

class WhatsAppController extends Controller
{
    public function sendText(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:30'],
            'message' => ['required', 'string'],
        ]);

        return $this->dispatchRequest('send-message', [
            'to' => $data['to'],
            'message' => $data['message'],
        ]);
    }

    public function sendMedia(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:30'],
            'mediaBase64' => ['required', 'string'],
            'filename' => ['required', 'string', 'max:200'],
            'caption' => ['nullable', 'string'],
        ]);

        $mediaUrl = $this->normalizeMediaUrl($data['mediaBase64'], $data['filename']);

        return $this->dispatchRequest('send-message', [
            'to' => $data['to'],
            'message' => $data['caption'] ?? 'Media',
            'media_type' => $this->guessMediaType($data['filename'], $mediaUrl),
            'media_url' => $mediaUrl,
        ]);
    }

    protected function dispatchRequest(string $endpoint, array $payload): JsonResponse
    {
        $baseUrl = config('services.whatsapp.base_url');
        $token = config('services.whatsapp.token');

        if (!$baseUrl) {
            return response()->json([
                'message' => 'WhatsApp provider not configured',
            ], 501);
        }

        $client = Http::timeout(config('services.whatsapp.timeout', 10));

        if ($token) {
            $client = $client->withToken($token);
        }

        $response = $client->post(rtrim($baseUrl, '/').'/'.$endpoint, $payload);

        if (!$response->successful()) {
            Log::warning('whatsapp.send.failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);
            return response()->json([
                'message' => 'Failed to send WhatsApp message',
                'error' => $response->json() ?? $response->body(),
            ], $response->status());
        }

        Log::info('whatsapp.send.success', [
            'endpoint' => $endpoint,
        ]);

        return response()->json([
            'message' => 'Sent',
            'data' => $response->json() ?? $response->body(),
        ]);
    }

    protected function normalizeMediaUrl(string $mediaBase64, string $filename): string
    {
        if (Str::startsWith($mediaBase64, 'data:')) {
            return $mediaBase64;
        }

        $mimeTypes = new MimeTypes();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimes = $mimeTypes->getMimeTypes($extension);
        $mime = $mimes[0] ?? 'application/octet-stream';

        return 'data:'.$mime.';base64,'.$mediaBase64;
    }

    protected function guessMediaType(string $filename, string $mediaUrl): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $map = [
            'jpg' => 'image',
            'jpeg' => 'image',
            'png' => 'image',
            'gif' => 'image',
            'webp' => 'image',
            'mp4' => 'video',
            'mov' => 'video',
            'avi' => 'video',
            'mkv' => 'video',
            'mp3' => 'audio',
            'wav' => 'audio',
            'ogg' => 'audio',
            'pdf' => 'document',
            'doc' => 'document',
            'docx' => 'document',
            'xls' => 'document',
            'xlsx' => 'document',
            'ppt' => 'document',
            'pptx' => 'document',
            'webm' => 'sticker',
        ];

        if (isset($map[$extension])) {
            return $map[$extension];
        }

        if (Str::contains($mediaUrl, 'image/')) {
            return 'image';
        }
        if (Str::contains($mediaUrl, 'video/')) {
            return 'video';
        }
        if (Str::contains($mediaUrl, 'audio/')) {
            return 'audio';
        }

        return 'document';
    }
}
