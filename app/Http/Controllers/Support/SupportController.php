<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Business;
use App\Http\Controllers\Controller;
use App\Services\Support\SupportAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modo Suporte (ADR 0305) — Visão de suporte READ-ONLY (v1 "B").
 *
 * Lista as empresas-cliente acessíveis (todas exceto a operadora) e mostra um resumo
 * read-only de uma delas. Autorização + auditoria ficam no middleware EnsureSupportAccess
 * (service-direct). As leituras cross-tenant são EXPLÍCITAS (business_id = alvo já autorizado)
 * — marcadas `// SUPORTE:` (convenção ADR 0093). v1 NÃO troca o contexto de sessão (sem
 * "atuar"); o switch read-write é a fase A, após auditoria de scoping.
 *
 * @see App\Http\Middleware\EnsureSupportAccess
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

    /** Visão read-only de uma empresa-cliente (acesso já autorizado + auditado no middleware). */
    public function show(int $business): Response
    {
        // SUPORTE: leitura cross-tenant intencional (ADR 0305) — perfil + resumo do cliente.
        $empresa = Business::query()->whereKey($business)->firstOrFail(['id', 'name']);

        $resumo = [
            'usuarios'   => $this->safeCount('users', $business),
            'transacoes' => $this->safeCount('transactions', $business),
            'contatos'   => $this->safeCount('contacts', $business),
        ];

        return Inertia::render('Suporte/Visao', [
            'empresa' => ['id' => (int) $empresa->id, 'name' => (string) $empresa->name],
            'resumo'  => $resumo,
        ]);
    }

    /** Contagem cross-tenant defensiva (a tabela pode não existir em todo ambiente). */
    private function safeCount(string $table, int $business): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        // SUPORTE: leitura cross-tenant intencional (ADR 0305).
        return (int) DB::table($table)->where('business_id', $business)->count();
    }
}
