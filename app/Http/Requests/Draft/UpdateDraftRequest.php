<?php
namespace App\Http\Requests\Draft;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDraftRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'passenger_name'  => ['required','string','max:120'],
            'passenger_phone' => ['required','string','max:20'],
            'passenger_email' => ['nullable','email','max:120'],

            'booker_name'     => ['nullable','string','max:120'],
            'booker_phone'    => ['nullable','string','max:20'],

            'pickup_location_id'  => ['nullable','integer','exists:locations,id'],
            'dropoff_location_id' => ['nullable','integer','exists:locations,id'],
            'pickup_address'      => ['nullable','string','max:500'],
            'dropoff_address'     => ['nullable','string','max:500'],

            'passenger_info'      => ['nullable','array'],
            'coupon_id'           => ['nullable','integer','exists:coupons,id'],
            'notes'               => ['nullable','string','max:1000'],

            // Cờ khứ hồi để ràng buộc legs
            'is_round_trip'      => ['sometimes', 'boolean'],
            // Legs: OUT và (nếu khứ hồi) RETURN
            'legs'               => ['sometimes', 'array', 'min:1'],
            'legs.*.leg_type'    => ['required_with:legs', Rule::in(['OUT','RETURN'])],
            'legs.*.pickup_location_id'  => ['sometimes', 'nullable', 'integer', 'exists:locations,id'],
            'legs.*.dropoff_location_id' => ['sometimes', 'nullable', 'integer', 'exists:locations,id'],
            'legs.*.pickup_address'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'legs.*.dropoff_address'     => ['sometimes', 'nullable', 'string', 'max:255'],
            // thêm phương thức thanh toán
            'payment_provider' => ['required', Rule::in(['cash','payos'])],
        ];
    }

    public function message()
    {
        return [
            // Quy tắc chung
            'required' => ':attribute không được để trống.',
            'string'   => ':attribute phải là chuỗi ký tự.',
            'integer'  => ':attribute phải là số nguyên.',
            'max'      => ':attribute không được vượt quá :max ký tự.',
            'array'    => ':attribute phải là một mảng.',
            'min'      => ':attribute phải có ít nhất :min phần tử.',
            'email'    => ':attribute không đúng định dạng email.',
            'boolean'  => ':attribute phải là giá trị đúng hoặc sai (true/false).',
    
            // Tên trường tùy chỉnh (Custom Attribute Names)
            'passenger_name.required' => 'Họ tên hành khách là bắt buộc.',
            'passenger_name.string'   => 'Họ tên hành khách phải là chuỗi ký tự.',
            'passenger_name.max'      => 'Họ tên hành khách không được vượt quá 120 ký tự.',
    
            'passenger_phone.required' => 'Số điện thoại hành khách là bắt buộc.',
            'passenger_phone.max'      => 'Số điện thoại hành khách không được vượt quá 20 ký tự.',
    
            'passenger_email.email' => 'Email hành khách không đúng định dạng.',
    
            'booker_name.max'   => 'Họ tên người đặt không được vượt quá 120 ký tự.',
            'booker_phone.max'  => 'Số điện thoại người đặt không được vượt quá 20 ký tự.',
    
            'pickup_location_id.integer' => 'ID điểm đón phải là số nguyên.',
            'pickup_location_id.exists'  => 'ID điểm đón không hợp lệ hoặc không tồn tại.',
            
            'dropoff_location_id.integer' => 'ID điểm trả phải là số nguyên.',
            'dropoff_location_id.exists'  => 'ID điểm trả không hợp lệ hoặc không tồn tại.',
    
            'pickup_address.max'  => 'Địa chỉ đón không được vượt quá 500 ký tự.',
            'dropoff_address.max' => 'Địa chỉ trả không được vượt quá 500 ký tự.',
    
            'coupon_id.integer' => 'Mã khuyến mãi phải là số nguyên.',
            'coupon_id.exists'  => 'Mã khuyến mãi không hợp lệ hoặc không tồn tại.',
    
            'notes.max' => 'Ghi chú không được vượt quá 1000 ký tự.',
            
            // Quy tắc cho Legs (các chặng)
            'legs.array' => 'Thông tin các chặng đi (legs) phải là một mảng.',
            'legs.min'   => 'Bạn phải chọn ít nhất một chặng đi (OUT).',
    
            'legs.*.leg_type.required_with' => 'Loại chặng (leg_type) là bắt buộc trong mỗi chặng.',
            'legs.*.leg_type.in'            => 'Loại chặng (leg_type) không hợp lệ. Chỉ chấp nhận OUT hoặc RETURN.',
    
            'legs.*.pickup_location_id.integer' => 'ID điểm đón của chặng phải là số nguyên.',
            'legs.*.pickup_location_id.exists'  => 'ID điểm đón của chặng không hợp lệ hoặc không tồn tại.',
            
            'legs.*.dropoff_location_id.integer' => 'ID điểm trả của chặng phải là số nguyên.',
            'legs.*.dropoff_location_id.exists'  => 'ID điểm trả của chặng không hợp lệ hoặc không tồn tại.',
    
            'legs.*.pickup_address.max'  => 'Địa chỉ đón không được vượt quá 255 ký tự.',
            'legs.*.dropoff_address.max' => 'Địa chỉ trả không được vượt quá 255 ký tự.',
    
            // Quy tắc cho Payment
            'payment_provider.required' => 'Vui lòng chọn hình thức thanh toán.',
            'payment_provider.in'       => 'Phương thức thanh toán không hợp lệ. Chỉ chấp nhận cash hoặc payos.',
        ];
    }
}
