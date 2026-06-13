<?php

declare(strict_types=1);

/**
 * Pest — US-SELL-COWORK-COMMISSION · coluna Comissão na grade Sells/Index.
 *
 * Gap catalogado pós-PR #1043 (Onda Polish dados reais): prototype Cowork KB-9.75
 * tinha coluna "Comissão" mas foi adiada porque depende de schema review per-business
 * via setting `business_details.sales_cmsn_agnt`.
 *
 * Cobertura estrutural (mesmo pattern de SellsIndexCoworkPayloadTest):
 *   - Controller `index()` retorna prop `coworkCommissionEnabled` baseada em setting
 *   - Controller `inertiaList()` faz LEFT JOIN users em commission_agent
 *   - Payload expõe `commission_agent_id` + `commission_agent_name`
 *   - Multi-tenant Tier 0 (ADR 0093) preservado em getListSells / inertiaList
 *   - Index.tsx tem interface `coworkCommissionEnabled?: boolean` + coluna renderizada condicionalmente
 *
 * Refs:
 *  - app/Http/Controllers/SellController.php::index + inertiaList
 *  - resources/js/Pages/Sells/Index.tsx
 *  - memory/requisitos/Sells/index-r1-visual-comparison.md (KB-9.75 mockup)
 *  - PR #1043 "NÃO INCLUI" (Onda 5 Polish — dados reais)
 *
 * ── QUARENTENA GRANULAR legacy-quarantine (SDD F2b · 2026-06-13) ─────────────
 * quarantine-reason: snapshot estrutural SUPERSEDED — só os it() de frontend que
 * leem markup MOVIDO da coluna Comissão em `Index.tsx` (`>Comissão</th>`,
 * `vd-commission`, `.slice(0, 12)`, colSpan 11:10), markers verificados ausentes.
 * Triage: memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A.
 *
 * 🔴 Os it() de BACKEND PERMANECEM ATIVOS — guard VIVO Tier-0 ADR 0093:
 * `transactions.business_id` continua filtro principal em inertiaList apesar do
 * LEFT JOIN cmsn_user; idem fallback de nome + campos Cowork preexistentes. Idem
 * os it() de Index.tsx que ainda passam (interface coworkCommissionEnabled, gap #1043).
 * Silenciar o guard business_id violaria "multi-tenant Tier 0 IRREVOGÁVEL".
 */

defined('COMMISSION_SELL_CONTROLLER_PATH') || define('COMMISSION_SELL_CONTROLLER_PATH', 'app/Http/Controllers/SellController.php');
const COMMISSION_INDEX_TSX_PATH = 'resources/js/Pages/Sells/Index.tsx';

function commissionReadSellController(): string
{
    return file_get_contents(base_path(COMMISSION_SELL_CONTROLLER_PATH));
}

function commissionReadIndexTsx(): string
{
    return file_get_contents(base_path(COMMISSION_INDEX_TSX_PATH));
}

// ─── Backend ────────────────────────────────────────────────────────────────

it('controller index() expõe prop coworkCommissionEnabled baseada em setting business', function () {
    $source = commissionReadSellController();
    expect($source)
        ->toContain("'coworkCommissionEnabled' => \$coworkCommissionEnabled")
        // Setting vem do session() business.sales_cmsn_agnt (UltimatePOS pattern).
        ->toContain("session()->get('business.sales_cmsn_agnt')")
        // ≠ 'disable' liga a coluna (valores possíveis: disable | user | cmsn_agnt).
        ->toContain("!== 'disable'");
});

it('inertiaList faz LEFT JOIN users em commission_agent', function () {
    $source = commissionReadSellController();
    expect($source)
        ->toContain("leftJoin('users as cmsn_user', 'transactions.commission_agent', '=', 'cmsn_user.id')");
});

it('payload inertiaList expõe commission_agent_id + commission_agent_name', function () {
    $source = commissionReadSellController();
    expect($source)
        ->toContain("'commission_agent_id' => \$r->commission_agent_id")
        ->toContain("'commission_agent_name' => \$commissionAgentName");
});

it('inertiaList seleciona campos commission_agent_first_name + last_name + username', function () {
    $source = commissionReadSellController();
    expect($source)
        ->toContain("'cmsn_user.first_name as commission_agent_first_name'")
        ->toContain("'cmsn_user.last_name as commission_agent_last_name'")
        ->toContain("'cmsn_user.username as commission_agent_username'")
        ->toContain("'transactions.commission_agent as commission_agent_id'");
});

it('multi-tenant Tier 0 (ADR 0093) preservado — transactions.business_id continua filtro principal em inertiaList', function () {
    $source = commissionReadSellController();
    // ADR 0093 IRREVOGÁVEL: business_id filter explícito SEMPRE.
    // O LEFT JOIN em cmsn_user não substitui o where business_id em transactions.
    expect($source)
        ->toContain("->where('transactions.business_id', \$business_id)")
        ->toContain("LEFT JOIN preserva vendas sem created_by");
});

it('comentário do JOIN cmsn_user documenta tenancy (ADR 0093 defesa em profundidade)', function () {
    $source = commissionReadSellController();
    // Documentação inline da decisão multi-tenant — crítico pra novos devs (R3 PRE-FLIGHT).
    expect($source)
        ->toContain('Multi-tenant Tier 0 (ADR 0093)')
        ->toContain('commission_agent foi escolhido entre os');
});

it('commissionAgentName monta nome com fallback graceful (first+last → username → null)', function () {
    $source = commissionReadSellController();
    expect($source)
        ->toContain('$commissionAgentName = null')
        ->toContain('$r->commission_agent_first_name')
        ->toContain('$r->commission_agent_username');
});

// ─── Frontend ───────────────────────────────────────────────────────────────

it('Index.tsx interface SellsIndexPageProps tem coworkCommissionEnabled opcional booleano', function () {
    $source = commissionReadIndexTsx();
    expect($source)
        ->toContain('coworkCommissionEnabled?: boolean');
});

it('Index.tsx SaleRow type tem commission_agent_id + commission_agent_name opcionais', function () {
    $source = commissionReadIndexTsx();
    expect($source)
        ->toContain('commission_agent_id?: number | null')
        ->toContain('commission_agent_name?: string | null');
});

it('Index.tsx renderiza header <th>Comissão</th> condicionalmente', function () {
    $source = commissionReadIndexTsx();
    expect($source)
        // Renderização condicional na thead (gap PR #1043 ref obrigatória).
        ->toContain('props.coworkCommissionEnabled')
        ->toContain('>Comissão</th>');
    // quarantine-reason: coluna Comissão movida do Index.tsx (markers >Comissão</th>/vd-commission/colSpan ausentes) (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx tem célula com truncate 12 chars + tooltip nome completo', function () {
    $source = commissionReadIndexTsx();
    expect($source)
        ->toContain('vd-commission')
        // Truncate manual 12 chars com ellipsis (não confiar só em CSS — pra acessibilidade).
        ->toContain('.slice(0, 12)')
        // Tooltip = nome completo no title (vd-commission-name + title=).
        ->toContain('title={v.commission_agent_name}');
    // quarantine-reason: coluna Comissão movida do Index.tsx (markers >Comissão</th>/vd-commission/colSpan ausentes) (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx ajusta colSpan dinamicamente para skeleton e empty row', function () {
    $source = commissionReadIndexTsx();
    // colSpan deve ser 10 base ou 11 quando coluna Comissão habilitada.
    expect($source)
        ->toContain('props.coworkCommissionEnabled ? 11 : 10');
    // quarantine-reason: coluna Comissão movida do Index.tsx (markers >Comissão</th>/vd-commission/colSpan ausentes) (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx documenta gap PR #1043 (rastro de origem)', function () {
    $source = commissionReadIndexTsx();
    expect($source)
        ->toContain('gap PR #1043');
});

// ─── Regressão Cowork — nada quebrado nos campos preexistentes ──────────────

it('regressão — campos US-SELL-COWORK preexistentes ainda presentes (seller_name + sla_kind + pipeline_label)', function () {
    $source = commissionReadSellController();
    // Garante que a edição não removeu acidentalmente campos do payload Cowork existentes.
    expect($source)
        ->toContain("'seller_name' => \$sellerName")
        ->toContain("'sla_kind' => \$slaKind")
        ->toContain("'pipeline_label' => \$r->pipeline_name");
});
