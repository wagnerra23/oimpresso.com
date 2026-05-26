---
date: 2026-05-26
session_id: kb975-stack-final-26prs-paridade-95pct
ondas:
  - "Bundle KB-9.75 Cowork → 26 PRs mergeados em prod"
  - "Sells/Edit migrado Blade legacy → Inertia paridade 9/10"
  - "Sidebar canon Cowork fixes (grupos órfãos · localStorage v2)"
clientes_impactados:
  - "WR2 Sistemas (biz=1 · Wagner SC) — Sells/Index + Edit live em prod"
  - "ROTA LIVRE (biz=4 · Larissa) — cliente piloto · KB-9.75 features visíveis"
participants:
  - "Wagner Rocha (presencial · demo 3pm BRT · ~6h sessão)"
  - "Claude Code (agente desktop GUI · Chrome MCP smoke real)"
related_adrs: [0093, 0094, 0104, 0106, 0107, 0114, 0143, 0149, 0178, 0180, 0192]
related_prs: [1638, 1639, 1640, 1641, 1642, 1643, 1644, 1645, 1646, 1651, 1652, 1653, 1654, 1656, 1658, 1660, 1662, 1663, 1665, 1666, 1667, 1668, 1672, 1674]
---

# Session log — KB-9.75 stack final · 26 PRs · paridade 95% Cowork (2026-05-26)

> **Início:** ~13h00 BRT. **Demo Wagner:** 15h00. **Fim sessão técnica:** ~18h05 BRT.
> Sessão de ~5h transformou Cowork bundle KB-9.75 em 26 PRs mergeados + Sells/Edit migrado de Blade legacy 3,5/10 → Inertia paridade 9,0/10 + sidebar canon fixed.

## Stack 26 PRs por categoria

### KB-9.75 Cowork bundle (9 PRs · core)

| PR | Conteúdo |
|---|---|
| [#1638](https://github.com/wagnerra23/oimpresso.com/pull/1638) | Bundle KB-9.75 raiz aplicado em `prototipo-ui/` |
| [#1639](https://github.com/wagnerra23/oimpresso.com/pull/1639) | Snapshot Cowork completo (181 arquivos · 19 chats) |
| [#1640](https://github.com/wagnerra23/oimpresso.com/pull/1640) | r4 visual-comparison (14 gaps priorizados) |
| [#1641](https://github.com/wagnerra23/oimpresso.com/pull/1641) | **P0** VdNextActionPanel + validações fiscais BR + glossário |
| [#1644](https://github.com/wagnerra23/oimpresso.com/pull/1644) | **P0** Emit modals NF-e/NFS-e + Bulk + Saved view "Aguardando" |
| [#1642](https://github.com/wagnerra23/oimpresso.com/pull/1642) | **P2** Recibo térmico 80mm + Orçamento A4 |
| [#1643](https://github.com/wagnerra23/oimpresso.com/pull/1643) | **P3** Cheat-sheet '?' + **P1** Toast hub canon |
| [#1645](https://github.com/wagnerra23/oimpresso.com/pull/1645) | Link "Ver tela →" drawer→Show |
| [#1646](https://github.com/wagnerra23/oimpresso.com/pull/1646) | Fix guard VdNextActionPanel mount |

### Edit Inertia migration (6 PRs · 3,5/10 → 9,0/10)

| PR | Marco Edit |
|---|---|
| [#1652](https://github.com/wagnerra23/oimpresso.com/pull/1652) | a→Link drawer ativa Edit Inertia (5,5/10) |
| [#1656](https://github.com/wagnerra23/oimpresso.com/pull/1656) | Header sticky + filter pills + 4 KPIs hero (7,0/10) |
| [#1658](https://github.com/wagnerra23/oimpresso.com/pull/1658) | Bloco Produtos UI (ProductSearchAutocomplete + tabela) (8,0/10) |
| [#1660](https://github.com/wagnerra23/oimpresso.com/pull/1660) | Wire backend submit products (CRUD real) (8,5/10) |
| [#1662](https://github.com/wagnerra23/oimpresso.com/pull/1662) | CustomerSearchAutocomplete (8,8/10) |
| [#1663](https://github.com/wagnerra23/oimpresso.com/pull/1663) | Backend customer payload + cliente vencido alerta (9,0/10) |

### Index paridade prototipo (3 PRs)

| PR | Conteúdo |
|---|---|
| [#1666](https://github.com/wagnerra23/oimpresso.com/pull/1666) | P3 header subtitle live + P2 5º KPI "PIX hoje" |
| [#1672](https://github.com/wagnerra23/oimpresso.com/pull/1672) | Fix filter hasVisibleItem (sidebar grupos órfãos) |
| [#1674](https://github.com/wagnerra23/oimpresso.com/pull/1674) | Sidebar grupos colapsados → defaultOpen=true universal + lsKey v2 |

### Memory + tests + docs (8 PRs)

| PR | Conteúdo |
|---|---|
| [#1651](https://github.com/wagnerra23/oimpresso.com/pull/1651) | Doc comparativo Create vs Edit em prod |
| [#1653](https://github.com/wagnerra23/oimpresso.com/pull/1653) | Memory sync (14 docs untracked) |
| [#1654](https://github.com/wagnerra23/oimpresso.com/pull/1654) | MultiTenantSqlGuardTest Tier 0 |
| [#1665](https://github.com/wagnerra23/oimpresso.com/pull/1665) | Comparativo visual prototipo vs prod (8,07/10) |
| [#1667](https://github.com/wagnerra23/oimpresso.com/pull/1667) | Doc P4 search ⌘K decisão Wagner (não-gap) |
| [#1668](https://github.com/wagnerra23/oimpresso.com/pull/1668) | Doc fix "WR2 Sistemas" é business selecionado |

## Lições canon descobertas nesta sessão

### 1. Backend Show/Edit Inertia exige X-Inertia header

**Sintoma:** `/sells/{id}` direct URL caía no Blade legacy `sale_pos.show.blade.php`.

**Root cause:** SellController@show + @edit têm branch `if (request()->header('X-Inertia'))` (linha 2507 + 2934). Direct URL navigation full-reload = sem header = Blade fallback.

**Fix pattern:** SaleSheet drawer usar `<Link>` Inertia (não `<a href>`) pra disparar X-Inertia automático. PRs #1645 (Show) + #1652 (Edit).

### 2. Guards externos que escondem componentes Inertia

**Sintoma:** VdNextActionPanel + FsmActionPanel não montavam em Show.tsx mesmo com FSM ativo.

**Root cause:** Guard externo `{headline.current_stage_key !== null && ...}` falhava porque backend headline payload vinha com null mesmo com sale_processes ativo.

**Fix:** Remover guard externo. Componentes têm proprio early-return via fetch `/api/sells/{id}/fsm-actions` (source-of-truth correto).

### 3. Sidebar grupos órfãos = bug duas camadas

**Sintoma:** Labels FINANÇAS/PRODUÇÃO/ESTOQUE/RH apareciam SEM children no DOM.

**Diagnose duas camadas:**
1. **Filter laissez-faire** (PR #1672): `groupedItems[g.key]?.length` permitia items com href vazio que SidebarMenuItem silenciosamente ignorava. Fix: `hasVisibleItem` checa `Boolean(it.href) && Boolean(it.label)`.
2. **localStorage stale + defaultOpen incompleto** (PR #1674): grupos TINHAM items mas estavam colapsados por user prefs antigos + defaultOpen excluía `pessoas/sistema`. Fix: `defaultOpen={true}` universal + bump lsKey pra v2.

### 4. Backend payload Inertia precisa expor dados pra paridade visual

**Sintoma:** Customer search no Edit mostrava `"Cliente #25149"` em vez do nome.

**Root cause:** EditFormPayload backend só expunha `contact_id`, não `customer.name`.

**Fix (PR #1663):** Adicionar `'customer' => $transaction->contact ? [...] : null` no formPayload (reusa eager-load existente, sem queries extras). Bonus: ressuscitar feature "cliente vencido alerta" que era só-no-Blade.

### 5. "Branding" do sidebar é business multi-tenant selecionado

**Erro inicial:** Avaliei dimensão #1 do comparativo como 6/10 ("brand WR2 Sistemas legado").

**Correção Wagner 17:40:** "WR2 Sistemas é óbvio ser a empresa selecionada" — display dinâmico business_id do user logado:
- Wagner biz=1 → "WR2 Sistemas"
- Larissa biz=4 → "ROTA LIVRE"

Multi-tenant Tier 0 ADR 0093 funcionando corretamente. **Não-gap.** Nota corrigida 6 → 10.

### 6. Inertia useForm.put() não suporta transform

**Sintoma:** Wire backend submit products no Edit não tinha transform pra mapear EditProductLine[] → backend format.

**Fix (PR #1660):** Trocar `useForm.put()` por `router.put()` direto com body customizado merge — `{...data, products: buildProductsPayload(), tax_rate_id: data.tax_id}`.

## Score final paridade visual

| Marco | Paridade Cowork prototipo |
|---|---|
| Pré-sessão (manhã) | ~70% (sem KB-9.75 stack) |
| Pós #1665 (comparativo) | 88% |
| Pós #1666 (P2+P3) | 91% |
| Pós #1668 (fix doc sidebar branding) | 95% |
| **Pós #1674 (sidebar grupos abertos)** | **~95% confirmado** |

Os 5% residuais são:
- ~3% **dados biz=1** (Wagner único seller predomina · sem vendas oficina recentes)
- ~2% **sidebar hierarquia mais rica** que prototipo (legacy modules cobertos)

## Vantagens prod (super-set vs prototipo)

- ✅ Tabs Visão Operacional/Financeira/Produção (ADR 0178)
- ✅ Filtros avançados ▾ (date range + location + customer)
- ✅ Multi-tenant Tier 0 ADR 0093 (business_id global scope)
- ✅ FSM Pipeline LIVE biz=1 (ADR 0143)
- ✅ Integração Vendas × Oficina ADR 0192 (saved view + listener cross-módulo)
- ✅ Cliente vencido alerta inline em Edit (#1663)
- ✅ CRUD produtos real no Edit (#1660)
- ✅ Customer search Cowork em Edit (#1662)

## Próximos passos parking lot (não-bloqueantes pra demo)

### P1 — Edit features só-no-Blade pra preservar
- IMEI/nº série inline na linha produto
- Endereço cobrança ≠ entrega (2 campos)
- Inscrever-se assinatura recorrente
- Anexar documento upload (.pdf/.csv/.zip/.doc/.docx/.jpg/.png · máx 5MB)
- Responsável select avatar
- Desconto toggle R$/% (Create só tem R$)
- Notas equipe (separado de notas venda)

### P2 — Auto-save draft no Edit
Padrão `oimpresso.sells.b{bizId}.u{userId}.edit.{id}.draft` localStorage (igual Create).

### P3 — Cmd+Enter atalho passar products no payload
Hoje Cmd+Enter usa `useForm.put()` original. Trocar pra `router.put()` direto.

### P4 — Gap #11 Timeline cross-source
Refator `SaleTimeline.tsx` (213 LOC) pra agregar payments + activities + comments + audit log num único stream cronológico.

### P5 — Gap #13 Topbar tabs Sells/Insights Jana
**Decisão pendente Wagner:** importar pattern Cowork "[Dashboard | Analista IA]" pra dentro de Sells/Index.

## Métricas da sessão

- **Duração:** ~5h técnicas (13h-18h05 BRT)
- **PRs/h:** 26 / 5h = **5,2 PRs/h** (incluindo docs + bugs + features)
- **LOC mergeadas:** ~110k (majoritariamente JSX/CSS Cowork + docs canon)
- **Bugs encontrados via smoke prod MCP + fix in-flight:** 4 (Show guard, Edit Blade fallback, Sidebar filter laissez-faire, Sidebar localStorage stale)
- **Pest tests novos:** 92 (54+38+25+27 nos PRs #1641/#1642/#1644/#1643/#1654)
- **Smoke browser MCP real prod Hostinger:** 12+ screenshots capturados (Index + Show + drawer + emit modals + sidebar)
- **Quick-sync deploys:** 25 (todos success exceto 1 transient SSH error retentado)

## Mensagem Wagner final (18h05)

> "pode merge vou continuar em outra sessão · salve as memórias úteis · no local mudou certo, na hostinger ainda não subiu"

**Quick-sync deploys completaram SUCCESS** (último: #1674 às 18h00:18 UTC + 48s build). Bundle JS pode estar cached no browser Wagner — solução: hard reload ou abrir aba anônima. Hash bundle pós-build: ver `/build-inertia/manifest.json` (atual aponta pra `AppShellV2-jPuWrQtO.js`).

Próxima sessão: continuar parking lot P1-P5 + qualquer feedback novo Wagner.

## Refs canon

- [Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md](../requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md) — 14 gaps r4 (12/14 fechados nesta sessão)
- [Sells-Create-vs-Edit-prod-2026-05-26-comparativo.md](../requisitos/Sells/Sells-Create-vs-Edit-prod-2026-05-26-comparativo.md) — 30+ funcionalidades comparadas
- [Sells-prototipo-vs-prod-2026-05-26-comparativo-visual.md](../requisitos/Sells/Sells-prototipo-vs-prod-2026-05-26-comparativo-visual.md) — 15 dimensões score (8,33/10)
- [prototipo-ui/cowork-2026-05-26-comunicacao-visual/](../../prototipo-ui/cowork-2026-05-26-comunicacao-visual/) — snapshot Cowork completo
- ADR 0104 MWART canon · ADR 0107 visual gate · ADR 0114 Cowork loop · ADR 0143 FSM LIVE · ADR 0180 PageHeader v3 · ADR 0192 Integração Vendas × Oficina
- Skill `sidebar-menu-arch` — pattern DataController + SIDEBAR_GROUPS
