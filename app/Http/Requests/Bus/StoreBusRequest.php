<?php

namespace App\Http\Requests\Bus;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:buses,code',
            'name' => 'required|string|max:255',
            'plate_number' => 'required|string|max:20|unique:buses,plate_number',
            'type_bus_id' => 'required|exists:type_buses,id',
            'seat_layout_template_id' => ['required','integer','exists:seat_layout_templates,id'],
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