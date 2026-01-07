<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MacroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Adjust authorization if needed (user must be authenticated elsewhere)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $isPost = $this->isMethod('post');
        $base = $isPost ? 'required' : 'sometimes';

        return [
            'name' => [$base, 'string', 'max:255'],
            'abbreviation' => [$base, 'string', 'max:50'],
            'text' => [$base, 'string'],
        ];
    }
}
