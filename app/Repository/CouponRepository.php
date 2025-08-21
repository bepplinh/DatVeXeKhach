<?php

namespace App\Repository;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CouponRepository
{
    protected $model;

    public function __construct(Coupon $model)
    {
        $this->model = $model;
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getFiltered(array $filters): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true)
                      ->where(function ($q) {
                          $q->whereNull('valid_until')
                            ->orWhere('valid_until', '>', now());
                      })
                      ->where(function ($q) {
                          $q->whereNull('valid_from')
                            ->orWhere('valid_from', '<=', now());
                      })
                      ->where(function ($q) {
                          $q->whereNull('max_usage')
                            ->orWhereRaw('used_count < max_usage');
                      });
            } elseif ($filters['status'] === 'inactive') {
                $query->where(function ($q) {
                    $q->where('is_active', false)
                      ->orWhere('valid_until', '<', now())
                      ->orWhere('valid_from', '>', now())
                      ->orWhereRaw('used_count >= max_usage');
                });
            }
        }

        // Discount type filter
        if (!empty($filters['discount_type'])) {
            $query->where('discount_type', $filters['discount_type']);
        }

        // Sort
        $sortField = $filters['sort'] ?? 'created_at';
        $sortOrder = $filters['order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        // Pagination
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findById(int $id): ?Coupon
    {
        return $this->model->find($id);
    }

    public function findByCode(string $code): ?Coupon
    {
        return $this->model->where('code', $code)->first();
    }

    public function create(array $data): Coupon
    {
        return $this->model->create($data);
    }

    public function update(Coupon $coupon, array $data): bool
    {
        return $coupon->update($data);
    }

    public function delete(Coupon $coupon): bool
    {
        return $coupon->delete();
    }

    public function getActiveCoupons(): Collection
    {
        return $this->model->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            })
            ->where(function ($query) {
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('max_usage')
                    ->orWhereRaw('used_count < max_usage');
            })
            ->get();
    }

    public function incrementUsage(Coupon $coupon): bool
    {
        return $coupon->increment('used_count');
    }

    public function search(string $keyword): Collection
    {
        return $this->model->where('name', 'like', "%{$keyword}%")
            ->orWhere('code', 'like', "%{$keyword}%")
            ->orWhere('description', 'like', "%{$keyword}%")
            ->get();
    }
} 