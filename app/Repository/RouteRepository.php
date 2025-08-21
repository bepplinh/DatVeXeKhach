<?php

namespace App\Repository;

use App\Models\Route;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Interfaces\RouteRepositoryInterface;

class RouteRepository implements RouteRepositoryInterface
{
    public function getAll(): Collection
    {
        return Route::with(['fromCity:id,type,name', 'toCity:id,type,name'])->get();
    }

    public function findById(int $id): ?Route
    {
        return Route::with(['fromCity', 'toCity'])->find($id);
    }

    public function create(array $data): Route
    {
        return Route::create($data);
    }

    public function update(int $id, array $data): ?Route
    {
        $route = Route::find($id);
        if ($route) {
            $route->update($data);
            return $route->load(['fromCity', 'toCity']);
        }
        return null;
    }

    public function delete(int $id): bool
    {
        $route = Route::find($id);
        return $route ? $route->delete() : false;
    }

    public function routeExists(int $fromCity, int $toCity): bool
    {
        return Route::where('from_city', $fromCity)
                   ->where('to_city', $toCity)
                   ->exists();
    }

    public function findRoute(int $fromCity, int $toCity): ?Route
    {
        return Route::where('from_city', $fromCity)
                   ->where('to_city', $toCity)
                   ->first();
    }

    public function findReverseRoute(int $fromCity, int $toCity): ?Route
    {
        return Route::where('from_city', $toCity)
                   ->where('to_city', $fromCity)
                   ->first();
    }

    public function createWithReverse(array $data): array
    {
        return DB::transaction(function() use ($data) {
            $results = [];

            $from = Location::find($data['from_city']);
            $to = Location::find($data['to_city']);

            // Tự sinh name nếu không truyền vào
            $data['name'] = ($from ? $from->name : 'Unknown') . ' - ' . ($to ? $to->name : 'Unknown');

            // Tạo tuyến chính và load quan hệ
            $mainRoute = $this->create($data)->load(['fromCity', 'toCity']);
            $results['main_route'] = $mainRoute;
 
            // Tạo route ngược lại nếu chưa tồn tại
            if (!$this->routeExists($data['to_city'], $data['from_city'])) {
                $reverseData = [
                    'from_city' => $data['to_city'],
                    'to_city' => $data['from_city'],
                    'name' => ($to ? $to->name : 'Unknown') . ' - ' . ($from ? $from->name : 'Unknown'),
                ];
                $results['reverse_route'] = $this->create($reverseData)->load(['fromCity', 'toCity']);
            }

            return $results;
        });
    } 
}