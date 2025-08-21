<?php

namespace App\Http\Controllers;

use App\Services\LocationService;
use App\Http\Requests\Location\LocationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    protected $locationService;

    public function __construct(LocationService $locationService)
    {
        $this->locationService = $locationService;
    }

    public function index(Request $request): JsonResponse
    {
        // Tìm kiếm theo keyword
        if ($keyword = $request->get('search')) {
            $locations = $this->locationService->searchLocations($keyword);

            if (empty($locations)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not Locations Found'
                ]);
            }

            return response()->json([
                'success' => true,
                'data'    => $locations,
                'message' => 'Tìm kiếm thành công'
            ]);
        }

        // Lọc theo type
        if ($type = $request->get('type')) {
            $locations = $this->locationService->getLocationsByType($type);

            if (empty($locations)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not Locations Found'
                ]);
            }

            return response()->json([
                'success' => true,
                'data'    => $locations,
                'message' => 'Lấy danh sách thành công'
            ]);
        }

        // Lấy theo parent_id
        if ($parentId = $request->get('parent_id')) {
            if ($request->get('parent_type') === 'city') {
                $locations = $this->locationService->getDistricts($parentId);
            } elseif ($request->get('parent_type') === 'district') {
                $locations = $this->locationService->getWards($parentId);
            } else {
                $locations = collect();
            }

            if ($locations->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not Locations Found'
                ]);
            }

            return response()->json([
                'success' => true,
                'data'    => $locations,
                'message' => 'Lấy danh sách con thành công'
            ]);
        }

        // Mặc định: phân trang
        $perPage = $request->get('per_page', 15);
        $locations = $this->locationService->getPaginatedLocations($perPage);

        if ($locations->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Not Locations Found'
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $locations,
            'message' => 'Lấy danh sách địa điểm thành công'
        ]);
    }


    public function show($id): JsonResponse
    {
        try {
            $location = $this->locationService->getLocationById($id);

            return response()->json([
                'success' => true,
                'data' => $location,
                'message' => 'Lấy thông tin địa điểm thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function store(LocationRequest $request): JsonResponse
    {
        $location = $this->locationService->createLocation($request->validated());

        return response()->json([
            'success' => true,
            'data' => $location,
            'message' => 'Tạo địa điểm thành công'
        ], 201);
    }

    public function update(LocationRequest $request, $id): JsonResponse
    {
        try {
            $location = $this->locationService->updateLocation($id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $location,
                'message' => 'Cập nhật địa điểm thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $this->locationService->deleteLocation($id);

            return response()->json([
                'success' => true,
                'message' => 'Xóa địa điểm thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    // API endpoints riêng cho hierarchy
    public function cities(): JsonResponse
    {
        try {
            $cities = $this->locationService->getCities();

            return response()->json([
                'success' => true,
                'data' => $cities,
                'message' => 'Lấy danh sách thành phố thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function districts(Request $request): JsonResponse
    {
        try {
            $cityId = $request->get('city_id');
            $districts = $this->locationService->getDistricts($cityId);

            return response()->json([
                'success' => true,
                'data' => $districts,
                'message' => 'Lấy danh sách quận/huyện thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function wards(Request $request): JsonResponse
    {
        try {
            $districtId = $request->get('district_id');
            $wards = $this->locationService->getWards($districtId);

            return response()->json([
                'success' => true,
                'data' => $wards,
                'message' => 'Lấy danh sách phường/xã thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function tree(): JsonResponse
    {
        try {
            $tree = $this->locationService->getTreeStructure();

            return response()->json([
                'success' => true,
                'data' => $tree,
                'message' => 'Lấy cây địa điểm thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
