<?php

namespace App\Services;

use App\Repository\Interfaces\TripStationRepositoryInterface;
use App\Models\TripStation;
use Illuminate\Pagination\LengthAwarePaginator;

class TripStationService
{
    public function __construct(private TripStationRepositoryInterface $repo) {}

    public function list(array $filters = [], int $perPage = 15, ?string $sortBy = null, string $sortDir = 'asc'): LengthAwarePaginator
    {
        return $this->repo->list($filters, $perPage, $sortBy, $sortDir);
    }

    public function find(int $id): ?TripStation
    {
        return $this->repo->find($id);
    }

    public function create(array $data): TripStation
    {
        return $this->repo->create($data);
    }

    public function update(int $id, array $data): ?TripStation
    {
        return $this->repo->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
} 