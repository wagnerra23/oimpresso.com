<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Fisio\Ai\Agents\TranscricaoDocumentoAgent;
use Modules\Fisio\Entities\DocumentoPaciente;
use Modules\Fisio\Entities\TranscricaoDocumento;
use Modules\Fisio\Services\TranscricaoDocumentos;

/**
 * Transcrição pela IA da foto do exame ou da receita, conferida pelo profissional
 * (US-FISIO-063, parte 3). Tudo exige `fisio.editar`. Documento e transcrição de outra empresa:
 * 404 antes de validar (ADR 0093). Regra recusada pelo serviço (desligada, provedor fora da
 * lista, já decidida) vira 422 com o motivo.
 */
class TranscricaoDocumentoController extends Controller
{
    public function __construct(private TranscricaoDocumentos $transcricao) {}

    /** Liga ou desliga a transcrição pela IA para a empresa da sessão. */
    public function configurar(Request $request): JsonResponse|RedirectResponse
    {
        $this->exigirEdicao($request);
        $ligar = (bool) $request->validate(['ligado' => ['required', 'boolean']])['ligado'];
        $this->transcricao->ligar($this->empresa($request), $ligar, $request->user());

        return $this->resposta($request, ['ligado' => $ligar], $ligar ? 'Transcrição pela IA ligada.' : 'Transcrição pela IA desligada.');
    }

    public function sugerir(Request $request, int $documento): JsonResponse|RedirectResponse
    {
        /** @var DocumentoPaciente $doc */
        $doc = DocumentoPaciente::query()->where('business_id', $this->empresa($request))->findOrFail($documento);
        $this->exigirEdicao($request);
        try {
            $t = $this->transcricao->sugerir($doc, $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['transcricao' => $e->getMessage()]);
        }

        return $this->resposta($request, ['id' => $t->id, 'sugestao' => $t->suggested], 'Sugestão pronta para conferir.', 201);
    }

    public function confirmar(Request $request, int $transcricao): JsonResponse|RedirectResponse
    {
        $t = $this->transcricaoDaEmpresa($request, $transcricao);
        $this->exigirEdicao($request);
        /** @var DocumentoPaciente $doc */
        $doc = DocumentoPaciente::query()->where('business_id', $t->business_id)->findOrFail($t->document_id);
        $receita = TranscricaoDocumentos::modo($doc) === TranscricaoDocumentoAgent::RECEITA;

        $regras = ['data' => ['nullable', 'date', 'before_or_equal:today']];
        $regras += $receita ? [
            'medico' => ['nullable', 'string', 'max:120'],
            'crm' => ['nullable', 'string', 'max:40'],
            'medicamentos' => ['present', 'array', 'max:20'],
            'medicamentos.*.nome' => ['required', 'string', 'max:120'],
            'medicamentos.*.dose' => ['nullable', 'string', 'max:60'],
            'medicamentos.*.frequencia' => ['nullable', 'string', 'max:120'],
        ] : [
            'tipo_exame' => ['nullable', 'string', 'max:120'],
            'conclusao' => ['nullable', 'string', 'max:5000'],
        ];
        $campos = $request->validate($regras, ['medicamentos.*.nome.required' => 'Diga o nome do medicamento ou tire a linha.']);

        try {
            $this->transcricao->confirmar($t, $campos, $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['transcricao' => $e->getMessage()]);
        }

        return $this->resposta($request, ['id' => $t->id, 'status' => $t->status], 'Transcrição conferida e gravada.');
    }

    public function descartar(Request $request, int $transcricao): JsonResponse|RedirectResponse
    {
        $t = $this->transcricaoDaEmpresa($request, $transcricao);
        $this->exigirEdicao($request);
        try {
            $this->transcricao->descartar($t, $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['transcricao' => $e->getMessage()]);
        }

        return $this->resposta($request, ['id' => $t->id, 'status' => $t->status], 'Sugestão descartada.');
    }

    private function transcricaoDaEmpresa(Request $request, int $id): TranscricaoDocumento
    {
        /** @var TranscricaoDocumento $t */
        $t = TranscricaoDocumento::query()->where('business_id', $this->empresa($request))->findOrFail($id);

        return $t;
    }

    /** @param array<string, mixed> $json */
    private function resposta(Request $request, array $json, string $mensagem, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($json, $status);
        }

        return back()->with('success', $mensagem);
    }

    private function empresa(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function exigirEdicao(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
    }
}
