<?php

namespace App\Services\GeminiAI;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class GeminiAiService
{
    public function __construct(
        private ?Client $http = null,
        private string $apiKey = '',
        private string $endpoint = '',
        private string $model = ''
    ) {
        $this->apiKey  = $this->apiKey  ?: config('services.gemini.key');
        $this->endpoint= $this->endpoint?: rtrim(config('services.gemini.endpoint'), '/');
        $this->model   = $this->model   ?: config('services.gemini.model', 'gemini-1.5-flash');

        $this->http ??= new Client([
            'timeout' => 15,
            'connect_timeout' => 5,
        ]);
    }

    /**
     * Gọi Gemini để trích JSON tham số tìm chuyến.
     */
    public function extractSearchParams(string $userQuery, string $tz = 'Asia/Bangkok'): array
    {
        $url = "{$this->endpoint}/{$this->model}:generateContent?key={$this->apiKey}";

        // Prompt hướng dẫn + schema
        $schema = <<<JSON
                {
                "type":"object",
                "properties":{
                    "origin":{"type":"string"},
                    "destination":{"type":"string"},
                    "date":{"type":"string","description":"YYYY-MM-DD or relative vi-VN (vd: 'tối mai')"},
                    "time_window":{"type":"string","description":"HH:mm-HH:mm hoặc 'sáng|chiều|tối'"},
                    "seats":{"type":"integer","minimum":1},
                    "pickup_hint":{"type":"string"},
                    "dropoff_hint":{"type":"string"},
                    "price_cap":{"type":"integer"},
                    "flexible_days":{"type":"integer","minimum":0,"maximum":3},
                    "preferences":{
                    "type":"object",
                    "properties":{
                        "bus_type":{"type":"string","enum":["giuong_nam","limousine","ghe_ngoi"]},
                        "avoid_night":{"type":"boolean"},
                        "nearby_stops_km":{"type":"integer"}
                    }
                    }
                },
                "required":["origin","destination","date","seats"]
                }
                JSON;

        $sys = <<<SYS
Bạn là bộ trích tham số tìm chuyến xe khách. 
- Output CHỈ là JSON theo schema.
- Chuẩn hóa 'sáng=04:30-11:59', 'chiều=12:00-17:59', 'tối=18:00-23:59'.
- Hiểu mốc thời gian tiếng Việt ('hôm nay','mai','thứ bảy tuần này','tối mai') theo timezone {$tz}.
- Nếu mơ hồ, đặt flexible_days>=1 và cố gắng suy luận hợp lý.
- Không viết thêm chữ ngoài JSON.
SYS;

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => $sys . "\n\nSCHEMA:\n" . $schema . "\n\nCÂU HỎI:\n" . $userQuery
                ]]
            ]],
            // ép trả MIME JSON để dễ parse
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'temperature' => 0.1,
            ],
        ];

        try {
            $res = $this->http->post($url, ['json' => $payload]);
            $json = json_decode((string)$res->getBody(), true);

            $text = Arr::get($json, 'candidates.0.content.parts.0.text', '{}');
            $data = json_decode($text, true);
            if (!is_array($data)) {
                throw new \RuntimeException('Gemini returned non-JSON');
            }
            // Validate minimal required fields; if missing, try heuristic fallback
            $hasCore = isset($data['origin'], $data['destination']);
            if (!$hasCore) {
                $fallback = $this->fallbackExtract($userQuery);
                if (!empty($fallback)) {
                    return $fallback;
                }
            }
            return $data;
        } catch (\Throwable $e) {
            Log::warning('Gemini extract failed', ['err' => $e->getMessage()]);
            // Heuristic fallback parser for common vi-VN patterns
            $fallback = $this->fallbackExtract($userQuery);
            return $fallback ?: [];
        }
    }

    /**
     * Very simple Vietnamese heuristic extractor to handle common patterns like:
     * "Ngày mai có chuyến xe nào từ Hà Nội đi Thanh Hóa không?"
     */
    private function fallbackExtract(string $userQuery): array
    {
        $q = trim(mb_strtolower($userQuery));

        // Seats: e.g., "2 vé" or "2 người"
        $seats = 1;
        if (preg_match('/(\d{1,2})\s*(vé|nguoi|người)/u', $q, $m)) {
            $seats = max(1, (int) $m[1]);
        }

        // Date: detect "ngày mai" or "mai"
        $date = null;
        if (preg_match('/ngày\s*mai|\bmai\b/u', $q)) {
            $date = 'mai';
        } elseif (preg_match('/hôm\s*nay/u', $q)) {
            $date = 'hôm nay';
        }

        // Origin/Destination patterns
        $origin = null;
        $destination = null;

        // Pattern 1: "từ X (đi|đến|tới|về) Y"
        if (preg_match('/từ\s+(.+?)\s+(?:đi|đến|tới|về|ra)\s+(.+?)(\?|$|\.|,)/u', $userQuery, $m)) {
            $origin = trim($m[1]);
            $destination = trim($m[2]);
        }

        // Pattern 2: "X đi Y"
        if (!$origin && !$destination && preg_match('/\b(.+?)\s+đi\s+(.+?)(\?|$|\.|,)/u', $userQuery, $m)) {
            $origin = trim($m[1]);
            $destination = trim($m[2]);
        }

        // Pattern 3: "X -> Y"
        if ((!$origin || !$destination) && preg_match('/(.+?)->(.+?)(\?|$|\.|,)/u', $userQuery, $m)) {
            $origin = $origin ?: trim($m[1]);
            $destination = $destination ?: trim($m[2]);
        }

        // Price cap: "dưới 250k", "<= 300000"
        $priceCap = null;
        if (preg_match('/dưới\s*(\d{2,6})\s*(k|nghìn|ngàn)?/u', $q, $m)) {
            $v = (int) $m[1];
            $priceCap = isset($m[2]) && $m[2] ? $v * 1000 : $v;
        } elseif (preg_match('/<=?\s*(\d{5,7})/u', $q, $m)) {
            $priceCap = (int) $m[1];
        }

        // If we cannot find both origin and destination, give up
        if (!$origin || !$destination) {
            return [];
        }

        return [
            'origin' => $origin,
            'destination' => $destination,
            'date' => $date ?? 'hôm nay',
            'time_window' => null,
            'seats' => $seats,
            'pickup_hint' => null,
            'dropoff_hint' => null,
            'price_cap' => $priceCap,
            'flexible_days' => 0,
            'preferences' => [
                'bus_type' => null,
                'avoid_night' => false,
                'nearby_stops_km' => null,
            ],
        ];
    }
}
