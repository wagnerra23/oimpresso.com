---
slug: boletos-visual-comparison
title: "Financeiro — Comparativo visual da tela Boletos (refator Cockpit V2)"
type: visual-comparison
module: Financeiro
status: pending_wagner_decisions
date: 2026-05-14
canon_reference: prototipo-ui/prototipos/boletos/cowork-app.jsx (Cowork F1 export 2026-05-14, "Boleto e Contas Inter")
inertia_target_atual: resources/js/Pages/Financeiro/Boletos/Index.tsx (já em prod, 175 linhas)
controller_atual: Modules/Financeiro/Http/Controllers/BoletoController.php (já em prod, 71 linhas — refator mínimo)
stories: US-BOL-XXX (a criar)
related_adrs: [ui/0114, 0093]
---

# Comparativo visual — Financeiro · Boletos (refator)

> **Tipo de tela:** dashboard cobrança (funil + KPIs + tabela rica + drawer detalhe)
> **Persona alvo:** Eliana [E] — financeiro escritório. Desktop ≥1024px.
> **Refs:**
> - Tela atual em prod: [`resources/js/Pages/Financeiro/Boletos/Index.tsx`](../../../resources/js/Pages/Financeiro/Boletos/Index.tsx) (Inertia, 175 linhas, sem charter)
> - Canon Cockpit: [`prototipo-ui/prototipos/boletos/cowork-app.jsx`](../../../prototipo-ui/prototipos/boletos/cowork-app.jsx) — F1 export Claude Design 2026-05-14
> - ADRs: [ui/0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md), [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)

## Resumo executivo

Eliana hoje vê **tabela simples** de boletos com filtros básicos por status. O protótipo Claude Design entrega **dashboard de cobrança** completo: funil de 5 etapas (Em aberto → Lembrete → Cobrança ativa → Vencidos +5d → Protesto) + 3 KPIs (Pago no mês, Vencido, Próxima janela) + tabela rica com chip-banco visual + drawer detalhe com timeline cronológica.

Backend reaproveita `BoletoController` atual; refator mínimo pra incluir `titulo.contaBancaria` no shape + agregados pros KPIs.

## Tabela comparativa — 8 dimensões

### 1. Layout

| Aspecto | Atual `Boletos/Index.tsx` | Canon Cockpit (`cowork-app.jsx`) | Decisão MWART |
|---|---|---|---|
| Header | h1 "Boletos Emitidos" simples + descrição | Header rico: title "Boletos" + breadcrumb "Financeiro · Boletos emitidos via Inter API" + indicador "14 ativos · sync 09:14" + 2 botões (Remessa/Retorno + Emitir boleto) | Header Cockpit V2 — `PageHeader` + actions secundárias |
| Body grid | tabela full-width única | Funil de 5 steps + 3 KPIs + filter bar + tabela | Funil + 3 KPI Card + filter bar + tabela densa |
| Sidebar | AppShellV2 default | AppShellV2 default | Inalterado |

### 2. Hierarquia visual

| Aspecto | Atual | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Ação primária | nenhuma (read-only) | "Emitir boleto" (orange primary) | **F1: omitir** — emitir hoje vai por /financeiro/contas-receber → emitir (sheet entra em US-BOL-XXX) |
| Ação secundária | nenhuma | "Remessa/Retorno" (outline) | **F1: omitir** — CNAB upload entra Onda 2 com `CnabDirectStrategy` real |
| Funil cobrança | ❌ ausente | 5 steps com qtd + valor | ✅ implementar (UI-only F1; derivar de `status` existente) |
| KPIs | ❌ ausentes | 3 cards (Pago mês / Vencido / Próxima janela) | ✅ implementar — Pago mês + Vencido derivam de remessas[]; "Próxima janela" stub texto F1 |

### 3. Densidade

| Aspecto | Atual | Canon | Decisão |
|---|---|---|---|
| Tabela rows | `text-sm` + padding `py-3` | `text-[12.5px]` + padding `py-2.5` denso | Adotar denso `text-[12.5px]` |
| Tabular nums | parcial | obrigatório em valores monetários e datas | Aplicar `tabular-nums` em tudo |
| Banco visual | apenas texto `strategy` | chip cor + sigla curta ("Inter", "Itaú", "BB") | Adicionar `chip banco` derivando de `titulo.contaBancaria.banco_codigo` |

### 4. Cor e semântica

| Aspecto | Decisão MWART |
|---|---|
| Status `gerado_mock` | purple |
| Status `gerado`/`enviado`/`registrado` | blue/cyan (atual já cobre) |
| Status `pago` | emerald |
| Status `vencido` | rose |
| Status `cancelado` | muted |
| Dias atraso visual | `text-rose-600` abaixo do vencimento quando overdue ≥1d |
| Chip banco cor | `bg-orange-500` (Inter `077`), `bg-yellow-400` (BB `001`), `bg-orange-700` (Itaú `341`), etc — mapping fixo |

### 5. Interação / atalhos

| Aspecto | Atual | Canon | Decisão F1 |
|---|---|---|---|
| Click linha | nada | abre drawer detalhe lateral | ✅ implementar drawer simplificado (informações principais + ações; SEM timeline rica F1) |
| Hover linha | `hover:bg-muted/30` | `hover:bg-stone-50/60` | Trocar |
| Ações inline | Copiar + Cancelar | Copiar + Baixar PDF + Mais ações | **F1: manter Copiar + Cancelar** — PDF download depende de strategy real (Onda 2) |
| Filtros | 5 botões status | tabs `bg-stone-100` segmented + dropdown conta + busca | Adotar tabs segmented + dropdown conta (se >1 ContaBancaria) + busca |

### 6. Estado vazio / loading

| Aspecto | Decisão |
|---|---|
| Sem boletos | Manter mensagem atual "Nenhum boleto emitido" mas centralizado |
| Loading | SSR padrão, sem skeleton F1 |

### 7. Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

| Aspecto | Atual | Decisão |
|---|---|---|
| Tenant scope | `business_id = session('business.id')` ✅ | Manter |
| `whereNull('deleted_at')` | ✅ presente | Manter |
| Eager loading | `with(['titulo:...'])` | Adicionar `titulo.contaBancaria.account:id,name` pra chip banco |
| Pest cross-tenant | ❌ ausente | Criar Pest GUARD (biz=1 não vê biz=99) |

### 8. Performance

| Aspecto | Decisão |
|---|---|
| Limit | `limit(100)` atual mantido — Eliana raramente passa 50 boletos abertos |
| Eager loading | adicionar `contaBancaria.account` (1 join extra) |
| Cache | F1: sem cache; F2: Redis 5min se latência ≥300ms |
| p95 target | <250ms render |

---

## §F1.5 Critique — score esperado

**Score: 82 / 100** estimado (refator visual sólido, mas Sheets emitir/remessa ficam de fora cortando 10pts).

Pontos perdidos:
- **−8** Sheet "Emitir boleto multi-título" omitido (vai pra US-BOL-XXX backlog)
- **−6** Sheet "Remessa/Retorno" CNAB omitido (Onda 2)
- **−4** Drawer timeline rica simplificada (F1 = drawer básico)

**Aprovado pra F3 com gate ≥80.**

---

## §Decisões abertas pro Wagner (BLOQUEIA F3)

### Q1 — Funil de cobrança 5 etapas (Em aberto → Lembrete → Cobrança ativa → Vencidos +5d → Protesto)

Implementar **UI-only F1** OU **backend-driven**?

**Recomendação:** ✅ **UI-only F1** — derivar quantidades de `status` existentes (`open` = registrado+enviado; `vencido` = aging>0; "Lembrete"/"Cobrança ativa"/"Protesto" ficam com label "—" ou contagens derivadas de regras simples like `vencimento < hoje + 3d`). Backend job pra lembretes/cobranças/protestos automáticos entra em **CYCLE-XXX cobrança automática** (Onda 2).

**Razão:** entrega valor visual hoje sem schema novo, sem job novo, sem orchestrator de cobrança. Eliana vê funil + Wagner sabe que jobs entram depois.

### Q2 — Sheet "Emitir boleto multi-título" no F1?

**Recomendação:** ❌ **Pular F1** — entra em US-BOL-XXX separada. Hoje emissão acontece em `/financeiro/contas-receber` → ação inline "Emitir boleto" no título. Sheet bulk entra quando ficar dor real (>10 boletos/dia).

**Razão:** escopo creep — sheet exige backend novo (`BoletoBatchService` + transação), validação multi-conta, error handling parcial.

### Q3 — Sheet "Remessa/Retorno" CNAB upload no F1?

**Recomendação:** ❌ **Pular F1** — depende `CnabDirectStrategy` em prod real (hoje é mock). Entra em **Onda 2** com BoletoService refator.

**Razão:** CNAB ainda é mock em prod. Adicionar UI sem backend = botão NO-OP (anti-pattern T-AP-13 do LICOES).

### Q4 — Tela "Contas & Caixa" (no protótipo vem junto)

Protótipo tem 2 telas no mesmo arquivo: `/boletos` + `/contas`. A `/contas` mostra cards das contas bancárias com saldo, última movimentação, qtd boletos abertos.

**Recomendação:** ❌ **Não incluir** — `/financeiro/contas-bancarias` já existe em prod com CRUD básico. Tela "Contas & Caixa" do protótipo é refator visual da `contas-bancarias` — escopo separado (US-FIN-XXX-contas-caixa).

**Razão:** 1 PR = 1 intent (`commit-discipline` Tier A). Boletos refator é 1 intent; contas-caixa refator é outro.

### Q5 — Drawer detalhe com timeline cronológica completa F1?

Protótipo mostra timeline rica: "Boleto criado → Enviado Inter API → Pagamento confirmado webhook" com timestamps + atores.

**Recomendação:** 🟡 **Drawer simplificado F1** — só informações principais (vencimento, valor, nosso_número, linha digitável, status) + ações (Cancelar, Copiar). Timeline rica entra em F2 quando tiver dados reais (`activity_log` da `BoletoRemessa` via Spatie ActivityLog — já configurado mas precisa frontend).

**Razão:** timeline rica exige resolver `activity_log` payload, atores, traduções — mais 30-60min sem valor pra Larissa biz=4 (que não usa hoje).

---

## §Próxima ação após Wagner aprovar Q1-Q5

[CL] executa F3 em sequência (mesmo padrão Fluxo, ~2h estimadas):

1. `BoletoController::index()` refator — adicionar agregados KPIs (`pago_mes_valor/qtd`, `vencido_valor/qtd`, `aberto_valor/qtd`) + `titulo.contaBancaria.account` no eager load + `bancos[]` (mapping códigos→cor/short)
2. `Pages/Financeiro/Boletos/Index.tsx` refator — Funil + KPI grid + tabs segmented + dropdown conta + tabela rica + drawer simplificado
3. `Pages/Financeiro/Boletos/Index.charter.md` **criar** (não existe charter Tier A pro Boletos)
4. `Modules/Financeiro/Tests/Feature/BoletoControllerTest.php` **criar** — Tier 0 cross-tenant + props shape + funil counts
5. Pest cobertura: limit 100, status filter, KPI shape
6. Commit + PR + merge + deploy
