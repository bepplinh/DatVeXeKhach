<?php

namespace App\Http\Requests\Bus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bus = $this->route('bus');

        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('buses', 'code')->ignore($bus)
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'plate_number' => [
                'sometimes', 'required', 'string', 'max:20',
                Rule::unique('buses', 'plate_number')->ignore($bus)
            ],
            'type_bus_id' => ['sometimes', 'required', 'exists:type_buses,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Mã xe là bắt buộc.',
            'code.max' => 'Mã xe không được vượt quá 50 ký tự.',
            'code.unique' => 'Mã xe đã tồn tại.',

            'name.required' => 'Tên xe là bắt buộc.',
            'name.max' => 'Tên xe không được vượt quá 255 ký tự.',

            'plate_number.required' => 'Biển số xe là bắt buộc.',
            'plate_number.max' => 'Biển số xe không được vượt quá 20 ký tự.',
            'plate_number.unique' => 'Biển số xe đã tồn tại.',

            'type_bus_id.required' => 'Loại xe là bắt buộc.',
            'type_bus_id.exists' => 'Loại xe không tồn tại.',
        ];
    }
} 