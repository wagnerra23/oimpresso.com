<?php

declare(strict_types=1);

/**
 * ServiceOrderSheet + ServiceOrderFsmActionPanel smoke estrutural.
 *
 * Cobre US-OFICINA-OS-DRAWER (Wave 7-A frontend) — drawer lateral pra ServiceOrder
 * espelhando pattern Sells SaleSheet + FsmActionPanel (LIVE prod biz=1, ADR 0143).
 *
 * Verifica:
 *   1. Componentes existem nos paths esperados
 *   2. Props canônicas declaradas (TypeScript contract)
 *   3. Endpoints Wave 7-A backend referenciados (GET actions, POST execute, POST start-pipeline)
 *   4. Toast sonner integrado (substitui alert() legacy)
 *   5. **CRÍTICO** useMemo/useCallback presentes nos handlers descendentes
 *      — anti-regressão re-render loop bug PR #717 SaleSheet/FsmActionPanel
 *   6. Drawer plugado em Vehicles + ServiceOrders Index
 *   7. PT-BR copy canônica (Pipeline FSM, Confirmar, Motivo, Em breve)
 *
 * @see resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderSheet.tsx
 * @see resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderFsmActionPanel.tsx
 * @see resources/js/Pages/Sells/_components/SaleSheet.tsx (pattern referência canônica)
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */

// Drawer ÚNICO de OS (consolidação 2026-06-11 · [W]): o ServiceOrderSheet simples
// foi aposentado; o ServiceOrderRichSheet é o principal (mesmo do workspace e do
// Vehicles). 1 drawer só.
const SHEET_PATH = 'resources/js/Pages/OficinaAuto/ProducaoOficina/_components/ServiceOrderRichSheet.tsx';
const PANEL_PATH = 'resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderFsmActionPanel.tsx';
const VEHICLES_INDEX_PATH = 'resources/js/Pages/OficinaAuto/Vehicles/Index.tsx';
// Tela unificada 2026-06-11: a Index (ServiceOrders/Index) foi aposentada; o
// workspace (Board) é a tela única servida em /ordens-servico e usa o drawer RICO.
const ORDERS_WORKSPACE_PATH = 'resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx';

function readFileSOS(string $relative): string
{
    return file_get_contents(base_path($relative));
}

// ─── ServiceOrderRichSheet (drawer ÚNICO de OS) ───────────────────────────────

it('Drawer único (RichSheet) existe no path canônico', function () {
    expect(file_exists(base_path(SHEET_PATH)))->toBeTrue();
});

it('Corpo rico extraído (ServiceOrderRichBody) é compartilhado drawer × Fila inline (Onda 2 · zero duplicação)', function () {
    // O corpo do drawer vira export nomeado pra a view Fila do workspace reusar inline,
    // sem reimplementar as seções. Anti-regressão da unificação 2026-06-11.
    $src = readFileSOS(SHEET_PATH);
    expect($src)->toContain('export function ServiceOrderRichBody');
    expect($src)->toContain('export default function ServiceOrderRichSheet');

    // A Fila (Board.tsx) consome o body inline — não duplica as seções.
    $board = readFileSOS('resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx');
    expect($board)->toContain('ServiceOrderRichBody');
});

it('Drawer único usa shadcn Sheet (não Dialog ad-hoc)', function () {
    $src = readFileSOS(SHEET_PATH);
    expect($src)->toContain('@/Components/ui/sheet');
    expect($src)->toContain('SheetContent');
    expect($src)->toContain('SheetHeader');
    expect($src)->toContain('SheetTitle');
});

it('Drawer único declara Props canônicas (serviceOrderId, open, onOpenChange)', function () {
    $src = readFileSOS(SHEET_PATH);
    expect($src)->toContain('serviceOrderId: number | null');
    expect($src)->toContain('open: boolean');
    expect($src)->toContain('onOpenChange');
    expect($src)->toContain('onOrderChanged');
});

it('Drawer único renderiza side="right" (drawer direita pattern Sells)', function () {
    $src = readFileSOS(SHEET_PATH);
    expect($src)->toMatch('/side="right"/');
});

it('Drawer único inclui ServiceOrderFsmActionPanel (Pipeline FSM section)', function () {
    $src = readFileSOS(SHEET_PATH);
    expect($src)->toContain('ServiceOrderFsmActionPanel');
    expect($src)->toContain('Pipeline FSM');
});

it('Drawer único usa useCallback no handler FSM (estabilizar identidade — lição PR #717)', function () {
    $src = readFileSOS(SHEET_PATH);
    expect($src)->toContain('useCallback');
    expect($src)->toContain('handleFsmTransition');
    expect($src)->toMatch('/handleFsmTransition\\s*=\\s*useCallback/');
});

it('Drawer único renderiza as seções canônicas do reparo (RichSheet · ADR 0265)', function () {
    $src = readFileSOS(SHEET_PATH);
    // Seções do drawer rico (vocabulário de REPARO — locação erradicada ADR 0265).
    expect($src)->toContain('Observação');
    expect($src)->toContain('Vistoria Digital');
    expect($src)->toContain('Peças & Mão de obra');
    expect($src)->toContain('Pipeline FSM');
    expect($src)->toContain('Linha do tempo');
});

it('Drawer único NÃO usa vocabulário de locação (caçamba/Locação — erradicado ADR 0265)', function () {
    $src = readFileSOS(SHEET_PATH);
    // Anti-regressão: o drawer é 100% reparo (sub-vertical 4 ADR 0194/0265).
    expect($src)->not->toContain('OrderTypeBadge');
});

// ─── ServiceOrderFsmActionPanel (botões dinâmicos FSM) ────────────────────────

it('ServiceOrderFsmActionPanel existe no path canônico', function () {
    expect(file_exists(base_path(PANEL_PATH)))->toBeTrue();
});

it('ServiceOrderFsmActionPanel chama endpoint GET actions (Wave 7-A)', function () {
    $src = readFileSOS(PANEL_PATH);
    expect($src)->toContain('/oficina-auto/service-orders/');
    expect($src)->toContain('/fsm/actions');
});

it('ServiceOrderFsmActionPanel chama endpoint POST execute (Wave 7-A)', function () {
    $src = readFileSOS(PANEL_PATH);
    expect($src)->toContain('/fsm/execute');
    expect($src)->toContain("method: 'POST'");
    expect($src)->toContain('action_key');
});

it('ServiceOrderFsmActionPanel chama endpoint POST start-pipeline (Wave 7-A)', function () {
    $src = readFileSOS(PANEL_PATH);
    expect($src)->toContain('/fsm/start-pipeline');
    expect($src)->toContain('Iniciar pipeline FSM');
});

it('ServiceOrderFsmActionPanel integra toast sonner (substitui alert() legacy)', function () {
    $src = readFileSOS(PANEL_PATH);
    expect($src)->toContain("from 'sonner'");
    expect($src)->toContain('toast.success');
    expect($src)->toContain('toast.error');
    expect($src)->not->toContain('alert(');
});

it('ServiceOrderFsmActionPanel usa useMemo nos derivados (anti-regressão PR #717)', function () {
    $src = readFileSOS(PANEL_PATH);
    expect($src)->toContain('useMemo');
    // Lista filtrada de actions deve ser memoizada (não filtrar no render)
    expect($src)->toContain('actionsExecutable');
    expect($src)->toMatch('/actionsExecutable\\s*=\\s*useMemo/');
});

it('ServiceOrderFsmActionPanel usa useCallback nos handlers (anti-regressão PR #717)', function () {
    $src = readFileSOS(PANEL_PATH);
    expect($src)->toContain('useCallback');
    expect($src)->toMatch('/fetchActions\\s*=\\s*useCallback/');
    expect($src)->toMatch('/doExecute\\s*=\\s*useCallback/');
    expect($src)->toMatch('/executeAction\\s*=\\s*useCallback/');
    expect($src)->toMatch('/confirmExecute\\s*=\\s*useCallback/');
});

it('ServiceOrderFsmActionPanel modal confirmação tem textarea Motivo + botões PT-BR', function () {
    $src = readFileSOS(PANEL_PATH);
    expect($src)->toContain('Textarea');
    expect($src)->toContain('Motivo');
    expect($src)->toContain('Confirmar');
    expect($src)->toContain('Cancelar');
});

it('ServiceOrderFsmActionPanel destaca actions críticas (variant destructive + ícone alerta)', function () {
    $src = readFileSOS(PANEL_PATH);
    expect($src)->toContain('is_critical');
    expect($src)->toContain("'destructive'");
    expect($src)->toContain('AlertTriangle');
});

it('ServiceOrderFsmActionPanel sinaliza side_effect com ícone Zap', function () {
    $src = readFileSOS(PANEL_PATH);
    expect($src)->toContain('has_side_effect');
    expect($src)->toContain('Zap');
});

// ─── Integração nas Index pages ────────────────────────────────────────────────

it('Vehicles/Index importa o drawer ÚNICO ServiceOrderRichSheet (consolidação 2026-06-11)', function () {
    $src = readFileSOS(VEHICLES_INDEX_PATH);
    expect($src)->toContain('ServiceOrderRichSheet');
    expect($src)->toContain("from '../ProducaoOficina/_components/ServiceOrderRichSheet'");
    // Anti-regressão: não pode voltar o drawer simples aposentado.
    expect($src)->not->toContain("from '../ServiceOrders/_components/ServiceOrderSheet'");
});

it('Vehicles/Index abre drawer ao clicar caçamba locada (current_rental_id)', function () {
    $src = readFileSOS(VEHICLES_INDEX_PATH);
    expect($src)->toContain('current_rental_id');
    expect($src)->toContain('setOpenOsId');
    expect($src)->toContain('handleVehicleRowClick');
});

it('Vehicles/Index usa useCallback no handler row click (estabilidade)', function () {
    $src = readFileSOS(VEHICLES_INDEX_PATH);
    expect($src)->toContain('useCallback');
    expect($src)->toMatch('/handleVehicleRowClick\\s*=\\s*useCallback/');
});

it('Workspace (Board) importa o drawer rico ServiceOrderRichSheet', function () {
    $src = readFileSOS(ORDERS_WORKSPACE_PATH);
    expect($src)->toContain('ServiceOrderRichSheet');
    expect($src)->toContain("from '@/Pages/OficinaAuto/ProducaoOficina/_components/ServiceOrderRichSheet'");
});

it('Workspace (Board) abre drawer direto via setOpenOsId (em vez de navegar pra show)', function () {
    $src = readFileSOS(ORDERS_WORKSPACE_PATH);
    expect($src)->toContain('setOpenOsId(c.id)');
});

it('Workspace (Board) refresh após FSM transition (router.reload partial only columns/kpis)', function () {
    $src = readFileSOS(ORDERS_WORKSPACE_PATH);
    expect($src)->toContain('router.reload');
    expect($src)->toContain("only: ['columns', 'kpis']");
});
