<?php

namespace App\Http\Requests\Route;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $routeId = $this->route('route');
        
        return [
            'from_city' => [
                'required',
                'exists:locations,id',
                'different:to_city'
            ],
            'to_city' => [
                'required', 
                'exists:locations,id',
                'different:from_city'
            ],
            'name' => 'nullable|string|max:255'
        ];
    }

    public function messages(): array
    {
        return [
            'from_city.required' => 'Điểm khởi hành là bắt buộc',
            'from_city.exists' => 'Điểm khởi hành không tồn tại',
            'from_city.different' => 'Điểm khởi hành phải khác điểm đến',
            'to_city.required' => 'Điểm đến là bắt buộc',
            'to_city.exists' => 'Điểm đến không tồn tại', 
            'to_city.different' => 'Điểm đến phải khác điểm khởi hành',
            'name.max' => 'Tên tuyến đường không được vượt quá 255 ký tự'
        ];
    }
}