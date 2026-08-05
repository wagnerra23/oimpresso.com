<?php

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Services\CustosService;

/**
 * US-COPI-070 — Dashboard de custo de IA, agora sob a Governança.
 *
 * PORTE de `Modules\Jana\Http\Controllers\Admin\CustosController`
 * (ADR 0366 §D-B/§D-C item 2). A pergunta que a tela responde é
 * "a regra está sendo cumprida?" (custo de IA sob controle), não
 * "como está meu negócio?" — logo o dono é a Governança, não o Jana.
 * O `Jana/Chat.charter.md` já mandava: "custo vai pra /governance".
 *
 * O que NÃO mudou de propósito:
 *  - A permissão segue `jana.admin.custos.view`. Renomear permissão exige
 *    ADR + migration de `permissions`/`role_has_permissions` + re-atribuição
 *    por business (regra explícita em Modules/Jana/Http/routes.php).
 *  - O `CustosService` continua em `Modules\Jana\Services` — só o controller
 *    mudou de dono. Estado intermediário legítimo declarado na ADR 0366
 *    §Consequências ("telas movidas e tabelas não"). Precedente vivo:
 *    Modules/Forja/.../RoadmapController importa Modules\Jana\Entities\Mcp\McpTask.
 *
 * Scope: `business_id` da SESSÃO (ADR 0093 Tier 0) — nunca do request.
 *
 * RUNBOOK: memory/requisitos/Governance/RUNBOOK-custos.md
 */
class CustosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:jana.admin.custos.view');
    }

    public function index(Request $request, CustosService $service): Response
    {
        $businessId = (int) $request->session()->get('user.business_id');

        $preset = $request->get('preset', 'mes_atual');
        if (! in_array($preset, ['mes_atual', 'mes_anterior', '90d', 'custom'], true)) {
            $preset = 'mes_atual';
        }

        $range = $service->resolverPeriodo(
            $preset,
            $request->get('de'),
            $request->get('ate'),
        );

        // HOTFIX Wagner 2026-05-25 PRESERVADO no porte: `Inertia::defer` fica FORA
        // de kpis/por_usuario/serie_diaria. A Page desestrutura direto
        // (`serie_diaria.reduce(...)`) sem wrap `<Deferred>` nem fallback — defer
        // entrega undefined no primeiro render → TypeError → tela BRANCA em prod
        // (mesmo bug do Dashboard.tsx, fix anterior PR #1550).
        //
        // Reintroduzir defer SÓ junto com o wrap no frontend:
        //   <Deferred data={['kpis','por_usuario','serie_diaria']} fallback={<Skeleton/>}>
        // Uma coisa sem a outra reabre o incidente.
        //
        // Painel é DB-bound + foreach pesado, mas 1 chamada eager é melhor que
        // tela branca. Chamada única (não 4x) — destructure em vars locais.
        $painel = $service->painel($businessId, $range['inicio'], $range['fim']);

        return Inertia::render('governance/Custos', [
            // CORREÇÃO DE PORTE (declarada, não silenciosa): a origem mandava `$range`
            // cru — `['inicio' => Carbon, 'fim' => Carbon]`, SEM a chave `label` que a
            // Page consome em `periodo.label` (subtítulo saía vazio) e com os Carbon
            // serializados como datetime ISO em vez de date string. `$painel['periodo']`
            // é o mesmo range já formatado pelo Service (`inicio`/`fim` date string +
            // `label` "dd/mm/aaaa — dd/mm/aaaa"). Zero query extra: vem do mesmo retorno.
            'periodo'      => $painel['periodo'],
            'filters'      => [
                'preset' => $preset,
                'de'     => $request->get('de'),
                'ate'    => $request->get('ate'),
            ],
            'pricing' => [
                'modelo_default' => config('copiloto.ai.pricing_default_model'),
                'cambio_brl_usd' => (float) config('copiloto.ai.cambio_brl_usd'),
            ],
            'kpis'         => $painel['kpis'],
            'por_usuario'  => $painel['por_usuario'],
            'serie_diaria' => $painel['serie_diaria'],
        ]);
    }
}
