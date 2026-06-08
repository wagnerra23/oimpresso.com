---
date: 2026-05-26
session_id: kb975-stack-completa-prod
ondas:
  - "Bundle KB-9.75 Cowork → 11 PRs mergeados em prod Hostinger"
clientes_impactados:
  - "WR2 Sistemas (biz=1) — vendas finalizadas via /sells (Cockpit V2)"
  - "ROTA LIVRE (biz=4 · Larissa) — cliente piloto Cowork canon, monitor 1280px"
participants:
  - "Wagner Rocha (presencial · demo 3pm)"
  - "Claude Code (agente desktop · GUI Chrome MCP)"
related_adrs: [0093, 0094, 0104, 0106, 0107, 0114, 0143, 0149, 0178, 0192]
related_prs: [1638, 1639, 1640, 1641, 1642, 1643, 1644, 1645, 1646, 1651, 1652]
---

# Session log — KB-9.75 stack completa em prod (11 PRs · 2026-05-26)

> **Contexto:** Wagner apresentou demo às 15:00 BRT. Sessão de ~3h transformou o protótipo Cowork "Oimpresso ERP Comunicação Visual" do Claude Cowork em features mergeadas e deployed em `oimpresso.com` Hostinger.

## Stack de 11 PRs (ordem de merge)

| # | PR | Conteúdo | LOC | Tests |
|---|---|---|---:|---:|
| 1 | [#1638](https://github.com/wagnerra23/oimpresso.com/pull/1638) | Bundle KB-9.75 raiz aplicado em `prototipo-ui/` (10 arquivos JSX/CSS) | +4798 | — |
| 2 | [#1639](https://github.com/wagnerra23/oimpresso.com/pull/1639) | Snapshot Cowork completo (181 arquivos · 19 chats + project) | +95k | — |
| 3 | [#1640](https://github.com/wagnerra23/oimpresso.com/pull/1640) | r4 visual-comparison (15 dim · 14 gaps priorizados P0-P3) | +131 | — |
| 4 | [#1641](https://github.com/wagnerra23/oimpresso.com/pull/1641) | **P0** VdNextActionPanel + validações fiscais BR (lib) + glossário Faturar≠Pagar | +922 | 17/17 ✅ |
| 5 | [#1644](https://github.com/wagnerra23/oimpresso.com/pull/1644) | **P0** Emit modals NF-e/NFS-e 3-step + Bulk emit tricolor + Saved view "Aguardando" | +1727 | 25/25 ✅ |
| 6 | [#1642](https://github.com/wagnerra23/oimpresso.com/pull/1642) | **P2** Recibo térmico 80mm + Orçamento A4 (`@page` print canon) | +1490 | 23/23 ✅ |
| 7 | [#1643](https://github.com/wagnerra23/oimpresso.com/pull/1643) | **P3** Cheat-sheet `?` overlay + **P1** Toast hub canon `oimpressoToast` | +836 | 27/27 ✅ |
| 8 | [#1645](https://github.com/wagnerra23/oimpresso.com/pull/1645) | Link "Ver tela →" no drawer SaleSheet → /sells/{id} Inertia | +6 | — |
| 9 | [#1646](https://github.com/wagnerra23/oimpresso.com/pull/1646) | **Fix** guard `current_stage_key !== null` que escondia VdNextActionPanel | +23/-22 | — |
| 10 | [#1651](https://github.com/wagnerra23/oimpresso.com/pull/1651) | **Doc** comparativo Create vs Edit em prod (achado: Edit ainda Blade) | +223 | — |
| 11 | [#1652](https://github.com/wagnerra23/oimpresso.com/pull/1652) | **a→Link** ativa Edit.tsx Inertia em prod (substituí `<a href>` por `<Link>`) | +2/-2 | — |
| | **Total** | | **+105k** | **92 testes** |

## Gaps r4 KB-9.75 fechados (de 14 mapeados)

✅ **#1** VdNextActionPanel contextual + gates fiscais
✅ **#2** VdNfeEmitModal 3-step UI stub
✅ **#3** VdNfseEmitModal 3-step UI stub
✅ **#4** VdBulkEmitModal progress tricolor
✅ **#5** Validações fiscais BR lib (`validacoesFiscaisBr.ts` · 7 validators · DV real RF + máscara + NCM + CFOP UF + CST + CSOSN + ISS + email)
✅ **#6** Glossário BR corrigido (toast diferenciado Faturar vs Receber pagamento)
✅ **#7** Saved view "Aguardando faturamento" (filter payment≠paid AND fiscal=null)
✅ **#8** Recibo térmico 80mm (`@page size: 80mm auto`)
✅ **#9** Orçamento A4 (proposta comercial Q-XXXX + validade 7d)
✅ **#10** Toast hub canon `oimpressoToast` (event emitter sonner + custom events)
✅ **#12** Cheat-sheet overlay `?` fullscreen + grid atalhos
✅ **#14** Namespace `oimpresso:venda-*` events (invoiced/paid/emitted-nfe/nfse)

❌ **#11** Timeline rica cross-source (pendente — SaleTimeline.tsx existe mas só FSM, falta agregar payments + activities + comments)
❌ **#13** Topbar tabs Sells/Insights Jana (precisa decisão arquitetural Wagner)

**12 de 14 = 85% fechado em 1 sessão.**

## Bugs encontrados em prod (smoke MCP browser) + fix

### Bug 1 (#1645 abriu) → Fix #1646

**Sintoma:** `/sells/{id}` direto cai no Blade legacy `sale_pos.show.blade.php` mesmo com `Sells/Show.tsx` Inertia existindo.

**Causa raiz:** Backend `SellController@show` linha 2507 só ativa branch Inertia se `request()->header('X-Inertia')` presente. Direct URL navigation = full reload = sem header.

**Fix:** Adicionar `<Link href={data.urls.edit}>` Inertia no drawer SaleSheet (PR #1645 fez pra `/sells/{id}` Show, PR #1652 fez pra `/sells/{id}/edit`).

### Bug 2 (smoke prod descobriu) → Fix #1646

**Sintoma:** /sells/{id} Inertia renderiza mas painel direito SÓ mostra "Atalhos" — VdNextActionPanel + FsmActionPanel NÃO aparecem.

**Causa raiz:** `Sells/Show.tsx` tem guard `{headline.current_stage_key !== null && <VdNextActionPanel/>}`. Backend `headline` payload vinha com `current_stage_key=null` mesmo quando pipeline FSM ativo (sale_processes table existe + `/api/sells/{id}/fsm-actions` retorna `in_pipeline=true` + 5 actions).

**Fix:** Remover guard externo. Components têm proprio early-return via `/api/sells/{id}/fsm-actions` check (linhas 191-194 VdNextActionPanel, linha 251-253 FsmActionPanel). Source-of-truth correto = API, não headline payload.

## Comparativo Create vs Edit em prod (doc canon PR #1651)

Smoke test MCP browser em prod descobriu gap arquitetural:

| Tela | Status pré-#1652 | Status pós-#1652 |
|---|---|---|
| `/sells/create` | ✅ Inertia Cowork (AppShellV2 + 5 tabs + 4 KPIs hero) | ✅ Inertia (igual) |
| `/sells/{id}/edit` | ❌ Blade legacy (sidebar roxo "WR2 Sistemas" · 179 inputs) | ✅ Inertia (AppShellV2 + Cowork) |

Inconsistência visual era critical pré-#1652 — Larissa cria venda num shell moderno, ao clicar "Editar" caía em outro app antigo. Resolvido.

**Edit.tsx atual ainda é SIMPLES** (3 blocos: Dados / Desconto+observações / Comissão mecânico/balcão) vs Create (5 tabs · KPIs · customer search · product autocomplete). Próximo ciclo: refator paridade Create.

## Próximos passos (parking lot — não rolaram nesta sessão)

### P0 — Edit.tsx paridade Create (próximo)
Refator pra match Create.tsx: 5 tabs · 4 KPI hero · customer search · product autocomplete + tabela linhas · bloco pagamento. Esforço estimado: ~6-8h codáveis IA-pair (ADR 0106 fator 10x) · ~1 dia útil.

### P1 — Features Edit Blade a preservar quando migrar
- Cliente vencido alerta inline (R$ 27.657,79)
- IMEI/nº série na linha produto
- Endereço cobrança ≠ entrega (2 campos)
- Inscrever-se (assinatura recorrente)
- Anexar documento upload (.pdf/.csv/.zip/.doc/.docx/.jpg/.png · máx 5MB)
- Responsável pela venda (user select avatar)
- Desconto toggle R$/% (Create só tem R$)
- Notas equipe (separado de notas venda)

### P2 — Auto-save no Edit (padrão Create)
`oimpresso.sells.b{bizId}.u{userId}.edit.{id}.draft` localStorage.

### P3 — Gap #11 Timeline cross-source
Refator `SaleTimeline.tsx` (213 LOC, só FSM hoje) pra agregar payments + activities + comments + audit log num único stream cronológico reverso.

### P4 — Gap #13 Topbar tabs Sells/Insights Jana
**Decisão pendente Wagner:** importar pattern de Cowork "[Dashboard | Analista IA]" pra dentro do Sells/Index. Aguarda arquitetura.

## Métricas da sessão

- **Início:** ~13:30 BRT
- **Fim demo Wagner:** 15:00 BRT
- **Fim sessão técnica:** ~14:00 BRT
- **PRs/h:** 11 / 2.5h = **4.4 PRs/h**
- **LOC/h:** ~10.5k / h (majoritariamente boilerplate JSX/CSS Cowork)
- **Smoke tests prod via browser MCP:** 6 estados visuais capturados
- **Bugs encontrados + fixados in-flight:** 2

## Refs canon

- [Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md](../requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md) — 14 gaps · 15 dimensões
- [Sells-Create-vs-Edit-prod-2026-05-26-comparativo.md](../requisitos/Sells/Sells-Create-vs-Edit-prod-2026-05-26-comparativo.md) — 30+ funcionalidades comparadas
- [prototipo-ui/cowork-2026-05-26-comunicacao-visual/](../../prototipo-ui/cowork-2026-05-26-comunicacao-visual/) — snapshot Cowork completo
- ADR 0104 MWART canon · ADR 0107 visual gate · ADR 0114 Cowork loop · ADR 0143 FSM live
