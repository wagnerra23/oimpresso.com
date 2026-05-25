# TELAS_REVIEW_QUEUE.md — fila de telas pra review

> Lista priorizada de telas pra passar pelo loop Claude Design.
> Wagner pode reordenar. Status atualizado a cada movimento de fase.
>
> **Última reconciliação:** 2026-05-18 — Método KB-9.75 v2 aplicado em Vendas + Financeiro (PR #1064 mergeado). Sells/Index, Sells/Create e Financeiro/Unificado marcadas **done · A+** (≥9,5 visual) — implementação Inertia/React separada em ondas técnicas (7 ondas estimadas).
>
> **Reconciliação anterior:** 2026-05-09 — auditoria charters live no repo + 4 novas P2 com drafts (Cliente, Produto/Unificado, Produto/Index, Orcamento). 11 telas reposicionadas de `[ ]` pra `[x]` (todas com charter `status: live` no frontmatter).

**Legenda:**
- `[ ]` pending — não começou
- `[~]` in-flight — em alguma fase F0-F3.5 (charter draft conta como `[~]`)
- `[x]` done — mergeada / charter live
- `[!]` blocked — bloqueada (Wagner explica em SYNC_LOG)

---

## ✅ P0 — Coração da venda (todas live)

| Status | Tela | Score visual | Refs |
|---|---|---|---|
| `[x]` | [`Sells/Create`](../resources/js/Pages/Sells/Create.tsx) | **A+ · 9,75/10** (KB-9.75 v2 · PR #1064) | charter live, last_validated 2026-05-08, US-SELL-001..008 (PRs #257-261). Impl Inertia/React pendente em ondas técnicas. |
| `[x]` | [`Sells/Index`](../resources/js/Pages/Sells/Index.tsx) | **A+ · 9,75/10** (KB-9.75 v2 · PR #1064) | charter live, last_validated 2026-05-08, PR #261. Impl Inertia/React pendente em ondas técnicas. |

## ✅ P1 — Fluxos com charter existente (live exceto bloqueios backend)

| Status | Tela | Refs |
|---|---|---|
| `[x]` | [`Repair/ProducaoOficina`](../resources/js/Pages/Repair/ProducaoOficina/Index.tsx) | charter live, F1→F3 em 1 dia (PR #326→#330, 2026-05-09) |
| `[x]` | [`Repair/Dashboard`](../resources/js/Pages/Repair/Dashboard/Index.tsx) | charter live (rascunho exemplo ADR 0101), last_validated 2026-05-07 |
| `[x]` | [`Repair/JobSheet`](../resources/js/Pages/Repair/JobSheet/Index.tsx) | charter live, last_validated 2026-05-07 (sprint 2.5/MWART-0002) |
| `[x]` | [`Repair/Status`](../resources/js/Pages/Repair/Status/Index.tsx) | charter stub F1 live, last_validated 2026-05-07 |
| `[x]` | [`Financeiro/Unificado`](../resources/js/Pages/Financeiro/Unificado/Index.tsx) | em prod com fixes #355/#358. **Visual: A+ · 9,75/10** (KB-9.75 v2 · PR #1064). Impl Inertia/React pendente em ondas técnicas. |
| `[x]` | [`Financeiro/ContasBancarias`](../resources/js/Pages/Financeiro/ContasBancarias/Index.tsx) | charter stub F1 live, last_validated 2026-05-07 |
| `[x]` | [`Financeiro/Extrato`](../resources/js/Pages/Financeiro/Extrato/Index.tsx) | charter live, last_validated 2026-05-07, US-RB-046 |
| `[x]` | [`ProjectMgmt/Board`](../resources/js/Pages/ProjectMgmt/Board/Index.tsx) | charter live, last_validated 2026-05-08, ADR 0070 PMG |
| `[x]` | [`governance/Dashboard`](../resources/js/Pages/governance/Dashboard.charter.md) | charter live |
| `[~]` | `Financeiro/Fluxo` (não criada) | F1 pino [aqui](prototipos/financeiro-fluxo/) — sem backend service ainda |
| `[!]` | `Financeiro/PlanoContas` (não criada) | F1 pino [aqui](prototipos/financeiro-plano-contas/) — bloqueada por ADR `arq/0008` + migration `chart_of_accounts` |
| `[!]` | `Financeiro/DRE` (não criada) | F1 pino [aqui](prototipos/financeiro-dre/) — bloqueada por PlanoContas + ADR `arq/0007` |
| `[!]` | `Financeiro/Conciliacao` (não criada) | F1 pino [aqui](prototipos/financeiro-conciliacao/) — bloqueada por ADR `arq/0006` + tabela `bank_statement_lines` |

## 🟡 P2 — Charters novos draft (aguardando aprovação Wagner)

> Charters criados em batch 2026-05-09 a partir do canon visual `cowork-2026-05-09`. Cada um tem decisões pendentes pra Wagner aprovar antes de virar `status: live`.

| Status | Tela | Material canon | Charter draft |
|---|---|---|---|
| `[~]` | `Cliente/Index` (a criar) | [`clientes-page.jsx`](../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/clientes-page.jsx) (10 KB) | [draft](../resources/js/Pages/Cliente/Index.charter.md) — Wagner aprova Non-Goals + Anti-hooks |
| `[~]` | `Produto/Unificado/Index` (a criar) | [`produto-app.jsx`](../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/produto-app.jsx) (60 KB) + [screenshot-06](../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/screenshot-06-produto.png) + [pino F1](prototipos/produto-unificado/) | [draft](../resources/js/Pages/Produto/Unificado/Index.charter.md) — decisões pendentes (multiplier schema → ADR `Produto/arq/0001`, MfgRecipe namespace, cache strategy) |
| `[~]` | `Produto/Index` (a criar) | [`prod-page.jsx`](../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/prod-page.jsx) (6.5 KB) | [draft](../resources/js/Pages/Produto/Index.charter.md) — decisão pendente: simples vs unificado coexistem? |
| `[~]` | `Orcamento/Index` (a criar) | [`orc-page.jsx`](../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/orc-page.jsx) (6.3 KB) | [draft](../resources/js/Pages/Orcamento/Index.charter.md) — decisão pendente: Model `App\Transaction` (`type: quotation`) vs dedicado |

## ✅ P2 — Outras telas com charter live

| Status | Tela | Refs |
|---|---|---|
| `[x]` | [`Jana/Chat`](../resources/js/Pages/Jana/Chat.tsx) | charter live, last_validated 2026-05-09 — refinamento visual em F0 aberto em [COWORK_NOTES.md](COWORK_NOTES.md) (7 problemas detectados em prod) |
| `[x]` | [`Whatsapp/Settings`](../resources/js/Pages/Whatsapp/Settings.charter.md) | charter live |

## ⏳ P2 — Sem charter (charter-write antes de F0)

| Status | Tela | Bloqueador |
|---|---|---|
| `[ ]` | `NfeBrasil/Tributacao/Index` | charter ausente; sem material canon |
| `[ ]` | `NfeBrasil/Transactions/NfceStatus` | charter ausente; sem material canon |
| `[ ]` | `Whatsapp/Conversations/Index` | charter ausente; sem material canon |
| `[ ]` | `Inventario/Index` | HTML do canon 2026-05-09 é meta-doc de migração, não protótipo de tela operacional. Pino F1 [aqui](prototipos/inventario-migracao/) é placeholder. Charter precisa nascer com escopo claro (lista vs entradas/saídas vs ajuste). |

## P3 — Site público (vitrine de vendas)

| Status | Tela | Refs |
|---|---|---|
| `[ ]` | `Site/Home` | charter + material ausentes |
| `[ ]` | `Site/Pricing` | charter + material ausentes |
| `[ ]` | `Site/Login` | charter + material ausentes |

## ✅ A1 cross-module — Integração Vendas × Oficina (2026-05-25)

> Pedido completo em [`INTEGRACAO_VENDAS_OFICINA.md`](INTEGRACAO_VENDAS_OFICINA.md). Pré-requisito A1 do método KB-9.75 (sobe Vendas 9,0 → 9,3). F1 entregue Cowork + F2 aprovado por Wagner em 2026-05-25 (screenshot pre-merge). PR #1493 mergeado em main `b2fcabbf2` (squash --admin · 4/4 CI verde). 5 pontos de costura entre Sells/Index e Repair/ProducaoOficina sem reescrever módulos (cross-link bidirecional via evento `oimpresso:open-venda`).

| Status | Tela | Prioridade | Refs |
|---|---|---|---|
| `[~]` | `Sells/Index` + coluna Origem (Balcão · Oficina · Online) | **P0 F3** | F1+F2 done · F3 pendente traduzir `vendas-page.jsx` (`VdSource` + tree branch `origem` + listener cross-módulo + KPI hero breakdown) pra Inertia/React |
| `[~]` | `Repair/ProducaoOficina` drawer card "Esta OS gerou venda #V-NNNN" | **P0 F3** | F1+F2 done · F3 pendente traduzir `oficina-page.jsx` (`.ofc-venda-card` quando stage=pronto + dispatch `oimpresso:open-venda`) pra Inertia/React |
| `[~]` | `Sells/Caixa` seção "Por origem" | **P1 F3** | F1+F2 done · F3 pendente traduzir `vendas-extras.jsx` (`VendasCaixaPage` com bySource + barras de progresso por source + refs) pra Inertia/React |
| `[!]` | Backend `OsObserver@updated` (auto-faturar OS→Venda) | **Bloqueador F3** | Substitui mock `window.dispatchEvent('oimpresso:open-venda')` do protótipo. Aguarda ADR registrar decisão de auto-faturar como evento. Decisões Wagner: auto-faturar (sem click manual) · split comissão mecânico/balcão · OS sem nota vira venda mesmo (`fiscal: {}`) · Felipe vê tudo com filtro `Por origem · Oficina` pré-aplicado |

---

## 🟡 F0 batch — PaymentGateway UI (2026-05-19)

> Pedido completo em [`COWORK_NOTES.amendment-paymentgateway-batch.md`](COWORK_NOTES.amendment-paymentgateway-batch.md). Vinculado [ADR 0170](../memory/decisions/0170-paymentgateway-extracao-camada-cobranca.md). Backend já mergeado em main (Ondas 0/1/2/2.5/3/4a · PRs #1123/#1125/#1126/#1127/#1128/#1130). F3 UI depende de F2 aprovação Wagner (screenshot) + Onda 4 backend completar.

| Status | Tela | Prioridade | Refs |
|---|---|---|---|
| `[~]` | `Financeiro/Cobranca/Index` (rename + expansão `/financeiro/boletos`) | **P0** | F1 em Cowork [CC] em curso (chat11 2026-05-19). Material canon `boleto-contas-app.jsx` linhas 215-557. KB-9.75 mira score Vendas/Financeiro PR #1064. |
| `[~]` | `Settings/PaymentGateways/Index` (nova CRUD credenciais) | **P1** | F1 em Cowork [CC] em curso. Substitui `SheetConfigInter` inline (linhas 668-826 `boleto-contas-app.jsx`). |
| `[~]` | `Sells/Index drawer + botão "Emitir cobrança"` (cirúrgico) | **P0** | F1 em Cowork [CC] em curso. Sells/Index já `[x]` done — amendment cirúrgico adiciona drawer step sem rewrite. Atalho `C`. |

---

## Critérios pra mover de coluna

- `[ ] → [~]`: charter draft criado OU Wagner adicionou em [COWORK_NOTES.md](COWORK_NOTES.md) com pedido completo
- `[~] → [x]`: PR de F3 mergeada + a11y-report sem critical, OU charter `status: live` aprovado por Wagner
- `[~] → [!]`: bloqueador (backend, schema, ADR) explicado em [SYNC_LOG.md](SYNC_LOG.md)

## Reordenação

Wagner pode subir P2/P3 pra P0/P1 a qualquer momento — basta editar este arquivo na PR e justificar em [SYNC_LOG.md](SYNC_LOG.md).
