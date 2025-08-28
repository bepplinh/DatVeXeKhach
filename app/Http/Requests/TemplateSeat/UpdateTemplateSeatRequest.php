<?php 
namespace App\Http\Requests\TemplateSeat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateSeatRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'deck'            => ['sometimes','integer','between:1,2'],
            'column_group'    => ['sometimes', Rule::in(['right','middle','left'])],
            'index_in_column' => ['sometimes','integer','min:1','max:255'],
            'seat_label'      => ['sometimes','string','max:50'],
            'class'           => ['sometimes', Rule::in(['regular','vip','sleeper'])],
            'position_meta'   => ['sometimes','array','nullable'],
        ];
    }
}
