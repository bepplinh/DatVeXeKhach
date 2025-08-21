<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trip\StoreTripRequest;
use App\Http\Requests\Trip\UpdateTripRequest;
use App\Services\TripService;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class TripController extends Controller
{
    protected TripService $tripService;

    public function __construct(TripService $tripService)
    {
        $this->tripService = $tripService;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['route_id','status','bus_id','date_from','date_to','from_city','to_city','direction']);

        // Alias: route_pair=from-to (e.g., 1-2)
        $routePair = $request->query('route_pair');
        if ($routePair && preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', $routePair, $m)) {
            $filters['from_city'] = (int) $m[1];
            $filters['to_city'] = (int) $m[2];
        }

        $perPage = (int) ($request->query('perpage') ?? $request->query('per_page', 15));
        $trips = $this->tripService->listTrips($filters, $perPage);
        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách chuyến xe thành công',
            'data' => $trips,
        ]);
    }

    public function show(Trip $trip): JsonResponse
    {
        $trip = $this->tripService->getTripById($trip->id);
        if (!$trip) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy chuyến xe'], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Lấy thông tin chuyến xe thành công',
            'data' => $trip,
        ]);
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        try {
            $trip = $this->tripService->createTrip($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Tạo chuyến xe thành công',
                'data' => $trip,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        try {
            $updated = $this->tripService->updateTrip($trip->id, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật chuyến xe thành công',
                'data' => $updated,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Trip $trip): JsonResponse
    {
        try {
            $this->tripService->deleteTrip($trip->id);
            return response()->json([
                'success' => true,
                'message' => 'Xóa chuyến xe thành công',
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
} 