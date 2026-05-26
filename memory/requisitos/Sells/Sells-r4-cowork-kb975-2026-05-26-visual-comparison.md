---
session: 2026-05-26 — bundle Cowork KB-9.75 "Comunicação Visual" delta vs main pós PR #1638
page: /sells (Index + Create + Show + Edit + drawer SaleSheet)
component: resources/js/Pages/Sells/{Index,Create,Show,Edit}.tsx + _components/*
visual_source: prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/vendas-flow.jsx + vendas-{ai,curation,output,shortcuts,tweaks}.jsx + vendas-page.jsx
canon_method: Cowork KB-9.75 bundle 2026-05-26 (PR #1638 aplicou prototipo-ui/; PR #1639 snapshot completo)
related_adrs: [0093, 0094, 0104, 0107, 0114, 0143, 0149, 0178, 0192]
charter_impact: Sells/Index.charter.md v5 → v6 candidato (NextAction + Emit modals + bulk emit) · Show.charter.md wave1-draft → live candidato · Edit.charter.md wave1-draft mantém
---

# Visual Comparison — Sells r4 (Cowork KB-9.75 bundle 2026-05-26 delta)

> **Escopo:** identificação dos gaps que o bundle Cowork 2026-05-26 ainda guarda vs main pós PR #1638/#1639. r1-r3 cobriram a baseline (Cowork rewrite + tabs Visão + Integração Vendas × Oficina). Este r4 cobre o **delta KB-9.75 novo**: `VdNextActionPanel` + Emit modals NF-e/NFS-e + bulk emit + validações fiscais BR + recibo 80mm + orçamento A4 + saved view "Aguardando faturamento" + glossário BR corrigido + custom events `oimpresso:venda-invoiced/paid`.
>
> **Skill:** mwart-comparative V4 deliverable. Gate F1.5 entre F1 (snapshot aplicado em PR #1638/#1639) e F2 (Wagner aprova SCREENSHOT da nova feature antes do F3 código).

## Contexto

- **PR #1638** (já mergeado pra apenas `prototipo-ui/` raiz) aplicou 10 arquivos do bundle KB-9.75 — sem tocar `Modules/*` ou `resources/js/Pages/*`
- **PR #1639** (este ciclo) snapshot completo do bundle "Comunicação Visual" em `prototipo-ui/cowork-2026-05-26-comunicacao-visual/`
- Sessão Cowork chat19 (2026-05-26 11:02 UTC) entregou:
  - **Opção A** — Faturar ≠ Marcar como paga (correção semântica BR: faturar gera título no contas a receber; receber pagamento baixa o título)
  - **Opção B** — Validações fiscais BR estilo Bling/Tiny/Omie (DV real, máscara dinâmica, NCM, CFOP, CST/CSOSN, ISS, soma itens)
  - **Opção C** — Orçamento A4 imprimível (proposta comercial formal)
  - + Refinos #1-#4 KB-9.75 incrementais (shortcuts overlay, IA inline, curadoria, distribuição)

Score CD estimado bundle: **9.20** (Cowork chat19) — acima do threshold 8.0 do PR #295 baseline.

## Estado atual no projeto (pós r3)

Cobertura já implementada das peças do bundle:

| Peça Cowork (bundle 2026-05-26) | Cobertura Inertia atual | Path |
|---|---|---|
| `vendas-ai.jsx` (Refino #2 IA inline) | ✅ implementado | `_components/SaleAiPanel.tsx` |
| `vendas-curation.jsx` (Refino #3 comentários + audit + troubleshooter) | ✅ implementado | `_components/SaleAuditTrail.tsx` + `SaleItemComments.tsx` + `SaleMessagePreview.tsx` |
| `vendas-output.jsx` (Refino #4 Transcript PDF + apresentação + variáveis) | ✅ implementado | `_components/SaleTranscriptPDF.tsx` + `SalePresentationMode.tsx` |
| `vendas-shortcuts.jsx` (cheat-sheet overlay `?`) | ✅ parcial (atalhos J/K/Enter no Index, falta overlay) | `Sells/Index.tsx` linhas atalhos |
| `vendas-tweaks.jsx` (TweaksPanel canônico) | ⚠️ não-aplicável (devtool dev-only) | — |
| FSM Stepper inline 5 dots | ✅ implementado | `Sells/Index.tsx` `pipeline_step/total/label/color` |
| Source pill (Balcão/Oficina/Online) | ✅ implementado (r3) | `_components/VdSource.tsx` |
| FsmActionPanel (transições FSM) | ✅ implementado | `_components/FsmActionPanel.tsx` |
| Commission split editor | ✅ implementado | `_components/CommissionSplitEditor.tsx` |
| Drawer SaleSheet | ✅ implementado | `_components/SaleSheet.tsx` |

## 15 dimensões — gaps r4 KB-9.75 (skill mwart-comparative V4)

| # | Dimensão | Cowork (canon · vendas-flow.jsx 2026-05-26) | Implementação Inertia atual | Status |
|---|---|---|---|---|
| 1 | `VdNextActionPanel` (Próxima Ação contextual) | Painel sticky no drawer Show com 1-3 ações contextuais conforme FSM (Orçamento→Aprovar / Pedido→Faturar / Faturada→Receber pagto / Entregue→Confirmar entrega) + gate "emita NF antes de faturar" | `FsmActionPanel.tsx` existe mas é estritamente transição FSM — sem painel hierárquico de "próxima ação prioritária" + gates fiscais | ❌ GAP P0 |
| 2 | `VdNfeEmitModal` (emit NF-e 3-step guided) | Modal 3-step: 1) validar CFOP+CST+NCM + impostos calculados, 2) preview XML, 3) mock SEFAZ (autorizada/rejeitada/contingência) | Hoje Inertia tem `FiscalSection.tsx` (read-only) — emissão NF-e ainda é rota legacy Blade `/nfe/...` ou via NfeBrasil Modules direto | ❌ GAP P0 |
| 3 | `VdNfseEmitModal` (emit NFS-e 3-step guided) | Modal 3-step idêntico: prefeitura webservice mock + RPS + protocolo | Hoje sem fluxo Inertia inline — depende de `Modules/NfseBrasil` Blade legacy | ❌ GAP P0 |
| 4 | Bulk emit em lote (progress tricolor) | Bulk bar adiciona "Faturar em lote (emit NF-e)" + barra running/ok/bad por item — toast por venda completa | Hoje `BulkBar` no Index tem só "Marcar pagas" + "Exportar" + "Limpar" — sem bulk fiscal | ❌ GAP P1 |
| 5 | Validações fiscais BR inline (Bling/Tiny/Omie style) | **CPF/CNPJ DV real** (algoritmo Receita Federal) + **máscara dinâmica** `11222333000181` → `11.222.333/0001-81` + **NCM 8 dígitos** + **CFOP 4 dígitos** (5xxx intra-UF / 6xxx interestadual consistência) + **CST 2 + CSOSN 3** lista oficial + **email RFC** + **ISS 2-5% LC 116/2003** + soma itens × total (tolerância R$ [redacted Tier 0]) | Hoje sem validação inline — backend valida no submit (request validation) com 422 mas sem feedback visual border vermelha + chip motivo + footer Aviso + botão Avançar disabled | ❌ GAP P0 |
| 6 | Glossário BR corrigido (semântica financeira) | FSM canônico: `Orçamento → Pedido → Faturada (NF emitida + título no contas a receber) → Entregue → Paga (baixa do título)` — **Faturar ≠ Marcar como paga**. Bulk bar tem "Faturar em lote" + "Receber pagamento em lote" SEPARADOS. Toasts diferenciados: `"#V-7827 faturada · título no contas a receber"` vs `"Pagamento recebido · #V-7827 · R-3247 baixado"` | Hoje `payment_status` (paid/due/partial) ≠ `fiscal_status` (autorizada/...) ≠ `current_stage_key` — mas UI mistura conceitos no botão "Marcar paga" (que NÃO emite NF). Toast genérico não diferencia faturado vs pago | ❌ GAP P0 — DESVIO SEMÂNTICO |
| 7 | Saved view "Aguardando faturamento" | Branch novo no dropdown Visões: filtra `fsm=1 AND fiscal_status IS NULL` (pedidos confirmados mas sem NF emitida) | Hoje saved views Cowork tem Pendentes pgto / Pendentes / Atrasadas / Rejeitadas / Faturadas — sem "Aguardando faturamento" | ❌ GAP P1 |
| 8 | Recibo térmico 80mm (`@page size: 80mm auto`) | `<VdReciboPrint venda>` com botão "Imprimir recibo" no drawer Show quando `fsm >= 2` (faturada). Layout 80mm sem chrome do app | Hoje Show tem `printSaleReceipt` (`_Lib/printSaleReceipt.ts`) com 3 modos legacy (invoice/packing_slip/delivery_note) via iframe + CSS Bootstrap 3 print_section — diferente do Cowork (80mm dedicado vs A4 fiscal) | ⚠️ GAP P2 (coexiste? 80mm é COMPLEMENTAR ao fiscal A4) |
| 9 | Orçamento A4 imprimível (`@page size: A4`) | `<VdOrcamentoPrint venda>` no drawer Show quando `fsm <= 1` (orçamento/pedido). Layout proposta comercial: header brand + número `Q-XXXX` + validade 7 dias + destinatário + tabela itens + condições + assinaturas + footer | Hoje `Sells/Quotations.tsx` tem PDF via blade `sale_pos.print_quotation_blade.php` — sem versão Inertia inline + sem branding/validade padronizada Cowork | ⚠️ GAP P2 |
| 10 | Toast feedback global (custom events) | `window.dispatchEvent(new CustomEvent('oimpresso:toast',{detail:{tone,msg}}))` reativo a 4 eventos: `venda-invoiced` (fsm=2), `venda-paid` (fsm=4), `venda-emitted-nfe`, `venda-bulk-progress` | Hoje sem hub de toast canônico — cada feature usa `console.log` ou alert ou nada. `oimpresso:open-venda` é o único custom event registrado (r3 Onda 4) | ❌ GAP P1 |
| 11 | Timeline rica acumulando eventos | Timeline no drawer Show acumula automaticamente eventos FSM + fiscais + comentários + audit log em ordem cronológica reversa, com avatar + tone + colorbar | `_components/SaleTimeline.tsx` existe mas é mais simples — sem agregação cross-source (FSM + fiscal + comments em 1 stream único) | ⚠️ GAP P2 |
| 12 | Cheat-sheet overlay `?` | Overlay fullscreen ao apertar `?` mostra TODOS atalhos disponíveis na tela atual (J/K/Enter/N/E/P/?/Esc/⌘K) em grid + close ao apertar `?` ou `Esc` | Hoje Sells/Index linhas atalhos têm `?` no Sells/Index UI mas sem overlay — só tooltip individual | ⚠️ GAP P3 (UX-paving) |
| 13 | Topbar tabs `[Dashboard | Analista IA]` | `app.jsx` do bundle adiciona 2 modos no topnav da rota chat — chaveado por `oimpresso.chat.tab` localStorage. Modo "Analista IA" mostra Jana cockpit (Brief + KPIs + análises) | NÃO-APLICÁVEL pra Vendas — esse tab é da tela Chat. Mas Wagner apontou "destino: vendas" → sinal que poderia ser **importado pra Sells/Index como "Sells / Insights Jana"** abas | ❓ POSSÍVEL NEW FEATURE — confirmar com Wagner |
| 14 | Persistência custom events `oimpresso:venda-invoiced` `oimpresso:venda-paid` | Eventos disparam ao concluir emit NF / pagamento — outros módulos (Repair, Financeiro, Whatsapp) escutam pra reagir (atualizar card, abrir drawer, mandar mensagem) | Hoje só `oimpresso:open-venda` (Onda 4 r3) — namespace `oimpresso:venda-*` ainda não estabelecido | ❌ GAP P1 |
| 15 | Responsive 1280 / 1100 (Larissa monitor) | Cowork bundle reotimizou densidade pra cliente ROTA LIVRE: emit modals com layout 1-col abaixo 980px, validações fiscais empilham vertical, recibo 80mm permanece intacto | r3 já fez responsive 1280/1100 pra coluna Origem — emit modals (gap #2/#3) precisam respeitar mesmo break | ⏳ GAP herdado dos #2/#3 |

## Prioridade × esforço (próximos PRs candidatos)

| Gap | Prio | Esforço (10x IA-pair · ADR 0106) | PR sugerido | Dep |
|---|---|---|---|---|
| #6 Glossário BR corrigido (Faturar≠Pagar) | **P0** | S (~2h codáveis) | `refactor(sells): faturar vs receber-pagamento — separar bulk + toasts` | nenhuma |
| #5 Validações fiscais BR inline | **P0** | M (~4-6h codáveis · 7 validators + UI) | `feat(sells): validações fiscais BR inline (CPF/CNPJ DV + NCM + CFOP + CST/CSOSN + ISS)` | nenhuma |
| #1 `VdNextActionPanel` (Próxima Ação) | **P0** | M (~3-4h codáveis · refator drawer Show) | `feat(sells/show): VdNextActionPanel contextual + gates fiscais` | depende do #6 (semântica) |
| #2 `VdNfeEmitModal` 3-step | **P0** | L (~6-8h codáveis · integração Modules/NfeBrasil) | `feat(sells): emit NF-e inline modal 3-step` | depende do #5 (validações) |
| #3 `VdNfseEmitModal` 3-step | **P0** | L (~6-8h codáveis · integração Modules/NfseBrasil) | `feat(sells): emit NFS-e inline modal 3-step` | depende do #5 |
| #4 Bulk emit em lote | **P1** | M (~3-4h codáveis · BulkBar + progress) | `feat(sells): bulk emit fiscal com progress tricolor` | depende do #2/#3 |
| #7 Saved view "Aguardando faturamento" | **P1** | S (~1h codáveis) | `feat(sells/index): saved view aguardando faturamento` | depende do #6 |
| #10 Toast global custom events | **P1** | S (~1-2h codáveis) | `feat(shared): hub toast canônico oimpresso:toast` | nenhuma |
| #14 Namespace `oimpresso:venda-*` events | **P1** | S (~1h codáveis) | `chore(sells): namespace custom events venda-invoiced/paid/emitted` | depende do #10 |
| #11 Timeline rica unificada | **P2** | M (~3-4h codáveis · refator SaleTimeline) | `feat(sells/show): timeline cross-source FSM+fiscal+comments+audit` | nenhuma |
| #8 Recibo 80mm | **P2** | S (~2h codáveis · coexiste com fiscal A4) | `feat(sells/show): recibo térmico 80mm @page` | nenhuma |
| #9 Orçamento A4 Cowork | **P2** | M (~2-3h codáveis · branding padronizado) | `feat(sells/quotations): orçamento A4 Cowork branding` | nenhuma |
| #12 Cheat-sheet overlay `?` | **P3** | S (~1h codáveis) | `feat(sells): cheat-sheet overlay atalhos` | nenhuma |
| #13 Topbar tabs Sells/Insights | **❓** | M (~3h · pendente confirmação Wagner) | `feat(sells): tab Insights Jana cockpit` | precisa decisão arquitetural |

Esforço total estimado P0 + P1: ~30-40h codáveis com IA-pair (~3-4 dias úteis com margem 2x [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)).

## Anti-patterns (UX charter Anti-hooks — devem ser respeitados em F3)

- ❌ Misturar `payment_status` (UPOS) com `current_stage_id` (FSM ADR 0143) em UI — são EIXOS ortogonais (pagamento ≠ fiscal ≠ FSM operacional)
- ❌ Botão único "Marcar paga" que dispara emit NF + baixa título — viola gap #6 (Faturar ≠ Pagar)
- ❌ Cor crua Tailwind no TSX — canon `.sells-cowork` + tokens `--vd-*` em `sells-cowork.css`
- ❌ Modal/Dialog pra emit NF — canon é drawer/sheet ou stepper inline (sem `<Dialog>` shadcn pesado)
- ❌ `font-bold` em h1 (canon `font-semibold` ADR 0110)
- ❌ Mexer `current_stage_id` no `useForm` — trait `GuardsFsmTransitions` (ADR 0143) reverte
- ❌ Backend silent fallback sem 422 + msg PT-BR quando validação fiscal falha
- ❌ Custom event sem namespace `oimpresso:` (colide com listeners globais browser)
- ❌ Toast feedback via `alert()` ou `console.log` (canon `oimpresso:toast` hub)
- ❌ Mult-tenant: NUNCA importar `oimpresso.sells.*` localStorage de outro `business_id` (Tier 0 ADR 0093)

## Cross-references

- **Bundle PR #1638** (mergeado) — `prototipo-ui/vendas-flow.jsx` + refinos disponíveis local
- **Bundle PR #1639** (este ciclo) — snapshot completo em `prototipo-ui/cowork-2026-05-26-comunicacao-visual/`
- **Charter Sells/Index v5** — já cobre Integração Vendas × Oficina (r3); v6 candidato com NextAction + Emit modals
- **Charter Sells/Show wave1-draft** — candidato a `live` se Onda P0 completar (Próxima Ação + Emit + Recibo 80mm)
- **Charter Sells/Edit wave1-draft** — mantém wave1-draft (Edit não tem gaps r4 críticos)
- **ADR 0143 FSM Pipeline LIVE biz=1** — todas mudanças FSM via trait/service, nunca direto na entity
- **ADR 0192 Integração Vendas × Oficina** — payload `source/os_ref/commission_split` (já em main)
- **ADR 0149 MWART screen-pattern reuse** — Show reusa SaleSheet pattern (delegado)
- **ADR 0178 Tabs Visão unificadas** — supersede ADR 0136 Grade Avançada (já em main)
- **Modules/NfeBrasil + Modules/NfseBrasil** — backends a integrar nos modals #2/#3
- **`Modules/Financeiro/Observers`** — listener `oimpresso:venda-invoiced` cria `fin_titulos` (audit canon via `financeiro-bridge-auditor` agent)

## Gate F2 (Wagner aprova)

**Não rodado nesta sessão.** Próximos passos pra abrir F2:

1. Wagner abre `prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/Oimpresso ERP - Chat.html` em browser local (Tailwind CDN + UMD React + Babel — funciona standalone)
2. Aprovar SCREENSHOT da feature alvo do próximo PR (P0 #6 Faturar≠Pagar OR #5 Validações OR #1 NextActionPanel)
3. Decidir gap #13 (Topbar tabs Insights Jana) — sim/não pra deixar parking lot
4. SYNC_LOG em `prototipo-ui/SYNC_LOG.md` registra a aprovação (ADR 0114)
5. Worker para F3 spawnado com escopo isolado (1 PR = 1 gap)

Sem screenshot live nesta worktree (sem servidor rodando). Gate visual depende de smoke pós-merge biz=1 (canary Wagner WR2) + biz=4 (Larissa ROTA LIVRE — monitor 1280px crítico).

## Notas

- **Foco "vendas"** (Wagner 2026-05-26 resposta direta): este artefato consolida o trabalho de Vendas mesmo o arquivo Cowork sendo "Chat.html" — o bundle inteiro foi gerado na sessão KB-9.75 Vendas (chat19 Cowork), Chat.html foi apenas o arquivo aberto no momento do handoff
- **Sem código gerado nesta sessão** — apenas artefato F1.5 cumprindo ADR 0107 (visual-comparison gate antes de F3)
- **F1 aplicado em PR #1638** (bundle KB-9.75 raiz) + **F1 snapshot completo em PR #1639** (este ciclo)
- Próxima sessão é F2 (Wagner aprova screenshot) → F3 (worker spawnado por gap P0)
