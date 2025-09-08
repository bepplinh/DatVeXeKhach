<?php

namespace App\Http\Requests\DraftCheckout;

use Illuminate\Foundation\Http\FormRequest;

class StoreDraftCheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Có thể cho phép guest users tạo draft
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
            'seat_ids' => ['required', 'array', 'min:1', 'max:10'], // Tối đa 10 ghế
            'seat_ids.*' => ['integer', 'exists:seats,id'],
            'passenger_name' => ['required', 'string', 'max:255'],
            'passenger_phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]+$/', 'max:20'],
            'passenger_email' => ['nullable', 'email', 'max:255'],
            'pickup_location_id' => ['required', 'integer', 'exists:locations,id'],
            'dropoff_location_id' => ['required', 'integer', 'exists:locations,id'],
            'pickup_address' => ['nullable', 'string', 'max:500'],
            'dropoff_address' => ['nullable', 'string', 'max:500'],
            'total_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'coupon_id' => ['nullable', 'integer', 'exists:coupons,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'passenger_info' => ['nullable', 'array'],
            'passenger_info.cccd' => ['nullable', 'string', 'max:20'],
            'passenger_info.date_of_birth' => ['nullable', 'date', 'before:today'],
            'passenger_info.gender' => ['nullable', 'in:male,female,other'],
            'session_id' => ['nullable', 'string', 'max:255'], // Cho guest users
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'trip_id.required' => 'Vui lòng chọn chuyến đi',
            'trip_id.exists' => 'Chuyến đi không tồn tại',
            'seat_ids.required' => 'Vui lòng chọn ít nhất một ghế',
            'seat_ids.array' => 'Danh sách ghế không hợp lệ',
            'seat_ids.min' => 'Vui lòng chọn ít nhất một ghế',
            'seat_ids.max' => 'Không thể chọn quá 10 ghế',
            'seat_ids.*.exists' => 'Một số ghế không tồn tại',
            'passenger_name.required' => 'Vui lòng nhập họ tên hành khách',
            'passenger_name.max' => 'Họ tên không được quá 255 ký tự',
            'passenger_phone.required' => 'Vui lòng nhập số điện thoại',
            'passenger_phone.regex' => 'Số điện thoại không hợp lệ',
            'passenger_phone.max' => 'Số điện thoại không được quá 20 ký tự',
            'passenger_email.email' => 'Email không hợp lệ',
            'passenger_email.max' => 'Email không được quá 255 ký tự',
            'pickup_location_id.required' => 'Vui lòng chọn điểm đón',
            'pickup_location_id.exists' => 'Điểm đón không tồn tại',
            'dropoff_location_id.required' => 'Vui lòng chọn điểm trả',
            'dropoff_location_id.exists' => 'Điểm trả không tồn tại',
            'pickup_address.max' => 'Địa chỉ đón không được quá 500 ký tự',
            'dropoff_address.max' => 'Địa chỉ trả không được quá 500 ký tự',
            'total_price.required' => 'Vui lòng nhập tổng tiền',
            'total_price.numeric' => 'Tổng tiền phải là số',
            'total_price.min' => 'Tổng tiền phải lớn hơn 0',
            'total_price.max' => 'Tổng tiền quá lớn',
            'discount_amount.numeric' => 'Số tiền giảm giá phải là số',
            'discount_amount.min' => 'Số tiền giảm giá không được âm',
            'discount_amount.max' => 'Số tiền giảm giá quá lớn',
            'coupon_id.exists' => 'Mã giảm giá không tồn tại',
            'notes.max' => 'Ghi chú không được quá 1000 ký tự',
            'passenger_info.cccd.max' => 'Số CCCD không được quá 20 ký tự',
            'passenger_info.date_of_birth.date' => 'Ngày sinh không hợp lệ',
            'passenger_info.date_of_birth.before' => 'Ngày sinh phải trước ngày hiện tại',
            'passenger_info.gender.in' => 'Giới tính không hợp lệ',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'trip_id' => 'chuyến đi',
            'seat_ids' => 'danh sách ghế',
            'seat_ids.*' => 'ghế',
            'passenger_name' => 'họ tên hành khách',
            'passenger_phone' => 'số điện thoại',
            'passenger_email' => 'email',
            'pickup_location_id' => 'điểm đón',
            'dropoff_location_id' => 'điểm trả',
            'pickup_address' => 'địa chỉ đón',
            'dropoff_address' => 'địa chỉ trả',
            'total_price' => 'tổng tiền',
            'discount_amount' => 'số tiền giảm giá',
            'coupon_id' => 'mã giảm giá',
            'notes' => 'ghi chú',
            'passenger_info.cccd' => 'số CCCD',
            'passenger_info.date_of_birth' => 'ngày sinh',
            'passenger_info.gender' => 'giới tính',
        ];
    }
}

