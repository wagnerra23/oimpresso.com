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
>
> **Wave Z-2 mergeada 2026-05-25 (10 PRs · Onda 0 + 1 + 2 + 3+4 + 5 + W1 + W2 + W3 + W4 + W5):** F3 traduzido pra Inertia/React real · Backend `JobSheetObserver` substitui mock window event · Schema canon migrations aplicadas · Pendente smoke prod biz=1 ([checklist 8 blocos A-H](../memory/sessions/2026-05-25-wave-z2-smoke-checklist.md) · [script deploy](../scripts/deploy-wave-z2-integracao-vendas-oficina.sh)) · Larissa biz=4 ROTA LIVRE só após 7d canary biz=1 verde.

| Status | Tela | Prioridade | Refs |
|---|---|---|---|
| `[~]` | `Sells/Index` + coluna Origem (Balcão · Oficina · Online) | **P0 F3** | F1+F2+F3 done (Ondas 3+4 `e40289010`) · `VdSource` pill + tree branch `origem` + KPI breakdown + listener `oimpresso:open-venda` em Inertia/React · **aguarda smoke prod biz=1** |
| `[~]` | `Repair/ProducaoOficina` drawer card "Esta OS gerou venda #V-NNNN" | **P0 F3** | F1+F2+F3 done (Onda 5 PR #1505 `94300b057` + W2 expand PR #1510 + W3 Compartilhar PR #1508) · card + breakdown peças/serviço/fiscal + atalhos Abrir/Imprimir/Compartilhar (Web Share API + clipboard fallback) · **aguarda smoke prod biz=1** |
| `[~]` | `Sells/Caixa` seção "Por origem" (rota nova `/vendas/caixa`) | **P1 F3** | F1+F2+F3 done (W1 PR #1513 `4483ae855`) · `VendasCaixaPage` com bySource + barras de progresso por source + refs em Inertia/React · coexiste com Index legacy · **aguarda smoke prod biz=1** |
| `[x]` | Backend `JobSheetObserver@updated` (auto-faturar OS→Venda) | **Bloqueador F3 RESOLVIDO** | Onda 2 mergeada `e98649989` · ADR 0192 accepted 542b11ccf · Observer pattern síncrono + idempotência `(business_id, os_ref)` · W5 reverse hook `2d86ee55a` (OS reaberta → `cancelled_at`) · W4 `commission_split` editor `166791e8d` · 2 migrations aditivas (`source/os_ref/commission_split` + `cancelled_at`) · Pest GUARDs cobrindo CREATE/REVERSE/NO-OP/multi-tenant |

---

## 🟡 F0 batch — PaymentGateway UI (2026-05-19)

> Pedido completo em [`COWORK_NOTES.amendment-paymentgateway-batch.md`](COWORK_NOTES.amendment-paymentgateway-batch.md). Vinculado [ADR 0170](../memory/decisions/0170-paymentgateway-extracao-camada-cobranca.md). Backend já mergeado em main (Ondas 0/1/2/2.5/3/4a · PRs #1123/#1125/#1126/#1127/#1128/#1130). F3 UI depende de F2 aprovação Wagner (screenshot) + Onda 4 backend completar.

| Status | Tela | Prioridade | Refs |
|---|---|---|---|
| `[~]` | `Financeiro/Cobranca/Index` (rename + expansão `/financeiro/boletos`) | **P0** | F1 em Cowork [CC] em curso (chat11 2026-05-19). Material canon `boleto-contas-app.jsx` linhas 215-557. KB-9.75 mira score Vendas/Financeiro PR #1064. |
| `[~]` | `Settings/PaymentGateways/Index` (nova CRUD credenciais) | **P1** | F1 em Cowork [CC] em curso. Substitui `SheetConfigInter` inline (linhas 668-826 `boleto-contas-app.jsx`). |
| `[~]` | `Sells/Index drawer + botão "Emitir cobrança"` (cirúrgico) | **P0** | F1 em Cowork [CC] em curso. Sells/Index já `[x]` done — amendment cirúrgico adiciona drawer step sem rewrite. Atalho `C`. |

---

## 🟡 F0 batch — Drawer de OS V2 (ServiceOrderRichSheet · Oficina) (2026-06-09)

> Pedido completo no bloco F0 de [`COWORK_NOTES.md`](COWORK_NOTES.md) (`[2026-06-09] F0 — Fila V2 do drawer de OS`). Origem: avaliação F1.5 [`AVALIACAO_OS_GIT_2026-06-09.md`](AVALIACAO_OS_GIT_2026-06-09.md) + conferência pós-merge [#2477](https://github.com/wagnerra23/oimpresso.com/pull/2477). Vinculado [ADR 0265](../memory/decisions/0265-oficina-reparo-erradica-locacao.md). O drawer `ServiceOrderRichSheet` já espelha o protótipo canon; os 6 itens abaixo são os gaps V2 (4 originais + V2-5/V2-6 abertos no batch 2 de 2026-06-09). **Fechamento total landado: OS-V2-1..6 todos `[x]`.** Prioridade por impacto no balcão do Martinho (biz=164 LIVE).

| Status | Tela | Prioridade | Refs |
|---|---|---|---|
| `[x]` | OS-V2-1 · Fotos & Laudo reais no drawer | **P1** | ✅ F3 [CC] 2026-06-09 — upload OS-level real (3 estados + progresso XHR + lightbox legenda editável) via `ServiceOrderPhotoController` (HasArquivos morphTo OS) + seção "Fotos da vistoria" no print A4. Persona Técnico Repair (touch ≥44px). |
| `[x]` | OS-V2-2 · DVI inline com severidade | **P1** | ✅ F3 [CC] 2026-06-09 — semáforo radiogroup 1-toque (ok/atenção/crítico, tokens DS) no drawer via `DviInlineEditor` + `dvi_items` no payload; CRUD reusa `DviInspectionController` + CTA "Pedir aprovação" (gate WhatsApp). |
| `[x]` | OS-V2-3 · Gate "Pedir aprovação" com ciclo de estados | **P1** | ✅ F3 [CC] 2026-06-09 (F2 [W]) — `DviGateFoot` 4 estados (none→pending→approved\|declined) no `DviInlineEditor`, derivados do backend (`ServiceOrder::approval_state` + colunas `approval_requested_at`/`approval_decided_at`/`approval_decision`). "Cobrar" re-dispara WhatsApp; "Revisar e reenviar" volta pra pending. Sem botões de simulação. |
| `[x]` | OS-V2-4 · Linha do tempo FSM auditável | **P1** | ✅ F3 [CC] 2026-06-09 (F2 [W]) — `ServiceOrderTimeline` real (quem/quando/de→pra com chips) via endpoint existente `/service-orders/{id}/history`; fallback skeleton derivado quando OS antiga sem histórico. |
| `[x]` | OS-V2-5 · StageGate — checklist de bloqueio por etapa | **P1** | ✅ F3 [CC] 2026-06-09 (F2 [W]) — seção "Checklist de etapa" (`ServiceOrderStageGate`) entre Peças e Pipeline FSM; requisitos data-driven por transição (`StageGateEvaluator`), gate ENFORÇADO no servidor (`fsm/execute` → 422) + UI espelho (FsmActionPanel desabilita). Override gerente/superadmin registrado na trilha. |
| `[x]` | OS-V2-6 · Lançar item inline no drawer | **P2** | ✅ F3 [CC] 2026-06-09 (F2 [W]) — "+ Adicionar item" abre `ServiceOrderItemFormSheet` nested (sem fechar o drawer) + Editar/Remover por item (`ServiceOrderItemRow`); refetch atualiza Total OS. Touch ≥44px. |

> **Residual técnico (chore, não-UI):** backfill `order_type='locacao'\|null → 'mecanica'` nas OS legadas (badge "—" na lista) · renomear LABELS (não keys) dos estágios FSM `cacamba_locacao` pro vocabulário de reparo.

---

## Critérios pra mover de coluna

- `[ ] → [~]`: charter draft criado OU Wagner adicionou em [COWORK_NOTES.md](COWORK_NOTES.md) com pedido completo
- `[~] → [x]`: PR de F3 mergeada + a11y-report sem critical, OU charter `status: live` aprovado por Wagner
- `[~] → [!]`: bloqueador (backend, schema, ADR) explicado em [SYNC_LOG.md](SYNC_LOG.md)

## Reordenação

Wagner pode subir P2/P3 pra P0/P1 a qualquer momento — basta editar este arquivo na PR e justificar em [SYNC_LOG.md](SYNC_LOG.md).

---

## 🎯 Régua ≥9 — programa contínuo (PACOTE-Q9 PR-4 · 2026-06-10)

> **Fonte única do placar:** [`audit/CONSOLIDADO.md`](audit/CONSOLIDADO.md) (GERADO por `score-mechanized.mjs` + `consolidate.mjs` — rodar a cada onda; não duplicar números aqui).
> Run 2026-06-10 @main `c8caaec01`: **242 telas · média 87/100 · 16 abaixo de 70 · golden validado (cache Cowork dizia 86, real 87)**.
> Regra: **toda tela <90 (=nota 9) entra na fila** com o gap nomeado (R# do [`audit/GOLDEN-REFERENCE.md`](audit/GOLDEN-REFERENCE.md)). Identidade única: `--accent` fora do roxo 250–330 = **ZERO sobrou** (conformance-gate `--all` verde 2026-06-10 — item 3 do PR-4 já conforme).

### Onda W2 — Financeiro (pior gap conhecido, espelha Cowork "Reestruturação Identidade Única e Qualidade 9")

9 das 16 telas <70 são do Financeiro — confirma o W2 do Cowork (8.3):

| Tela | Nota | Gap nomeado |
|---|--:|---|
| `Financeiro/Unificado/Index` | 46 | R1 cor crua · R2 nativos · R4 ícones · R6 emoji · R7 bg-fill status |
| `Financeiro/Fluxo/Index` | 61 | R1 · R2 · R7 |
| `Financeiro/Caixa/Index` | 62 | R1 · R2 · R7 |
| `RecurringBilling/Index` | 62 | R1 · R2 · R4 · R7 |
| `RecurringBilling/Faturas/Index` | 65 | R1 · R2 · R7 |
| `RecurringBilling/Planos/Index` | 66 | R1 · R2 · R7 |
| `Financeiro/Conciliacao/Index` | 67 | R1 · R2 · R7 |
| `Financeiro/ContasBancarias/Index` | 68 | R1 · R2 · R7 |
| `Financeiro/ContasReceber/Index` | 68 | R1 · R2 · R7 |

### Fora do Financeiro (<70)

| Tela | Nota | Gap nomeado |
|---|--:|---|
| `Jana/Cockpit` | 56 | R1 · R2 · R4 · R6 · R7 |
| `Cliente/Index` | 57 | R1 · R2 · R6 · R7 |
| `Jana/Admin/Qualidade/Index` | 66 | R1 · R2 · R4 · R6 |
| `Sells/Edit` | 67 | R1 · R2 · R7 |
| `Admin/GovernanceV4Dashboard` | 68 | R1 · R4 · R7 |
| `Repair/Index` | 68 | R1 · R2 · R7 |
| `Financeiro/Dre/Index` | 68 | R1 · R2 |

**Cadência:** 1 onda por vez, W2 Financeiro primeiro (US-FIN-029 3 lentes + pilar fiscal). Telas 70–89 (faixa Advanced/Leader) entram via CONSOLIDADO conforme as ondas avançam — a fila inline carrega só o bottom-16 pra não drifar do gerado.
