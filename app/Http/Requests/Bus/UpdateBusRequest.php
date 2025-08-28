<?php

namespace App\Http\Requests\Bus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tuỳ quyền của bạn. Để true nếu đã có middleware/can:admin ở routes.
        return true;
    }

    public function rules(): array
    {
        $bus = $this->route('bus');

        
    return [
        'code' => [
            'sometimes', 'string', 'filled', 'max:50',
            Rule::unique('buses','code')->ignore($bus?->id),
        ],
        'name' => ['sometimes','string','filled','max:255'],

        'plate_number' => [
            'sometimes','string','filled','max:20',
            Rule::unique('buses','plate_number')->ignore($bus?->id),
        ],

        'type_bus_id' => ['sometimes','integer','exists:type_buses,id'],

        // đổi template -> controller sẽ rematerialize
        'seat_layout_template_id' => ['sometimes','integer','exists:seat_layout_templates,id'],
    ];
    }

    public function messages(): array
    {
        return [
            'code.string' => 'Mã xe phải là chuỗi ký tự.',
            'code.filled' => 'Mã xe không được để trống.',
            'code.max'    => 'Mã xe không được vượt quá 50 ký tự.',
            'code.unique' => 'Mã xe đã tồn tại trong hệ thống.',
    
            'name.string' => 'Tên xe phải là chuỗi ký tự.',
            'name.filled' => 'Tên xe không được để trống.',
            'name.max'    => 'Tên xe không được vượt quá 255 ký tự.',
    
            'plate_number.string' => 'Biển số xe phải là chuỗi ký tự.',
            'plate_number.filled' => 'Biển số xe không được để trống.',
            'plate_number.max'    => 'Biển số xe không được vượt quá 20 ký tự.',
            'plate_number.unique' => 'Biển số xe đã tồn tại trong hệ thống.',
    
            'type_bus_id.integer' => 'Loại xe không hợp lệ.',
            'type_bus_id.exists'  => 'Loại xe không tồn tại.',
    
            'seat_layout_template_id.integer' => 'Mẫu sơ đồ ghế không hợp lệ.',
            'seat_layout_template_id.exists'  => 'Mẫu sơ đồ ghế không tồn tại.',
        ];
    }
}
