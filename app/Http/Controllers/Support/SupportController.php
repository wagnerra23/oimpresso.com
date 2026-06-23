<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Business;
use App\Http\Controllers\Controller;
use App\Services\Support\SupportAccessService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modo Suporte (ADR 0305) — telas read-only do agente de suporte.
 *
 * `index` lista as empresas-cliente acessíveis (todas EXCETO a operadora — resolução
 * central `SupportAccessService::accessibleBusinessIds`). Autorização + auditoria ficam no
 * middleware `EnsureSupportAccess` (service-direct, NÃO via Gate). Leituras cross-tenant são
 * EXPLÍCITAS por `business_id` — nunca trocam o contexto de sessão (ver SPEC §Desenho seguro).
 *
 * @see App\Http\Middleware\EnsureSupportAccess
 * @see App\Services\Support\SupportAccessService
 * @see memory/requisitos/Suporte/RUNBOOK-empresas.md
 * @see memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md
 */
class SupportController extends Controller
{
    public function __construct(private SupportAccessService $access)
    {
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
}
