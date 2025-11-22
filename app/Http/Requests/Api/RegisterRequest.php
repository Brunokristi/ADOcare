<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:users,code',
            'login' => 'required|string|max:255|unique:users,login',
            'pin' => 'required|string|min:4|confirmed',
        ];
    }
}
