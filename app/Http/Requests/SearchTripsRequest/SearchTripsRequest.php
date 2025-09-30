<?php

namespace App\Http\Requests\SearchTripsRequest;

use Illuminate\Foundation\Http\FormRequest;

class SearchTripsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
           'from_location_id' => ['required','integer','exists:locations,id'],
            'to_location_id'   => ['required','integer','exists:locations,id'],
            'date'             => ['required','date_format:Y-m-d','after_or_equal:today'],
            'return_date'      => ['nullable','date_format:Y-m-d','after_or_equal:date'],

            //fillter thêm
            'bus_type'         => ['nullable','integer','exists:type_buses,id'],
            'time_from'  => ['nullable','date_format:H:i'],
            'time_to'    => ['nullable','date_format:H:i'],
            'min_seats'  => ['nullable', 'integer','min:1'],

            'sort'             => ['nullable','in:time'],
            'order'            => ['nullable','in:asc,desc'],
        ];
    }
}