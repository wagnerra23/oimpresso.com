---
slug: capterra-inventario-financeiro
title: "Inventário Capterra-style — Financeiro (pós-Ondas 12-21)"
type: audit
module: Financeiro
status: ativo
related: [COMPARATIVO_CONCORRENCIA.md, BRIEFING.md, AUDIT-FUNCOES-2026-05-19.md, SPEC.md]
generated_at: 2026-05-19
generated_by: skill comparativo-do-modulo
nota_anterior: 66/100 (2026-04-25)
nota_atual: 74/100
delta: +8pp
---

# Inventário Capterra-style — Financeiro

> **Cruze:** [`COMPARATIVO_CONCORRENCIA.md`](COMPARATIVO_CONCORRENCIA.md) (capacidades P0-P3 vs concorrentes) × [`AUDIT-FUNCOES-2026-05-19.md`](AUDIT-FUNCOES-2026-05-19.md) (53 funções inventariadas) × código real em `Modules/Financeiro/` + `Modules/PaymentGateway/` (4 drivers ativos) × 14 Pages Inertia em `resources/js/Pages/Financeiro/`.

## Resumo executivo

- **Score Capterra-style:** 66 → **74/100** (+8pp em 25 dias, Ondas 12-21)
- **Cobertura funcional:** 75% → **87%** (53/61 funções)
- **Paridade canon Cowork:** 4.8 → **9.5/10**
- **Posição BR:** Atrás de Conta Azul (85) · Tiny (78) · Omie (76) · empata Bling (73)
- **Diferencial vivo:** Tela única `/financeiro/unificado` 4 estados + 21 bancos boleto + 4 drivers PaymentGateway (Inter/C6/Asaas/BcbPix) + integração POS UPos nativa + ⌘K palette + Modo apresentação

---

## Bucket ✅ APROVADO (24 capacidades — full feature + UX paridade)

### Core lançamentos
1. ✅ Listar Contas a Receber (`/financeiro/contas-receber`) — `ContaReceberController@index`
2. ✅ Listar Contas a Pagar (`/financeiro/contas-pagar`) — `ContaPagarController@index`
3. ✅ Lançar título manual (Receber/Pagar) — `UnificadoController@store` + Novo picker 2 cards canon
4. ✅ 1-clique baixa (Recebi/Paguei) — `POST /unificado/{id}/baixar`
5. ✅ Editar título inline (TituloEditSheet) — `PUT /unificado/{id}`
6. ✅ Cancelar título com soft delete + audit
7. ✅ Tela única 4 estados (a receber / recebidas / a pagar / pagas) — `/financeiro/unificado` **DIFERENCIAL canon**
8. ✅ Filtros lifecycle multi-select + período + plano de contas + busca textual
9. ✅ Densidade compact/comfortable

### Categorização contábil
10. ✅ Categorias CRUD + toggle ativo
11. ✅ Plano de Contas hierárquico BR (49 entries Receita Federal/DCASP seedados biz=4 e biz=164) — **Onda 18**
12. ✅ Vinculação categoria → plano contábil opcional

### Dashboard + relatórios
13. ✅ Dashboard KPI (a receber / a pagar / recebido mês / pago mês) com Inertia::defer skeleton
14. ✅ KPI hero saldo previsto warm dark + sparkline 30d defer
15. ✅ Drill-down ao clicar KPI → filtros aplicados
16. ✅ DRE Gerencial comparativo 4 meses — `RelatoriosController` **Onda 14**
17. ✅ Resumo do período + Fluxo de caixa realizado vs projetado
18. ✅ Export CSV — `GET /financeiro/relatorios/export-csv`

### Boleto + cobrança
19. ✅ 21 bancos boleto via `eduardokum/laravel-boleto` **DIFERENCIAL canon**
20. ✅ Strategy CNAB direto vs Gateway (ADR ARQ-0003) — config per-tenant
21. ✅ Wizard Configurar boleto 3 steps + upload cert.crt + key mTLS Inter — **PaymentGateway Onda 5**
22. ✅ Emitir cobrança via PaymentGateway (4 drivers: Inter / C6 / Asaas / BcbPix) — `POST /cobranca/emitir`
23. ✅ PIX nativo (cob estático / cobv dinâmico / recv automático) — via `PaymentGatewayService->emitirPix()` **mudou ❌ → ✅ desde 04-25**

### Conciliação
24. ✅ Conciliação OFX MVP (upload + parser STMTTRN regex + fuzzy match 85% score) — **Onda 19** **mudou ❌ → ✅ desde 04-25**

### Integração POS UPos
25. ✅ Auto-título de venda via TransactionObserver (sincronizarDeTransacao) **DIFERENCIAL nativo**
26. ✅ Auto-título purchase a prazo (BUG-3 fix US-FIN-015) — Listener cria `titulo_pagar` pra purchase due

### UX advanced (anti-comoditização)
27. ✅ ⌘K Command palette navegação rápida
28. ✅ Atalhos teclado (J/K, /, ␣, B, ?) — `Index.tsx useEffect`
29. ✅ Cross-link automático #V-/#OS-/#PC-/#R-/#P- com tooltip — `FinCrossLinkify regex`
30. ✅ Conferido per-user toggle + Comentários inline + Audit trail completo
31. ✅ Modo apresentação fullscreen (`FinPresentationMode`)
32. ✅ Checklist fechamento 12 passos (`FinChecklistFechamento` localStorage)
33. ✅ Folha jurídica imprimível @print (`FinTranscriptPDF`)
34. ✅ Favoritos pessoais atalho B (`useFinFavs`)
35. ✅ Anomaly detector (R$ outlier vs média contraparte) — `FinAnomalyDetector`
36. ✅ Party history (contraparte stats) — `FinPartyHistory`
37. ✅ Frescor pill 6 estados (paid/overdue/today/warning/soon/fresh) — `FinPillFrescor` per-linha

### Workflow corporativo
38. ✅ Workflow Aprovação 3 endpoints (solicitar → aprovar/rejeitar) — **Onda 21** (Eliana solicita, Wagner aprova)
39. ✅ Anexos NF/comprovante por título (storage local SHA-256 idempotência) — **Onda 20**

### Bancos
40. ✅ Listar Contas Bancárias + Extrato por conta + Saldo cached
41. ✅ Banner CTA "Sem conta cadastrada" no Fluxo — guia pra `/account/account/create`

> **24 capacidades CORE + 17 advanced = 41 capacidades full-feature** (alguns itens contam múltiplas funções do AUDIT, números agregados ao bucket).

---

## Bucket 🟡 PARCIAL (8 capacidades — existe mas faltando peças)

| # | Capacidade | O que tem | O que falta | Importância | Esforço |
|---|---|---|---|---|---|
| P1 | UI lista anexos no drawer | POST upload ok, idempotência SHA-256 | GET lista + thumbnail PDF + delete | P0 (Onda 22 H) | Baixo ~80 LOC |
| P2 | Pill `aprovacao_status` visível na tabela Unificado | Coluna DB + drawer mostra | Coluna na tabela linha (não só drawer) + filtro por status workflow | P0 (Onda 22 I) | Baixo ~50 LOC |
| P3 | Permissions Spatie `financeiro.titulo.aprovar` | Endpoint existe | Spatie permission registrada + gate UI esconde botão | P0 (Onda 22 J) | Baixo ~30 LOC |
| P4 | Aging buckets <30/30-60/60-90/90+ | `FinPillFrescor` per-linha (6 estados visuais) | Agregação header KPI + filtro bucket + Charter v5 Non-Goal F1 → Goal | P1 | Baixo ~50 LOC |
| P5 | Categorias livres vs Plano de Contas | 2 sistemas paralelos, vínculo opcional | Decisão produto: Plano vira primary? Categorias deprecadas? | P1 (decisão produto Wagner) | Médio (decisão) |
| P6 | Conciliação CNAB retorno | OFX upload ok + fuzzy match | Parser CNAB 240/400 retorno + match remessa→retorno automático | P1 | Alto ~200 LOC |
| P7 | Inter API auto-conciliação webhook | Driver Inter mTLS funcional + cert upload | Webhook PIX recebido marcar título pago automaticamente | P1 | Médio ~80 LOC |
| P8 | API pública | Stub OAuth | Endpoints REST documentados + rate limit + Swagger/OpenAPI | P2 | Alto ~300 LOC |

---

## Bucket ❌ AUSENTE (7 capacidades — gap com mercado)

| # | Capacidade | Líderes | Por que importa | Importância | Esforço |
|---|---|---|---|---|---|
| A1 | **OCR boleto upload** | Conta Azul (killer) · Tiny · Omie | Eliana cola foto/PDF do boleto recebido → sistema extrai linha digitável + valor + vencimento automaticamente. Mata digitação manual de boletos PJ recebidos. | **P0 KILLER** | Alto ~300 LOC + OpenAI Vision/AWS Textract API |
| A2 | **App mobile (PWA ou nativo)** | TODOS concorrentes | Wagner/Eliana fora do escritório precisa baixar conta no celular. PWA já resolve 70% sem store. | P1 (PWA leve) ou P3 (nativo) | Médio PWA ~150 LOC · Alto nativo |
| A3 | **Bulk actions na tabela** | Conta Azul · Tiny · Bling · Omie | Selecionar N títulos → baixar todos / mudar categoria em lote / exportar selecionados / cancelar lote. Crítico pra fechamento mês com 200+ títulos. | P1 | Médio ~200 LOC |
| A4 | **Notificações vencimento próximo** (e-mail/WhatsApp) | Conta Azul · Tiny | Schedule diário avisa cliente X dias antes vencimento (cobrar antes de atrasar) + lembra Eliana de pagar boletos próprios. | P1 | Médio ~100 LOC + queue + integração WA módulo |
| A5 | **Importação massiva CSV/Excel** | TODOS | Onboarding novo cliente: migra 500 títulos da planilha legacy num upload. Hoje só digitação manual. | P2 | Médio ~150 LOC |
| A6 | **Repetir lançamento (duplicate next month)** | Conta Azul · Tiny | Botão "Duplicar pra próximo mês" em recorrentes não-assinatura (luz/internet/aluguel) — UX rápida. | P2 | Baixo ~40 LOC |
| A7 | **Combobox cliente/contraparte autocomplete** | TODOS | Digita "Ban" → autocomplete BANCO/BANDEIRA/BANCO DO BRASIL. Charter v5 Non-Goal F1 — mover Goal. | P2 | Médio ~80 LOC |

---

## Score Capterra-style atualizado (vs 2026-04-25)

| Critério | Nós 04-25 | **Nós 05-20** | C.Azul | Tiny | Bling | Omie | Δ |
|---|---|---|---|---|---|---|---|
| Easy of use | 8 | **9** | 9 | 7 | 7 | 7 | +1 (⌘K + drawer canon + densidade compact) |
| Customer service | 6 | 6 | 9 | 7 | 7 | 8 | — |
| Features (cobertura) | 6 | **8.5** | 9 | 8 | 7 | 9 | **+2.5** (Concil OFX + DRE + PIX + Inter + Anexos + Workflow + Plano Contas BR) |
| Value for money | 8 | 8 | 7 | 8 | **9** | 7 | — |
| Performance | 8 | **8.5** | 8 | 7 | 7 | 7 | +0.5 (Inertia::defer + bundle CSS copy 9054 LOC) |
| Mobile-friendliness | 4 | 4 | **9** | 8 | 7 | 8 | — (gap maior do projeto) |
| Integrations | 7 | **8** | 8 | **9** | 7 | 8 | +1 (4 drivers PaymentGateway ativos: Inter mTLS / C6 / Asaas / BcbPix) |
| Onboarding | 6 | **7.5** | **9** | 8 | 7 | 7 | +1.5 (49 plano contas seedado + banner CTA + Wizard 3 steps) |
| **Total /80** | 53 | **59.5** | 68 | 62 | 58 | 61 | **+6.5** |
| **Score /100** | **66** | **74** | **85** | **78** | **73** | **76** | **+8** |

**Gap pra alcançar Conta Azul (85):** 11 pontos. Top 3 vetores: OCR boleto (+3) · Mobile/PWA (+3) · Customer service network (+1.5) · Bulk actions (+1).

---

## Top gaps priorizados (esforço × impacto)

### 🔴 P0 — Próxima Onda (22) — fechar pendências workflow Anexos+Aprovação

Pequenos, baixo risco, fecha workflow corporativo do US-FIN-021 (Aprovação) e desbloqueia Eliana.

| # | Task proposta | LOC | Impacto |
|---|---|---|---|
| Onda 22.1 | UI lista anexos GET no drawer + thumbnail + delete | ~80 | Fecha workflow upload-only → upload+visualiza+remove |
| Onda 22.2 | Coluna `aprovacao_status` na tabela Unificado + filtro pill | ~50 | Eliana vê de longe o que está pendente sem abrir drawer |
| Onda 22.3 | Permissions Spatie `financeiro.titulo.aprovar` + gate UI | ~30 | Bloqueia Eliana de aprovar (só Wagner) — segurança real |

**Subtotal P0:** ~160 LOC · 1 PR · 1 dia útil de Claude+Wagner

### 🟠 P1 — Killer features (Ondas 23-25) — fechar gap mercado top 4

| # | Task proposta | LOC | Impacto |
|---|---|---|---|
| Onda 23 | **OCR boleto upload** (OpenAI Vision API → linha digitável + valor + vencimento) | ~300 | +3pp score · Mata #1 feedback Conta Azul · DIFERENCIAL VINGADO |
| Onda 24 | **Aging buckets** agregação header + filtro <30/30-60/60-90/90+ | ~50 | +1pp · Charter v5 Goal · Eliana vê envelhecimento |
| Onda 25 | **Bulk actions** (checkbox + select-all + ações baixar/categorizar/cancelar/exportar) | ~200 | +1pp · Crítico fechamento mês 200+ títulos |
| Onda 26 | **Inter API webhook auto-conciliação** PIX recebido → titulo pago automaticamente | ~80 | Fecha ciclo PaymentGateway Onda 5 (dogfooding SaaS) |

**Subtotal P1:** ~630 LOC · 4 PRs · 4-5 dias úteis

### 🟡 P2 — Adoption boosters (Ondas 27-29)

| # | Task | LOC | Por que |
|---|---|---|---|
| Onda 27 | **Notificações vencimento próximo** (e-mail + WhatsApp X dias antes) | ~100 | Schedule diário aciona WA módulo |
| Onda 28 | **Importação massiva CSV** (mapping wizard + dry-run + commit) | ~150 | Onboarding migra planilha legacy |
| Onda 29 | **Repetir lançamento** + **Combobox autocomplete** contraparte | ~120 | UX rápida recorrentes |
| Onda 30 | **PWA básico** (manifest + service worker + offline cache) | ~150 | Mobile sem app store |

### 🔵 P3 — Futuro / decisões produto pendentes

- API pública documentada (Swagger + rate limit) — depende cliente B2B real
- White-label (cliente revende oimpresso branded) — depende estratégia comercial
- App nativo iOS/Android — opcional se PWA fechar 70%
- Aprovação multi-step FSM (3+ aprovadores) — depende cliente médio porte
- Conciliação CNAB 240/400 retorno parser dedicado

### 🟢 Decisões produto resolvidas (Wagner 2026-05-19)

- **DC1 ✅ RESOLVIDA**: Categorias e Plano de Contas ficam **PARALELOS** com vínculo opcional. Categorias livres permanecem (UX rápida lançamento). Plano de Contas é vista contábil/fiscal para o contador. Nenhum dos dois deprecated. CategoriaSheet mantém dropdown "Plano de Contas (opcional)".
- **DC2 ✅ APROVADA**: Customer service network (advisor contadores) **VAI ROLAR** — US-FIN-037 criada. Fase 1 MVP ~400 LOC (portal advisor + grant readonly access biz→contador + multi-business view). Fase 2 (referral commission 15% + marketplace + white-label) em US separadas.

---

## PRs canon aplicados (Ondas 12-21)

20 PRs mergeados (#1158 → #1180). Detalhes completos em [`handoffs/2026-05-20-0112-fin-canon-ondas-12-21-completas.md`](../../handoffs/2026-05-20-0112-fin-canon-ondas-12-21-completas.md).

---

## Próxima revisão

**Trigger:** quando Onda 25 mergear (OCR + Aging + Bulk + Inter webhook fechados) OU 2026-08-19 (90d).

**Anti-padrão a evitar:** Comparativo manual sem rodar `/comparativo Financeiro` skill — INVENTARIO precisa ser auditável vs código real, não intuição.

---

**Refs:**
- [COMPARATIVO_CONCORRENCIA.md](COMPARATIVO_CONCORRENCIA.md) — Capterra-style cards concorrentes (próx update 2026-07-25, hoje desatualizado 25d)
- [AUDIT-FUNCOES-2026-05-19.md](AUDIT-FUNCOES-2026-05-19.md) — 53 funções implementadas + 15 faltantes
- [BRIEFING.md](BRIEFING.md) — estado consolidado atualizado 2026-05-20 madrugada
- [SPEC.md](SPEC.md) — US-FIN-001 a US-FIN-018
- [ADR 0089](../../decisions/0089-capterra-driven-module-evolution.md) — Capterra-driven module evolution
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 mãe
- [ADR 0170](../../decisions/0170-paymentgateway-extraction-from-recurringbilling.md) — PaymentGateway extraction
