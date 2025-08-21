<?php

namespace App\Http\Requests\CouponUser;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCouponUserRequest extends FormRequest
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
        return [
            'user_id' => 'sometimes|integer|exists:users,id',
            'coupon_id' => 'sometimes|integer|exists:coupons,id',
            'is_used' => 'sometimes|boolean',
            'used_at' => 'sometimes|nullable|date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'user_id.integer' => 'ID người dùng phải là số nguyên.',
            'user_id.exists' => 'Người dùng không tồn tại.',
            'coupon_id.integer' => 'ID mã giảm giá phải là số nguyên.',
            'coupon_id.exists' => 'Mã giảm giá không tồn tại.',
            'is_used.boolean' => 'Trạng thái sử dụng phải là true hoặc false.',
            'used_at.date' => 'Thời gian sử dụng phải là định dạng ngày tháng hợp lệ.',
        ];
    }
}
