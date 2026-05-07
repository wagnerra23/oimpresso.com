<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Whatsapp\Entities\WhatsappConversation;

/**
 * SendMessageRequest — validação manual send via UI Composer.
 *
 * Regras:
 * - kind=freeform: exige body; só permitido SE conversation.within_24h_window
 *   OU driver atual é zapi/baileys (que ignoram janela 24h)
 * - kind=template: exige template_name + locale; params opcional
 * - kind=media: exige media_url + media_type (image/document/audio)
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-003 (manual send via UI)
 */
class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && method_exists($user, 'can')
            && $user->can('whatsapp.send');
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['freeform', 'template', 'media'])],
            'body' => ['nullable', 'string', 'max:4096'],
            'template_name' => ['nullable', 'string', 'max:64'],
            'template_locale' => ['nullable', 'string', 'max:10'],
            'template_params' => ['nullable', 'array'],
            'template_params.*' => ['string', 'max:500'],
            'media_url' => ['nullable', 'url', 'max:1024'],
            'media_type' => ['nullable', Rule::in(['image', 'document', 'audio', 'video'])],
            'media_caption' => ['nullable', 'string', 'max:1024'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $kind = $this->input('kind');

            if ($kind === 'freeform' && empty($this->input('body'))) {
                $v->errors()->add('body', "kind=freeform exige body não-vazio.");
                return;
            }

            if ($kind === 'template' && empty($this->input('template_name'))) {
                $v->errors()->add('template_name', "kind=template exige template_name.");
                return;
            }

            if ($kind === 'media') {
                if (empty($this->input('media_url'))) {
                    $v->errors()->add('media_url', "kind=media exige media_url.");
                }
                if (empty($this->input('media_type'))) {
                    $v->errors()->add('media_type', "kind=media exige media_type (image/document/audio/video).");
                }
            }

            // Janela 24h Meta — pra freeform com driver=meta_cloud, exige window aberta
            if ($kind === 'freeform') {
                $conversation = $this->resolveConversation();
                if ($conversation === null) {
                    return;
                }

                $config = $conversation->business->whatsappBusinessConfig
                    ?? \Modules\Whatsapp\Entities\WhatsappBusinessConfig::query()
                        ->withoutGlobalScope(\Modules\Jana\Scopes\ScopeByBusiness::class)
                        ->where('business_id', $conversation->business_id)
                        ->first();

                if ($config !== null && $config->effectiveDriver() === 'meta_cloud'
                    && ! $conversation->isWithinMeta24hWindow()) {
                    $v->errors()->add(
                        'body',
                        "Janela 24h Meta fechada — Meta Cloud não permite freeform. Use kind=template ou aguarde cliente responder."
                    );
                }
            }
        });
    }

    private function resolveConversation(): ?WhatsappConversation
    {
        $id = $this->route('id');
        if ($id === null) {
            return null;
        }
        return WhatsappConversation::find((int) $id);
    }
}
