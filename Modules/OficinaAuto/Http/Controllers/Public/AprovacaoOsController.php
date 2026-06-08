<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Services\AprovacaoOsService;

/**
 * AprovacaoOsController — Endpoint público SEM auth pra cliente aprovar/rejeitar OS
 * via link WhatsApp + PIN (US-OFICINA-006).
 *
 * Multi-tenant Tier 0 (ADR 0093): token HMAC carrega business_id assinado.
 * Validação re-checa OS pertence ao mesmo tenant; PIN tem rate limit anti-bruteforce.
 *
 * Rate limit em rotas (Routes/web.php): throttle:30,1 (30 req/min/IP).
 * Lockout adicional no Service após 5 tentativas PIN inválidas.
 *
 * Fluxo:
 *  1. Job WhatsApp gera token + PIN via AprovacaoOsService::gerarTokenAprovacao
 *  2. Cliente recebe link `oimpresso.com/aprovar-os/{token}` + PIN separado
 *  3. GET /aprovar-os/{token} → Page Inertia com info OS + form PIN
 *  4. POST /aprovar-os/{token} {pin, decisao=aprovar|rejeitar}
 *  5. PIN ok + decisao=aprovar → OS status orcamento → aprovada
 *  6. PIN errado 5x → lockout 30min
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-006
 */
class AprovacaoOsController extends Controller
{
    public function __construct(
        private readonly AprovacaoOsService $service,
    ) {
    }

    /**
     * GET /aprovar-os/{token} — Exibe Page Inertia com info parcial OS + form PIN.
     *
     * Empty state se token inválido/expirado (não vaza qual condição quebrou).
     */
    public function show(string $token): Response
    {
        $os = $this->service->validarToken($token);

        if ($os === null) {
            return Inertia::render('OficinaAuto/AprovacaoPublica', [
                'erro'             => 'link_invalido',
                'mensagem'         => 'Este link expirou ou é inválido. Entre em contato com a oficina pra solicitar um novo link.',
                'token'            => $token,
                'os'               => null,
                'tentativasRestantes' => 0,
            ]);
        }

        return Inertia::render('OficinaAuto/AprovacaoPublica', [
            'erro'             => null,
            'mensagem'         => null,
            'token'            => $token,
            'os'               => $this->osPayload($os),
            'tentativasRestantes' => $this->service->tentativasRestantes($os),
        ]);
    }

    /**
     * POST /aprovar-os/{token}
     *
     * Body: {pin: "1234", decisao: "aprovar" | "rejeitar"}
     *
     * - PIN ok + aprovar → status orcamento → aprovada (Inertia redirect com flash success)
     * - PIN ok + rejeitar → status permanece (idempotente — cenário 5 do test); flash neutral
     * - PIN errado → flash error + decrementa tentativas
     * - Lockout → flash error "bloqueado por 30min"
     */
    public function submit(string $token, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pin'     => ['required', 'string', 'regex:/^\d{4}$/'],
            'decisao' => ['required', 'string', 'in:aprovar,rejeitar'],
        ]);

        $os = $this->service->validarToken($token);
        if ($os === null) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Link expirou ou é inválido.',
            ]);
        }

        if (! $this->service->validarPin($os, $data['pin'])) {
            $restantes = $this->service->tentativasRestantes($os);

            return back()->with('flash', [
                'type'    => 'error',
                'message' => $restantes === 0
                    ? 'Muitas tentativas inválidas. Aguarde 30 minutos pra tentar novamente.'
                    : "PIN inválido. Você tem {$restantes} tentativa(s) restante(s).",
            ]);
        }

        // PIN OK — aplica decisão
        if ($data['decisao'] === 'aprovar') {
            $this->aprovar($os);

            return back()->with('flash', [
                'type'    => 'success',
                'message' => 'Aprovação registrada! A oficina foi avisada e iniciará o serviço.',
            ]);
        }

        // Rejeitar: cenário 5 do test — preserva em orcamento (idempotente).
        // Operador humano negocia depois. Loga pra audit ad-hoc.
        Log::info('[AprovacaoOsController] Cliente rejeitou OS via link público', [
            'os_id'       => $os->id,
            'business_id' => $os->business_id,
            'ip'          => $request->ip(),
        ]);

        return back()->with('flash', [
            'type'    => 'info',
            'message' => 'Rejeição registrada. A oficina entrará em contato.',
        ]);
    }

    /**
     * Aplica transição orcamento → aprovada.
     *
     * V0: update direto no status (não usa FsmExecuteStageActionService porque
     * cliente não é User do sistema). Quando US-OFICINA-003 FSM canônica entregar,
     * substituir por action `aprovar_orcamento_cliente_publico` no
     * sale_stage_actions com side-effect próprio.
     */
    private function aprovar(ServiceOrder $os): void
    {
        // Re-busca SEM scope pra evitar problemas de session (rota pública)
        $fresh = ServiceOrder::withoutGlobalScopes() // SUPERADMIN: rota pública sem session
            ->where('id', $os->id)
            ->where('business_id', $os->business_id)
            ->lockForUpdate()
            ->first();

        if ($fresh === null || $fresh->status !== 'orcamento') {
            // OS sumiu OU já mudou de status (outro fluxo) — idempotente, sai silencioso
            return;
        }

        $fresh->update(['status' => 'aprovada']);

        Log::info('[AprovacaoOsController] OS aprovada via link público + PIN', [
            'os_id'       => $fresh->id,
            'business_id' => $fresh->business_id,
        ]);
    }

    /**
     * Payload mínimo da OS pra exibir ao cliente (sem PII excedente).
     */
    private function osPayload(ServiceOrder $os): array
    {
        $os->loadMissing(['vehicle']);

        return [
            'id'                  => $os->id,
            'numero'              => (string) $os->id, // V0 — quando US-OFICINA-006 entregar coluna `numero_humano`, trocar
            'order_type'          => $os->order_type,
            'status'              => $os->status,
            'entered_at'          => $os->entered_at?->toIso8601String(),
            'expected_completion' => $os->expected_completion?->toIso8601String(),
            'notes'               => $os->notes,
            'valor_total'         => $this->valorTotal($os),
            'vehicle'             => $os->vehicle ? [
                'plate'        => $os->vehicle->plate,
                'vehicle_type' => $os->vehicle->vehicle_type,
            ] : null,
        ];
    }

    /**
     * Valor total estimado (best-effort V0 — quando US-OFICINA-006 mapear
     * transaction_id → final_total, usar).
     */
    private function valorTotal(ServiceOrder $os): ?float
    {
        if ($os->order_type === 'locacao' && $os->daily_rate !== null) {
            return (float) $os->daily_rate * max(1, $os->dias_locacao);
        }

        return null;
    }
}
