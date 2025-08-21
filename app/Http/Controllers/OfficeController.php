<?php

namespace App\Http\Controllers;

use App\Http\Requests\Office\OfficeRequest;
use App\Models\Office;
use Illuminate\Http\JsonResponse;

class OfficeController extends Controller
{
    public function index(): JsonResponse
    {
        $offices = Office::all();

        return response()->json([
            'success' => true,
            'data' => $offices,
        ]);
    }

    public function store(OfficeRequest $request): JsonResponse
    {
        $office = Office::create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $office,
            'message' => 'Thêm văn phòng thành công'
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $office = Office::with('location')->find($id);

        if (!$office) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        return response()->json(['data' => $office]);
    }

    public function update(OfficeRequest $request, $id): JsonResponse
    {
        $office = Office::find($id);

        if (!$office) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        $office->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $office,
            'message' => 'Cập nhật thành công'
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $office = Office::find($id);

        if (!$office) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        $office->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xoá thành công'
        ]);
    }
}
