---
title: "RUNBOOK — Erradicação de order_type=locacao (ADR 0265, parte backend coupled)"
owner: W
last_validated: "2026-06-09"
---

# RUNBOOK — Erradicação de `order_type=locacao` (parte backend acoplada)

> **Por que este RUNBOOK existe.** A [ADR 0265](../../decisions/0265-oficina-reparo-erradica-locacao.md) decidiu erradicar o resíduo `locacao`. A parte **durável anti-retorno** (proibições + gate `dominio:check`) já landou. Esta parte — enum + importer + service + fixtures + UI — é um **refactor Tier 0 de módulo LIVE em prod** (Martinho biz=164) **acoplado** e **não dá pra fazer blind**: precisa de **Pest local** (sem PHP no agente desktop, CI roda em SQLite e não exercita o caminho MySQL do enum nem a rota FSM real).
>
> **Regra de execução:** rodar `php artisan test --filter=OficinaAuto` (ou o subconjunto citado) **verde** antes de cada commit. 1 PR = 1 intent (`feat(oficina): erradica order_type=locacao`).

## Limite Tier 0 — o que NÃO tocar (charter v4, PR #2417, Wagner 2026-06-08)

`order_type` (o enum) **≠** FSM keys `disponivel/locada`. São coisas diferentes:

- ✅ **Em escopo (esta erradicação):** o **enum `service_orders.order_type`** + tudo que ramifica nele.
- ⛔ **FORA de escopo (dívida F3, ADR própria):** as **keys de coluna do kanban** `disponivel/locada/aguardando/manutencao/pronta` (FSM `cacamba_locacao` LIVE prod), `vehicles.current_status.locada`, `vehicles.vehicle_type.cacamba_*`, e os componentes `CacambaCard.tsx`/`CacambaKanbanColumn.tsx`. O charter `ProducaoOficina/Index.charter.md` (v4) declara isso explicitamente como **dívida F3 Tier 0 em ADR própria**. **NÃO mexer** nesta onda — quebra o kanban e a journey Martinho LIVE.

## Passos (ordenados — cada um com seu teste)

### P1 · Importer `normalizeOrderType` (1ª — desacopla o enum)
`Modules/OficinaAuto/Console/Commands/ImportFirebirdMartinhoCommand.php` (~L373):
```php
// antes:  $v === 'locacao' => 'locacao',
// depois: remover a linha → locacao cai no default 'manutencao'
private function normalizeOrderType(mixed $raw): string {
    $v = strtolower(trim((string) ($raw ?? '')));
    return match (true) {
        $v === 'mecanica' => 'mecanica',
        default           => 'manutencao', // locacao erradicado (ADR 0265) → manutencao
    };
}
```
Docblock: `Mapeia order_type legacy → {manutencao|mecanica}` (citar ADR 0265).
- **Teste acoplado:** `ImportFirebirdMartinhoW28Test.php:62` `expect(w28Invoke('normalizeOrderType','locacao'))->toBe('manutencao')` (era `'locacao'`). E `ImportFirebirdMartinhoCommandTest.php:105` (fixture input `order_type=>'locacao'`): conferir o que ele asserta na saída e ajustar pra `manutencao`.

### P2 · Migration de dados + ALTER enum (idempotente, MySQL-guard)
Nova migration `Modules/OficinaAuto/Database/Migrations/2026_06_09_000001_erradica_locacao_from_order_type.php`, espelhando `2026_06_02_000001` (mesmo guard `driver==='mysql'`, `SHOW COLUMNS` idempotência):
1. `if (!Schema::hasColumn('service_orders','order_type')) return;` + `if driver !== 'mysql' return;`
2. **Data-fix ANTES de estreitar** (evita truncamento): `DB::table('service_orders')->where('order_type','locacao')->update(['order_type'=>'manutencao']);` (cross-business correto — migration de schema, não tenant-scoped).
3. Se enum atual ainda contém `'locacao'`: `ALTER TABLE service_orders MODIFY order_type ENUM('manutencao','mecanica') NOT NULL DEFAULT 'manutencao' COMMENT '...reparo (ADR 0265)...'`.
4. `down()`: reverte pra `ENUM('locacao','manutencao','mecanica')` (não desfaz o data-fix — não há como saber quais eram locacao).
- **Verificação:** `dominio:check` (PR-2) deve cair de 1→0 divergência em OficinaAuto (o baseline `scripts/domain-dict-baseline.json` perde `dominio:undeclared-value:OficinaAuto:service_orders.order_type:locacao` → rodar `npm run dominio:baseline:write` no MESMO PR pra fotografar o débito zerado).

### P3 · KPI `locacao_ativa` no `ServiceOrderSummaryService`
`Modules/OficinaAuto/Services/ServiceOrderSummaryService.php` `kpisDashboard()`: remover o ramo `$locacaoAtiva` + a key `'locacao_ativa'` do array de retorno + a menção no docblock (`@return array{manutencao_ativa:int,concluida_mes:int,atrasada:int}`). **Sem consumidor** (grep `locacao_ativa` em Modules/+resources/js = 0 fora do service e dos testes).
- **Testes acoplados:** `Wave25SaturationTest.php:161` e `Wave26OficinaAutoSaturationTest.php:74` — tirar `'locacao_ativa'` da lista de KPIs esperada no docblock.

### P4 · Fixtures de teste FSM-roteadas (CUIDADO — order_type roteia FSM)
7 testes criam `order_type=>'locacao'`: `FsmTransitionTest`, `ProducaoBoxFilterTest`, `ServiceOrderHistoryControllerTest`, `ServiceOrderIndexStageFilterTest`, `ServiceOrderStagePipelineTest`, `WhatsAppAprovacaoPinTest`, `ImportFirebirdMartinhoCommandTest`. **Cada um pode depender do processo FSM roteado por `ORDER_TYPE_TO_PROCESS`** (`ServiceOrderFsmActionController`). Trocar `locacao`→`manutencao`/`mecanica` **um a um, rodando o teste**, porque muda o processo FSM (estágios diferentes). NÃO find-replace cego.

### P5 · UI cosmético (acoplado a saturação)
- `Resources/menus/topnav.php` L23: `'label' => 'Caçambas'` → `'Veículos'` (href já é `/oficina-auto/veiculos`).
- `Routes/web.php` L36-39: reescrever comentário stale (cita `disponivel/locada`) → descrever fluxo de reparo.
- **Testes acoplados:** `Wave25/26/27SaturationTest` source-grepam `'Caçambas'` — atualizar a asserção pra `'Veículos'`.

## Controle-negativo de aceite (ADR 0265)
Pós-merge, `grep -rn "order_type.*locacao\|locacao_ativa" Modules/OficinaAuto/` em **código de produção** = **zero** (fixtures de teste tratadas no P4). `dominio:check` baseline OficinaAuto = 0. **Preservado:** FSM keys `disponivel/locada`, journey E2E Martinho biz=1, idempotência `FB_LEGACY_ID`.

## Trilha
- 2026-06-09 · [CL] catalogou o RUNBOOK (parte backend acoplada da ADR 0265 não executável blind sem Pest). Parte anti-retorno (proibições + gate) landou no mesmo PR.
