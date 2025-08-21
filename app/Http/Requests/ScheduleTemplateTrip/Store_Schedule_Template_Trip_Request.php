<?php

namespace App\Http\Requests\ScheduleTemplateTrip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ScheduleTemplateTrip;

class Store_Schedule_Template_Trip_Request extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $busId = $this->input('bus_id');

    $rules = [
        'route_id'       => ['required','exists:routes,id'],
        'bus_id'         => ['nullable','exists:buses,id'],
        'weekday'        => ['required','integer','between:0,6'],
        'departure_time' => ['required','date_format:H:i'],
        'active'         => ['boolean'],
    ];

    // Nếu có bus_id -> chống double-booking: (bus_id, weekday, departure_time) phải duy nhất
    if ($busId !== null && $busId !== '') {
        $rules['bus_id'][] =
            Rule::unique('schedule_template_trips', 'bus_id')
                ->where(fn($q) => $q
                    ->where('weekday', $this->input('weekday'))
                    ->where('departure_time', $this->input('departure_time'))
                );
    }

    return $rules;
}

public function withValidator($validator): void
{
    // Nghiệp vụ: 1 xe chỉ chạy 1 route trong 1 ngày (cùng weekday), nhiều giờ vẫn OK
    $validator->after(function ($v) {
        $busId = $this->input('bus_id');
        if (!$busId) return;

        $existsOtherRoute = ScheduleTemplateTrip::query()
            ->where('bus_id', $busId)
            ->where('weekday', $this->input('weekday'))
            ->where('route_id', '!=', $this->input('route_id'))
            ->exists();

        if ($existsOtherRoute) {
            $v->errors()->add('bus_id',
                'Xe này đã được gán tuyến khác trong cùng ngày (weekday). Mỗi xe chỉ được chạy 1 tuyến cho mỗi ngày.'
            );
        }
    });
}

public function messages(): array
{
    return [
        'route_id.required'           => 'Vui lòng chọn tuyến.',
        'route_id.exists'             => 'Tuyến không tồn tại.',
        'bus_id.exists'               => 'Xe không tồn tại.',
        'bus_id.unique'               => 'Xe này đã có mẫu ở đúng thứ và giờ khởi hành.',
        'weekday.required'            => 'Vui lòng chọn thứ.',
        'weekday.integer'             => 'Thứ phải là số.',
        'weekday.between'             => 'Thứ chỉ hợp lệ từ 0 (CN) đến 6 (Th7).',
        'departure_time.required'     => 'Vui lòng nhập giờ khởi hành.',
        'departure_time.date_format'  => 'Giờ khởi hành phải theo định dạng HH:mm.',
        'active.boolean'              => 'Trường active chỉ nhận true/false.',
    ];
}
}