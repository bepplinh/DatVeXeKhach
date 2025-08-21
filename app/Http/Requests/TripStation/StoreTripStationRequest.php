<?php

namespace App\Http\Requests\TripStation;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'route_id' => ['required','integer','exists:routes,id'],
            'from_location_id' => ['required','integer','exists:locations,id','different:to_location_id'],
            'to_location_id' => ['required','integer','exists:locations,id','different:from_location_id'],
            'price' => ['required','integer','min:0'],
            'duration_minutes' => ['required','integer','min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'route_id.required' => 'Tuyến (route) là bắt buộc',
            'route_id.exists' => 'Tuyến không tồn tại',

            'from_location_id.required' => 'Điểm đón là bắt buộc',
            'from_location_id.exists' => 'Điểm đón không tồn tại',
            'from_location_id.different' => 'Điểm đón phải khác điểm trả',

            'to_location_id.required' => 'Điểm trả là bắt buộc',
            'to_location_id.exists' => 'Điểm trả không tồn tại',
            'to_location_id.different' => 'Điểm trả phải khác điểm đón',

            'price.required' => 'Giá là bắt buộc',
            'price.integer' => 'Giá phải là số nguyên',
            'price.min' => 'Giá không được âm',

            'duration_minutes.required' => 'Thời lượng là bắt buộc',
            'duration_minutes.integer' => 'Thời lượng phải là số nguyên (phút)',
            'duration_minutes.min' => 'Thời lượng tối thiểu là 1 phút',
        ];
    }
} 