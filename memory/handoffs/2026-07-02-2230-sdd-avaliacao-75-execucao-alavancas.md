---
date: "2026-07-02"
slug: "sdd-avaliacao-75-execucao-alavancas"
tldr: "Reavaliação adversarial SDD 75/100 (run 2 do dia, pós-P14) + execução IMEDIATA das alavancas: anchor_coverage ARMADO (floor 55.2%), drift de 13d do script fullsuite morto (pcov já estava na imagem!), gate-selftest REQUIRED (23º), 3 casos de bookkeeping stale corrigidos (US-GOV-018/020/021 + 7 bugs do 019 já resolvidos), 5 unclear convertidos em dado no CT100. Nightly com coverage + P04 encadeada."
---

# Handoff — 2026-07-02 22:30 · Avaliação SDD 75/100 + execução das alavancas (sessão mystifying-fermat)

## O que esta sessão fez (ordem cronológica)

1. **`/sdd-avaliar` run 2 do dia** (`wf_ec10ba07-13f`, 8 agents, 1.07M tokens): **composto 75/100** (manhã tinha dado 67; P14 fechou o defeito nº 1 no meio). Scorecard completo apendado em [2026-07-01-sdd-avaliacao-adversarial.md](../sessions/2026-07-01-sdd-avaliacao-adversarial.md) (#3578 MERGED). Padrão residual dominante: **"medido, não armado"**.
2. **Wagner: "Pode fazer"** → execução do escopo inteiro (R11):
   - **anchor_coverage ARMADO** no GT-G3 (#3586 MERGED): floor 55.2%, valid 1→13 (12 medições commitadas dos batches P10 + live), dentes provados exit 0/exit 1. Fecha o risco sistêmico nº 1 (regressão agregada passava livre — gates required são diff-aware).
   - **"pcov nunca rodou" tinha diagnóstico enganoso**: pcov JÁ estava na imagem `oimpresso/mcp` desde 21/jun. O elo quebrado era a **cópia do `ct100-fullsuite.sh` no cron driftada desde 18/jun** (0 hits de coverage-clover — o passo manual "atualizar após merge" falhou 13 dias). Fix imediato no CT100 (cp + md5 conferido) + **#3587 MERGED**: self-update.sh agora sincroniza a cópia a cada 15min (mv atômico).
   - **Status-truth US-GOV-018/020/021** (#3589 MERGED): SPEC dizia review/doing, MCP (fonte durável, ADR 0144) tem as 3 **done desde 13-21/jun** — o "presas em review" que enganou DUAS avaliações era bookkeeping stale. Âncoras + Aceite + **contract test** `tests/fullsuiteHarness.spec.ts` (8 asserts derivados do DoD; o required anchor entry/covers mordeu o PR e foi atendido sem afrouxar).
3. **Wagner: "Merge é continue" + "tbm Promover gate-selftest, 273 aceito" + "6-8 tbm"**:
   - **gate-selftest REQUIRED** (#3591 MERGED + flip gh api): contexto `gate selftest (as catracas mordem · GT-G6)`, 23º required, baseline atualizado no mesmo PR. **Prova viva no mesmo minuto**: #3592 (pré-flip) bloqueou no "Expected" → update-branch + auto-merge. Fecha o risco nº 5.
   - **ADR 0273 "Proposto" no corpo**: NÃO editado — corpo de ADR é imutável (append-only Tier 0; exceções 0257/0297 só cobrem frontmatter). O frontmatter já diz `status: aceito` (o que os gates leem). **Ratificação Wagner registrada aqui: "273 aceito" (2026-07-02)**.
   - **US-GOV-019 (item 8)**: os "7 bugs confirmados" estavam TODOS resolvidos desde 13-30/jun (3º bookkeeping stale da noite) — #3592 marca os checkboxes com evidência. Dos **11 unclear**, 5 rodados isolados no CT100 staging: #8 TaskParserService **12/12 passed** (resolvido); #6/#7 já-quarentenados era-sqlite (viram burn-down); #9 Wave27Polish 5 fails só em Officeimpresso (asserts estáticos vs código refatorado — classe Q-B); #10 Ponto Wave18 1 fail reproduzível (precisa dive). Registrado na timeline MCP do US-GOV-019.
   - **Itens 6-7 (burn-down B1-B4, C2→T1→T2)**: gated no relógio externo — ver "Em voo" abaixo.

## Em voo (relógio externo — próxima sessão colhe)

- **Run fullsuite ENCADEADO no CT100** (flock-wait armado atrás do run 13:29 que estava com 8h+): primeiro run com o script novo → **1º clover.xml** (C2 `coverage_pct` sai de not_yet_measured) + **medição do efeito do P04 no floor (298→?)**. As nightlies 02:00 continuam a série. Quando a medição chegar: fan-out burn-down B1-B4 (plano canon: [P04](../requisitos/_Governanca/roadmap/P04-burn-down-ate-nightly-verde.md)).
- **RAGAS real semanal** (dom 07:00 CT100) → 1ª medição de `ragas_real_uptime`.
- **PRs abertos pré-flip do gate-selftest** precisam re-run/update-branch pra reportar o contexto novo (mesma dança do P14).

## Decisões Wagner tomadas nesta sessão

- "Pode fazer" (escopo: merge + 4 alavancas) · "Merge é continue" · "Promover gate-selftest" · "273 aceito" (ratificação; corpo fica, frontmatter já era aceito) · "6-8 tbm".

## Fica pra decidir / próxima sessão

- **Unclear restantes do US-GOV-019**: #1 FeedbackRelevanceTest (floor de recência torna `<30` inalcançável — bug de score ou expectativa do teste?); #2/#5/#11 investigáveis rápidos; #3/#4 splits re-triar pós próximo nightly; #9 test-fix Officeimpresso; #10 dive Ponto.
- **Armar `distiller_freshness`** (measured mas fora do baseline) — mesma receita do anchor_coverage.
- Reconciliar fonte stale do `ragas_real_uptime` no scorecard (ainda diz "mock com mock"; eval real já rodou 1×, faith 0.69).

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ativo em COPI** (CYCLE-08 encerrado; trabalho corre off-cycle governança).
- `my-work` (wagner): 30 ativas — 8 review (US-TR-3xx triage/inbox, US-PG-008, US-FIN-023), 8 blocked (trilha Gold NFe dormente, FIN-4), 14 todo (US-SELL-036 FSM rollout p0, RecurringBilling escopos, US-OFICINA-026 Martinho, US-FISCAL-018 Larissa).
- `decisions-search` "SDD scorecard anchor": 0279 (floor MEDIR→GOVERNAR), 0275 (scorecard canônico), 0273 (anchor formato), 0307 (Onda 0 enforcement), 0303 (wired/testado) — todas ativas, nenhuma nova aceita nesta sessão.
- `sessions-recent`: tool indisponível no ToolSearch desta sessão (schema não carregou) — fallback: session log do dia é [2026-07-01-sdd-avaliacao-adversarial.md](../sessions/2026-07-01-sdd-avaliacao-adversarial.md) (2 runs + este handoff).
- US-GOV-018/020/021: done (MCP) = done (SPEC) ✓ · US-GOV-019: todo, comentário de triagem 2026-07-02 na timeline.

## Lições

- **Bookkeeping stale é o inimigo nº 1 da avaliação adversarial**: 3 casos na mesma noite (018/020 "review", 021 "doing", 7 bugs do 019 abertos) fizeram 2 avaliações apontarem alavancas já puxadas. Regra prática: skeptic deve cruzar SPEC × MCP (`tasks-detail`) antes de declarar "US presa"; e todo trabalho que fecha US DEVE atualizar o SPEC no mesmo PR (o campo status do SPEC é o que os avaliadores leem).
- **"Nunca rodou" ≠ "não existe"**: o pcov existia na imagem; o que nunca rodou era a CÓPIA deployada do script (drift do vetor nº 1). Procurar o elo de deploy antes de construir a peça "faltante".
- **Required novo morde na hora**: #3592 pré-flip travou no Expected — o flip foi provado pelo próprio fluxo da sessão.
