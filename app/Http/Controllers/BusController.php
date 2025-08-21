<?php

namespace App\Http\Controllers;

use App\Models\Bus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Bus\StoreBusRequest;
use App\Http\Requests\Bus\SearchBusRequest;
use App\Http\Requests\Bus\UpdateBusRequest;

class BusController extends Controller
{
    public function index(Request $request)
    {
        $perPage     = $request->query('per_page', 10);      // mặc định phân trang 10 bản ghi
        $search      = $request->query('search');            // tìm kiếm theo code / name / plate_number
        $typeBusId   = $request->query('type_bus_id');       // lọc theo loại xe

        $buses = Bus::with('typeBus')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhere('name', 'like', "%$search%")
                        ->orWhere('plate_number', 'like', "%$search%");
                });
            })
            ->when($typeBusId, function ($query) use ($typeBusId) {
                $query->where('type_bus_id', $typeBusId);
            })
            ->paginate($perPage);

        return response()->json($buses);
    }

    public function store(StoreBusRequest $request): JsonResponse
    {
        $bus = Bus::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Thêm xe thành công',
            'data' => $bus->load('typeBus'),
        ], 201);
    }

    public function show(Bus $bus): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Lấy thông tin xe thành công',
            'data' => $bus->load('typeBus'),
        ]);
    }

    public function update(UpdateBusRequest $request, Bus $bus): JsonResponse
    {
        $bus->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật xe thành công',
            'data' => $bus->load('typeBus'),
        ]);
    }

    public function destroy(Bus $bus): JsonResponse
    {
        $bus->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xoá xe thành công',
        ]);
    }
}
