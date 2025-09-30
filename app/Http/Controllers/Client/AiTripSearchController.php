<?php

namespace App\Http\Controllers\Client;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\TripSearchService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Support\Time\ViDatetimeParser;
use App\Services\GeminiAI\GeminiAiService;
use Illuminate\Validation\ValidationException;
use App\Services\PlaceResolverService; // <— service map text -> location_id (bạn tạo theo dự án)

class AiTripSearchController extends Controller
{
    public function __construct(
        private GeminiAiService      $gemini,
        private TripSearchService    $tripSearch,
        private PlaceResolverService $placeResolver,   // <— inject service map địa danh
    ) {}

    /**
     * POST /api/ai/search-trips
     * body: { "query": "Cho mình 2 vé Thanh Hóa -> Hà Nội tối mai, gần Mỹ Đình, dưới 250k/ghế" }
     */
    public function search(Request $req)
    {
        $req->validate([
            'query' => 'required|string|max:500',
        ]);

        $query = $req->string('query');

        try {
            // 1) Gọi Gemini trích tham số
            $params = $this->gemini->extractSearchParams($query);

            if (empty($params) || !is_array($params)) {
                throw ValidationException::withMessages([
                    'query' => 'Không trích được tham số từ câu hỏi. Vui lòng cụ thể hơn (điểm đi/đến, ngày/giờ).'
                ]);
            }

            // 2) Chuẩn hoá thời gian (Asia/Bangkok)
            $date = ViDatetimeParser::resolveDate($params['date'] ?? 'hôm nay', 'Asia/Bangkok')
                        ->toDateString();
            [$timeFrom, $timeTo] = ViDatetimeParser::resolveTimeWindow($params['time_window'] ?? 'tối');

            // 3) Map địa danh → location_id và kiểm tra route
            $originText = (string)($params['origin'] ?? '');
            $destText   = (string)($params['destination'] ?? '');

            $fromId = $this->placeResolver->toLocationId($originText);
            $toId   = $this->placeResolver->toLocationId($destText);

            if (!$fromId || !$toId) {
                throw ValidationException::withMessages([
                    'query' => 'Không xác định được điểm đi/đến. Vui lòng nêu rõ địa danh hoặc chọn từ gợi ý.'
                ]);
            }

            // 4) Kiểm tra route có tồn tại không (giống TripSearchService)
            if (!$this->placeResolver->hasRoute($originText, $destText)) {
                throw ValidationException::withMessages([
                    'query' => 'Không tìm thấy tuyến đường từ ' . $originText . ' đến ' . $destText . '. Vui lòng chọn tuyến đường khác.'
                ]);
            }

            // 5) Lấy các filter
            $filters = [
                'time_from'  => $timeFrom,
                'time_to'    => $timeTo,
                'min_seats'  => (int)($params['seats'] ?? 1),
                'price_cap'  => (int)($params['price_cap'] ?? 0),
                'bus_type'   => $this->mapBusType(data_get($params, 'preferences.bus_type')), // id[]
                'sort_by'    => 'departure_time',
                'sort'       => 'asc',
                'limit'      => 50,
                // 'relax_on_empty' => true, // tuỳ bạn mở rộng
            ];

            // 6) Gọi service search
            $items = $this->tripSearch->searchOneWay(
                fromLocationId: $fromId,
                toLocationId:   $toId,
                dateYmd:        $date,
                filters:        $filters
            );

            // 7) Trả về FE: items + filters chuẩn hoá để hiển thị chips
            $chips = [
                'origin'       => $originText ?: null,
                'destination'  => $destText ?: null,
                'date'         => $date,
                'time_window'  => "{$timeFrom}-{$timeTo}",
                'seats'        => (int)($params['seats'] ?? 1),
                'price_cap'    => $filters['price_cap'] ?: null,
                'bus_type'     => data_get($params, 'preferences.bus_type') ?: null,
                'pickup_hint'  => $params['pickup_hint'] ?? null,
                'dropoff_hint' => $params['dropoff_hint'] ?? null,
            ];

            return response()->json([
                'filters' => $chips,
                'items'   => $items,
                // 'relaxed' => false, // nếu bạn có cơ chế relaxed search thì set ở đây
            ]);
        } catch (ValidationException $ve) {
            throw $ve; // để Laravel trả 422 với messages
        } catch (\Throwable $e) {
            Log::warning('AI trip search failed', [
                'q'   => (string) $query,
                'err' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Đã có lỗi khi xử lý yêu cầu tìm chuyến.',
            ], 500);
        }
    }

    /**
     * Tìm route trực tiếp từ text địa điểm (alternative method)
     * Sử dụng khi muốn tìm route mà không cần location_id
     */
    public function searchByRouteText(Request $req)
    {
        $req->validate([
            'query' => 'required|string|max:500',
        ]);

        $query = $req->string('query');

        try {
            // 1) Gọi Gemini trích tham số
            $params = $this->gemini->extractSearchParams($query);

            if (empty($params) || !is_array($params)) {
                throw ValidationException::withMessages([
                    'query' => 'Không trích được tham số từ câu hỏi. Vui lòng cụ thể hơn (điểm đi/đến, ngày/giờ).'
                ]);
            }

            // 2) Chuẩn hoá thời gian
            $date = ViDatetimeParser::resolveDate($params['date'] ?? 'hôm nay', 'Asia/Bangkok')
                        ->toDateString();
            [$timeFrom, $timeTo] = ViDatetimeParser::resolveTimeWindow($params['time_window'] ?? 'tối');

            // 3) Tìm route trực tiếp từ text
            $originText = (string)($params['origin'] ?? '');
            $destText   = (string)($params['destination'] ?? '');

            $routeIds = $this->placeResolver->findAllRouteIds($originText, $destText);
            
            if (empty($routeIds)) {
                throw ValidationException::withMessages([
                    'query' => 'Không tìm thấy tuyến đường từ ' . $originText . ' đến ' . $destText . '. Vui lòng chọn tuyến đường khác.'
                ]);
            }

            // 4) Lấy location_id để sử dụng với TripSearchService
            $fromId = $this->placeResolver->toLocationId($originText);
            $toId   = $this->placeResolver->toLocationId($destText);

            // 5) Lấy các filter
            $filters = [
                'time_from'  => $timeFrom,
                'time_to'    => $timeTo,
                'min_seats'  => (int)($params['seats'] ?? 1),
                'price_cap'  => (int)($params['price_cap'] ?? 0),
                'bus_type'   => $this->mapBusType(data_get($params, 'preferences.bus_type')),
                'sort_by'    => 'departure_time',
                'sort'       => 'asc',
                'limit'      => 50,
            ];

            // 6) Gọi service search
            $items = $this->tripSearch->searchOneWay(
                fromLocationId: $fromId,
                toLocationId:   $toId,
                dateYmd:        $date,
                filters:        $filters
            );

            // 7) Trả về kết quả
            $chips = [
                'origin'       => $originText ?: null,
                'destination'  => $destText ?: null,
                'date'         => $date,
                'time_window'  => "{$timeFrom}-{$timeTo}",
                'seats'        => (int)($params['seats'] ?? 1),
                'price_cap'    => $filters['price_cap'] ?: null,
                'bus_type'     => data_get($params, 'preferences.bus_type') ?: null,
                'pickup_hint'  => $params['pickup_hint'] ?? null,
                'dropoff_hint' => $params['dropoff_hint'] ?? null,
                'route_ids'    => $routeIds, // Thêm thông tin route_ids tìm được
            ];

            return response()->json([
                'filters' => $chips,
                'items'   => $items,
                'route_count' => count($routeIds),
            ]);

        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            Log::warning('AI trip search by route text failed', [
                'q'   => (string) $query,
                'err' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Đã có lỗi khi xử lý yêu cầu tìm chuyến.',
            ], 500);
        }
    }

    /**
     * Map chuỗi bus_type (giuong_nam|limousine|ghe_ngoi hoặc null)
     * sang mảng id type_buses trong DB. Trả về [] nếu không map được.
     */
    private function mapBusType(?string $busType): array
    {
        if (!$busType) return [];

        // Ví dụ map slug -> name
        $nameMap = [
            'giuong_nam' => 'Giường nằm',
            'limousine'  => 'Limousine',
            'ghe_ngoi'   => 'Ghế ngồi',
        ];

        $targetName = $nameMap[$busType] ?? null;
        if (!$targetName) return [];

        $id = (int) DB::table('type_buses')->where('name', $targetName)->value('id');
        return $id ? [$id] : [];
    }
}
