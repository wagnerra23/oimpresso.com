<?php

namespace Modules\Essentials\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToDoUploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'task_id'       => 'required|integer|exists:essentials_to_dos,id',
            'description'   => 'nullable|string|max:500',
            'documents'     => 'required|array|min:1',
            'documents.*'   => 'file|max:10240|mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx,csv,txt,zip',
        ];
    }
}
