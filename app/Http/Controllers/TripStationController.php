<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\TripStation\StoreTripStationRequest;
use App\Http\Requests\TripStation\UpdateTripStationRequest;
use App\Services\TripStationService;
use App\Models\TripStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class TripStationController extends Controller
{
    public function __construct(private TripStationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'route_id','from_location_id','to_location_id',
            'price_min','price_max','duration_min','duration_max','q'
        ]);
        $perPage = (int) ($request->query('perpage') ?? $request->query('per_page', 15));
        $sortBy = $request->query('sort_by');
        $sortDir = $request->query('sort_dir', 'asc');

        $data = $this->service->list($filters, $perPage, $sortBy, $sortDir);
        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách trạm tuyến thành công',
            'data' => $data,
        ]);
    }

    public function show(TripStation $tripStation): JsonResponse
    {
        $station = $this->service->find($tripStation->id);
        if (!$station) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy bản ghi'], 404);
        }
        return response()->json(['success' => true, 'message' => 'Lấy chi tiết thành công', 'data' => $station]);
    }

    public function store(StoreTripStationRequest $request): JsonResponse
    {
        try {
            $created = $this->service->create($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Tạo trạm tuyến thành công',
                'data' => $created,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function update(UpdateTripStationRequest $request, TripStation $tripStation): JsonResponse
    {
        try {
            $updated = $this->service->update($tripStation->id, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạm tuyến thành công',
                'data' => $updated,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(TripStation $tripStation): JsonResponse
    {
        try {
            $this->service->delete($tripStation->id);
            return response()->json(['success' => true, 'message' => 'Xóa trạm tuyến thành công']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
} 