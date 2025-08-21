<?php

namespace App\Repository\Interfaces;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function getAllPaginated(int $perPage = 10): LengthAwarePaginator;
    
    public function findById(int $id): ?User;
    
    public function create(array $data): User;
    
    public function update(int $id, array $data): bool;
    
    public function delete(int $id): bool;
    
    public function search(string $keyword, int $perPage = 10): LengthAwarePaginator;
}