<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

abstract class DestroyManyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Base implementation enforces delete ability on each resource using the Gate.
        $user = $this->user();
        if (!$user)
            return false;

        $ids = $this->input('ids');
        if (!is_array($ids) || count($ids) === 0) {
            return false;
        }

        $modelClass = $this->modelClass();
        if (!$modelClass)
            return false;

        $chunkSize = 100;
        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            $items = $modelClass::whereIn('id', $chunk)->get();
            if ($items->count() !== count($chunk)) {
                // Some ids missing -> deny
                return false;
            }

            foreach ($items as $item) {
                if (!Gate::forUser($user)->allows('delete', $item))
                    return false;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * Return the model class to authorize against. Subclasses MUST implement.
     *
     * @return string
     */
    abstract protected function modelClass(): string;
}
