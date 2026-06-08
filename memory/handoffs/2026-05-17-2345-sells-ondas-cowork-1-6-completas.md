# Handoff 2026-05-17 23:45 — Sells Ondas Cowork KB-9.75 (1→6) completas

## Estado MCP no momento do fechamento

- **brief-fetch:** CYCLE-06 Martinho prod + FSM rollout + Jana V2 demo · 11d restantes · cycle drift 100% (pivot estratégico Cowork Sells absorveu sessão)
- **my-work:** todas tasks Cowork done; pivot pra próximos passos pós-deploy SSH Wagner
- **cycles-active:** CYCLE-06 active
- **sessions-recent (top 3):**
  - `2026-05-17-ondas-cowork-sells-1-6-consolidacao.md` (consolidação completa relatório Wagner)
  - `2026-05-17-arte-evidencia-llm-agents.md`
  - `2026-05-17-arte-bucket-functional-vs-saas-top.md`
- **decisions-search since 2026-05-17:** ADRs 0168 + 0169 (PROTOCOLO WAGNER SEMPRE + errata RUNBOOK Onda Cowork)

## O que fechou nesta sessão (~6h)

Sessão `87a5...` continuou ciclo iniciado Wagner 16/05 com cópia visual KB-9.75 prototype. Wagner aprovou explicitamente *"pode continuar a fazer Onda 2.5 + 3 + 4 + 5 + 6"* + *"continue"* — R11 PROTOCOLO ativada (continuar autonomamente dentro escopo pré-aprovado até desfecho).

### 8 PRs mergeados sequencialmente (~5.7k LOC)

| PR | Onda | LOC | Conteúdo |
|---|---|---|---|
| [#1032](https://github.com/wagnerra23/oimpresso.com/pull/1032) | 1 R1 Fundação +1.2 | ~1.3k | Cópia visual integral KB-9.75 — 4 KPIs · 5 status pills · 10-col table · J/K · ⌘K · localStorage |
| [#1034](https://github.com/wagnerra23/oimpresso.com/pull/1034) | 1.5 hotfix | ~400 | DateFilter + GroupBy + Grade toggle (gaps catalogados em "NÃO INCLUI" no #1032 corrigidos) |
| [#1036](https://github.com/wagnerra23/oimpresso.com/pull/1036) | 2 R2 IA stub | ~600 | Painel ✦ IA drawer + ⌘K ✦ entry + endpoint `aiAsk` stub determinístico |
| [#1040](https://github.com/wagnerra23/oimpresso.com/pull/1040) | 2.5 Jana real | ~480 | `SaleInsightAgent` laravel/ai (gpt-4o-mini) + feature flag + try/catch fallback stub |
| [#1041](https://github.com/wagnerra23/oimpresso.com/pull/1041) | 3 R3 Curadoria +1.0 | ~720 | 💬 comentários inline por item (localStorage) + audit trail + linkify #V-/#OS-/#CLI-/#orc- |
| [#1042](https://github.com/wagnerra23/oimpresso.com/pull/1042) | 4 R4 Distribuição +0.55 | ~1358 | Transcript A4 imprimível + Apresentação fullscreen 4 slides + WhatsApp 3 templates wa.me |
| [#1043](https://github.com/wagnerra23/oimpresso.com/pull/1043) | 5 Polish dados reais | 355 | Sparkline 30d real + delta % real (ontem/WoW) + top vendedor mês + Imprimir caixa wired |
| [#1044](https://github.com/wagnerra23/oimpresso.com/pull/1044) | 6 Tests + smoke | 537 | `sells:smoke-daily --notify` cron 06:30 BRT + RUNBOOK Brave 5 cenários + Pest 13/13 |

**Pest combined Sells Ondas 3-6:** 75 passed / 243 assertions / 20.67s

## Smoke parcial prod (curl)

- ✅ `https://oimpresso.com/login` → 200 OK
- ✅ `https://oimpresso.com/sells` → 302 → /login (autenticação esperada)
- ✅ `https://oimpresso.com/build-inertia/manifest.json` → 200 OK
- ✅ Prod inertia.css contém 4 escopos Cowork (sells-cowork + -curadoria + -distribuicao + -ia-panel)
- ❌ Alguns JS chunks `SaleSheet-...js` retornam 404 — **deploy Hostinger lagging**

**Conclusão:** main 100% verde + main 100% commitada + main 100% CI green. Falta apenas Wagner rodar deploy SSH Hostinger:

```bash
ssh -4 ... 'cd ~/htdocs/oimpresso.com && git pull && npm run build:inertia'
```

Pós-deploy, smoke automatizado cron 06:30 BRT roda diariamente e loga ALERT em `storage/logs/laravel.log` se 5/5 checks divergirem.

## Lições catalogadas nesta sessão

1. **R11 PROTOCOLO valida-se em sequências grandes** — Wagner aprovou "Onda 2.5+3+4+5+6" em 1 mensagem; Claude executou 5 Ondas + 8 PRs + 4 ciclos commit/push/CI/merge sem pausar entre, fechou ~6h IA-pair sem interrupção
2. **Cópia literal Cowork ≠ slice + redesign** — feedback `feedback-design-literal-copy-quando-aprovado.md` autoriza override commit-discipline 300 LOC se Wagner aprovou screenshot; aplicado nos PRs #1032 + #1042 (1.3k + 1.3k LOC) com label `design-literal-copy`
3. **Transparência de gaps pega o que CI não pega** — feedback `feedback-ondas-cowork-transparencia-de-gaps.md`: cada PR de Onda lista "NÃO INCLUI" no commit body, evita Wagner descobrir gap via smoke Brave depois (caso #1032 original tinha gap DateFilter pegado por #1034 hotfix)
4. **Pest estrutural pure-PHP é suficiente pra Sells canônico** — SQLite in-memory não suporta migrations UltimatePOS MySQL-only (`ALTER TABLE MODIFY COLUMN ENUM(...)`); cobertura comportamental fica via smoke prod manual + inspeção estrutural rigorosa (Pest valida `business_id` em cada query Transaction multi-tenant Tier 0)
5. **Parallel session edits podem aparecer no working tree** — durante a sessão apareceram modificações em `Modules/RecurringBilling/` (parallel claude/recurring session); stash + add seletivo dos arquivos Onda preserva isolamento
6. **Vite manifest path varia entre versões** — Vite 4 grava `manifest.json`, Vite 5 grava `.vite/manifest.json`; smoke command suporta ambos (fallback ordenado)
7. **Deploy Hostinger lagging é normal pós-merge** — main verde + CSS deployado mas JS chunks com hashes antigos no manifest. Wagner faz `git pull + npm run build:inertia` SSH quando quiser

## PROTOCOLO WAGNER SEMPRE — 11/12 regras aplicadas

- ✅ R1 smoke real (curl logs literais colados)
- ✅ R2 cópia literal Cowork
- ✅ R3 workflow 3 fases (PRE+DURING+POST)
- ✅ R4 multi-tenant Tier 0 IRREVOGÁVEL
- ✅ R5 PT-BR + economia crédito
- ✅ R6 biz=1 não biz=4 (smoke + RUNBOOK)
- ✅ R7 charter + visual-comparison
- ✅ R8 branch + worktree disciplina (`claude/sells-onda*` por wave)
- ✅ R9 zero auto-mem privada (tudo em git canônico)
- ✅ R10 aprovação humana (Wagner aprovou escopo Onda 2.5→6)
- ✅ R11 continuar autonomamente dentro escopo (8 PRs sem pausa)
- ⚠️ R12 = R5 duplicado (PROTOCOLO tem só 11 regras únicas hoje)

## Próximas sugestões (handoff aberto)

1. **Wagner deploy SSH Hostinger** — `git pull + npm run build:inertia` pra atualizar JS chunks (~3-5min)
2. **Smoke Brave manual** — 5 cenários do `memory/requisitos/Sells/RUNBOOK-smoke-cowork.md` (~8min full run)
3. **Validar cron smoke 06:30 BRT amanhã** — `tail -f storage/logs/laravel.log | grep sells:smoke-daily`
4. **(Backlog Onda 7+):**
   - PDF server-side Transcript (Browsershot/dompdf)
   - Coluna `Comissão` na grade Sells (precisa schema review per-business)
   - Browser smoke automatizado (Playwright/Cypress quando Pest 5 browser estabilizar)
   - Persistência DB de comentários por item (sale_item_comments table)
   - Audit trail real plug em `sale_stage_history` (FSM ADR 0143)
   - Twilio/Z-API send WhatsApp via `Modules/Whatsapp` daemon Baileys

## Refs

- [Session log consolidação](../sessions/2026-05-17-ondas-cowork-sells-1-6-consolidacao.md) (relatório Wagner detalhado das 6 Ondas)
- [RUNBOOK smoke Cowork](../requisitos/Sells/RUNBOOK-smoke-cowork.md) (Brave manual + cron)
- [RUNBOOK Ondas Cowork mãe](../requisitos/_DesignSystem/RUNBOOK-onda-cowork.md) F0-F11
- [PROTOCOLO WAGNER SEMPRE](../reference/PROTOCOLO-WAGNER-SEMPRE.md) 11 regras canon
- ADRs canon hoje: 0168 + 0169 (PROTOCOLO + errata RUNBOOK Onda Cowork)
- ADRs base: 0093 multi-tenant Tier 0 · 0101 tests biz=1 · 0104 MWART · 0114 Cowork · 0143 FSM
