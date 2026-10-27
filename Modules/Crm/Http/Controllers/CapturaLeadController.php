<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Crm\Entities\FormularioCaptura;
use Modules\Crm\Services\CapturaDeLead;

/**
 * POST /api/crm/captura/{chave} — formulário público de captura de lead (US-CRM-096).
 *
 * Defesas da rota pública:
 *  - flag `crm.captura_lead_enabled` desligada por padrão → 404, igual a chave inexistente;
 *  - limite de requisições por IP na rota (throttle) — sem sessão, sem CSRF (o form mora no
 *    site do cliente);
 *  - armadilha anti-robô: o campo `website` fica escondido no form; preenchido, a resposta é
 *    a de sucesso e nada é gravado (o robô não aprende que foi barrado);
 *  - a empresa vem só da chave; a resposta nunca devolve dado da empresa, do contato ou do
 *    negócio — só `{ok: true}` ou o redirecionamento configurado pela própria empresa.
 */
class CapturaLeadController extends Controller
{
    public function __construct(private readonly CapturaDeLead $captura) {}

    public function store(Request $request, string $chave): JsonResponse|RedirectResponse
    {
        abort_unless((bool) config('crm.captura_lead_enabled', false), 404);

        $form = FormularioCaptura::ativoPelaChave($chave);
        abort_if($form === null, 404);

        if (trim((string) $request->input('website', '')) !== '') {
            return $this->resposta($request, $form);
        }

        $v = Validator::make($request->all(), [
            'nome' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['nullable', 'email', 'max:191', 'required_without:telefone'],
            'telefone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 ()+\-.]{8,30}$/', 'required_without:email'],
            'mensagem' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'erros' => $v->errors()], 422);
        }

        $this->captura->capturar($form, $v->validated(), $request->only(CapturaDeLead::UTM));

        return $this->resposta($request, $form);
    }

    private function resposta(Request $request, FormularioCaptura $form): JsonResponse|RedirectResponse
    {
        if ($form->obrigado_url && ! $request->expectsJson()) {
            return redirect()->away($form->obrigado_url, 303);
        }

        return response()->json(['ok' => true]);
    }
}
