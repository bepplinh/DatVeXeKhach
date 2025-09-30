<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchTripsRequest\SearchTripsRequest;
use App\Services\TripSearchService;

class TripSearchController extends Controller
{
    public function __construct(private TripSearchService $service) {}

    public function search(SearchTripsRequest $request)
    {
        $q = $request->validated();

        $outbound = $this->service->searchOneWay(
            $q['from_location_id'],
            $q['to_location_id'],
            $q['date'],
            $q
        );

        $data = ['outbound' => $outbound];

        if (!empty($q['return_date'])) {
            $data['return'] = $this->service->searchOneWay(
                $q['to_location_id'],
                $q['from_location_id'],
                $q['return_date'],
                $q
            );
        }

        if($data['outbound'] === [] ||
           (isset($data['return']) && $data['return'] === [])) {
            return response()->json([
                'success' => false,
                'message' => 'Không có chuyến xe nào'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

}