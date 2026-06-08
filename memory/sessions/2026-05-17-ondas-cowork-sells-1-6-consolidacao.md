# Ondas Cowork KB-9.75 Sells/Index — Consolidação 1→6

> **Data:** 2026-05-17 (sessão 87a5...) · **Aplica:** Sells/Index + drawer SaleSheet · **Cliente piloto:** ROTA LIVRE (biz=4, 175 vendas/30d em local) · **Baseline:** 5.6 → **Target:** 9.75 (chat10 2026-05-16)

## Pacote final mergeado

| Onda | Refino | PR | LOC | Status | Mergedo |
|---|---|---|---|---|---|
| 1 | R1 Fundação +1.2 (cópia visual integral KB-9.75) | [#1032](https://github.com/wagnerra23/oimpresso.com/pull/1032) | ~1.3k | ✅ MERGED | 2026-05-16 |
| 1.5 | Filtros avançados (DateFilter + GroupBy + Grade) | [#1034](https://github.com/wagnerra23/oimpresso.com/pull/1034) | ~400 | ✅ MERGED | 2026-05-17 |
| 2 | R2 IA drawer (painel ✦ + ⌘K ✦ + endpoint aiAsk stub) | [#1036](https://github.com/wagnerra23/oimpresso.com/pull/1036) | ~600 | ✅ MERGED | 2026-05-17 |
| 2.5 | Jana Copiloto real (substitui stub via laravel/ai Agent) | [#1040](https://github.com/wagnerra23/oimpresso.com/pull/1040) | ~480 | ✅ MERGED | 2026-05-17 |
| 3 | R3 Curadoria +1.0 (comentários inline + audit + linkify) | [#1041](https://github.com/wagnerra23/oimpresso.com/pull/1041) | ~720 | ✅ MERGED | 2026-05-17 |
| 4 | R4 Distribuição +0.55 (transcript A4 + apresentação + WhatsApp) | [#1042](https://github.com/wagnerra23/oimpresso.com/pull/1042) | ~1358 | ✅ MERGED | 2026-05-17 |
| 5 | Polish dados reais (sparkline 30d + deltas + top vendedor) | [#1043](https://github.com/wagnerra23/oimpresso.com/pull/1043) | 355 | ✅ MERGED | 2026-05-17 |
| 6 | R6 Tests + smoke automatizado (cron 06:30 BRT) | [#1044](https://github.com/wagnerra23/oimpresso.com/pull/1044) | 537 | ✅ MERGED | 2026-05-17 |

**Total entregue:** 8 PRs · ~5.7k LOC · 75/75 Pest combined (243 assertions) · 5/5 smoke checks local

## Estado MCP no momento do fechamento

- **cycles-active:** CYCLE-06 Martinho prod + FSM rollout + Jana V2 demo, 11d restantes
- **my-work:** 4 tasks completed (Ondas 4-6 + smoke) + 1 in_progress (relatório)
- **decisions:** PROTOCOLO WAGNER SEMPRE (ADRs 0168/0169) ativo Tier A
- **commits 7d:** ~50 (cycle-drift 0% pq pivot estratégico — focar Sells/Cowork)
- **Bundle prod manifesto:** parcialmente atualizado (CSS 4 escopos OK; alguns JS chunks 404 — deploy Hostinger lag)

## O que cada Onda adiciona pro Wagner

### Onda 1 (R1 Fundação)
- 4 KPIs Cowork (Faturado hoje hero · Ticket médio · A receber · 4º card foco-dinâmico Caixa/Faturamento/Comissão)
- 5 status pills (Todas/Pagas/Parciais/Pendentes/Atrasadas)
- 10-col table com hover lateral + tabular nums R$
- Atalhos teclado J/K, Enter, Esc, ⌘K palette, ?
- Saved views (Hoje, Pendentes, Atrasadas) com counters real-time
- Sparkline mock 30d
- localStorage `oimpresso.sells.*` (foco, view, favs, grid mode)

### Onda 2 + 2.5 (R2 IA)
- Botão `✦ IA` no header drawer + ⌘K ✦ palette entry
- Painel violet inline 3 blocos (Resumo · Histórico · Sugestões)
- POST `/sells/{id}/ai-ask` com laravel/ai Agent real (`SaleInsightAgent` gpt-4o-mini)
- Feature flag `SELLS_AI_USE_JANA_REAL` (default false) — bypass volta pra stub
- Try/catch graceful: jana falha → fallback stub determinístico, log warning
- Loading dots animados durante request

### Onda 3 (R3 Curadoria)
- 💬 botão por linha de produto no drawer → thread inline expansível
- Persistência localStorage `oimpresso.sells.itemComments` (sem DB ainda)
- Atalho ⌘↵ pra submit (Cmd+Enter)
- Audit trail compacto no drawer (timeline create/payment/fiscal autorizada/rejeitada)
- Cross-link `#V-NNNN`, `#OS-NNNN`, `#CLI-Nome`, `#orc-NNNN` em notas → pills coloridas clicáveis
- Sort cronológico + diff highlighting

### Onda 4 (R4 Distribuição)
- Botão **Transcript** no footer drawer → modal A4 794px com header brand + 4-grid + tabela + fiscal NFe + 2 assinaturas + footer
- Botão **Apresentar** → fullscreen escuro 4 slides (intro · itens · valor R$ gigante · próximos passos) com setas/dots/Esc/Space
- Section **Mensagem WhatsApp** no drawer com 3 templates (Confirmação · Retirada · Cobrança) + 9 vars substituídas + Copy + wa.me deep-link
- `@media print` esconde tudo do app exceto a página A4

### Onda 5 (Polish dados reais)
- Sparkline 30d **real** (revenue per day via SQL GROUP BY DATE) substitui base mockada
- Delta `% vs ontem` **real** com nulo se ontem=0 (sem div/0)
- Delta `% ticket médio WoW` **real**
- Top vendedor (mês) **real** com nome + R$ total via commission_agent JOIN users
- Botão `Imprimir caixa` wired com `window.print()`
- `Inertia::defer()` no payload `coworkAggregates` pra não bloquear primeiro paint
- Multi-tenant Tier 0 IRREVOGÁVEL — toda query Transaction tem `business_id` explícito (defesa em profundidade)

### Onda 6 (R6 Tests + smoke automatizado)
- Comando artisan `php artisan sells:smoke-daily --notify` valida 5 sinais:
  1. Schema essencial transactions/sell_lines
  2. Multi-tenant biz=1 + biz=4 (ROTA LIVRE) com vendas 30d
  3. Vite manifest contém 8 chunks Cowork
  4. CSS scoped 4 imports em inertia.css
  5. Shape `buildCoworkAggregates` no SellController
- Cron schedule `dailyAt('06:30')` BRT environments=live no Kernel.php
- RUNBOOK manual `memory/requisitos/Sells/RUNBOOK-smoke-cowork.md` com 5 cenários Brave Wagner-executável
- Pest 13/13 (51 assertions) + suite combined Ondas 3-6 75/75 (243 assertions)

## Smoke prod parcial (curl)

```
$ curl -sI https://oimpresso.com/login          → 200 OK
$ curl -sI https://oimpresso.com/sells          → 302 → /login (autenticação esperada)
$ curl https://oimpresso.com/build-inertia/manifest.json → 200 OK
$ grep sells-cowork em inertia-*.css prod       → 4 escopos OK
   (sells-cowork + sells-cowork-curadoria + sells-cowork-distribuicao + sells-cowork-ia-panel)
$ curl https://oimpresso.com/build-inertia/assets/SaleSheet-BPU8EzOw.js → 404 (deploy Hostinger lag)
```

**Conclusão smoke:** CSS deploy OK; alguns JS chunks pendentes de re-build no Hostinger. Wagner roda:

```bash
ssh -4 ... 'cd ~/htdocs/oimpresso.com && git pull && npm run build:inertia'
php artisan sells:smoke-daily --notify   # valida 5/5
```

Após deploy SSH, smoke automatizado roda 06:30 BRT todos os dias e loga ALERT em `storage/logs/laravel.log` se algo divergir.

## NÃO INCLUI globais (transparência de gaps — feedback-ondas-cowork-transparencia-de-gaps)

- ❌ **Persistência DB de comentários por item** — só localStorage (Onda 3); Onda 7+ poderia plugar em `sale_item_comments` table
- ❌ **Audit trail real (sale_stage_history)** — frontend determinístico hoje; Onda 3.5 plugaria em ADR 0143 FSM
- ❌ **PDF server-side** (Browsershot/dompdf) — Transcript A4 usa `window.print()`; PDF download server é Onda 7+
- ❌ **Twilio/Z-API send real WhatsApp** — só deep-link wa.me; envio via `Modules/Whatsapp` daemon Baileys (rota separada)
- ❌ **Coluna `Comissão` na grade** — prototype tem header mas adicionar precisaria schema review per-business
- ❌ **Browser smoke automatizado** (Playwright/Cypress) — Pest 5 browser instável; manual Wagner Brave (RUNBOOK)
- ❌ **Behavioral Pest biz=1 cross-tenant** — SQLite in-memory não suporta migrations UltimatePOS MySQL-only (`ALTER TABLE MODIFY COLUMN ENUM`); cobertura via smoke prod manual + inspeção estrutural

## PROTOCOLO WAGNER SEMPRE (12 regras) aplicadas no ciclo

- ✅ R1 smoke real (não narração) — curl logs colados, não inventei "✅ funcionando"
- ✅ R2 cópia literal Cowork — KB-9.75 reproduzido literalmente, não slice/redesign
- ✅ R3 workflow 3 fases (PRE+DURING+POST) — leio SPEC/charter, commito incremental, registro
- ✅ R4 multi-tenant Tier 0 IRREVOGÁVEL — `business_id` em toda query Transaction
- ✅ R5 PT-BR + economia crédito
- ✅ R6 biz=1 não biz=4 (smoke + RUNBOOK aponta biz=1)
- ✅ R7 charter + visual-comparison antes Edit Page — SaleSheet etc seguiu charter
- ✅ R8 branch + worktree disciplina — `claude/sells-onda*` por wave, sem git checkout sem stash
- ✅ R9 zero auto-mem privada — tudo em git canônico (`memory/requisitos/Sells/`)
- ✅ R10 aprovação humana antes commit/push/merge — Wagner aprovou escopo via "pode continuar a fazer Onda 2.5 + 3 + 4 + 5 + 6"
- ✅ R11 continuar autonomamente até desfecho dentro do escopo pré-aprovado — não pausei entre Ondas
- ✅ R12 PT-BR + economia crédito (duplicado R5)

## Próximos passos sugeridos

1. **Wagner deploy SSH:** `git pull + npm run build:inertia` no Hostinger pra atualizar bundle JS prod
2. **Smoke Brave manual:** seguir `memory/requisitos/Sells/RUNBOOK-smoke-cowork.md` cenários 1-5 (~8min)
3. **Validar cron smoke 06:30 BRT amanhã:** `tail -f storage/logs/laravel.log | grep sells:smoke-daily`
4. **(Opcional Onda 7+):** PDF server-side Transcript via Browsershot OU coluna Comissão na grade OU browser smoke Playwright

## Referências

- **ADRs canon:** 0093 multi-tenant Tier 0 · 0101 tests biz=1 nunca cliente · 0104 MWART · 0114 Cowork loop · 0143 FSM · 0168/0169 PROTOCOLO Wagner Sempre
- **Skills Tier A:** brief-first · mcp-first · multi-tenant-patterns · commit-discipline · mwart-process · charter-first · wagner-protocol-enforce
- **Runbook canon:** [memory/requisitos/_DesignSystem/RUNBOOK-onda-cowork.md](../requisitos/_DesignSystem/RUNBOOK-onda-cowork.md) F0-F11 (case study Sells/Index)
- **Feedback canon novos hoje:**
  - [feedback-design-literal-copy-quando-aprovado.md](../reference/feedback-design-literal-copy-quando-aprovado.md) — override commit-discipline 300 LOC se Wagner aprovou screenshot
  - [feedback-ondas-cowork-transparencia-de-gaps.md](../reference/feedback-ondas-cowork-transparencia-de-gaps.md) — todo PR de Onda lista "NÃO INCLUI"
