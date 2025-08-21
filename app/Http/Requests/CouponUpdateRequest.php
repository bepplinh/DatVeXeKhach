<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponUpdateRequest extends FormRequest
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
        $couponId = $this->route('coupon');
        
        return [
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('coupons', 'code')->ignore($couponId),
            ],
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'sometimes|in:fixed,percentage',
            'discount_value' => [
                'sometimes',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    if ($this->discount_type === 'percentage' && $value > 100) {
                        $fail('Phần trăm giảm giá không được vượt quá 100%.');
                    }
                },
            ],
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'max_usage' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    if ($value && $this->valid_from && $value <= $this->valid_from) {
                        $fail('Thời gian kết thúc phải sau thời gian bắt đầu.');
                    }
                },
            ],
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Mã giảm giá đã tồn tại.',
            'code.max' => 'Mã giảm giá không được vượt quá 50 ký tự.',
            'name.max' => 'Tên mã giảm giá không được vượt quá 255 ký tự.',
            'discount_type.in' => 'Loại giảm giá phải là fixed hoặc percentage.',
            'discount_value.numeric' => 'Giá trị giảm giá phải là số.',
            'discount_value.min' => 'Giá trị giảm giá phải lớn hơn hoặc bằng 0.',
            'minimum_order_amount.numeric' => 'Số tiền đơn hàng tối thiểu phải là số.',
            'minimum_order_amount.min' => 'Số tiền đơn hàng tối thiểu phải lớn hơn hoặc bằng 0.',
            'max_usage.integer' => 'Số lần sử dụng tối đa phải là số nguyên.',
            'max_usage.min' => 'Số lần sử dụng tối đa phải lớn hơn 0.',
            'valid_from.date' => 'Thời gian bắt đầu không hợp lệ.',
            'valid_until.date' => 'Thời gian kết thúc không hợp lệ.',
        ];
    }
}
