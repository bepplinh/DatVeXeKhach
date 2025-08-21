<?php

namespace App\Services;

use App\Repository\Interfaces\TripRepositoryInterface;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;

class TripService
{
    protected TripRepositoryInterface $tripRepository;

    public function __construct(TripRepositoryInterface $tripRepository)
    {
        $this->tripRepository = $tripRepository;
    }

    public function listTrips(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->tripRepository->getAll($filters, $perPage);
    }

    public function getTripById(int $id): ?Trip
    {
        return $this->tripRepository->findById($id);
    }

    public function createTrip(array $data): Trip
    {
        if (empty($data['status'])) {
            $data['status'] = 'scheduled';
        }
        return $this->tripRepository->create($data);
    }

    public function updateTrip(int $id, array $data): ?Trip
    {
        return $this->tripRepository->update($id, $data);
    }

    public function deleteTrip(int $id): bool
    {
        return $this->tripRepository->delete($id);
    }

    public function changeStatus(int $id, string $status): ?Trip
    {
        $allowed = ['scheduled','running','finished','cancelled'];
        if (!in_array($status, $allowed, true)) {
            throw new Exception('Trạng thái không hợp lệ');
        }
        return $this->tripRepository->update($id, ['status' => $status]);
    }

    public function assignBus(int $id, ?int $busId): ?Trip
    {
        return $this->tripRepository->update($id, ['bus_id' => $busId]);
    }
} 