<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;

class TripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'route_id' => ['required','exists:routes,id'],
            'bus_id' => ['nullable','exists:buses,id'],
            'departure_time' => ['required','date'],
            'status' => ['nullable','in:scheduled,running,finished,cancelled'],
        ];
    }

    public function messages(): array
    {
        return [
            'route_id.required' => 'Tuyến đường là bắt buộc',
            'route_id.exists' => 'Tuyến đường không tồn tại',
            'bus_id.exists' => 'Xe bus không tồn tại',
            'departure_time.required' => 'Thời gian khởi hành là bắt buộc',
            'departure_time.date' => 'Thời gian khởi hành không hợp lệ',
            'status.in' => 'Trạng thái không hợp lệ',
        ];
    }
} 