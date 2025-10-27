<?php

namespace App\Services\GeminiAI;

use Illuminate\Support\Arr;
use App\Services\GeminiAI\GeminiClient;
use App\Services\TripSearchService;
use App\Support\Time\ViDatetimeParser;
use App\Services\GeminiAI\LocationResolverService;

/**
 * GeminiAiService
 * - Nhận message tự nhiên của người dùng
 * - Gọi Gemini (function calling) để trích tham số
 * - Chuẩn hoá date/time window & resolve location text -> id
 * - Gọi TripSearchService::searchOneWay() và trả message + trips
 *
 * Yêu cầu:
 * - Đã có GeminiClient (App\Services\AI\GeminiClient)
 * - Đã bind LocationResolverService (có thể null nếu bạn chưa dùng text → id)
 * - Đã có TripSearchService::searchOneWay(int $fromLocationId, int $toLocationId, string $dateYmd, array $filters)
 */
class GeminiAiService
{
    public function __construct(
        private GeminiClient $gemini,
        private TripSearchService $tripSearch,
        private LocationResolverService $resolver,
    ) {}

    /**
     * Xử lý 1 lượt chat.
     * @return array{message:string, trips?:array<int, array>}
     */
    public function chat(string $userMessage): array
    {
        // ====== tools (function calling) ======
        $tools = [[
            'functionDeclarations' => [[
                'name' => 'search_trips',
                'description' => 'Tìm chuyến xe khách một chiều theo câu hỏi của người dùng',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        // Người dùng có thể đưa text:
                        'from_text' => ['type' => 'string', 'description' => 'Điểm đi dạng chữ: "Hà Nội", "Kim Mã"'],
                        'to_text'   => ['type' => 'string', 'description' => 'Điểm đến dạng chữ: "Thọ Xuân", "Thanh Hóa"'],
                        // Hoặc đưa sẵn location_id:
                        'from_location_id' => ['type' => 'integer'],
                        'to_location_id'   => ['type' => 'integer'],
                        // Thời gian tự nhiên:
                        'date_text'   => ['type' => 'string', 'description' => '"hôm nay", "mai", "thứ 6", hoặc "YYYY-MM-DD"'],
                        'time_window' => ['type' => 'string', 'description' => '"sáng"|"chiều"|"tối" hoặc "HH:mm-HH:mm"'],
                        // Tuỳ chọn lọc:
                        'passengers'   => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                        'bus_type_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'price_cap'    => ['type' => 'integer'],
                        'min_seats'    => ['type' => 'integer'],
                        'sort'         => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc'],
                        'limit'        => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    ],
                    // Tối thiểu: có ý niệm về ngày (date_text). from/to có thể là text để backend resolve.
                    'required' => ['date_text']
                ]
            ]]
        ]];

        // ====== system instruction (ngắn mà “ép kỷ luật”) ======
        $system = [
            'role' => 'user',
            'parts' => [[
                'text' => <<<PROMPT
<<<SYS

Bạn là trợ lý **chỉ** hỗ trợ tìm **chuyến xe khách (bus)** trên hệ thống nội bộ.
- Trả lời **tiếng Việt**, ngắn gọn.
- Nếu đủ dữ kiện: gọi function `search_trips` để tìm chuyến.
- Nếu thiếu **from/to/date**: hỏi đúng trường bị thiếu, KHÔNG hỏi flights/trains/hotel.
- Thời gian: hiểu nhãn tiếng Việt như "hôm nay/mai/thứ x", "sáng/chiều/tối" hoặc "HH:mm-HH:mm".
- Nếu không có kết quả: nói rõ **không tìm thấy**, gợi ý nới **giờ/giá/số ghế**, giữ nguyên ngôn ngữ Việt.
=======
Bạn là trợ lý tiếng việt *chỉ* hỗ trợ tìm *chuyến xe khách (bus)* trên hệ thống nội bộ.
- Trả lời *tiếng Việt*, ngắn gọn và tuyệt đối không trả lời bằng tiếng anh.
- Nếu đủ dữ kiện: gọi function search_trips để tìm chuyến.
- Nếu không có chuyến nào, nói rõ “Không tìm thấy chuyến nào phù hợp.” bằng tiếng Việt.
- Nếu thiếu *from/to/date*: hỏi đúng trường bị thiếu, KHÔNG hỏi flights/trains/hotel.
- Thời gian: hiểu nhãn tiếng Việt như "hôm nay/mai/thứ x", "sáng/chiều/tối" hoặc "HH:mm-HH:mm".
- Nếu không có kết quả: nói rõ *không tìm thấy*, gợi ý nới *giờ/giá/số ghế*, giữ nguyên ngôn ngữ Việt.
>>>>>>> 0cb8e877b2f3af4d7f0e4097190fa585b8081be8
SYS;
PROMPT
            ]]
        ];

        // ====== Turn 1: hỏi model xem có muốn gọi function không ======
        $payload1 = [
            'contents' => [
                $system,
                ['role' => 'user', 'parts' => [['text' => $userMessage]]],
            ],
            'tools' => $tools,
        ];

        try {
            $first = $this->gemini->firstTurn($payload1);
        } catch (\Throwable $e) {
            return ['message' => 'Xin lỗi, hệ thống AI đang bận. Bạn thử lại sau nhé.'];
        }

        $call = GeminiClient::extractFunctionCall($first);

        // ====== Model yêu cầu gọi function: tiến hành thực thi nghiệp vụ ======
        if ($call && ($call['name'] ?? null) === 'search_trips') {
            $args = $call['args'] ?? [];



            $preFrom = $preTo = '';
            if (preg_match('/^\s*(?:từ\s+)?(.+?)\s+(?:đi|đến|->)\s+(.+?)\s*$/iu', $userMessage, $m)) {
                $preFrom = trim($m[1]);
                $preTo   = trim($m[2]);
            }


            // 1) Resolve from/to
            $fromId = (int) ($args['from_location_id'] ?? 0);
            $toId   = (int) ($args['to_location_id'] ?? 0);

            $fromText = trim((string) ($args['from_text'] ?? ''));
            $toText   = trim((string) ($args['to_text'] ?? ''));

            $fromText = trim((string) ($args['from_text'] ?? $args['from'] ?? $args['origin'] ?? $preFrom));
            $toText   = trim((string) ($args['to_text']   ?? $args['to']   ?? $args['destination'] ?? $preTo));

            if ($this->resolver) {
                if ($fromId <= 0 && $fromText !== '') {
                    $fromId = (int) ($this->resolver->resolveIdFromText($fromText) ?? 0);
                }
                if ($toId <= 0 && $toText !== '') {
                    $toId = (int) ($this->resolver->resolveIdFromText($toText) ?? 0);
                }
            }

            if ($fromId <= 0 || $toId <= 0) {
                $missing = [];
                if ($fromId <= 0) $missing[] = 'điểm đi';
                if ($toId <= 0)   $missing[] = 'điểm đến';
                $m = implode(' và ', $missing);
                return ['message' => "Mình cần $m cụ thể (bạn chọn từ gợi ý giúp mình nhé?)."];
            }

            // ---- Lấy date/time từ nhiều key + fallback từ message gốc ----
            $dateText = (string)($args['date_text']
                ?? $args['date']
                ?? $args['day']
                ?? $args['when']
                ?? $args['travel_date']
                ?? ''
            );

            // Fallback: nếu model không map, thử bóc trực tiếp từ câu người dùng
            if ($dateText === '' && !empty($userMessage)) {
                $dateText = $userMessage; // ViDatetimeParser của bạn hiểu "hôm nay/mai/thứ x"
            }

            if (trim($dateText) === '') {
                return ['message' => 'Bạn muốn đi ngày nào?'];
            }


            $dateYmd = \App\Support\Time\ViDatetimeParser::resolveDate($dateText, 'Asia/Bangkok')->format('Y-m-d');

            $dateYmd = ViDatetimeParser::resolveDate($dateText, 'Asia/Bangkok')->format('Y-m-d');

            // ---- Time window ----
            $timeRaw = (string)($args['time_window'] ?? $args['time'] ?? $args['period'] ?? '');
            $time = mb_strtolower(trim($timeRaw));
            $map = [
                'buổi sáng' => 'sáng',
                'sang' => 'sáng',
                'sáng sớm' => 'sáng',
                'buổi chiều' => 'chiều',
                'chieu' => 'chiều',
                'chiều muộn' => 'chiều',
                'buổi tối'  => 'tối',
                'toi' => 'tối',
                'tối muộn' => 'tối',
            ];
            if (isset($map[$time])) $time = $map[$time];

            $timeFrom = $timeTo = null;
            if ($time !== '') {
                [$timeFrom, $timeTo] = \App\Support\Time\ViDatetimeParser::resolveTimeWindow($time);
            }

            // 3) Map filters sang searchOneWay
            $filters = [];
            if ($timeFrom && $timeTo) {
                $filters['time_from'] = $timeFrom;
                $filters['time_to']   = $timeTo;
            }
            if (!empty($args['bus_type_ids']) && is_array($args['bus_type_ids'])) {
                $filters['bus_type'] = array_map('intval', $args['bus_type_ids']);
            }
            if (isset($args['price_cap']))  $filters['price_cap']  = (int)$args['price_cap'];
            if (isset($args['min_seats']))  $filters['min_seats']  = max(0, (int)$args['min_seats']);
            if (isset($args['limit']))      $filters['limit']      = (int)$args['limit'];
            $sort = strtolower((string)($args['sort'] ?? 'asc'));
            $filters['sort'] = in_array($sort, ['asc', 'desc'], true) ? $sort : 'asc';

            // passengers => tối thiểu số ghế
            if (isset($args['passengers'])) {
                $filters['min_seats'] = max((int)($filters['min_seats'] ?? 0), (int)$args['passengers']);
            }

            // 4) Gọi service tìm chuyến của bạn
            try {
                $trips = $this->tripSearch->searchOneWay($fromId, $toId, $dateYmd, $filters);
            } catch (\Throwable $e) {
                return ['message' => 'Không thể tìm chuyến lúc này. Bạn thử lại sau nhé.'];
            }

            // 5) Turn 2: gửi functionResponse cho Gemini để “kể chuyện” đẹp
            $payload2 = [
                'contents' => [[
                    'role' => 'model',
                    'parts' => [[
                        'functionResponse' => [
                            'name' => 'search_trips',
                            'response' => [
                                'name' => 'search_trips',
                                'content' => [
                                    'trips' => $trips,
                                ],
                            ],
                        ]
                    ]]
                ]]
            ];

            try {
                $second = $this->gemini->secondTurn($payload2);
                $text = GeminiClient::extractText($second) ?? 'Mình đã tìm được một số chuyến phù hợp.';
            } catch (\Throwable $e) {
                // Fallback: nếu turn 2 lỗi, vẫn trả trips để FE render
                $text = 'Mình đã tìm được một số chuyến phù hợp.';
            }

            return ['message' => $text, 'trips' => $trips];
        }

        // ====== Không gọi function: thường là câu hỏi làm rõ ======
        $text = GeminiClient::extractText($first) ?? 'Bạn vui lòng cho biết điểm đi, điểm đến và ngày đi.';
        return ['message' => $text];
    }
}
