---
slug: sells-runbook-import-sales
title: "Sells — Runbook da Importação de vendas /import-sales (migração MWART)"
type: runbook
module: Sells
tela: ImportSales/Index
status: ativo
owner: W
last_validated: "2026-10-02"
date: 2026-10-02
preconditions:
  - "Permissão sell.create para importar (mesma trava do Blade); sell.delete para reverter lote"
  - "Rotas existentes: GET /import-sales · POST /import-sales/preview · POST /import-sales · GET /revert-sale-import/{batch}"
  - "Para planilha acima do limite: worker da fila sales-import de pé (cron do Kernel) e QUEUE_CONNECTION=database"
steps:
  - "ImportSalesController@index ganha branch X-Inertia → Inertia::render('ImportSales/Index')"
  - "ImportSalesController@preview ganha branch X-Inertia → Inertia::render('ImportSales/Preview')"
  - "POST /import-sales: até o limite importa na hora; acima despacha ImportarVendasJob (D2)"
  - "A Page Index recarrega só a prop estado enquanto a importação anda na fila"
  - "view('import_sales.index') e view('import_sales.preview') seguem como fallback (cutover F5 é humano)"
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/venda-blade.jsx (TelaImportar) · venda-blade-telas.jsx (VendaImportPreview)"
  derived_from: "Sells/Drafts (lista dual) · Essentials ImportarPresencaJob (fila dedicada com estado em cache)"
  alvo_medido: "governance/design/targets/vendas--importacao--index.secoes.json"
---

# RUNBOOK — Importação de vendas (`/import-sales`)

> **Tipo:** runbook MWART (Blade → Inertia/React) · thread 05 do playbook `venda-menu`.
> **Refs:** ADR 0104 (MWART), ADR 0093 (multi-tenant), ADR 0062 (Hostinger ≠ CT 100),
> ADR 0358 (tenant 98 em teste). Decisões de [W] em 2026-10-02: D2 (fila acima de um limite)
> e D3 (reverter cancela em vez de apagar — **parada**, ver §5).
> **Estado origem:** Blade `import_sales.index` + `import_sales.preview`; import inteiro no
> request com `max_execution_time = 0`.
> **Estado alvo:** `resources/js/Pages/ImportSales/Index.tsx` (PT-01) +
> `resources/js/Pages/ImportSales/Preview.tsx` (PT-02).

## 1. Objetivo

Trazer venda de fora por planilha, com prévia e mapeamento de colunas antes de gravar, sem
derrubar o processo quando a planilha é grande. A conta de valor e de estoque **não muda**: o
request e a fila chamam o mesmo `App\Services\Sells\ImportSalesService`.

## 2. Pré-condições

- `sell.create` (senão 403, igual ao Blade). Reverter lote exige `sell.delete`.
- Sem rota nova: as quatro rotas do legado bastam. O progresso da fila chega pela própria
  `GET /import-sales` (recarga parcial da prop `estado`).
- Escopo `business_id` da sessão em toda consulta; SKU, unidade e local da baixa são do negócio
  (Tier 0).
- Fila `sales-import` com worker no `app/Console/Kernel.php`. **A fila `default` não serve**:
  quem a drena está atrás de `queue.backlog_worker_enabled`.

## 3. Passo a passo

1. **Index** (`GET /import-sales`, `X-Inertia`): props `campos` (14 + 5 com tipos de serviço),
   `lotes` (deferred), `estado` (cache da última importação em fila), `limiteSincrono`,
   `permissions`, `urls`. Sem `X-Inertia` → Blade, como hoje.
2. **Enviar planilha**: `router.post('/import-sales/preview', { sales: arquivo }, { forceFormData: true })`.
3. **Preview** (`X-Inertia`): cabeçalho com o índice real de cada coluna, as 100 primeiras
   linhas para conferência (o Blade mostrava as mesmas 100), o pré-mapeamento por semelhança
   (≥ 50%) e quantas vendas cada coluna geraria em "Agrupar por".
4. A tela valida antes de enviar: telefone OU e-mail, produto OU SKU, quantidade, preço
   unitário, nenhum campo em duas colunas, local e "Agrupar por" escolhidos.
5. **Importar** (`POST /import-sales`): até `limiteSincrono` linhas grava na hora; acima,
   valida a planilha e despacha `ImportarVendasJob`. Volta para a Index, que acompanha o estado
   (`na_fila` → `processando` feitas/total → `concluido` com o lote, ou `erro` com a linha).
6. **Reverter lote** (`GET /revert-sale-import/{batch}`): comportamento do legado (apaga),
   com confirmação na tela dizendo exatamente isso.

## 4. Verificação

- Pest: `tests/Feature/Sells/ImportSalesContratoTest.php` (lane `sells-pest.yml`, MySQL).
- `node scripts/design/ds-guard.mjs resources/js/Pages/ImportSales/Index.tsx resources/js/Pages/ImportSales/Preview.tsx`.
- Pós-deploy: `php artisan schedule:list | grep sales-import`.

## 5. Fora deste runbook

- **D3 — cancelar em vez de apagar:** parada. O projeto não tem um "cancelar venda" que tire a
  venda dos totais sem efeito externo (o cancelamento do FSM dispara SEFAZ/gateway e não devolve
  estoque). As opções estão no PR do backend e no `_saida-05.md` do playbook.
- Cutover F5 (remover os Blades) — humano, com aviso ao cliente.
