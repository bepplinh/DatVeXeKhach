<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tripId = $this->route('trip')?->id ?? $this->route('trip');

        $rules = [
            'route_id' => ['required', 'integer', 'exists:routes,id'],
            'bus_id' => ['nullable', 'integer', 'exists:buses,id'],
            'departure_time' => ['required', 'date_format:Y-m-d H:i:s'],
            'status' => ['nullable', 'in:scheduled,running,finished,cancelled'],
        ];

        if ($this->filled('bus_id')) {
            $rules['departure_time'][] = Rule::unique('trips', 'departure_time')
                ->ignore($tripId)
                ->where(fn($q) => $q->where('bus_id', $this->input('bus_id')));
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'route_id.required' => 'Chuyến xe là bắt buộc',
            'route_id.integer' => 'Chuyến xe không hợp lệ',
            'route_id.exists' => 'Chuyến xe không tồn tại',

            'bus_id.integer' => 'Xe bus không hợp lệ',
            'bus_id.exists' => 'Xe bus không tồn tại',

            'departure_time.required' => 'Thời gian khởi hành là bắt buộc',
            'departure_time.date_format' => 'Thời gian khởi hành phải theo định dạng YYYY-MM-DD HH:MM:SS',
            'departure_time.unique' => 'Mỗi xe bus không thể có 2 chuyến cùng thời gian khởi hành',

            'status.in' => 'Trạng thái không hợp lệ (scheduled, running, finished, cancelled)'
        ];
    }
} 