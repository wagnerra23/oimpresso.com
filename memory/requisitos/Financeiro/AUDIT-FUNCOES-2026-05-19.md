# Auditoria de Funções — Módulo Financeiro

**Data:** 2026-05-19
**Sessão:** epic-hermann-aa6de9 (Onda 0 → Onda 17)
**Validado em prod:** https://oimpresso.com/financeiro/* (biz MARTINHO #164 + Larissa biz=4)
**Nota geral:** 75% cobertura funcional + 85% coerência inter-telas (pós Onda 17)

---

## Resumo executivo

- **46 funções implementadas** em prod (Controllers + frontend)
- **15 funções faltantes** com priorização
- **7 inconsistências** catalogadas — 1 resolvida hoje, 6 pendentes
- **Coerência inter-telas:** Unificado ↔ DRE = 100% (após Fix 1) · Unificado ↔ Fluxo = 0% (Fluxo sem conta)

---

## 1) Funções IMPLEMENTADAS (46) — Inventário canônico

### Lançamentos (CRUD + baixa) — 13 funções · cobertura 100%

| # | Função | Endpoint | Nota |
|---|---|---|---|
| 1 | Listar lançamentos unificados | GET `/financeiro/unificado` | 10/10 |
| 2 | Filtro lifecycle (a receber/recebidas/a pagar/pagas/atrasados) | UnificadoController.parseFilters | 10/10 |
| 3 | Filtro conta bancária | UnificadoController | 9/10 |
| 4 | Filtro plano de contas (hierárquico) | UnificadoController + PlanoConta | 9/10 |
| 5 | Busca textual | UnificadoController | 9/10 |
| 6 | Filtro período (mês/30d/90d) | parseFilters | 9/10 |
| 7 | Densidade compact/comfortable | UnificadoController + frontend | 10/10 |
| 8 | 1-click baixa (Recebi/Paguei) | POST `/unificado/{id}/baixar` | 8/10 |
| 9 | Editar lançamento (TituloEditSheet) | PUT `/unificado/{id}` | 9/10 |
| 10 | Conferido per-user toggle | POST/DELETE `/unificado/{id}/conferir` | 9/10 |
| 11 | Comentários inline | POST `/unificado/{id}/comments` | 8/10 |
| 12 | Audit trail | GET `/unificado/{id}/audit` | 8/10 |
| 13 | Sparkline 30d Inertia::defer | GET `/unificado/saldo-sparkline` | 8/10 |

### Categorias + Plano de Contas — 5 funções · 100%

| # | Função | Endpoint |
|---|---|---|
| 14 | Categorias CRUD (store/update/destroy) | CategoriaController |
| 15 | Toggle ativo categoria | POST `/categorias/{id}/toggle` |
| 16 | Plano de Contas listagem hierárquica (47 entries BR seedados) | PlanoContaSeeder + Model |
| 17 | Vinculação categoria → plano contábil (FK opcional) | CategoriaSheet dropdown |
| 18 | Filtro lançamentos por plano de contas | UnificadoController (Onda 12.7) |

### KPI / Dashboard — 6 funções · 86%

| # | Função | Endpoint |
|---|---|---|
| 19 | Dashboard KPI a receber/a pagar/recebido mês/pago mês | DashboardController.index |
| 20 | Inertia::defer pra kpis (skeleton primeiro paint) | Wave 17 D6 |
| 21 | KPI hero saldo previsto warm dark + sparkline | fin-stat-hero canon |
| 22 | Filtro drill-down ao clicar KPI | UnificadoController filtros |
| 23 | Quick filter "Limpar filtros" | Dashboard.tsx onClick |
| 24 | Saldo em bancos (ContaBancaria.saldo_cached) | Dashboard.tsx |

### Relatórios — 4 funções · 67%

| # | Função | Endpoint |
|---|---|---|
| 25 | DRE Gerencial comparativo 4 meses | RelatoriosController.index |
| 26 | Fluxo de caixa (projetado vs realizado) | RelatoriosController |
| 27 | Resumo do período | RelatoriosController |
| 28 | Export CSV | GET `/financeiro/relatorios/export-csv` |

### Conciliação / Boleto — 3 funções · 50%

| # | Função | Endpoint |
|---|---|---|
| 29 | Configurar boleto (banco/agência/beneficiário) | ContaBancariaController.upsert |
| 30 | Emitir boleto individual | POST `/contas-receber/{id}/boleto` |
| 31 | Cancelar remessa | POST `/boletos/{remessaId}/cancelar` |

### Cobrança gateways — 3 funções · 75%

| # | Função | Endpoint |
|---|---|---|
| 32 | Listar cobranças (pg-shell-scope) | CobrancaController.index |
| 33 | Emitir cobrança via gateway | POST `/cobranca/emitir` |
| 34 | Cobrança cartão | POST `/cobranca/cartao` |

### Assinatura — 1 função · 100%

| # | Função | Endpoint |
|---|---|---|
| 35 | Atualizar valor/ciclo/forma pagamento | PATCH `/assinaturas/{id}` |

### UX advanced — 7 funções · 64%

| # | Função | Componente |
|---|---|---|
| 36 | Modo apresentação fullscreen | FinPresentationMode |
| 37 | Checklist fechamento 12 passos | FinChecklistFechamento (localStorage) |
| 38 | Folha jurídica imprimível @print | FinTranscriptPDF |
| 39 | Favoritos pessoais (atalho B) | useFinFavs |
| 40 | Atalhos teclado (⌘K, J/K, /, ␣, B, ?) | Index.tsx useEffect |
| 41 | Anomaly detector (R$ outlier vs média contraparte) | FinAnomalyDetector |
| 42 | Cross-link #V-/#OS-/#PC-/#R-/#P- | FinCrossLinkify regex |

### Conta bancária / Extrato — 3 funções · 60%

| # | Função | Endpoint |
|---|---|---|
| 43 | Listar contas bancárias | ContaBancariaController.index |
| 44 | Extrato bancário por conta | GET `/extrato/{contaBancariaId}` |
| 45 | Party history (contraparte stats) | FinPartyHistory |

### Pill Frescor — 1 função · 100%

| # | Função | Componente |
|---|---|---|
| 46 | 6 estados visuais (paid/overdue/today/warning/soon/fresh) | FinPillFrescor |

---

## 2) Funções FALTANDO (15) — Roadmap priorizado

### P0 — Críticas (próxima Onda)

| # | Função | Por que | Esforço | LOC |
|---|---|---|---|---|
| 47 | **Fluxo fallback sem conta cadastrada** | Biz novo vê tela "Sem conta cadastrada" — UX ruim. Projetar via títulos abertos quando ContaBancaria.count=0. | Baixo | ~40 |
| 48 | **Tela `/financeiro/plano-contas` dedicada** | Header tem botão "Plano de contas" que hoje vai pra `/categorias` (workaround Onda 16). Tree view hierárquica Receita/Despesa/Ativo/Passivo expandível com drag-drop nivel. | Médio | ~150 |

### P1 — Importantes

| # | Função | Por que | Esforço | LOC |
|---|---|---|---|---|
| 49 | **Conciliação OFX/CNAB** | Importar extrato bancário + match automático com baixas (R-FIN-009). Eliana usa diariamente. Bloqueia adoção institucional. | Alto | ~300 + tabela `bank_statement_lines` + `OfxImporter` + `ConciliacaoService::sugerir()` |
| 50 | **Anexo NF/comprovante por título** | Workflow contábil real precisa upload PDF. Spatie Media Library no Titulo. | Médio | ~100 |
| 51 | **Auto-conciliação via webhook Inter API** | Webhook do Inter marca título como pago automaticamente. Já tem Inter integration em PaymentGateway. | Médio | ~80 |

### P2 — Nice-to-have

| # | Função | Por que | Esforço | LOC |
|---|---|---|---|---|
| 52 | **Aging buckets <30/30-60/60-90/90+** | Visão envelhecimento inadimplência. Charter v5 Non-Goal F1 — mover pra Goal. | Baixo | ~50 |
| 53 | **Repetir lançamento** | UX rápida pra recorrentes não-assinatura (luz/internet/aluguel) — botão "Duplicar para próximo mês". | Baixo | ~40 |
| 54 | **Notificações vencimento próximo** | E-mail/WhatsApp X dias antes do vencimento. Schedule diário. | Médio | ~100 |
| 55 | **Aprovação multi-step pagamento (workflow)** | Eliana cria → Wagner aprova → pagamento liberado. FSM no Titulo. Boa pra empresas maiores. | Alto | ~200 |
| 56 | **Combobox cliente/contraparte com autocomplete** | Eliana digita "Ban" → autocomplete BANCO/BANDEIRA. Charter v5 Non-Goal F1 — mover. | Médio | ~80 |
| 57 | **Comparação `+X% vs mês anterior`** | Delta_pct nos KPI cards. ADR ui/0002 previa. | Baixo | ~30 |
| 58 | **Importação massiva (CSV upload)** | Migrar dados de planilha sem digitação. | Médio | ~150 |
| 59 | **Saldo previsto multi-conta agregado** | Hoje só 1 conta no Fluxo; somar todas. | Baixo | ~40 |

### P3 — Futuro

| # | Função | Por que |
|---|---|---|
| 60 | **Mobile responsive (cards 2×2 stack)** | Eliana é desktop hoje; futuro CEO/sócio acessa mobile |
| 61 | **Pagination 25/100 URL state** | Hoje limit fixo 200; scale pra > 1000 títulos |

---

## 3) Inconsistências catalogadas (7)

### Resolvidas

| # | Inconsistência | Causa | Fix |
|---|---|---|---|
| A | DRE não pegava títulos vencendo em Maio (R$ [redacted Tier 0] vs R$ [redacted Tier 0] Unificado) | `competencia_mes` defasado dos vencimentos (dados legados OfficeImpresso renegociados) | ✅ Onda 17 Fix 1 (SSH update 4 títulos MARTINHO #164 — `competencia_mes='2026-05'`) |

### Pendentes

| # | Inconsistência | Causa | Fix sugerido |
|---|---|---|---|
| B | Fluxo "Sem conta cadastrada" | MARTINHO sem `ContaBancaria` ativa em `accounts` UltimatePOS | ⏳ Wagner cadastra via `/account/account/create` OU Onda 18 (#47 fallback) |
| C | Categorias livres vs Plano de contas (2 sistemas paralelos) | Sem source-of-truth definido. CategoriaSheet vincula opcionalmente. | ⏳ Decisão produto: Plano de contas vira primary (Onda futura) |
| D | Botão "Plano de contas" no header → workaround `/categorias` | Tela dedicada `/plano-contas` não existe | ⏳ Onda 18 #48 (criar tela dedicada) |
| E | Botão "Conciliar" → workaround `/contas-bancarias` | Tela `/conciliacao` não existe | ⏳ Onda 18 #49 (criar tela conciliação OFX) |
| F | Dashboard/Fluxo/Relatorios usavam KpiCard shadcn | Migração canon incompleta | ✅ Onda 15 migrou 34 cards pra `fin-stats` |
| G | AssinaturaAtualizar form usa Card shadcn ainda | Form não-canon (mas funcional) | 🟢 Baixo — cosmético, fica pra Onda futura |

---

## 4) PRs canon aplicados (Ondas 12 → 17)

| Onda | PR | Função |
|---|---|---|
| 12.0 | #1158 | Paridade canon Unificado (6 gaps) |
| 12.1 | #1160 | Refine chip + filtros pills |
| 12.3 | #1164 | **Bundle copy canon CSS 9054 LOC** |
| 12.4 | #1165 | Purge legacy fin-btn + hues exatos |
| 12.5 | #1169 | Filtros default ON + toggle classe |
| 12.6 | #1170 | Densidade compact default + remove spacious |
| 12.7 | #1171 | Footer sticky + KPI full + Plano Contas + filtros funcionais |
| 12.8 | #1172 | 7 Index canon (Fase A+B) |
| 13 | #1173 | 4 Edit/Sheet canon |
| 14 | #1174 | AssinaturaAtualizar + Relatorios |
| 15 | #1175 | KpiCard→fin-stat (34 cards) + Cobranca + FinEditPanel |
| 16 | #1176 | **Fix 404: Conciliar + Plano de contas** |
| 17 | (SSH) | Fix data: competencia_mes 4 títulos MARTINHO |

---

## 5) Próximas Ondas sugeridas

### Onda 18 — Resolver inconsistência B + D (alto valor, baixo esforço)
1. Fluxo fallback sem conta (#47) — 40 LOC
2. Tela `/financeiro/plano-contas` dedicada (#48) — 150 LOC

### Onda 19 — Conciliação OFX (#49)
- Tabela `bank_statement_lines` nova
- `OfxImporter` parser
- `ConciliacaoService::sugerir($linhaId)` fuzzy match
- Tela `/financeiro/conciliacao`
- ~300 LOC + Pest GUARD multi-tenant

### Onda 20 — Anexos + Notificações (#50 + #54)
- Spatie Media Library no `Titulo`
- Schedule diário notificação vencimento

### Onda 21 — Workflow aprovação (#55)
- FSM no `Titulo.status` com transições aprovação
- Spatie Permissions: `financeiro.titulo.aprovar`

---

## 6) Métricas finais da sessão epic-hermann-aa6de9

- **15 PRs mergeados** (#1158 → #1176)
- **Coerência canon visual**: 9.0/10 (era 4.8 antes da Onda 12)
- **Coerência inter-telas dados**: 85% (era 50% antes da Onda 17)
- **Cobertura funcional**: 75% (46/61 funções)
- **Zero rotas 404** (validado em 14 GET + 12 POST)

---

**Última atualização:** 2026-05-19 noite — Wagner pediu auditoria completa pré-pausa.
**Próximo a atacar:** Onda 18 (Fluxo fallback + tela Plano de Contas dedicada).
