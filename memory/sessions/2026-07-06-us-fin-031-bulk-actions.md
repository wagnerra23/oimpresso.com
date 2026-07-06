---
sessao: US-FIN-031 · Bulk actions Financeiro Unificado
data: "2026-07-06"
owner: wagner
autor: "[CC]"
pr: "#3905"
modulo: Financeiro
tela: /financeiro/unificado
---

# Sessão 2026-07-06 — US-FIN-031 bulk actions (Financeiro Unificado)

> **TL;DR:** entregue a US-FIN-031 (Onda 25, p1, ~6h) — ações em lote na Visão
> Unificada. A US estava `parcial` no SPEC (checkbox/select-all/footer/categoria em
> lote já existiam desde as Ondas 12/15); faltava o endpoint bulk genérico + 3 ações
> + modal destrutivo. Fechada em [PR #3905](https://github.com/wagnerra23/oimpresso.com/pull/3905), 4 commits.

## O que já existia (pré-flight — não re-implementei)

Medido em `origin/main` fresco (checkout estava −4849; guard SessionStart pegou):
- Coluna checkbox por linha + `<Checkbox>` header select-all (Onda 12, 2026-05-20)
- Footer condicional "N selecionados · +totalIn / −totalOut · Limpar" (Onda 12)
- `bulkUpdateCategoria` (POST `/unificado/bulk-update-categoria`) + Sheet (Onda 15)

A âncora do SPEC (`verificado@dd3ed7c`) já dizia isso — evitou re-fazer trabalho pronto
(lição herdada das US-FIN-027/028, que estavam `todo` no MCP mas já feitas).

## O que faltava e foi entregue

- **`POST /financeiro/unificado/bulk`** genérico `{action, ids[≤500], payload{}}` —
  `UnificadoController::bulk`. 5 ações: `baixar` (quitação total instantânea, 1 request
  no lugar do loop de N POSTs), `categoria` (migrado pro endpoint; rota legacy preservada
  back-compat), `plano_conta` (Sheet novo), `cancelar` (`status='cancelado'` append-only,
  quitado pulado, Sheet destrutivo com "N títulos totalizando R$ X"), `exportar_csv`
  (BOM UTF-8 + `;`, download via fetch/XSRF).
- **Tier 0 (ADR 0093):** ownership de cada id do lote validada ANTES de qualquer escrita —
  1 id de outro business = 422 fail-closed (≠ do `bulkUpdateCategoria` legacy, que filtra
  silencioso). Limite 500. Audit trail `Activity bulk_*` com `{action, ids, count, total}`.

## REGRA MESTRE valor (dupla confirmação)

Mexe em dinheiro (baixa em lote). Prova por 2 caminhos no `UnificadoBulkGuardTest` G2:
2 títulos (R$ 100,00 + R$ 50,50) → soma das `fin_titulo_baixas` criadas = **150,50** ×
`total` gravado no audit trail = **150,50**. Feature nova, sem migration/backfill — nenhum
registro existente é alterado por este PR; baixa/cancelamento só ocorrem quando o operador
seleciona e confirma no Sheet (que exibe o total antes).

## Cobertura + memória canônica

- `UnificadoBulkGuardTest` (UC-F04, 6 GUARDs, DB MySQL real) → allowlist da lane required
  `financeiro-pest.yml` (ratchet up) + `@covers-us US-FIN-031` (G-2 covers-check)
- Charter **v17** (Goal bulk + Non-Goal cancelamento emendado: lote ≠ estorno + Automation Hook)
- `Index.casos.md` UC-F04 + trilha do tempo · SPEC done `verificado@ec17185` · BRIEFING atualizado

## Loop de gates CI (2 rodadas de fix)

1º run: 6 fails — todos de fixture/ratchet, **zero de lógica de produção** (G2/G3/G5 já verdes):
- Pest G1/G4: `business.owner_id` FK NOT NULL → add `owner_id` no business fictício
- Pest G6: `StreamedResponse::status()` inexiste → `baseResponse->getStatusCode()`
- PHPStan: `?->nome` dinâmico (6 > baseline 3) → `getAttribute('nome')` no CSV
- Layout primitives: 2 `flex` soltos (21 > 19) → `<Inline>` nas Sheets
- doneness/anchor: "**mé**todo" + "POST /..." na âncora casavam `/TODO/i` e o path-parser →
  reescrita da âncora
- Infra Contract: rota nova sem seção → `## Infra Contract` no PR body (era stale, virou pass)

2º run: 1 fail — PHPStan `Activity::$business_id` undefined property → `setAttribute` no tap().

## Lições

- **Quirk do doneness/anchor-lint com português:** `/TODO/i` casa "mé**todo**" e "**todo**s";
  o path-parser trata qualquer backtick com `/` (ex.: `` `POST /rota` ``) como path de disco →
  `anchored_dead`. Âncora de SPEC deve evitar essas duas armadilhas (usar "action"/nome-de-rota).
- **Foundation ratchet (advisory):** `Business::first()` é o bootstrap canônico dos `*GuardTest`
  da lane MySQL-real (RefreshDatabase é proibido lá). Baseline subiu 75→76 via `--force` (visível
  no diff), quarantine desceu 127→126.
- **StreamedResponse ≠ Illuminate\Http\Response:** streamDownload não tem `->status()`; em teste,
  `$resp->baseResponse->getStatusCode()`.

## Pendências

- MCP oimpresso indisponível nesta sessão (brief-fetch em fallback; `tasks-*` não conectadas)
  → fechar US-FIN-031 no MCP (todo→done + tasks-comment) quando reconectar.
- Merge + deploy + smoke Chrome/comparação com protótipo (Regra 0 RUNTIME) — em andamento.
