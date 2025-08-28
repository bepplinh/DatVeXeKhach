<?php 
namespace App\Http\Requests\TemplateSeat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateSeatRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'deck'            => ['required','integer','between:1,2'],
            'column_group'    => ['required', Rule::in(['right','middle','left'])],
            'index_in_column' => ['required','integer','min:1','max:255'],
            'seat_label'      => ['required','string','max:50'],
            'class'           => ['sometimes', Rule::in(['regular','vip','sleeper'])],
            'position_meta'   => ['sometimes','array','nullable'],
        ];
    }
}
