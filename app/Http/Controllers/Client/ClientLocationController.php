<?php

namespace App\Http\Controllers\Client;

use App\Models\Location;
use Illuminate\Http\Request;

class ClientLocationController
{
    public function index()
    {
        $locations = Location::with(['children' => function($q) {
            $q->select('id','name','type','parent_id');
        }])
            ->whereNull('parent_id')
            ->select('id','name','type')
            ->get();

            return response()->json([
                'status' => 'success',
                'data' => $locations
            ], 200);
    }

    public function search()
    {
        $keyword = request('q');

        $result = Location::where('name', 'like', "%{$keyword}%")->select('id', 'name', 'type', 'parent_id')->get();

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}