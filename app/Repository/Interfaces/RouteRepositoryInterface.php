<?php

namespace App\Repository\Interfaces;

use App\Models\Route;
use Illuminate\Database\Eloquent\Collection;

interface RouteRepositoryInterface
{
    public function getAll(): Collection;
    
    public function findById(int $id): ?Route;
    
    public function create(array $data): Route;
    
    public function update(int $id, array $data): ?Route;
    
    public function delete(int $id): bool;
    
    public function routeExists(int $fromCity, int $toCity): bool;
    
    public function findRoute(int $fromCity, int $toCity): ?Route;
    
    public function findReverseRoute(int $fromCity, int $toCity): ?Route;
    
    public function createWithReverse(array $data): array;
}