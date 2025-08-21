<?php

namespace App\Http\Requests\BusType;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTypeBusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('type_bus');
        return [
            'name'       => 'required|string|max:255|unique:type_buses,name,' . $id,
            'seat_count' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên loại xe là bắt buộc',
            'name.unique'   => 'Tên loại xe đã tồn tại',
            'seat_count.required' => 'Số ghế là bắt buộc',
            'seat_count.min'      => 'Số ghế tối thiểu là 1'
        ];
    }
}
