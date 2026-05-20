# 2026-05-20 08:30 — Mega-sessão Financeiro: comparativo + 5 ondas mergeadas + hotfix prod

**Sessão:** continuação 2026-05-19 → 2026-05-20 (~8h reais, 10 PRs)
**Branch trabalho:** worktree `jovial-goldwasser-4215f8` (várias branches efêmeras)
**Status:** 5 ondas Capterra Financeiro entregues em prod · hotfix tela branca validado · backlog Ondas 24/25/27/28/29 em série pendente

## TL;DR

Pedido inicial "podecontinuar o financeiro? compare" virou: rodada `/comparativo Financeiro` (PR #1182) → backlog 11 US-FIN-026..036 + 1 advisor 037 → 5 ondas implementadas (Onda 22 anexos+pill+permission + Onda 26 Inter webhook + Onda 30 PWA + Onda 31 Advisor portal + Onda 23 OCR boleto) + hotfix #1192 tela branca prod causada por Onda 22.

**Score Capterra Financeiro:** 66 → **74/100** (+8pp em 25 dias). Próximo target: 82+ via Ondas 24 (aging) + 25 (bulk) + 27 (notif) + 28 (CSV) + 29 (repetir+combobox).

## Estado MCP no momento do fechamento

- `cycles-active`: CYCLE-06 (Martinho prod + FSM rollout + Jana V2) — 8d restantes
- **Cycle drift detectado:** 46/46 commits 7d off-cycle. Sessão foi pivot estratégico Capterra Financeiro (Wagner aprovou explicitamente "Tudo P0+P1+P2 Ondas 22-30").
- HITL pending Wagner: 6 (top: proposta comercial Gold + upgrade on-prem)
- `decisions-search since:2026-05-19`: ADRs canon intactas
- `sessions-recent limit:3`: handoff 2026-05-20 01:12 (Ondas 12-21) + 2026-05-19 18:05 (F3 PaymentGateway)

## 10 PRs mergeados

| # | PR | Onda / US | Foco | LOC |
|---|---|---|---|---|
| 1 | [#1182](https://github.com/wagnerra23/oimpresso.com/pull/1182) | — | docs CAPTERRA-INVENTARIO + backlog Ondas 22-30 (US-FIN-026..036) | ~479 docs |
| 2 | [#1184](https://github.com/wagnerra23/oimpresso.com/pull/1184) | 22 P0 | US-FIN-026 anexos GET+download+delete + US-FIN-027 pill aprovacao_status + US-FIN-028 Spatie permission + 11 Pest | ~280 |
| 3 | [#1186](https://github.com/wagnerra23/oimpresso.com/pull/1186) | 26 P1 | US-FIN-032 Inter webhook PIX → titulo auto-pago + 13 Pest | ~840 |
| 4 | [#1187](https://github.com/wagnerra23/oimpresso.com/pull/1187) | 30 P2 | US-FIN-036 PWA básico (manifest+sw+offline+install) | ~370 |
| 5 | [#1188](https://github.com/wagnerra23/oimpresso.com/pull/1188) | 31 P2 | US-FIN-037 Portal Advisor Contadores Fase 1 MVP + guard custom + 10 Pest | ~960 |
| 6 | [#1191](https://github.com/wagnerra23/oimpresso.com/pull/1191) | 23 P1 KILLER | US-FIN-029 OCR boleto OpenAI Vision API | ~300 |
| 7 | [#1192](https://github.com/wagnerra23/oimpresso.com/pull/1192) | hotfix | tela branca prod — auth.can é objeto não array | ~9 |

**Total: 7 PRs mergeados + 3 outros PRs paralelos Fiscal (#1183/1185/1190) que conviveram sem conflito.**

## Decisões produto Wagner aprovou nesta sessão

- **DC1**: Categorias livres + Plano de Contas ficam PARALELOS (coexistência, vínculo opcional) — nada a codar
- **DC2**: Customer service network advisor APROVADA → US-FIN-037 Fase 1 MVP entregue

## Funções novas em prod (resumo)

- ✅ UI lista anexos GET + thumbnail PDF + delete + download stream signed (Onda 22 US-FIN-026)
- ✅ Pill `aprovacao_status` (pendente/aprovado/rejeitado) na tabela Unificado + filtro chips workflow (Onda 22 US-FIN-027)
- ✅ Spatie permission `financeiro.titulo.aprovar` + UI gate `canApprove` (Onda 22 US-FIN-028)
- ✅ Inter webhook PIX recebido → Cobranca paga + listener Onda 5 cria Titulo+TituloBaixa idempotente (Onda 26 US-FIN-032)
- ✅ PWA `/financeiro/*` instalável no celular Larissa biz=4 (Onda 30 US-FIN-036)
- ✅ Portal Advisor Contadores `/advisor` com guard `web-advisor` isolado + middleware AdvisorViewScope readonly enforce + LGPD consent (Onda 31 US-FIN-037)
- ✅ OCR boleto upload via OpenAI Vision API extrai linha digitável + valor + vencimento (Onda 23 US-FIN-029)

## Pegadinhas catalogadas (lições pro time)

1. **`auth.can` é OBJETO `Record<string, boolean>`, não array** — `HandleInertiaRequests::userPermissions()` retorna assoc array. `userCanList.includes(...)` em objeto crasha com `TypeError: N.includes is not a function` → tela branca pra TODOS users. Defesa: `Array.isArray(can) ? can.includes(k) : can[k] === true`. [Hotfix #1192]
2. **Build Inertia falha no Hostinger** — `rayon-core` thread pool não inicializa em shared hosting (`ThreadPoolBuildError IOError WouldBlock`). Workaround: build local + SCP `public/build-inertia/*` pro Hostinger. **Follow-up canônico:** mover build pra GitHub Actions CD.
3. **Docstring com `***/` quebra PHP parse** — Agent C escreveu `Ex: 12.345.***/0001-**` em docblock, PHP interpretou `***/` como close de comment, gerou parse error "unexpected integer '0001'". Sempre reescrever exemplos LGPD masking sem o trigger `*/`.
4. **3 agents paralelos salvaram em worktree filha** — `Write/Edit` com path absoluto respeitou cwd do batch da Bash, mas o repo principal `D:/oimpresso.com/` não tinha. Solução: `find` recursivo + git ops dentro da worktree.
5. **Shell cwd reset bug** — `cd D:/oimpresso.com && ...` voltava CWD pra worktree filha entre comandos. Causou commit acidental em branch alheia `claude/fiscal-cockpit-nfe-pr1` (PR #1183 sessão paralela). Cherry-pick + force-with-lease + reset --hard recuperou estado correto sem contaminar PR alheio.
6. **Scope-guard drift pré-existente** — controllers já em prod sem entry em SCOPE.md (`ConciliacaoController`, `PlanoContaController`). Fix-forward em cada PR que tocou módulo. Sessão paralela Fiscal aplicou fix idêntico no mesmo arquivo — merge resolveu convergentemente.
7. **Case-sensitive Windows bug** — `Nfe-visual-comparison.md` vs `nfe-visual-comparison.md` no git index causou loops de rebase abortado. Workaround: `git rm --cached` uppercase entry.

## Pendentes pós-sessão

- **Ondas 24/25/27/28/29 sequenciais** (tocam `Unificado/Index.tsx` ou `UnificadoController` — não paralelizáveis): Aging buckets / Bulk actions / Notificações vencimento / Importação CSV / Repetir+Combobox
- **Charters + visual-comparison.md** de `Unificado/Index.tsx` (drift MWART soft) — fica pra batch dedicado
- **Memory schema gates** (168 ADR + 12 Handoff + 50 Session warnings grace period) — fix em batch dedicado
- **Build Inertia em CI/CD** ao invés de SSH live (follow-up Onda 32+)
- **Sidebar entry pra `/financeiro/configuracoes/contador`** (Advisor — TODO Onda 32)
- **Email convite + reset senha advisor + throttle login** (Advisor Fase 2)

## Próximas ondas serial

| Onda | US | LOC est | Score Δ |
|---|---|---|---|
| 24 | US-FIN-030 Aging buckets <30/30-60/60-90/90+ | ~50 | +1pp |
| 25 | US-FIN-031 Bulk actions (checkbox + select-all + 5 ações) | ~200 | +1pp |
| 27 | US-FIN-033 Notificações vencimento (email + WhatsApp) | ~100 | +0.5pp |
| 28 | US-FIN-034 Importação CSV mapping wizard | ~150 | +0.5pp |
| 29 | US-FIN-035 Repetir lançamento + Combobox autocomplete | ~120 | +1pp |

**Target pós-Onda 29:** Score 74 → **78/100**. Gap restante pra Conta Azul (85): OCR (já feito Onda 23) + mobile (já PWA Onda 30) + customer service network (advisor já Fase 1 Onda 31). Próximo gap real: bulk actions + aging detalhado.

## Refs

- [CAPTERRA-INVENTARIO.md](../requisitos/Financeiro/CAPTERRA-INVENTARIO.md) — score 74/100 + buckets ✅🟡❌
- [SPEC.md](../requisitos/Financeiro/SPEC.md) — US-FIN-026..037
- ADR 0089 (Capterra-driven) · ADR 0093 (multi-tenant) · ADR 0101 (biz=1 tests) · ADR 0143 (FSM Pipeline) · ADR 0170 (PaymentGateway)
