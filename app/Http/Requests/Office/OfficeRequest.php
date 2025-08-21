<?php

namespace App\Http\Requests\Office;

use Illuminate\Foundation\Http\FormRequest;

class OfficeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            // ✅ SIMPLE: Chỉ check số và độ dài
            'phone' => 'nullable|string|min:10|max:15',
        ];

        // Kiểm tra unique name
        if ($this->isMethod('post')) {
            $rules['name'] .= '|unique:offices,name';
        } elseif ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['name'] .= '|unique:offices,name,' . $this->route('office');
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'name.required' => 'Tên văn phòng là bắt buộc',
            'name.unique' => 'Tên văn phòng đã tồn tại',
            'name.max' => 'Tên văn phòng không được quá 255 ký tự',
            'address.required' => 'Địa chỉ là bắt buộc', 
            'address.max' => 'Địa chỉ không được quá 500 ký tự',
            'phone.min' => 'Số điện thoại phải có ít nhất 10 số',
            'phone.max' => 'Số điện thoại không được quá 15 số',
        ];
    }

    // ✅ Custom validation sau khi pass basic rules
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->phone && !empty($this->phone)) {
                // Remove spaces and special chars
                $cleanPhone = preg_replace('/[^0-9+]/', '', $this->phone);
                
                // Simple check: starts with 0 or +84 or 84
                $isValid = (
                    str_starts_with($cleanPhone, '0') ||
                    str_starts_with($cleanPhone, '+84') ||
                    str_starts_with($cleanPhone, '84')
                ) && strlen($cleanPhone) >= 10 && strlen($cleanPhone) <= 15;
                
                if (!$isValid) {
                    $validator->errors()->add('phone', 'Số điện thoại không hợp lệ');
                }
            }
        });
    }
}