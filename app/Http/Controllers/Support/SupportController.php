<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Business;
use App\Http\Controllers\Controller;
use App\Services\Support\SupportAccessService;
use App\Services\Support\SupportAuditService;
use App\Services\Support\SupportClientViewService;
use App\SupportAccessLog;
use App\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modo Suporte — telas do agente de suporte (ADR 0305 read-only + ADR 0308 "Acessar como").
 *
 * `index`/`show` são read-only com `business_id` EXPLÍCITO (nunca trocam contexto de sessão —
 * SPEC §Desenho seguro). `acessarComo` (fase A) é a ÚNICA porta de escrita: faz login-as
 * completo reusando o primitivo do core, atrás da trava Tier 0 `canImpersonate` + auditoria.
 *
 * Autorização de nível-empresa + auditoria de ENTRADA ficam no middleware `EnsureSupportAccess`
 * (service-direct, NÃO via Gate). `acessarComo` re-checa no servidor (defesa em profundidade).
 *
 * @see App\Http\Middleware\EnsureSupportAccess
 * @see App\Services\Support\SupportAccessService
 * @see memory/requisitos/Suporte/RUNBOOK-empresas.md
 * @see memory/decisions/0308-modo-suporte-fase-a-acessar-como-login-as-guardado.md
 */
class SupportController extends Controller
{
    public function __construct(
        private SupportAccessService $access,
        private SupportClientViewService $view,
        private SupportAuditService $audit,
    ) {
    }

    /** Lista de empresas-cliente acessíveis pelo suporte (exceto a operadora). */
    public function index(): Response
    {
        $ids = $this->access->accessibleBusinessIds();

        // SUPORTE: leitura cross-tenant intencional (ADR 0305) — nomes das empresas-cliente.
        $empresas = Business::query()
            ->whereIn('id', $ids->all())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Business $b): array => ['id' => (int) $b->id, 'name' => (string) $b->name])
            ->values();

        return Inertia::render('Suporte/Empresas', [
            'empresas' => $empresas,
        ]);
    }

    /** Visão read-only de uma empresa-cliente: resumo + usuários (com flag de "Acessar como"). */
    public function show(int $business): Response
    {
        /** @var \App\User $agent */
        $agent = Auth::user();

        $resumo = $this->view->clientSummary($agent, $business);
        $usuarios = $this->view->clientUsers($agent, $business);

        return Inertia::render('Suporte/Visao', [
            'empresa'   => $resumo['empresa'],
            'contagens' => $resumo['contagens'],
            'usuarios'  => $usuarios,
        ]);
    }

    /**
     * Log de acessos de suporte — leitura da trilha append-only `support_access_logs` (RF3).
     *
     * Read-only por construção: só existe GET, e o Model barra update/delete. Escopo: linhas
     * cuja empresa-ALVO está em `accessibleBusinessIds()` — a mesma fonte única da lista de
     * empresas, que exclui a operadora (ADR 0305). Tentativas negadas contra a operadora ficam
     * FORA desta tela por isso. Mostra todos os agentes: é a trilha do time de suporte.
     *
     * `logs` vai sob Inertia::defer (paginate + 3 joins — rule de Pages).
     */
    public function log(): Response
    {
        $ids = $this->access->accessibleBusinessIds()->all();

        return Inertia::render('Suporte/Log', [
            'logs' => Inertia::defer(fn () => $this->logsPaginados($ids)),
        ]);
    }

    /**
     * @param  array<int, int>  $ids  empresas-cliente acessíveis (sem a operadora)
     */
    private function logsPaginados(array $ids): LengthAwarePaginator
    {
        // SUPORTE: leitura cross-tenant intencional (ADR 0305) — a trilha é do time de suporte,
        // escopada às empresas-cliente acessíveis; `business_id` aqui é a empresa AUDITADA.
        return SupportAccessLog::query()
            ->from('support_access_logs as l')
            ->leftJoin('users as agente', 'agente.id', '=', 'l.support_user_id')
            ->leftJoin('users as alvo', 'alvo.id', '=', 'l.target_user_id')
            ->leftJoin('business as b', 'b.id', '=', 'l.business_id')
            ->whereIn('l.business_id', $ids)
            ->orderByDesc('l.id')
            ->select([
                'l.id', 'l.business_id', 'l.action', 'l.created_at',
                'agente.username as agente', 'alvo.username as alvo', 'b.name as empresa',
            ])
            ->paginate(50)
            ->withQueryString()
            ->through(fn (SupportAccessLog $l): array => [
                'id'         => (int) $l->id,
                'quando'     => $l->created_at?->toIso8601String(),
                'agente'     => (string) ($l->agente ?? '—'),
                'alvo'       => $l->alvo !== null ? (string) $l->alvo : null,
                'acao'       => (string) $l->action,
                'empresa'    => (string) ($l->empresa ?? '—'),
                'empresa_id' => (int) $l->business_id,
            ]);
    }

    /**
     * Fase A (ADR 0308) — "Acessar como": login-as completo de um usuário do cliente.
     *
     * Trava Tier 0 antes de trocar a identidade: o alvo precisa pertencer à empresa da rota E
     * passar `canImpersonate` (empresa acessível ≠ operadora · alvo não-superadmin · ativo).
     * Auditado em support_access_logs ANTES do loginUsingId. Reusa o primitivo do core.
     */
    public function acessarComo(Request $request, int $business, int $user): RedirectResponse
    {
        /** @var \App\User $agent */
        $agent = Auth::user();
        $target = User::findOrFail($user);

        $route = $request->path();
        $ip = $request->ip();
        $userAgent = mb_substr((string) $request->userAgent(), 0, 512);

        // Coerência (o usuário é mesmo daquela empresa) + trava Tier 0.
        if ((int) $target->business_id !== $business || ! $this->access->canImpersonate($agent, $target)) {
            $this->audit->record($agent, $business, SupportAuditService::ACTION_NEGADO, $route, $ip, $userAgent, $user);
            abort(403, 'Usuário fora do alcance do Modo Suporte (ADR 0308).');
        }

        // RF3: grava a impersonação ANTES de trocar a identidade (append-only).
        $this->audit->recordImpersonation($agent, $business, $user, $route, $ip, $userAgent);

        // Reusa o primitivo do core (ManageUserController::signInAsUser): guarda quem eu sou e
        // loga como o cliente. O banner "voltar pra mim" sai de `switched_from` (HandleInertiaRequests).
        $previousId = (int) $agent->id;
        $previousUsername = (string) $agent->username;
        session()->flush();
        session(['previous_user_id' => $previousId, 'previous_username' => $previousUsername]);
        Auth::loginUsingId($user);

        return redirect()->route('home');
    }
}
