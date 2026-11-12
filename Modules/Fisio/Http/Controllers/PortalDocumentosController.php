<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Fisio\Entities\DocumentoPaciente;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\Consentimentos;
use Modules\Fisio\Services\DocumentosDoPaciente;
use Modules\Fisio\Services\PortalTokenService;
use Modules\Fisio\Services\SessaoDoPortal;

/**
 * Envio de foto de exame ou receita pelo portal do paciente (US-FISIO-063, parte 4).
 *
 * Portões, nesta ordem: link válido (o business vem assinado no token), PIN aceito (ou aparelho
 * lembrado), consentimento de teleatendimento e o consentimento próprio `documentos`, na versão
 * vigente. O envio entra como `pendente` de conferência, sem autor de usuário, e nunca direto no
 * prontuário: alguém da clínica aceita ou recusa na ficha. O paciente não recebe o arquivo de
 * volta, nem transcrição, nem o motivo da recusa.
 */
class PortalDocumentosController extends Controller
{
    public function __construct(
        private readonly PortalTokenService $tokens,
        private readonly Consentimentos $consentimentos,
        private readonly DocumentosDoPaciente $documentos,
    ) {}

    public function store(Request $request, string $token): JsonResponse
    {
        $linha = $this->tokens->resolver($token);
        if ($linha === null) {
            return response()->json(['erro' => 'link_invalido'], 404);
        }
        if (! app(SessaoDoPortal::class)->pinAceito($request, $linha)) {
            return response()->json(['erro' => 'pin_pendente'], 403);
        }
        $paciente = $linha->getRelation('patient');
        abort_unless($paciente instanceof Patient, 404);
        if (! $this->consentimentos->vigente($paciente, Consentimentos::TELEATENDIMENTO)) {
            return response()->json(['erro' => 'consentimento_pendente'], 403);
        }
        if (! $this->consentimentos->vigente($paciente, Consentimentos::DOCUMENTOS)) {
            return response()->json(['erro' => 'consentimento_documentos'], 403);
        }
        if ($this->documentos->enviadosHoje($paciente) >= (int) config('fisio.documentos.portal_max_dia', 10)) {
            return response()->json(['erro' => 'limite_do_dia'], 429);
        }

        $regras = DocumentosDoPaciente::regras();
        $regras['tipo'] = ['required', 'string', Rule::in(array_keys(DocumentoPaciente::TIPOS))];
        unset($regras['data_documento']);
        $dados = $request->validate($regras, DocumentosDoPaciente::mensagens());

        $doc = $this->documentos->anexar($paciente, $dados, $request->file('arquivo'), null, DocumentoPaciente::ORIGEM_PORTAL);

        return response()->json(['ok' => true, 'situacao' => 'aguardando', 'id' => $doc->id], 201);
    }
}
