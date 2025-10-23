<?php
namespace App\Http\Requests\Draft;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDraftInfoRequest extends FormRequest
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

            // thêm phương thức thanh toán
            'payment_provider' => ['required', Rule::in(['cash','payos'])],
        ];
    }
}
