<?php

namespace App\Services;

use App\Repository\Interfaces\LocationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class LocationService
{
    protected $locationRepository;

    public function __construct(LocationRepositoryInterface $locationRepository)
    {
        $this->locationRepository = $locationRepository;
    }

    public function getAllLocations(): Collection
    {
        return $this->locationRepository->all();
    }

    public function getPaginatedLocations($perPage = 15): LengthAwarePaginator
    {
        return $this->locationRepository->paginate($perPage);
    }

    public function getLocationById($id)
    {
        $location = $this->locationRepository->find($id);
        if (!$location) {
            throw ValidationException::withMessages([
                'id' => ['Địa điểm không tồn tại']
            ]);
        }
        return $location;
    }

    public function createLocation(array $data)
    {
        $this->validateLocationData($data);
        $this->validateHierarchy($data);
        $this->checkDuplicateName($data);

        return $this->locationRepository->create($data);
    }

    public function updateLocation($id, array $data)
    {
        $location = $this->getLocationById($id);

        $this->validateLocationData($data);
        $this->validateHierarchy($data, $id);
        $this->checkDuplicateName($data, $id);

        $updated = $this->locationRepository->update($id, $data);
        if (!$updated) {
            throw ValidationException::withMessages([
                'id' => ['Không thể cập nhật địa điểm']
            ]);
        }
        return $this->getLocationById($id);
    }

    public function deleteLocation($id)
    {
        $this->getLocationById($id);

        $children = $this->locationRepository->getChildren($id);
        if ($children->count() > 0) {
            throw ValidationException::withMessages([
                'id' => ['Không thể xoá địa điểm có chứa địa điểm con']
            ]);
        }

        $deleted = $this->locationRepository->delete($id);
        if (!$deleted) {
            throw ValidationException::withMessages([
                'id' => ['Không thể xoá địa điểm']
            ]);
        }
        return true;
    }

    public function searchLocations($keyword): Collection
    {
        if (empty($keyword)) {
            return collect([]);
        }
        return $this->locationRepository->searchByKeyword($keyword);
    }

    public function getCities(): Collection
    {
        return $this->locationRepository->getCities();
    }

    public function getDistricts($cityId = null): Collection
    {
        return $this->locationRepository->getDistricts($cityId);
    }

    public function getWards($districtId = null): Collection
    {
        return $this->locationRepository->getWards($districtId);
    }

    public function getLocationsByType($type): Collection
    {
        if (!in_array($type, ['city', 'district', 'ward'])) {
            throw ValidationException::withMessages([
                'type' => ['Loại địa điểm không hợp lệ']
            ]);
        }
        return $this->locationRepository->getByType($type);
    }

    public function getTreeStructure(): Collection
    {
        return $this->locationRepository->getTreeStructure();
    }

    public function getLocationWithPath($id)
    {
        $location = $this->locationRepository->find($id);
        if (!$location) {
            throw ValidationException::withMessages([
                'id' => ['Địa điểm không tồn tại']
            ]);
        }
        return $location;
    }

    /** ---------- private validate logic ---------- */

    private function validateLocationData(array $data)
    {
        if (empty($data['name'])) {
            throw ValidationException::withMessages([
                'name' => ['Tên địa điểm không được để trống']
            ]);
        }
        if (empty($data['type'])) {
            throw ValidationException::withMessages([
                'type' => ['Loại địa điểm không được để trống']
            ]);
        }
        if (!in_array($data['type'], ['city', 'district', 'ward'])) {
            throw ValidationException::withMessages([
                'type' => ['Loại địa điểm không hợp lệ (city, district, ward)']
            ]);
        }
    }

    private function validateHierarchy(array $data, $currentId = null)
    {
        $type     = $data['type'];
        $parentId = $data['parent_id'] ?? null;

        if ($type === 'city' && $parentId) {
            throw ValidationException::withMessages([
                'parent_id' => ['Thành phố không được có địa điểm cha']
            ]);
        }

        if ($type === 'district') {
            if (!$parentId) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Quận/Huyện phải thuộc về một Thành phố']
                ]);
            }
            $parent = $this->locationRepository->find($parentId);
            if (!$parent || $parent->type !== 'city') {
                throw ValidationException::withMessages([
                    'parent_id' => ['Quận/Huyện chỉ có thể thuộc về Thành phố']
                ]);
            }
        }

        if ($type === 'ward') {
            if (!$parentId) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Phường/Xã phải thuộc về một Quận/Huyện']
                ]);
            }
            $parent = $this->locationRepository->find($parentId);
            if (!$parent || $parent->type !== 'district') {
                throw ValidationException::withMessages([
                    'parent_id' => ['Phường/Xã chỉ có thể thuộc về Quận/Huyện']
                ]);
            }
        }

        if ($currentId && $parentId == $currentId) {
            throw ValidationException::withMessages([
                'parent_id' => ['Địa điểm không thể là cha của chính nó']
            ]);
        }
    }

    private function checkDuplicateName(array $data, $currentId = null)
    {
        $parentId = $data['parent_id'] ?? null;
        $name     = $data['name'];

        $existing = $this->locationRepository->all()
            ->where('name', $name)
            ->where('parent_id', $parentId);

        if ($currentId) {
            $existing = $existing->where('id', '!=', $currentId);
        }

        if ($existing->count() > 0) {
            throw ValidationException::withMessages([
                'name' => ['Tên đã tồn tại trong khu vực này']
            ]);
        }
    }
}
