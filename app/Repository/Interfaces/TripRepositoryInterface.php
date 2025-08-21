<?php

namespace App\Repository\Interfaces;

use App\Models\Trip;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface TripRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id): ?Trip;

    public function create(array $data): Trip;

    public function update(int $id, array $data): ?Trip;

    public function delete(int $id): bool;
} 