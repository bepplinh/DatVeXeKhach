<?php

namespace App\Http\Requests\ScheduleTemplateTrip;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ScheduleTemplateTrip;

class Store_Schedule_Template_Trip_Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'route_id' => 'required|exists:routes,id',
            'bus_id' => 'required|exists:buses,id',
            'weekday' => 'required|integer|between:0,6',
            'departure_time' => 'required|date_format:H:i:s',
            'active' => 'boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'route_id.required' => 'Vui lòng chọn tuyến đường',
            'route_id.exists' => 'Tuyến đường không tồn tại',
            'bus_id.required' => 'Vui lòng chọn xe',
            'bus_id.exists' => 'Xe không tồn tại',
            'weekday.required' => 'Vui lòng chọn ngày trong tuần',
            'weekday.between' => 'Ngày trong tuần phải từ 0-6 (0=Chủ nhật, 1=Thứ 2, ...)',
            'departure_time.required' => 'Vui lòng nhập giờ khởi hành',
            'departure_time.date_format' => 'Giờ khởi hành phải có định dạng HH:mm:ss'
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->validateNoScheduleConflict($validator);
        });
    }

    private function validateNoScheduleConflict($validator)
    {
        $busId = $this->input('bus_id');
        $weekday = $this->input('weekday');
        $departureTime = $this->input('departure_time');

        // Kiểm tra xem xe có bị trùng lịch không
        $conflicting = ScheduleTemplateTrip::where('bus_id', $busId)
            ->where('weekday', $weekday)
            ->where('departure_time', $departureTime)
            ->exists();

        if ($conflicting) {
            $weekdayNames = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
            $weekdayName = $weekdayNames[$weekday] ?? 'Không xác định';
            
            $validator->errors()->add('departure_time', 
                "Xe này đã có lịch trình vào {$weekdayName} lúc {$departureTime}. " .
                "Vui lòng chọn giờ khác hoặc xe khác."
            );
        }
    }
}