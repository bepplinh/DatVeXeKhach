<?php

namespace App\Http\Controllers;

use App\Models\BusType;
use Illuminate\Http\Request;
use App\Http\Requests\BusType\StoreTypeBusRequest;

class BusTypeController extends Controller
{
    public function index()
    {
        $data = BusType::all();
        if ($data->isEmpty()) {
            return response()->json([
                'message' => 'No bus types found',
            ], 404);
        }
        return response()->json([
            'message' => 'List of bus types',
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $busType = BusType::find($id);
        return response()->json([
            'message' => 'Bus type details',
            'data' => $busType
        ]);
    }

    public function store(StoreTypeBusRequest $request)
    {
        $busType = BusType::create($request->all());
        return response()->json([
            'message' => 'Bus type created successfully',
            'data' => $busType
        ], 201);
    }

    public function update(StoreTypeBusRequest $request, $id)
    {
        $busType = BusType::find($id);
        $busType->update($request->all());
        return response()->json([
            'message' => 'Bus type updated successfully',
            'data' => $busType
        ]);
    }
}
