<?php

namespace Modules\Essentials\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToDoCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'task_id' => 'required|integer|exists:essentials_to_dos,id',
            'comment' => 'required|string|min:1|max:2000',
        ];
    }
}
