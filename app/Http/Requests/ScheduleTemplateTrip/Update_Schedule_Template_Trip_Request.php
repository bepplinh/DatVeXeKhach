<?php

namespace App\Http\Requests\ScheduleTemplateTrip;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ScheduleTemplateTrip;

class Update_Schedule_Template_Trip_Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'route_id' => 'sometimes|exists:routes,id',
            'bus_id' => 'sometimes|exists:buses,id',
            'weekday' => 'sometimes|integer|between:0,6',
            'departure_time' => 'sometimes|date_format:H:i:s',
            'active' => 'sometimes|boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'route_id.exists' => 'Tuyến đường không tồn tại',
            'bus_id.exists' => 'Xe không tồn tại',
            'weekday.between' => 'Ngày trong tuần phải từ 0-6 (0=Chủ nhật, 1=Thứ 2, ...)',
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
        
        // Nếu không có thay đổi về bus_id, weekday, departure_time thì không cần kiểm tra
        if (!$busId || !$weekday || !$departureTime) {
            return;
        }

        // Lấy ID của template đang update
        $templateId = $this->route('schedule_template_trip');

        // Kiểm tra xem xe có bị trùng lịch không (loại trừ template hiện tại)
        $conflicting = ScheduleTemplateTrip::where('bus_id', $busId)
            ->where('weekday', $weekday)
            ->where('departure_time', $departureTime)
            ->where('id', '!=', $templateId)
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