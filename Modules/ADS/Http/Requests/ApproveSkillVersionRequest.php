<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 saturação.
 *
 * FormRequest pra POST /ads/admin/skills/versions/{versionId}/approve
 * (SkillsController@approve). Wagner aprova version draft → vira active.
 *
 * @see Modules\ADS\Http\Controllers\Admin\SkillsController::approve
 */
class ApproveSkillVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['sometimes', 'string', 'max:1000'],
        ];
    }
}
