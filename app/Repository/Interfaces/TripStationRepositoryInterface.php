<?php

namespace App\Repository\Interfaces;

use App\Models\TripStation;
use Illuminate\Pagination\LengthAwarePaginator;

interface TripStationRepositoryInterface
{
    public function list(array $filters = [], int $perPage = 15, ?string $sortBy = null, string $sortDir = 'asc'): LengthAwarePaginator;

    public function find(int $id): ?TripStation;

    public function create(array $data): TripStation;

    public function update(int $id, array $data): ?TripStation;

    public function delete(int $id): bool;
} 