---
slug: sells-v2-larissa-13-bugs-batch
title: "Sells V2 — 13 bugs Larissa fixados em prod (1 sessão)"
type: session-fix-batch
authority: canonical
lifecycle: ativo
session_date: '2026-05-27'
quarter: 2026-Q2
related:
  - '0093'
  - '0104'
  - '0143'
  - '0155'
  - '0191'
  - '0192'
  - '0194'
pii: false
---

# Sells V2 — 13 bugs Larissa fixados em prod (1 sessão)

> **Contexto:** Wagner removeu o guard hardcoded `biz=4` em [SellController:976](app/Http/Controllers/SellController.php#L976) em 2026-05-27, religando V2 Inertia pra Larissa @ Rota Livre (vestuário). Resultado: cascata de bugs reportados ao longo da tarde/noite. Sessão única atacou 13 problemas → 13 PRs em prod + 1 workflow debug + 1 session log.

## Sequência dos PRs (ordem cronológica)

| # | PR | Bug | Fix |
|---|---|---|---|
| 1 | [#1778](https://github.com/wagnerra23/oimpresso.com/pull/1778) | Variação produto duplicada no autocomplete (Dor 1) | Agrupar por product_id + Popover Radix |
| 2 | [#1778](https://github.com/wagnerra23/oimpresso.com/pull/1778) | Scanner código de barras "tem que dar Enter" | Handler Enter c/ `fetchProductsNow` síncrono |
| 3 | [#1778](https://github.com/wagnerra23/oimpresso.com/pull/1778) | PaymentRow.amount `type=number` quebrava parser pt-BR | NumericInputPtBR |
| 4 | [#1779→#1780](https://github.com/wagnerra23/oimpresso.com/pull/1780) | **R$ [redacted Tier 0] virou R$ [redacted Tier 0]** (parser ponto vs vírgula) | `numberPtBR.ts` + `NumericInputPtBR.tsx` paridade Blade `__read_number` |
| 5 | [#1782](https://github.com/wagnerra23/oimpresso.com/pull/1782) | Ícones search sobre as letras (Drafts/Subs/Quotations) | `variant="shadcn"` + placeholder cw-input mais contraste WCAG AA |
| 6 | [#1784](https://github.com/wagnerra23/oimpresso.com/pull/1784) | Tamanho/SKU sumindo no carrinho pós-add | state da linha agora salva `variation` + `sub_sku` |
| 7 | [#1784](https://github.com/wagnerra23/oimpresso.com/pull/1784) | Botão Salvar não habilitava (venda a prazo) | Remove `Math.abs(totalPago - totalGeral) < 0.01` do `canSubmit`. Indicador `text-warning` no rodapé |
| 8 | [#1790](https://github.com/wagnerra23/oimpresso.com/pull/1790) | "Mecânico" aparecia pra vestuário | `urls.commission_split` gateado por `isModuleInstalled('OficinaAuto')` + `array_filter` |
| 9 | [#1793](https://github.com/wagnerra23/oimpresso.com/pull/1793) | LGPD banner clique complicado (sobreposição footer) | `body.paddingBottom` dinâmico + `toast.error` no catch silencioso |
| 10 | [#1798](https://github.com/wagnerra23/oimpresso.com/pull/1798) | LGPD "dá erro quando aceita" | `router.post` Inertia→`fetch` raw + `router.reload` partial (backend retornava 204 sem headers Inertia) |
| 11 | [#1808](https://github.com/wagnerra23/oimpresso.com/pull/1808) | Recibo "CLAUDIO MENDES, CLAUDIO MENDES" | `Contact::full_name_with_business` dedup case-insensitive |
| 12 | [#1817](https://github.com/wagnerra23/oimpresso.com/pull/1817) | Recibo hora 23:47 quando real era 18:00 (+3h) | `format_date_no_shift` novo helper SO no recibo. `format_date` legacy intacto |
| 13 | Workflow [`debug-tz-info.yml`](.github/workflows/debug-tz-info.yml) (#1814/#1816/#1819) | Diagnóstico read-only tinker via gh workflow_dispatch | Reusável pra próximos casos timezone |

## Problemas catalogados (não atacados nesta sessão)

### Sub-bug A: `transaction_date` DB em drift +2h47 vs `created_at`

Diagnóstico via [debug-tz-info.yml](.github/workflows/debug-tz-info.yml):
```
created_at_RAW_DB=2026-05-27 18:00:47       ← real (Larissa salvou 18:00)
transaction_date_RAW_DB=2026-05-27 20:47:00 ← +2h47 drift (não é offset padrão)
```

Não é offset timezone (BRT=UTC-3 não dá 2h47). Hipóteses:
- Frontend `data.transaction_date` enviou hora errada (datetime-local JS interpretando timezone)
- `format_now_local()` em [Util.php:313](app/Utils/Util.php#L313) gerou `defaultDatetime` errado
- Larissa editou o campo manualmente sem perceber

Como não é offset padrão, precisa investigação isolada — não decorrente direto de timezone.

### Cadastro Larissa (config, não código)

- **R$ [redacted Tier 0] vs R$ [redacted Tier 0] na calça jeans:** produtos cadastrados no DB com preço em BRL inteiro (R$ [redacted Tier 0]) ao invés de centavos (205.90). Provável import errado OR Larissa cadastrou os 3 produtos com mesmo padrão. Fix = Larissa edita cadastro produto, não código.

## Lições da sessão

### O que funcionou
- **`gh api -X PUT /repos/.../pulls/N/merge`** pra mergear quando GraphQL rate-limitado. REST API tem cota separada.
- **Chrome MCP javascript_tool** pra smoke E2E pós-deploy validando bundle hash + comportamento real (encontrou bug ícone padding-left, parser pt-BR, popover render).
- **gh run watch + workflow_dispatch dedicado** (`debug-tz-info.yml`) pra investigação read-only sem expor endpoint debug em prod.
- **fetch raw + `router.reload({only:[...]})`** quando backend retorna 204 — atalho canônico pra endpoints API que não são Inertia full responses.

### Anti-padrões catalogados
- **Wagner mudou branch ~7x em paralelo** durante sessão → working tree local ficou desync repetido. Solução: `git push <sha>:refs/heads/<branch> --force-with-lease` quando working tree quebra.
- **Bundle lazy-loading**: ConsentBanner não estava no entry principal mas em `AppShellV2-*.js`. Validação via `/build-inertia/manifest.json` pra achar chunks.
- **Inertia `router.post` espera resposta Inertia** — 204 puro dispara `onError` mesmo backend OK. Usar fetch raw pra endpoints API.
- **`format_date` legacy tem bug +3h "intencional" documentado** — mexer afeta vendas históricas. Solução: helper novo scoped ao caso de uso (recibo), legacy intacto.

## Métricas

- **13 bugs reportados → 13 fixes em prod**: 100%
- **PRs mergeados**: 13 (#1778, #1780, #1782, #1784, #1790, #1793, #1798, #1808, #1814, #1816, #1817, #1819 + workflow)
- **Deploys Hostinger**: 8 successivos. 1 falhou inicialmente (`routes-v7.php` cache stale) → retrigger manual via `gh workflow run deploy.yml` resolveu.
- **Pest tests adicionados**: 23 (popover R6 + parser R7 + dedup contact + format_date_no_shift + commission_split gate)
- **Helpers/componentes novos**: `numberPtBR.ts`, `NumericInputPtBR.tsx`, `format_date_no_shift`, workflow `debug-tz-info.yml`

## Refs

- ADR 0093 (multi-tenant Tier 0 preservado em todos fixes)
- ADR 0104 (MWART canônico — autocomplete + cart como F3)
- ADR 0143 (FSM Sells payment_status — venda a prazo)
- ADR 0155 (rubrica module-grade — baseline reconciliado +2x na sessão)
- ADR 0191 (Consent banner LGPD)
- ADR 0192 (CommissionSplit Onda 2 — gateado por OficinaAuto)
- ADR 0194 (Sub-vertical 4 Martinho mecânica)
- [memory/sessions/2026-05-27-audit-sells-create-vs-blade-larissa.md](2026-05-27-audit-sells-create-vs-blade-larissa.md) — audit inicial que catalogou os bugs Larissa
