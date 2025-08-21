<?php

namespace App\Services;

use App\Models\ScheduleTemplateTrip;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class ScheduleTemplateTripService
{
    public function create(array $data): ScheduleTemplateTrip
    {
        try {
            return ScheduleTemplateTrip::create($data);
        } catch (QueryException $e) {
            // Duplicate key (MySQL): 1062
            if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
                throw ValidationException::withMessages([
                    'route_id' => 'Bản ghi trùng (tuyến/xe/thứ/giờ). Vui lòng kiểm tra lại.',
                ]);
            }
            throw $e;
        }
    }

    public function update(ScheduleTemplateTrip $tpl, array $data): ScheduleTemplateTrip
    {
        try {
            $tpl->update($data);
            return $tpl->refresh();
        } catch (QueryException $e) {
            if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
                throw ValidationException::withMessages([
                    'route_id' => 'Bản ghi trùng (tuyến/xe/thứ/giờ). Vui lòng kiểm tra lại.',
                ]);
            }
            throw $e;
        }
    }
}
