<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Fisio\Contracts\LinkDoPortal;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Jobs\EnviarProgramaWhatsappJob;
use Modules\Fisio\Services\Whatsapp\EnvioWhatsappFisio;
use Modules\Fisio\Services\Whatsapp\MensagensFisio;

/**
 * "Enviar pelo WhatsApp" na aba Programa (US-FISIO-009, UC-FPS-11/12).
 *
 *  - Empresa com número de saída ativo no módulo `Whatsapp` → 202 e o envio vai para a fila
 *    (`EnviarProgramaWhatsappJob`). Nada é enviado dentro do request.
 *  - Sem canal ativo (a clínica escolheu o envio manual, ou o canal caiu) → 200 com dois links
 *    `wa.me` (link do portal; depois o código) para o profissional enviar do próprio aparelho,
 *    nessa ordem — ADR 0437 §13.1 (f). Mesmas guardas do job: consentimento de WhatsApp vigente,
 *    paciente não bloqueado, telefone com opt-in.
 *
 * Exige `fisio.editar`; programa de outra empresa → 404 (escopo do model + where explícito).
 */
class EnvioWhatsappController extends Controller
{
    public function enviar(Request $request, int $id): JsonResponse
    {
        $biz = (int) $request->session()->get('user.business_id');
        $programa = Program::query()->where('business_id', $biz)->findOrFail($id);
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);

        $envio = app(EnvioWhatsappFisio::class);
        $paciente = $programa->patient()->where('business_id', $biz)->firstOrFail();
        if ($programa->status !== Program::STATUS_ATIVO || $paciente->estaBloqueado()) {
            return response()->json(['erro' => 'programa_inativo_ou_paciente_bloqueado'], 422);
        }
        if (! $envio->consentimentoVigente($paciente)) {
            return response()->json(['erro' => 'sem_consentimento_whatsapp'], 422);
        }
        $para = $envio->telefoneDoPaciente($paciente);
        if ($para === null) {
            return response()->json(['erro' => 'sem_telefone'], 422);
        }

        if ($envio->canalAtivo($biz) !== null) {
            EnviarProgramaWhatsappJob::dispatch($biz, (int) $programa->id);

            return response()->json(['modo' => 'fila'], 202);
        }

        $portal = app(LinkDoPortal::class)->emitir($paciente);
        if ($portal === null) {
            return response()->json(['erro' => 'portal_indisponivel'], 503);
        }

        return response()->json([
            'modo' => 'manual',
            'wa_me' => [
                'link' => MensagensFisio::waMe($para, MensagensFisio::link($envio->primeiroNome($paciente), $envio->nomeDaClinica($biz), $portal['url'])),
                'codigo' => MensagensFisio::waMe($para, MensagensFisio::pin($portal['pin'])),
            ],
        ]);
    }
}