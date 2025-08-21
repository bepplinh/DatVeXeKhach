<?php

namespace App\Services;

use App\Repository\Interfaces\RouteRepositoryInterface;
use App\Models\Route;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class RouteService
{
    protected RouteRepositoryInterface $routeRepository;

    public function __construct(RouteRepositoryInterface $routeRepository)
    {
        $this->routeRepository = $routeRepository;
    }

    public function getAllRoutes(): Collection
    {
        return $this->routeRepository->getAll();
    }

    public function getRouteById(int $id): ?Route
    {
        return $this->routeRepository->findById($id);
    }

    public function createRoute(array $data): array
    {
        // Kiểm tra route đã tồn tại chưa
        if ($this->routeRepository->routeExists($data['from_city'], $data['to_city'])) {
            throw new Exception('Tuyến đường này đã tồn tại');
        }

        return $this->routeRepository->createWithReverse($data);
    }

    public function updateRoute(int $id, array $data): ?Route
    {
        $existingRoute = $this->routeRepository->findById($id);
        if (!$existingRoute) {
            throw new Exception('Không tìm thấy tuyến đường');
        }

        // Kiểm tra nếu thay đổi from_city hoặc to_city thì không được trùng với route khác
        if (($data['from_city'] != $existingRoute->from_city || $data['to_city'] != $existingRoute->to_city)) {
            $duplicateRoute = $this->routeRepository->findRoute($data['from_city'], $data['to_city']);
            if ($duplicateRoute && $duplicateRoute->id != $id) {
                throw new Exception('Tuyến đường này đã tồn tại');
            }
        }

        return $this->routeRepository->update($id, $data);
    }

    public function deleteRoute(int $id): bool
    {
        $route = $this->routeRepository->findById($id);
        if (!$route) {
            throw new Exception('Không tìm thấy tuyến đường');
        }

        return $this->routeRepository->delete($id);
    }

    public function checkRouteExists(int $fromCity, int $toCity): bool
    {
        return $this->routeRepository->routeExists($fromCity, $toCity);
    }
}