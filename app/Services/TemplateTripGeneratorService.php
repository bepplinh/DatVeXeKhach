<?php

namespace App\Services;

use App\Models\ScheduleTemplateTrip;
use App\Models\Trip;
use App\Models\Bus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TemplateTripGeneratorService
{
    public function generateForDate(Carbon $date, ?array $templateIds = null): array
    {
        $weekday = (int) $date->dayOfWeek; // 0..6
        $created = 0;
        $skipped = 0;
        $details = [];

        $tplQuery = ScheduleTemplateTrip::query()
            ->where('active', true)
            ->where('weekday', $weekday);

        if ($templateIds) $tplQuery->whereIn('id', $templateIds);

        $templates = $tplQuery->get();

        foreach ($templates as $tpl) {
            // tạo DateTime đầy đủ
            $departureDateTime = Carbon::parse($date->toDateString() . ' ' . $tpl->departure_time);

            // chọn bus
            $busId = $tpl->bus_id ?: $this->pickAvailableBus($departureDateTime);

            if (!$busId) {
                $skipped++;
                $details[] = "No bus available {$departureDateTime} (tpl {$tpl->id})";
                continue;
            }

            try {
                DB::beginTransaction();

                // Idempotent: nếu đã có trip cùng route+bus+departure_time thì bỏ qua
                $existsQuery = Trip::where('route_id', $tpl->route_id)
                    ->where('departure_time', $departureDateTime);

                if ($busId !== null) {
                    $existsQuery->where('bus_id', $busId);
                } else {
                    // trường hợp hiếm khi cho phép bus null
                    $existsQuery->whereNull('bus_id');
                }

                if ($existsQuery->exists()) {
                    DB::rollBack();
                    $skipped++;
                    $details[] = "Skip exists {$departureDateTime} (tpl {$tpl->id})";
                    continue;
                }

                Trip::create([
                    'route_id'       => $tpl->route_id,
                    'bus_id'         => $busId,                       // luôn nên có để tránh trùng NULL
                    'departure_time' => $departureDateTime,           // DATETIME
                    'status'         => 'scheduled',
                ]);

                DB::commit();
                $created++;
                $details[] = "Created {$departureDateTime} (tpl {$tpl->id})";
            } catch (\Throwable $e) {
                DB::rollBack();
                $skipped++;
                $details[] = "Error tpl {$tpl->id}: " . $e->getMessage();
            }
        }

        return compact('created', 'skipped', 'details');
    }

    /**
     * Chọn 1 bus rảnh ở đúng khung giờ (không có trip trùng departure_time).
     * Bạn có thể nâng cấp rule theo route/garage/loại xe...
     */
    private function pickAvailableBus(Carbon $departureDateTime): ?int
    {
        $candidateBusIds = Bus::pluck('id');

        foreach ($candidateBusIds as $busId) {
            $busy = Trip::where('bus_id', $busId)
                ->where('departure_time', $departureDateTime)
                ->exists();
            if (!$busy) return (int)$busId;
        }
        return null;
    }
}
