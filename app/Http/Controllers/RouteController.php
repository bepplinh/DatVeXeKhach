<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Route\RouteRequest;
use App\Services\RouteService;
use App\Models\Route;
use Illuminate\Http\JsonResponse;
use Exception;

class RouteController extends Controller
{
    protected RouteService $routeService;

    public function __construct(RouteService $routeService)
    {
        $this->routeService = $routeService;
    }

    public function index(): JsonResponse
    {
        $routes = $this->routeService->getAllRoutes();

        if ($routes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Không có tuyến đường nào'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách tuyến đường thành công',
            'data' => $routes
        ]);
    }

    public function show(Route $route): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Lấy thông tin tuyến đường thành công',
                'data' => $route->load(['fromCity', 'toCity'])
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thông tin tuyến đường',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(RouteRequest $request): JsonResponse
    {
        try {
            $result = $this->routeService->createRoute($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Tạo tuyến đường thành công',
                'data' => $result
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function update(RouteRequest $request, Route $route): JsonResponse
    {
        try {
            $updatedRoute = $this->routeService->updateRoute($route->id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật tuyến đường thành công',
                'data' => $updatedRoute
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy(Route $route): JsonResponse
    {
        try {
            $this->routeService->deleteRoute($route->id);

            return response()->json([
                'success' => true,
                'message' => 'Xóa tuyến đường thành công'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function checkExists(int $fromCity, int $toCity): JsonResponse
    {
        try {
            $exists = $this->routeService->checkRouteExists($fromCity, $toCity);

            return response()->json([
                'success' => true,
                'exists' => $exists,
                'message' => $exists ? 'Tuyến đường đã tồn tại' : 'Tuyến đường chưa tồn tại'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi kiểm tra tuyến đường',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
