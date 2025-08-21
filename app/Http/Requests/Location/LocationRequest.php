<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'type' => [
                'required',
                'string',
                Rule::in(['city', 'district', 'ward'])
            ],
            'parent_id' => 'nullable|exists:locations,id',
        ];

        return $rules;
    }

    public function messages()
    {
        return [
            'name.required' => 'Tên địa điểm là bắt buộc',
            'name.max' => 'Tên địa điểm không được quá 255 ký tự',
            'type.required' => 'Loại địa điểm là bắt buộc',
            'type.in' => 'Loại địa điểm phải là: city, district, hoặc ward',
            'parent_id.exists' => 'Địa điểm cha không tồn tại',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $parentId = $this->input('parent_id');

            // Validate hierarchy rules
            if ($type === 'city' && $parentId) {
                $validator->errors()->add('parent_id', 'Thành phố không được có địa điểm cha');
            }

            if ($type === 'district' && !$parentId) {
                $validator->errors()->add('parent_id', 'Quận/Huyện phải thuộc về một Thành phố');
            }

            if ($type === 'ward' && !$parentId) {
                $validator->errors()->add('parent_id', 'Phường/Xã phải thuộc về một Quận/Huyện');
            }

            // Validate parent type
            if ($parentId) {
                $parent = \App\Models\Location::find($parentId);
                
                if ($parent) {
                    if ($type === 'district' && $parent->type !== 'city') {
                        $validator->errors()->add('parent_id', 'Quận/Huyện chỉ có thể thuộc về Thành phố');
                    }

                    if ($type === 'ward' && $parent->type !== 'district') {
                        $validator->errors()->add('parent_id', 'Phường/Xã chỉ có thể thuộc về Quận/Huyện');
                    }
                }
            }

            // Prevent self-reference on update
            if ($this->route('location') && $parentId == $this->route('location')) {
                $validator->errors()->add('parent_id', 'Địa điểm không thể là cha của chính nó');
            }
        });
    }
}