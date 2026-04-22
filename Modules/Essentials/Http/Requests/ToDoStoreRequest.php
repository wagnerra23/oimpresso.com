<?php

namespace Modules\Essentials\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToDoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'task'            => 'required|string|max:255',
            'users'           => 'nullable|array',
            'users.*'         => 'integer|exists:users,id',
            'priority'        => 'nullable|in:low,medium,high,urgent',
            'status'          => 'nullable|in:new,in_progress,on_hold,completed',
            'date'            => 'required',
            'end_date'        => 'nullable',
            'estimated_hours' => 'nullable|string|max:32',
            'description'     => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'task.required' => 'O campo tarefa é obrigatório.',
            'date.required' => 'A data de início é obrigatória.',
        ];
    }
}
