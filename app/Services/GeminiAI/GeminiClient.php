<?php

namespace App\Services\GeminiAI;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class GeminiClient
{
    public function __construct(
        private ?string $apiKey = null,
        private ?string $model  = null,
        private ?string $base   = null,
        private ?string $version = null,
        private int $timeout = 20,
    ) {
        $cfg = config('services.gemini');
        $this->apiKey  = $this->apiKey  ?? $cfg['key'];
        $this->model   = $this->model   ?? $cfg['model'];
        $this->base    = $this->base    ?? rtrim($cfg['base'], '/');
        $this->version = $this->version ?? $cfg['version'];
        $this->timeout = $cfg['timeout'] ?? $this->timeout;
    }

    private function endpoint(string $path): string
    {
        return "{$this->base}/{$this->version}/{$path}";
    }

    /**
     * Gọi generateContent lần 1 (user message + tools + system instruction).
     */
    public function firstTurn(array $payload): array
    {
        $url = $this->endpoint("models/{$this->model}:generateContent");
        $resp = Http::withOptions(['timeout' => $this->timeout])
            ->acceptJson()
            ->post($url . '?key=' . $this->apiKey, $payload);

        if (!$resp->ok()) {
            throw new RequestException($resp);
        }
        return $resp->json();
    }

    /**
     * Gọi generateContent lần 2 (đưa functionResponse để model “kể chuyện”).
     */
    public function secondTurn(array $functionResponsePayload): array
    {
        $url = $this->endpoint("models/{$this->model}:generateContent");
        $resp = Http::withOptions(['timeout' => $this->timeout])
            ->acceptJson()
            ->post($url . '?key=' . $this->apiKey, $functionResponsePayload);

        if (!$resp->ok()) {
            throw new RequestException($resp);
        }
        return $resp->json();
    }

    /**
     * Lấy text đầu ra từ candidates (nếu có).
     */
    public static function extractText(array $resp): ?string
    {
        return $resp['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

    /**
     * Lấy functionCall (nếu model yêu cầu gọi tool).
     */
    public static function extractFunctionCall(array $resp): ?array
    {
        // Có thể xuất hiện trong một trong các parts, ta tìm phần đầu tiên có functionCall
        $parts = $resp['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $p) {
            if (isset($p['functionCall'])) return $p['functionCall'];
        }
        return null;
    }
}
