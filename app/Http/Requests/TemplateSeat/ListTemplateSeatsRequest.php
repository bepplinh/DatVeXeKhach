<?php 
namespace App\Http\Requests\TemplateSeat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTemplateSeatsRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'group'     => ['sometimes','boolean'],    // default true: group by deck/column
            'per_page'  => ['sometimes','integer','between:1,200'], // if group=false (flat)
            'deck'      => ['sometimes','integer','between:1,2'],   // optional filter
            'column'    => ['sometimes', Rule::in(['right','middle','left'])],
        ];
    }
}
