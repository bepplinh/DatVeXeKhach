<?php

namespace App\Repository;

use App\Models\Location;
use App\Repository\Interfaces\LocationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class LocationRepository implements LocationRepositoryInterface
{
    protected $model;

    public function __construct(Location $model)
    {
        $this->model = $model;
    }

    public function all(): Collection
    {
        return $this->model->get();
    }

    public function paginate($perPage = 15): LengthAwarePaginator
    {
        return $this->model->paginate($perPage);
    }

    public function find($id): ?Location
    {
        return $this->model->with('children')->find($id);
    }

    public function create(array $data): Location
    {
        return $this->model->create($data);
    }

    public function update($id, array $data): bool
    {
        $location = $this->model->find($id);
        if (!$location) {
            return false;
        }
        
        return $location->update($data);
    }

    public function delete($id): bool
    {
        $location = $this->model->find($id);
        if (!$location) {
            return false;
        }
        
        return $location->delete();
    }

    public function findByName($name): ?Location
    {
        return $this->model->where('name', $name)->first();
    }

    public function searchByKeyword($keyword): Collection
    {
        return $this->model
            ->with('children')
            ->where('name', 'LIKE', "%{$keyword}%")
            ->get();
    }

    public function getCities(): Collection
    {
        return $this->model->cities()->with('children')->get();
    }

    public function getDistricts($cityId = null): Collection
    {
        return $this->model->districts($cityId)->with('children')->get();
    }

    public function getWards($districtId = null): Collection
    {
        return $this->model->wards($districtId)->with('children')->get();
    }

    public function getByType($type): Collection
    {
        return $this->model->ofType($type)->with('children')->get();
    }

    public function getChildren($parentId): Collection
    {
        return $this->model->where('parent_id', $parentId)->get();
    }

    public function getTreeStructure(): Collection
    {
        return $this->model->cities()
            ->with(['children.children'])
            ->get();
    }
}