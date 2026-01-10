<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InsuranceCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Allow by default; adjust authorization as needed.
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

        $baseString = $isPost ? 'required' : 'sometimes';
        $nullableString = $isPost ? 'nullable' : 'sometimes';

        return [
            'name' => [$baseString, 'string', 'max:255'],
            'address' => [$nullableString, 'string', 'max:255'],
            'city' => [$nullableString, 'string', 'max:255'],
            'psc' => [$nullableString, 'string', 'max:50'],
            'ico' => [$nullableString, 'string', 'max:50'],
            'dic' => [$nullableString, 'string', 'max:50'],
            'ic_dph' => [$nullableString, 'string', 'max:50'],
            'register' => [$nullableString, 'string', 'max:255'],
            'code' => [$nullableString, 'string', 'max:50'],
            'branch_code' => [$nullableString, 'string', 'max:50'],
        ];
    }
}
