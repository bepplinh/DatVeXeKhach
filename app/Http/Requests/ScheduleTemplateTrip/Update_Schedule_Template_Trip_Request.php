<?php
namespace App\Http\Requests\ScheduleTemplateTrip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ScheduleTemplateTrip;

class Update_Schedule_Template_Trip_Request extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        // Lấy id hiện tại (hỗ trợ cả route model binding lẫn id thường)
        $current = $this->route('schedule_template_trip');
        $id = is_object($current) ? $current->id : $current;

        $busId = $this->input('bus_id');

        $rules = [
            'route_id'       => [
                'sometimes','required','exists:routes,id',
                Rule::unique('schedule_template_trips','route_id')
                    ->ignore($id)
                    ->where(fn($q) => $q
                        ->where('weekday', $this->input('weekday'))
                        ->where('departure_time', $this->input('departure_time'))
                    ),
            ],
            'bus_id'         => ['nullable','exists:buses,id'],
            'weekday'        => ['sometimes','required','integer','between:0,6'],
            'departure_time' => ['sometimes','required','date_format:H:i'],
            'active'         => ['sometimes','boolean'],
        ];

        if ($busId !== null && $busId !== '') {
            $rules['bus_id'][] =
                Rule::unique('schedule_template_trips','bus_id')
                    ->ignore($id)
                    ->where(fn($q) => $q
                        ->where('weekday', $this->input('weekday'))
                        ->where('departure_time', $this->input('departure_time'))
                    );
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $busId = $this->input('bus_id');
            if (!$busId) return;

            $current = $this->route('schedule_template_trip');
            $id = is_object($current) ? $current->id : $current;

            $existsOtherRoute = ScheduleTemplateTrip::query()
                ->where('id', '!=', $id)
                ->where('bus_id', $busId)
                ->where('weekday', $this->input('weekday'))
                ->where('route_id', '!=', $this->input('route_id'))
                ->exists();

            if ($existsOtherRoute) {
                $v->errors()->add(
                    'bus_id',
                    'Xe này đã được gán tuyến khác trong cùng thứ. Mỗi xe chỉ được chạy 1 tuyến cho mỗi ngày.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'route_id.unique'            => 'Đã tồn tại mẫu cho tuyến này ở đúng thứ và giờ khởi hành.',
            'bus_id.unique'              => 'Xe này đã có mẫu ở đúng thứ và giờ khởi hành.',
            'departure_time.date_format' => 'Giờ khởi hành phải là HH:mm.',
        ];
    }

    public function attributes(): array
    {
        return [
            'route_id'       => 'tuyến',
            'bus_id'         => 'xe',
            'weekday'        => 'thứ',
            'departure_time' => 'giờ khởi hành',
            'active'         => 'trạng thái',
        ];
    }
}